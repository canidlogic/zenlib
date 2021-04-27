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
  </body>
</html>
<?php
  
  // End script here
  exit;
}

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                             Book class                             //
//                                                                    //
////////////////////////////////////////////////////////////////////////

class Book {
  
  // Fields
  //
  private $m_isbn;
  private $m_title;
  
  /*
   * Construct a new book given its ISBN-13 and its title.
   * 
   * The ISBN-13 must be normalized and valid.  The book title must be
   * a string.
   * 
   * Parameters:
   * 
   *   $isbn13 : string - the ISBN-13 of the book
   * 
   *   $btitle : string - the title of the book
   */
  public function __construct($isbn13, $btitle) {
    
    // Check parameters
    if (checkISBN($isbn13) !== true) {
      throw new Exception("add_book-" . strval(__LINE__));
    }
    if (strlen($isbn13) !== 13) {
      throw new Exception("add_book-" . strval(__LINE__));
    }
    if (is_string($btitle) !== true) {
      throw new Exception("add_book-" . strval(__LINE__));
    }
    
    // Set the fields
    $this->m_isbn = $isbn13;
    $this->m_title = $btitle;
  }
  
  /*
   * Return the normalized, valid ISBN-13 for this book.
   * 
   * Return:
   * 
   *   the ISBN-13
   */
  public function getISBN() {
    return $this->m_isbn;
  }
  
  /*
   * Return the title of this book.
   * 
   * Return:
   * 
   *   the book title
   */
  public function getTitle() {
    return $this->m_title;
  }
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
 * The return value is an array of Book objects, sorted from most
 * recently added to least recently added.
 * 
 * Return:
 * 
 *   array of Book objects
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
        $qr = $db->query(
          "SELECT isbn13, title, entry FROM booklist " .
          "ORDER BY entry DESC");
        if ($qr === false) {
          $qr = NULL;
          throw new Exception("Couldn't query local database");
        }
        
        // Go through all the records as associative arrays
        for($r = $qr->fetchArray(SQLITE3_ASSOC);
            $r !== false;
            $r = $qr->fetchArray(SQLITE3_ASSOC)) {
          
          // Add a Book object for this record
          array_push($result, new Book($r['isbn13'], $r['title']));
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

// Wrapper around htmlspecialchars() that is appropriate for main text
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
    <title>Book list</title>
    <meta name="viewport" 
        content="width=device-width, initial-scale=1.0"/>
    <link rel="preconnect" href="https://fonts.gstatic.com"/>
    <link
      href="https://fonts.googleapis.com/css2?<?php echo CSS_FAMILIES;
              ?>&display=swap"
      rel="stylesheet"/>
    <link href="main.css" rel="stylesheet"/>
    <style>

#btnrow {
  margin-top: 2.5em;
  margin-bottom: 2.5em;
}

.bki {
  margin-top: 1em;
}

    </style>
  </head>
  <body>
    <h1><?php echo wt(LISTNAME_TEXT); ?></h1>
    
    <p id="btnrow">
      <a class="ctlbtn" href="<?php
          echo MAINFORM_FILE_NAME; ?>">Add new book</a>
    </p>
    
<?php
$bcount = count($books);
for($i = 0; $i < $bcount; $i++) {
  $bk = $books[$i];
?>
  <p class="bki"><a href="<?php echo ISBNDETAIL_FILE_NAME; ?>?isbn=<?php
        echo $bk->getISBN(); ?>"><?php echo wt($bk->getTitle());
        ?></a></p>
<?php
}
?>
  </body>
</html>
