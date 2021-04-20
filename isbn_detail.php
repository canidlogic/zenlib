<?php

/*
 * Include the common utilities script
 */
require_once 'isbn_util.php';

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                      Error response function                       //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * Report that an error occurred and finish the script.
 * 
 * This can only be used before the script has written output.
 * 
 * Passing a non-string as a message is the same as passing an empty
 * string.
 * 
 * The response code must be an integer in range 400-599 (client or
 * server error).  If it is not an integer or it is out of range, 500
 * will be used.
 * 
 * This function will not return.
 * 
 * Parameters:
 * 
 *   $msg : string | mixed - the error message to report
 * 
 *   $code : integer | mixed - the HTTP error status code to use
 * 
 * Return:
 * 
 *   this function does not return!
 */
function report_err($msg, $code) {
  
  // If non-string passed, change to empty string
  if (is_string($msg) !== true) {
    $msg = '';
  }
  
  // If non-integer passed for code, set to 500
  if (is_int($code) !== true) {
    $code = 500;
  }
  
  // If code out of range, set to 500
  if (($code < 400) || ($code > 599)) {
    $code = 500;
  }
  
  // Escape special characters in message
  $msg = htmlspecialchars(
          $msg, ENT_NOQUOTES | ENT_HTML5, 'UTF-8', true);
  
  // Set response code and content type
  http_response_code($code);
  header('Content-Type: text/html');
  
  // Display the output form
  //
?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <title>Book ISBN detail</title>
    <meta name="viewport" 
        content="width=device-width, initial-scale=1.0"/>
  </head>
  <body>
    <h1>Error</h1>
    <p><?php echo $msg; ?></p>
    <p>
      <a href="<?php echo MAINFORM_FILE_NAME; ?>">
        Return to ISBN form
      </a>
    </p>
  </body>
</html>
<?php
  
  // End script here
  exit;
}

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                              Functions                             //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * Given a normalized ISBN code, a local database connection, and
 * optionally the URL and API key for ISBNdb, query databases to get a
 * JSON object with information about the book.
 * 
 * If an error occurs, an Exception is thrown with a relevant error
 * message.
 * 
 * The given ISBN parameter must be a valid, normalized ISBN that passes
 * checkISBN() or an Exception is thrown.
 * 
 * The given database connection must be an open SQLite3 database
 * connection with read/write access to the local database.
 * 
 * If you have credentials for ISBNdb, the two ISBNdb parameters must be
 * the URL for the API and the API key.  If you do not have credentials,
 * set the URL to NULL and the key will be ignored (and can also be set
 * to NULL).  The URL is for querying by book ISBN.  It is assumed that
 * the book ISBN can be suffixed to the given URL to get the proper
 * query location.
 * 
 * If the database is unable to find any information about the book,
 * false is returned.  Otherwise, the return value is a parsed JSON
 * object with JSON objects represented as associative arrays.
 * 
 * The function first attempts to query the local ISBN cache for cached
 * information about the book.  If a cache entry is found, it is
 * returned without further queries.
 * 
 * If no cache entry is found for the ISBN number, then check whether
 * ISBNdb credentials were provided.  If they were provided, a query is
 * made to ISBNdb.  If this query succeeds, then the result is stored in
 * the local database cache.  If no ISBNdb credentials were provided,
 * then this function is only able to return information about books
 * already in the local database cache, and all other queries will
 * return false.
 * 
 * Parameters:
 * 
 *   $isbn : string - the valid, normalized ISBN to query for
 * 
 *   $db : SQLite3 - the open database connection to local database
 * 
 *   $isbndb_url : string | null - the URL for querying by ISBN, or NULL
 *   if no credentials for ISBNdb
 * 
 *   $isbndb_key : string | null - the API key for ISBNdb; ignored and
 *   can be set to NULL if isbndb_url parameter is NULL, else this must
 *   be a string
 * 
 * Return:
 * 
 *   a parsed JSON Book object with information about the book, or false
 *   if the book was not found
 */
