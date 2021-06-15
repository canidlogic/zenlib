<?php

/*
 * Include zenlib
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'zenlib_vars.php';

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
  
  // Set response code and content type
  http_response_code($code);
  header('Content-Type: text/plain');
  
  // Display the output message
  //
  print $msg;
  print "\n";
  
  // End script here
  exit;
}

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                              Functions                             //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * Query all the books in the database.
 * 
 * Exceptions are thrown if there is a problem.
 * 
 * The return value is an array of ISBN-13 strings.
 * 
 * Return:
 * 
 *   array of ISBN-13 strings
 */
function listBooks() {
  
  // Attempt to open a local database connection, and wrap in a
  // try-finally block so database connection always closed on exit
  $db = NULL;
  $result = array();
  try {
    // Open the database
    try {
      $db = new SQLite3(BOOKDB_PATH, SQLITE3_OPEN_READONLY);
    } catch (Exception $e) {
      throw new Exception("Couldn't open local database");
    }
    
    // Wrap everything in a BEGIN DEFERRED transaction
    if ($db->exec("BEGIN DEFERRED TRANSACTION") !== true) {
      throw new Exception("Could not begin transaction");
    }
    try {
      
      // Query all the records in the book list
      $qr = NULL;
      try {
        // Perform the query
        $qr = $db->query("SELECT isbn13 FROM booklist");
        if ($qr === false) {
          $qr = NULL;
          throw new Exception("Couldn't query local database");
        }
        
        // Go through all the records as associative arrays
        for($r = $qr->fetchArray(SQLITE3_ASSOC);
            $r !== false;
            $r = $qr->fetchArray(SQLITE3_ASSOC)) {
          
          // Add an ISBN-13 string for this record
          array_push($result, $r['isbn13']);
        }
      
      } finally {
        // Close result set if open
        if (is_null($qr) === false) {
          $qr->finalize();
          $qr = NULL;
        }
      }
      
    } catch (Exception $e) {
      // Rollback transaction and rethrow
      $db->exec("ROLLBACK TRANSACTION");
      throw $e;
    }
    if ($db->exec("COMMIT TRANSACTION") !== true) {
      throw new Exception("Could not commit transaction");
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

// Query all the books
//
$books = NULL;
try {
  $books = listBooks();
} catch (Exception $e) {
  report_err('Error while querying: ' . $e->getMessage(), 500);
}

// Print the list of ISBN numbers one per line
//
header('Content-Type: text/plain');
header(
  'Content-Disposition: attachment; filename="isbn_export.txt"');

$bcount = count($books);
for($x = 0; $x < $bcount; $x++) {
  print($books[$x]);
  print("\n");
}
