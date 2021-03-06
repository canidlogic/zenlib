<?php

/*
 * Include zenlib and Gary
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'zenlib_vars.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'gary.php';

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
    <link rel="preconnect" href="https://fonts.gstatic.com"/>
    <link
      href="https://fonts.googleapis.com/css2?<?php echo CSS_FAMILIES;
              ?>&display=swap"
      rel="stylesheet"/>
    <link href="main.css" rel="stylesheet"/>
  </head>
  <body>
    <h1>Error</h1>
    <p><?php echo $msg; ?></p>
    <p class="eret">
      <a href="<?php echo MAINFORM_FILE_NAME; ?>" class="ctlbtn">
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
 * You should wrap the call to this function within a BEGIN IMMEDIATE
 * transaction so that everything is performed at once, and if an
 * Exception is thrown, rollback.
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
 * UPDATE: ISBNdb credentials are ignored.  Instead, this function calls
 * through to Gary and Gary handles query details.
 * 
 * If the database is unable to find any information about the book,
 * false is returned.  Otherwise, the return value is a parsed JSON
 * object with JSON objects represented as associative arrays.
 * 
 * The function first attempts to query the local ISBN cache for cached
 * information about the book.  If a cache entry is found, it is
 * returned without further queries.
 * 
 * If no cache entry is found for the ISBN number, then query Gary for
 * the information.  If this query succeeds, then the result is stored
 * in the local database cache.
 * 
 * Parameters:
 * 
 *   $isbn : string - the valid, normalized ISBN to query for
 * 
 *   $db : SQLite3 - the open database connection to local database
 * 
 *   $isbndb_url - (ignored)
 * 
 *   $isbndb_key - (ignored)
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
  
  // If we get here, we don't have the ISBN in the local cache, so now
  // we query Gary
  $result = gary_invoke_python('json', $isbn);
  
  // Store the original JSON so we will be able to cache it
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
    
    // Use a deferred transaction to read API url/key at one time
    if ($db->exec("BEGIN DEFERRED TRANSACTION") !== true) {
      throw new Exception("Couldn't begin key transaction");
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
    
    } catch (Exception $e) {
      $db->exec("ROLLBACK TRANSACTION");
      throw $e;
    
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
        
      } catch (Exception $e) {
        $db->exec("ROLLBACK TRANSACTION");
        throw $e;
        
      } finally {
        // Close result set if open
        if (is_null($qr) === false) {
          $qr->finalize();
          $qr = NULL;
        }
      }
    }
    
    // End the transaction for reading the API key/url
    if ($db->exec("COMMIT TRANSACTION") !== true) {
      throw new Exception("Couldn't commit key transaction");
    }
    
    // Call through to the database function, wrapped in transaction
    if ($db->exec("BEGIN IMMEDIATE TRANSACTION") !== true) {
      throw new Exception("Could not create ISBN transaction");
    }
    try {
      $result = dbISBN($isbn, $db, $isbndb_url, $isbndb_key);
    } catch (Exception $e) {
      $db->exec("ROLLBACK TRANSACTION");
      throw $e;
    }
    if ($db->exec("COMMIT TRANSACTION") !== true) {
      throw new Exception("Could not commit ISBN transaction");
    }
    
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

/*
 * Given a valid, normalized ISBN-13 number, determine whether the book
 * is recorded in the database.
 * 
 * This does NOT check whether information about the book is in the ISBN
 * cache.  Rather, it checks whether the book is in the booklist table.
 * 
 * Exceptions are thrown if there is a problem.
 * 
 * Parameters:
 * 
 *   $isbn13 : string - the valid, normalized ISBN-13 to look for
 * 
 * Return:
 * 
 *   true if book is recorded, false otherwise
 */