function dbISBN($isbn, $db, $isbndb_url, $isbndb_key) {
  
  // Check parameters
  if (checkISBN($isbn) !== true) {
    throw new Exception("isbn_detail-" . strval(__LINE__));
  }
  if (($db instanceof SQLite3) !== true) {
    throw new Exception("isbn_detail-" . strval(__LINE__));
  }
  if (is_null($isbndb_url) === false) {
    if (is_string($isbndb_url) !== true) {
      throw new Exception("isbn_detail-" . strval(__LINE__));
    }
    if (is_string($isbndb_key) !== true) {
      throw new Exception("isbn_detail-" . strval(__LINE__));
    }
  }
  
  // Query the local database cache for the book
  $qr = NULL;
  $result = NULL;
  try {
    // Go by either ISBN-10 or ISBN-13, depending on what was given
    $slen = strlen($isbn);
    if ($slen === 10) {
      // Query by ISBN-10
      $qr = $db->query(
        "SELECT json FROM isbncache WHERE isbn10='$isbn'");
      
    } else if ($slen === 13) {
      // Query by ISBN-13
      $qr = $db->query(
        "SELECT json FROM isbncache WHERE isbn13='$isbn'");
      
    } else {
      // Shouldn't happen
      throw new Exception("isbn_detail-" . strval(__LINE__));
    }
    
    // Check for query error
    if ($qr === false) {
      $qr = NULL;
      throw new Exception("Couldn't query local cache for ISBN");
    }
    
    // Get the row as an associative array and set result if we got a
    // result row
    $r = $qr->fetchArray(SQLITE3_ASSOC);
    if ($r !== false) {
      $result = $r['json'];
    }
    
  } finally {
    // Close result set if open
    if (is_null($qr) === false) {
      $qr->finalize();
      $qr = NULL;
    }
  }
  
  // If we got a result from the local cache, decode the JSON and return
  // the decoded inner Book object
  if (is_null($result) === false) {
    $result = json_decode($result, true);
    if (is_null($result)) {
      throw new Exception("Can't decode cached JSON");
    }
    if (array_key_exists('book', $result) === false) {
      throw new Exception("Cached JSON has wrong format");
    }
    $result = $result['book'];
    return $result;
  }
  
  // If we get here, we don't have the ISBN in the local cache; if no
  // ISBNdb credentials were provided, return false because we have no
  // other place to query
  if (is_null($isbndb_url)) {
    return false;
  }
  
  // We have ISBNdb credentials and we don't have local cache entry, so
  // now we query ISBNdb
  $query_res = curl_init();
  if ($query_res === false) {
    throw new Exception("Error initializing cURL");
  }
  
  // Wrap cURL session in try-finally so it always gets closed on the
  // way out
  try {
    // Determine the cURL query headers and the query URL
    $query_headers = array(
                      "Content-Type: application/json",
                      "Authorization: $isbndb_key");
    $query_url = $isbndb_url . $isbn;
    
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
    if (curl_setopt($query_res, CURLOPT_RETURNTRANSFER, true)
          !== true) {
      throw new Exception("Can't set cURL return transfer");
    }
    
    // Attempt to get the query result
    $result = curl_exec($query_res);
    if ($result === false) {
      throw new Exception("cURL query operation failed");
    }
    
    // If 200 OK status was not returned, couldn't find information
    if (curl_getinfo($query_res, CURLINFO_RESPONSE_CODE) !== 200) {
      $result = NULL;
    }
    
  } finally {
    // Close session
    if (is_null($query_res) !== true) {
      curl_close($query_res);
      unset($query_res);
    }
  }
  
  // If we didn't get any result from ISBNdb, then return false
  if (is_null($result)) {
    return false;
  }
  
  // If we got here, we have a fresh result from ISBNdb; store the
  // original JSON so we will be able to cache it
  $json = $result;
  
  // Decode the inner book object within the JSON
  $result = json_decode($result, true);
  if (is_null($result)) {
    throw new Exception("Can't decode queried JSON");
  }
  if (array_key_exists('book', $result) === false) {
    throw new Exception("Queried JSON has wrong format");
  }
  $result = $result['book'];
  
  // Get the ISBN-10 and ISBN-13 codes from the result, normalize them,
  // and make sure they are valid
  if (array_key_exists('isbn', $result) === false) {
    throw new Exception("Queried JSON didn't have ISBN-10");
  }
  if (array_key_exists('isbn13', $result) === false) {
    throw new Exception("Queried JSON didn't have ISBN-13");
  }
  
  $isbn10 = normISBN($result['isbn']);
  $isbn13 = normISBN($result['isbn13']);
  
  if ((checkISBN($isbn10) !== true) || (strlen($isbn10) !== 10)) {
    throw new Exception("Queried ISBN-10 wasn't valid");
  }
  if ((checkISBN($isbn13) !== true) || (strlen($isbn13) !== 13)) {
    throw new Exception("Queried ISBN-13 wasn't valid");
  }
  
  // If the image URL exists, we need to download the image for the
  // local cache
  $image = NULL;
  if (array_key_exists('image', $result)) {

    // Begin a cURL session for downloading the image
    $dimg_res = curl_init();
    if ($dimg_res === false) {
      throw new Exception("Error initializing image cURL");
    }
    
    // Wrap cURL session in try-finally so it always gets closed on the
    // way out
    try {
      // Set the URL
      if (curl_setopt($dimg_res, CURLOPT_URL, $result['image'])
              !== true) {
        throw new Exception("Can't set image cURL URL");
      }
      
      // Indicate that we want to receive response as a (binary) string
      // rather than sending it directly to output
      if (curl_setopt($dimg_res, CURLOPT_RETURNTRANSFER, true)
            !== true) {
        throw new Exception("Can't set cURL return transfer");
      }
      
      // Indicate that we want binary data
      if (curl_setopt($dimg_res, CURLOPT_BINARYTRANSFER, true)
            !== true) {
        throw new Exception("Can't set cURL binary transfer");
      }
      
      // Indicate that cURL should follow redirects
      if (curl_setopt($dimg_res, CURLOPT_FOLLOWLOCATION, true)
            !== true) {
        throw new Exception("Can't set cURL redirect follow");
      }
      
      // Attempt to download the image
      $image = curl_exec($dimg_res);
      if ($image === false) {
        throw new Exception("cURL image download failed");
      }
      
      // If 200 OK status was not returned, couldn't get image
      if (curl_getinfo($dimg_res, CURLINFO_RESPONSE_CODE) !== 200) {
        throw new Exception("cURL image download problem");
      }
      
      // If result isn't string, download failed
      if (is_string($image) !== true) {
        throw new Exception("cURL result wasn't string");
      }
      
    } finally {
      // Close session
      if (is_null($dimg_res) !== true) {
        curl_close($dimg_res);
        unset($dimg_res);
      }
    }
  }

  // If we got an image file, store it in the cover images directory
  if (is_null($image) === false) {
    if (file_put_contents(BOOKDB_COVERS_DIR . '/' . $isbn13, $image)
          === false) {
      throw new Exception("Couldn't store cover image");
    }
  }

  // Create a prepared statement for inserting the new ISBN record into
  // the local database cache
  $st = NULL;
  try {
    // Define the prepared statement
    $st = $db->prepare(
        "INSERT INTO isbncache (isbn10, isbn13, json) VALUES " .
        "(:ia, :ib, :j)");
    if ($st === false) {
      $st = NULL;
      throw new Exception("Couldn't prepare INSERT statement");
    }
    
    // Bind the ISBN numbers to the prepared statement
    if ($st->bindValue(':ia', $isbn10, SQLITE3_TEXT) !== true) {
      throw new Exception("Couldn't bind ISBN-10");
    }
    if ($st->bindValue(':ib', $isbn13, SQLITE3_TEXT) !== true) {
      throw new Exception("Couldn't bind ISBN-13");
    }
    
    // Bind the JSON to the prepared statement
    if ($st->bindValue(':j', $json, SQLITE3_TEXT) !== true) {
      throw new Exception("Couldn't bind JSON");
    }
    
    // Run the prepared statement
    $r = $st->execute();
    if ($r === false) {
      throw new Exception("Couldn't cache ISBN information");
    }
    $r->finalize();
    unset($r);
  
  } finally {
    // Close prepared statement if open
    if (is_null($st) === false) {
      $st->close();
      unset($st);
    }
  }
  
  // We can now return the result
  return $result;
}

