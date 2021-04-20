<?php

// IMPORTANT:
//
// You must add your API key as the $query_key variable
// before running this script

// We only support GET and HEAD requests
//
if (($_SERVER['REQUEST_METHOD'] !== 'GET') &&
    ($_SERVER['REQUEST_METHOD'] !== 'HEAD')) {
  http_response_code(405);
  header('Content-Type: text/plain');
  echo "Invalid request method!\n";
  exit;
}

// If ISBN parameter not provided, redirect to ISBN form
//
if (array_key_exists('isbn', $_GET) !== true) {
  // Determine if HTTPS was used for this request
  $uses_https = false;
  if (isset($_SERVER['HTTPS'])) {
    $uses_https = true;
  }
  
  // Determine protocol
  $prot = 'http://';
  if ($uses_https) {
    $prot = 'https://';
  }
  
  // Get the server name and script name
  $server_name = $_SERVER['SERVER_NAME'];
  $script_name = $_SERVER['SCRIPT_NAME'];
  
  // Get the index of the last / in the script name
  $last_sep = strrpos($script_name, '/');
  
  // If there is a / in the script name, the parent directory is
  // everything up to and including it; else, the parent directory is /
  $parent_dir = NULL;
  if ($last_sep !== false) {
    // Parent directory is everything up to and including last separator
    $parent_dir = substr($script_name, 0, $last_sep + 1);
    
  } else {
    // Didn't find any / so parent directory is root
    $parent_dir = '/';
  }
  
  // Form the path to the form
  $form_path = $prot . $server_name . $parent_dir . 'isbn.html';
  
  // Redirect to form
  http_response_code(302);
  header("Location: $form_path");
  exit;
}

// Grab the ISBN number
//
$isbn = $_GET['isbn'];

// Trim the ISBN number
//
$isbn = trim($isbn);

// Drop all non-alphanumeric symbols as well as tab and space from ISBN
// number, and set ISBN to empty if any invalid characters
//
$old_isbn = $isbn;
$isbn = '';
$slen = strlen($old_isbn);
for($i = 0; $i < $slen; $i++) {
  
  // Get current character code
  $c = ord($old_isbn[$i]);
  
  // Handle based on character type
  if (($c >= ord('a')) && ($c <= ord('z'))) {
    // Invalid ISBN containing letters
    $isbn = '';
    break;
  
  } else if (($c >= ord('A')) && ($c <= ord('Z'))) {
    // Invalid ISBN containing letters
    $isbn = '';
    break;
  
  } else if (($c >= ord('0')) && ($c <= ord('9'))) {
    // Digit, so transfer to new isbn
    $isbn = $isbn . chr($c);
  
  } else if (($c >= 0x21) && ($c <= 0x7e)) {
    // Non-alphanumeric symbol, so don't transfer
    continue;
  
  } else if (($c === ord("\t")) || ($c === ord("\r")) ||
              ($c === ord("\n")) || ($c === ord(' '))) {
    // Whitespace, so don't transfer
    continue;
  
  } else {
    // Invalid control or extended character
    $isbn = '';
    break;
  }
}

// @@TODO: some old ISBNs allow alphanumeric check digit

// Valid normalized ISBN has 10 or 13 digits
//
$slen = strlen($isbn);
if (($slen !== 10) && ($slen !== 13)) {
  // Invalid ISBN
  http_response_code(400);
  header('Content-Type: text/html');
?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <title>Book ISBN detail</title>
    <meta name="viewport" 
        content="width=device-width, initial-scale=1.0"/>
  </head>
  <body>
    <p>Invalid ISBN code!</p>
    <p><a href="isbn.html">Return to ISBN form</a></p>
  </body>
</html>
<?php
  exit;
}

// @@TODO: perform check digit test to reduce actual queries

// Define ISBN query information
//
$query_url = "https://api2.isbndb.com/book/$isbn";
$query_key = '';  // Add your API key here
$query_headers = array(
                    "Content-Type: application/json",
                    "Authorization: $query_key");

// Attempt to open cURL session
//
$query_res = curl_init();
if ($query_res === false) {
  http_response_code(500);
  header('Content-Type: text/plain');
  echo "Error initializing cURL!\n";
  exit;  
}

// Wrap cURL session in try-finally so it always gets closed on way out
//
$query_result = '';
try {
  // Set the URL and the headers for the request
  if (curl_setopt($query_res, CURLOPT_URL, $query_url) !== true) {
    throw new Exception("Can't set cURL URL");
  }
  if (curl_setopt($query_res, CURLOPT_HTTPHEADER, $query_headers)
        !== true) {
    throw new Exception("Can't set cURL headers");
  }
  
  // Indicate that we want to receive response as a string rather than
  // sending it directly to output
  if (curl_setopt($query_res, CURLOPT_RETURNTRANSFER, true) !== true) {
    throw new Exception("Can't set cURL return transfer");
  }
  
  // Attempt to get the query result
  $query_result = curl_exec($query_res);
  if ($query_result === false) {
    throw new Exception("cURL query operation failed");
  }
  
  // Check that 200 OK status was returned
  if (curl_getinfo($query_res, CURLINFO_RESPONSE_CODE) !== 200) {
    throw new Exception("ISBN database request failed");
  }
  
} catch (Exception $e) {
  // Something went wrong -- close session first
  if (is_null($query_res) !== true) {
    curl_close($query_res);
    unset($query_res);
  }
  
  // Report the error
  http_response_code(500);
  header('Content-Type: text/plain');
  echo "Error during cURL: " . $e->getMessage() . "\n";
  exit;
  
} finally {
  // Close session
  if (is_null($query_res) !== true) {
    curl_close($query_res);
    unset($query_res);
  }
}

