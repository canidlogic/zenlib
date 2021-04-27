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
    <title>Add book</title>
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
 * Add a new book to the database.
 * 
 * Pass the normalized, valid ISBN-13 code and the title for the book.
 * 
 * Exceptions are thrown if there is a problem.
 * 
 * If the book was added, true is returned.  If the book was already in
 * the database, false is returned.
 * 
 * Parameters:
 * 
 *   $isbn13 : string - the ISBN-13 code of the book
 * 
 *   $btitle : string - the title of the book
 * 
 * Return:
 * 
 *   true if book added, false if already in database
 */
function addBook($isbn13, $btitle) {
  
  // Check parameters
  if (checkISBN($isbn13) !== true) {
    throw new Exception("Invalid ISBN-13");
  }
  if (strlen($isbn13) !== 13) {
    throw new Exception("Invalid ISBN-13");
  }
  if (is_string($btitle) !== true) {
    throw new Exception("Invalid book title");
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
    
    // Wrap everything in a BEGIN IMMEDIATE transaction
    if ($db->exec("BEGIN IMMEDIATE TRANSACTION") !== true) {
      throw new Exception("Could not begin transaction");
    }
    try {
      
      // First thing to do is check whether ISBN-13 already entered
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
        
        // If we managed to get a result row, the book is in the
        // database already; else, it is not yet in the database so set
        // result to true in that case
        if ($r === false) {
          $result = true;
        } else {
          $result = false;
        }
      
      } finally {
        // Close result set if open
        if (is_null($qr) === false) {
          $qr->finalize();
          $qr = NULL;
        }
      }
      
      // Only proceed if book not yet in database
      if ($result) {
        
        // Create a prepared statement for inserting the book record
        $st = NULL;
        try {
          // Define the prepared statement
          $st = $db->prepare(
              "INSERT INTO booklist (isbn13, title, entry) VALUES " .
              "(:i, :t, :e)");
          if ($st === false) {
            $st = NULL;
            throw new Exception("Couldn't prepare INSERT statement");
          }
          
          // Bind the ISBN number to the prepared statement
          if ($st->bindValue(':i', $isbn13, SQLITE3_TEXT) !== true) {
            throw new Exception("Couldn't bind ISBN-13");
          }
          
          // Bind the title to the prepared statement
          if ($st->bindValue(':t', $btitle, SQLITE3_TEXT) !== true) {
            throw new Exception("Couldn't bind book title");
          }
          
          // Bind the current time to the prepared statement
          if ($st->bindValue(':e', time(), SQLITE3_INTEGER) !== true) {
            throw new Exception("Couldn't bind timestamp");
          }
          
          // Run the prepared statement
          $r = $st->execute();
          if ($r === false) {
            throw new Exception("Couldn't add book to database");
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

// We only support POST requests
//
if (($_SERVER['REQUEST_METHOD'] !== 'POST')) {
  report_err('Invalid request method!', 405);
}

// Check that we got our parameters
//
if ((array_key_exists('book_title', $_POST) !== true) ||
    (array_key_exists('isbn13', $_POST) !== true)) {
  report_err('Missing parameters!', 400);
}

// Grab the parameters
//
$btitle = $_POST['book_title'];
$isbn13 = $_POST['isbn13'];

// Normalize the ISBN and make sure it is valid ISBN-13
//
$isbn13 = normISBN($isbn13);
if (checkISBN($isbn13) !== true) {
  report_err('Invalid ISBN-13!', 400);
}
if (strlen($isbn13) !== 13) {
  report_err('Only ISBN-13 may be provided!', 400);
}

// Call through to the main function
//
$result = false;
try {
  $result = addBook($isbn13, $btitle);
} catch (Exception $e) {
  report_err('Error adding book: ' . $e->getMessage(), 500);
}

// Check whether book was already entered
//
if ($result === false) {
  report_err('Book is already in database!', 400);
}

// If we got to the end of the script successfully, redirect back to the
// ISBN entry form
//
redirectToForm();