/*
 * Given a normalized ISBN code, attempt to query databases to get a
 * JSON object with information about the book.
 * 
 * If an error occurs, an Exception is thrown with a relevant error
 * message.
 * 
 * The given parameter must be a valid, normalized ISBN that passes
 * checkISBN() or an Exception is thrown.
 * 
 * If the database is unable to find any information about the book,
 * false is returned.  Otherwise, the return value is a parsed JSON
 * object with JSON objects represented as associative arrays.
 * 
 * This uses the local ISBN cache and returns the cached entry if it is
 * found.  If a new entry is retrieved from ISBNdb, then it is added to
 * the local cache.
 * 
 * Parameters:
 * 
 *   $isbn : string - the valid, normalized ISBN to query
 * 
 * Return:
 * 
 *   a parsed JSON Book object with information about the book, or false
 *   if the book was not found
 */
function queryISBN($isbn) {
  
  // Exception if we weren't given a valid ISBN
  if (checkISBN($isbn) !== true) {
    throw new Exception('ISBN is not valid');
  }
  
  // Attempt to open a local database connection, and wrap in a
  // try-finally block so database connection always closed on exit
  $db = NULL;
  $result = false;
  try {
    // Open the database
    try {
      $db = new SQLite3(BOOKDB_PATH, SQLITE3_OPEN_READWRITE);
    } catch (Exception $e) {
      throw new Exception("Couldn't open local database");
    }
    
    // Query for the ISBNdb URL, or set URL to NULL if variable not
    // defined
    $qr = NULL;
    $isbndb_url = NULL;
    try {
      // Perform the query
      $qr = $db->query("SELECT val FROM vars WHERE name='isbndb_url'");
      if ($qr === false) {
        $qr = NULL;
        throw new Exception("Couldn't query ISBNdb URL");
      }
      
      // Get the row as an associative array
      $r = $qr->fetchArray(SQLITE3_ASSOC);
      
      // Only proceed if there was a row
      if ($r !== false) {
        // Get the URL
        $isbndb_url = $r['val'];
      }
    
    } finally {
      // Close result set if open
      if (is_null($qr) === false) {
        $qr->finalize();
        $qr = NULL;
      }
    }
    
    // If we got an ISBNdb URL, query for the API key
    $isbndb_key = NULL;
    if (is_null($isbndb_url) === false) {
      $qr = NULL;
      try {
        // Perform the query
        $qr = $db->query(
                    "SELECT val FROM vars WHERE name='isbndb_key'");
        if ($qr === false) {
          $qr = NULL;
          throw new Exception("Couldn't query ISBNdb key");
        }
        
        // Get the row as an associative array
        $r = $qr->fetchArray(SQLITE3_ASSOC);
        
        // There must be a row, or else we are missing the key
        if ($r === false) {
          throw new Exception("ISBNdb key is missing");
        }
        
        // Get the API key
        $isbndb_key = $r['val'];
        
      } finally {
        // Close result set if open
        if (is_null($qr) === false) {
          $qr->finalize();
          $qr = NULL;
        }
      }
    }
    
    // Call through to the database function
    $result = dbISBN($isbn, $db, $isbndb_url, $isbndb_key);
    
  } finally {
    // Close database connection if open
    if (is_null($db) !== true) {
      $db->close();
      unset($db);
    }
  }
  
  // Return result
  return $result;
}

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                           PHP entrypoint                           //
//                                                                    //
////////////////////////////////////////////////////////////////////////

// We only support GET and HEAD requests
//
if (($_SERVER['REQUEST_METHOD'] !== 'GET') &&
    ($_SERVER['REQUEST_METHOD'] !== 'HEAD')) {
  report_err('Invalid request method!', 405);
}

// If ISBN parameter not provided, redirect to ISBN form
//
if (array_key_exists('isbn', $_GET) !== true) {
  redirectToForm();
}

// Grab the ISBN number
//
$isbn = $_GET['isbn'];

// Normalize the ISBN number
//
$isbn = normISBN($isbn);

// If ISBN number is not valid, error; this also checks the check digit
//
if (checkISBN($isbn) !== true) {
  report_err(
    'Invalid ISBN code! Please check that you entered it correctly.',
    400);
}

// Query for the ISBN information
//
$result = NULL;
try {
  // Perform the query
  $result = queryISBN($isbn);
  if ($result === false) {
    report_err(
      "Can't locate book for requested URL!",
      400);
  }
  
} catch (Exception $e) {
  // Report the error and leave
  report_err('Error during cURL: ' . $e->getMessage(), 500);
}

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
          <td>
<img src="isbn_cover.php?isbn=<?php echo wt($result['isbn13']); ?>"/>
          </td>
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