// If we got here, query_result holds a JSON object with information
// about our book -- decode to PHP object
//
$result = json_decode($query_result, true);
if (is_null($result)) {
  http_response_code(500);
  header('Content-Type: text/plain');
  echo "ISBN database returned invalid JSON!\n";
  exit;
}

// Result should be in a "book" object
//
if (array_key_exists('book', $result) !== true) {
  http_response_code(500);
  header('Content-Type: text/plain');
  echo "ISBN database didn't return a book!\n";
  exit;
}

// Extract the book object for the result
//
$result = $result['book'];

// Wrapper around htmlspecialchars() that is appropriate for table
// output below
//
function wt($str) {
  return htmlspecialchars(
          $str, ENT_NOQUOTES | ENT_HTML5, 'UTF-8', true);
}

?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <title>Book ISBN detail</title>
    <meta name="viewport" 
        content="width=device-width, initial-scale=1.0"/>
    <style>

th {
  text-align: left;
}
td {
  padding-left: 1em;
}

    </style>
  </head>
  <body>

    <h1>Book ISBN detail</h1>

      <table>
        <tr>
          <th>ISBN:</th>
<?php
if (array_key_exists('isbn', $result)) {
?>
          <td><?php echo wt($result['isbn']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
        <tr>
          <th>ISBN-13:</th>
<?php
if (array_key_exists('isbn13', $result)) {
?>
          <td><?php echo wt($result['isbn13']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
        <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
        <tr>
          <th>Title:</th>
<?php
if (array_key_exists('title', $result)) {
?>
          <td><?php echo wt($result['title']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
        <tr>
          <th>Title (long):</th>
<?php
if (array_key_exists('title_long', $result)) {
?>
          <td><?php echo wt($result['title_long']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>

<?php
if (array_key_exists('authors', $result)) {
  $a = $result['authors'];
  $c = count($a);
  for($x = 0; $x < $c; $x++) {
    if ($x < 1) {
?>
        <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
<?php
    }
?>
        <tr>
          <th>Author(s):</th>
          <td><?php echo wt($a[$x]); ?></td>
        </tr>
<?php
  }
}
?>
        <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
        <tr>
          <th>Publisher:</th>
<?php
if (array_key_exists('publisher', $result)) {
?>
          <td><?php echo wt($result['publisher']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
        <tr>
          <th>Date published:</th>
<?php
if (array_key_exists('date_published', $result)) {
?>
          <td><?php echo wt($result['date_published']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
        <tr>
          <th>Edition</th>
<?php
if (array_key_exists('edition', $result)) {
?>
          <td><?php echo wt($result['edition']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
        <tr>
          <th>Binding</th>
<?php
if (array_key_exists('binding', $result)) {
?>
          <td><?php echo wt($result['binding']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
        <tr>
          <th>Pages</th>
<?php
if (array_key_exists('pages', $result)) {
?>
          <td><?php echo wt(strval($result['pages'])); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
        <tr>
          <th>Dimensions</th>
<?php
if (array_key_exists('dimensions', $result)) {
?>
          <td><?php echo wt($result['dimensions']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
        <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
        <tr>
          <th>Dewey decimal:</th>
<?php
if (array_key_exists('dewey_decimal', $result)) {
?>
          <td><?php echo wt($result['dewey_decimal']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
        <tr>
          <th>Language:</th>
<?php
if (array_key_exists('language', $result)) {
?>
          <td><?php echo wt($result['language']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
<?php
if (array_key_exists('subjects', $result)) {
  $a = $result['subjects'];
  $c = count($a);
  for($x = 0; $x < $c; $x++) {
    if ($x < 1) {
?>
        <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
<?php
    }
?>
        <tr>
          <th>Subject(s):</th>
          <td><?php echo wt($a[$x]); ?></td>
        </tr>
<?php
  }
}
?>        
        <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
        <tr>
          <th>Overview:</th>
<?php
if (array_key_exists('overview', $result)) {
?>
          <td><?php echo wt($result['overview']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
        <tr>
          <th>Synposis:</th>
<?php
if (array_key_exists('synopsys', $result)) {
?>
          <td><?php echo wt($result['synopsys']); ?></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
        <tr>
          <th>Cover:</th>
<?php
if (array_key_exists('image', $result)) {
?>
          <td><img src="<?php echo wt($result['image']); ?>"/></td>
<?php
} else {
?>
          <td>&nbsp;</td>
<?php
}
?>
        </tr>
      </table>

  </body>
</html>