function hasBook($isbn13) {
  
  // Exception if we weren't given a valid ISBN
  if (checkISBN($isbn13) !== true) {
    throw new Exception('ISBN-13 is not valid');
  }
  
  // Exception if we weren't given an ISBN-13
  if (strlen($isbn13) !== 13) {
    throw new Exception('ISBN-13 is required');
  }
  
  // Attempt to open a local database connection, and wrap in a
  // try-finally block so database connection always closed on exit
  $db = NULL;
  $result = false;
  try {
    // Open the database
    try {
      $db = new SQLite3(BOOKDB_PATH, SQLITE3_OPEN_READONLY);
    } catch (Exception $e) {
      throw new Exception("Couldn't open local database");
    }
    
    // Query for the book
    $qr = NULL;
    try {
      // Perform the query
      $qr = $db->query(
        "SELECT isbn13 FROM booklist WHERE isbn13='$isbn13'");
      if ($qr === false) {
        $qr = NULL;
        throw new Exception("Couldn't query local database");
      }
      
      // Get the row as an associative array
      $r = $qr->fetchArray(SQLITE3_ASSOC);
      
      // If we managed to get a result row, the book is in the database
      if ($r !== false) {
        $result = true;
      }
    
    } finally {
      // Close result set if open
      if (is_null($qr) === false) {
        $qr->finalize();
        $qr = NULL;
      }
    }
    
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
  
} catch (Exception $e) {
  // Report the error and leave
  report_err('Error during cURL: ' . $e->getMessage(), 500);
}

// Check whether record was found
//
if ($result === false) {
  report_err(
    "Can't locate book for requested ISBN!",
    400);
}

// Record must have ISBN-13 and title fields
//
if ((array_key_exists('isbn13', $result) !== true) ||
    (array_key_exists('title', $result) !== true)) {
  report_err(
    "Book record missing critical fields!",
    500);
}

// Check whether there is a cover image
//
$has_cover = false;
if (array_key_exists('image', $result)) {
  $has_cover = true;
}

// Set display flags to default
//
$show_add = false;
$show_list = true;

// If there is an action GET parameter, determine what additional
// information to show with the flags; otherwise leave at default; also
// leave at default if action not recognized
//
if (array_key_exists('action', $_GET)) {
  if ($_GET['action'] === 'add') {
    $show_add = true;
    $show_list = false;
  }
}

// If we are showing the add book panel, determine whether book already
// added to database
//
$already_entered = false;
if ($show_add) {
  try {
    if (hasBook(normISBN($result['isbn13']))) {
      $already_entered = true;
    }
    
  } catch (Exception $e) {
    report_err("Error during query: " . $e->getMessage(), 500);
  }
}

// Wrapper around htmlspecialchars() that is appropriate for table
// output below
//
function wt($str) {
  return htmlspecialchars(
          $str, ENT_NOQUOTES | ENT_HTML5, 'UTF-8', true);
}

// Wrapper around htmlspecialchars() that is appropriate for attribute
// output below
//
function wa($str) {
  return htmlspecialchars(
          $str, ENT_COMPAT | ENT_HTML5, 'UTF-8', true);
}

?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <title>Book ISBN detail</title>
    <meta name="viewport" 
        content="width=device-width, initial-scale=1.0"/>
    <link rel="preconnect" href="https://fonts.gstatic.com"/>
    <link
      href="https://fonts.googleapis.com/css2?<?php echo CSS_FAMILIES;
              ?>&display=swap"
      rel="stylesheet"/>
    <link href="main.css" rel="stylesheet"/>
    <style>

th {
  text-align: left;
  font-family: 'Merriweather', serif;
  vertical-align: top;
}

td {
  padding-left: 0.5em;
  vertical-align: top;
}

.imgtd {
  padding-left: 0;
}

.alr {
  font-weight: bold;
  margin-bottom: 1.5em;
}

#infotbl {
  margin-top: 2.5em;
}

    </style>
  </head>
  <body>

    <h1>Book ISBN detail</h1>

<?php
if ($show_add) {
  if ($already_entered) {
?>
    <p class="alr">Book is already entered in database.</p>
    <p>
      <a href="<?php echo MAINFORM_FILE_NAME; ?>" class="ctlbtn">
        Return to ISBN form
      </a>
    </p>
<?php
  } else {
?>
    <form action="<?php echo ADDBOOK_FILE_NAME;
      ?>" method="post" accept-charset="utf-8">
      <input
          type="hidden"
          name="isbn13"
          id="isbn13"
          value="<?php
echo wa(normISBN($result['isbn13']));
            ?>"/>
      <input
          type="hidden"
          name="book_title"
          id="book_title"
          value="<?php
echo wa($result['title']);
            ?>"/>
      
      <p>
        <input type="submit" value="Add book" class="ctlbtn"/>
        &nbsp;&nbsp;
        <a href="<?php echo MAINFORM_FILE_NAME; ?>" class="ctlbtn">
          Return to ISBN form
        </a>
      </p>
    </form>
<?php
  }
}
?>

<?php
if ($show_list) {
?>
    <p>
      <a href="<?php echo BOOKLIST_FILE_NAME; ?>" class="ctlbtn">
        Return to book list
      </a>
    </p>
<?php
}
?>

    <table id="infotbl">
      <tr>
        <th>Title:</th>
        <td><?php echo wt($result['title']); ?></td>
      </tr>
      <tr>
        <th>ISBN:</th>
        <td><?php echo wt($result['isbn13']); ?></td>
      </tr>
<?php
if ($has_cover) {
?>
      <tr><td>&nbsp;</td><td>&nbsp;</td></tr>
      <tr>
        <td colspan="2" class="imgtd">
          <img src="isbn_cover.php?isbn=<?php
echo wa($result['isbn13']);
            ?>"/>
        </td>
      </tr>
<?php
}
?>
    </table>
  </body>
</html>
