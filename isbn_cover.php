<?php

/*
 * Include the common utilities script
 */
require_once 'isbn_util.php';

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                              Functions                             //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * Given an ISBN number, get the cover image path from the local
 * database cache.
 * 
 * The given ISBN must be normalized and valid or an exception is
 * thrown.
 * 
 * Exceptions are thrown if there is an error.  false is returned if the
 * ISBN is not in the local cache or if it is but it doesn't have an
 * associated cover image.
 * 
 * Parameters:
 * 
 *   $isbn : string - the normalized, valid ISBN to query for
 * 
 * Return:
 * 
 *   string path to the cover image file or false if not found
 */
function getCoverPath($isbn) {
  
  // Check parameter
  if (checkISBN($isbn) !== true) {
    throw new Exception("isbn_cover-" . strval(__LINE__));
  }
  
  // Attempt to open a local database connection, and wrap in a
  // try-finally block so database connection always closed on exit
  $db = NULL;
  $result = false;
  try {
    // Open the database with a read-only connection
    try {
      $db = new SQLite3(BOOKDB_PATH, SQLITE3_OPEN_READONLY);
    } catch (Exception $e) {
      throw new Exception("Couldn't open local database");
    }
    
    // Query for the ISBN-13
    $qr = NULL;
    try {
      // Query based on ISBN-10 or ISBN-13
      if (strlen($isbn) === 13) {
        $qr = $db->query(
              "SELECT isbn13 FROM isbncache WHERE isbn13='$isbn'");
        
      } else if (strlen($isbn) === 10) {
        $qr = $db->query(
              "SELECT isbn13 FROM isbncache WHERE isbn10='$isbn'");
        
      } else {
        // Shouldn't happen
        throw new Exception("isbn_cover-" . strval(__LINE__));
      }
      if ($qr === false) {
        $qr = NULL;
        throw new Exception("Couldn't query for cover image");
      }
      
      // Get the row as an associative array
      $r = $qr->fetchArray(SQLITE3_ASSOC);
      
      // Only proceed if there was a row
      if ($r !== false) {
        $result = $r['isbn13'];
        if (is_string($result) === false) {
          throw new Exception("Wrong kind of ISBN data returned");
        }
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
  
  // If we found the database record, result currently holds the ISBN-13
  // number, so turn it into a full file path
  if ($result !== false) {
    $result = BOOKDB_COVERS_DIR . '/' . $result;
  }
  
  // If image file doesn't exist, set to false
  if ($result !== false) {
    if (is_file($result) !== true) {
      $result = false;
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
  http_response_code(405);
  header('Content-Type: text/plain');
  echo "Invalid request method!\n";
  exit;
}

// If ISBN parameter not provided, 404 error
//
if (array_key_exists('isbn', $_GET) !== true) {
  http_response_code(404);
  header('Content-Type: text/plain');
  echo "File not found!\n";
  exit;
}

// Grab the ISBN number
//
$isbn = $_GET['isbn'];

// Normalize the ISBN number
//
$isbn = normISBN($isbn);

// If ISBN number is not valid, 404 error; this also checks the check
// digit
//
if (checkISBN($isbn) !== true) {
  http_response_code(404);
  header('Content-Type: text/plain');
  echo "File not found!\n";
  exit;
}

// Try to get the cover image path from the cache
//
$image = NULL;
try {
  $image = getCoverPath($isbn);
  
} catch (Exception $e) {
  http_response_code(404);
  header('Content-Type: text/plain');
  echo $e->getMessage() . "!\n";
  exit;
}

// If we couldn't locate image, return 404
//
if ($image === false) {
  http_response_code(404);
  header('Content-Type: text/plain');
  echo "Couldn't find cover image!\n";
  exit;
}

// Figure out the type of image
$itype = exif_imagetype($image);
if ($itype === false) {
  http_response_code(500);
  header('Content-Type: text/plain');
  echo "Can't determine image file type!";
  exit;
}

// We only support GIF, JPEG, PNG, WEBP
if (($itype !== IMAGETYPE_GIF) &&
    ($itype !== IMAGETYPE_JPEG) &&
    ($itype !== IMAGETYPE_PNG) &&
     ($itype !== IMAGETYPE_WEBP)) {
  http_response_code(500);
  header('Content-Type: text/plain');
  echo "Unsupported image type!";
  exit;
}
  
// Get the image MIME type
$mtype = image_type_to_mime_type($itype);
  
// Write the content type header
header("Content-Type: $mtype");
  
// Write the image file to output
readfile($image);
