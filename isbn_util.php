<?php

/*
 * If this script was invoked directly by a client browser, return a 404
 * error to hide it.
 * 
 * This script may only be used when included from other PHP scripts.
 */
if (__FILE__ === $_SERVER['SCRIPT_FILENAME']) {
  http_response_code(404);
  header('Content-Type: text/plain');
  echo "Error 404: Not Found\n";
  exit;
}

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                      Configuration constants                       //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * The name to display in the header over the book list.
 */
const LISTNAME_TEXT = 'My Library';

/*
 * The file name of the main entry form.
 * 
 * This file should be in the same directory as the other scripts.
 */
const MAINFORM_FILE_NAME = 'isbn.php';

/*
 * The file name of the ISBN detail form.
 * 
 * This file should be in the same directory as the other scripts.
 */
const ISBNDETAIL_FILE_NAME = 'isbn_detail.php';

/*
 * The file name of the main book list.
 * 
 * This file should be in the same directory as the other scripts.
 */
const BOOKLIST_FILE_NAME = 'list.php';

/*
 * The file name of the book entry script.
 * 
 * This file should be in the same directory as the other scripts.
 */
const ADDBOOK_FILE_NAME = 'add_book.php';

/*
 * The full path on the server to the SQLite database for the books.
 */
const BOOKDB_PATH = '/home/example_user/books.sqlite';

/*
 * The full path on the server to the directory holding cover image
 * files where the title is the ISBN-13 and there is no extension.
 * 
 * Do NOT include the trailing slash on this directory name.
 */
const BOOKDB_COVERS_DIR = '/home/example_user/covers';

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                          Other constants                           //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * The special code at the beginning of the User-Agent string for the
 * barcode scanning browser.
 */
const SCANNER_UA = 'scan_to_web';

/*
 * The font family imports.
 * 
 * See main.css for more information.
 */
const CSS_FAMILIES =
  'family=Merriweather&family=Roboto&family=Roboto+Mono';

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                              Functions                             //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * Determine with a User-Agent query whether the client is using the
 * special "Scan To Web" barcode-enabled browser.
 * 
 * If this returns true, you can safely use extensions that are designed
 * for that special web browser.  Otherwise, extensions should not be
 * displayed.
 * 
 * Return:
 * 
 *   true if using "Scan To Web" browser, false if not
 */
function usingScanner() {
  
  // Get the user-agent string
  $ua = $_SERVER['HTTP_USER_AGENT'];
  
  // Trim leading and trailing whitespace
  $ua = trim($ua);
  
  // Begin with false result
  $result = false;
  
  // Only proceed if UA string long enough for the scanner UA
  if (strlen($ua) >= strlen(SCANNER_UA)) {
    // Get the beginning of the UA with a length matching the scanner
    // UA
    $ua = substr($ua, 0, strlen(SCANNER_UA));
    
    // Lowercase transform
    $ua = strtolower($ua);
    
    // Check if equal to scanner UA
    if ($ua === SCANNER_UA) {
      $result = true;
    }
  }
  
  // Return result
  return $result;
}

/*
 * Send a redirect to the client so that they go to the main ISBN input
 * form.
 * 
 * For this to work properly, the script must not yet have generated any
 * output.
 * 
 * This function will not return.
 * 
 * Return:
 * 
 *   this function does not return!
 */
function redirectToForm() {
  
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
  $form_path = $prot . $server_name . $parent_dir . MAINFORM_FILE_NAME;
  
  // Redirect to form
  http_response_code(302);
  header("Location: $form_path");
  exit;
}

/*
 * Given an ISBN string, normalize the string so that it only contains
 * the relevant digits.
 * 
 * This function drops all ASCII whitespace characters (tab, space,
 * carriage return, line feed) and all ASCII characters that are not
 * alphanumeric.
 * 
 * It also converts all ASCII letters to uppercase.  Note that ISBN-10
 * numbers may have an "X" as their check digit!
 * 
 * This function does NOT guarantee that the value it returns is a valid
 * ISBN.  You must use checkISBN for that.
 * 
 * Passing a non-string as the parameter is equivalent to passing an
 * empty string.
 * 
 * Parameters:
 * 
 *   $str : string | mixed - the ISBN number string to normalize
 * 
 * Return:
 * 
 *   the normalized ISBN string, which is NOT guaranteed to be valid
 */
function normISBN($str) {

  // If non-string passed, replace with empty string
  if (is_string($str) !== true) {
    $str = '';
  }

  // Begin with empty result
  $isbn = '';
  
  // Go through each character of string
  $slen = strlen($str);
  for($i = 0; $i < $slen; $i++) {
    
    // Get current character code
    $c = ord($str[$i]);
    
    // Handle based on character type
    if (($c >= ord('a')) && ($c <= ord('z'))) {
      // Lowercase letter, so transfer uppercase to normalized isbn
      $isbn = $isbn . chr($c - 0x20);
    
    } else if (($c >= ord('A')) && ($c <= ord('Z'))) {
      // Uppercase letter, so transfer to normalized isbn
      $isbn = $isbn . chr($c);
    
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
      // Control or extended character so transter to normalized
      $isbn = $isbn . chr($c);
    }
  }
  
  // Return normalized string
  return $isbn;
}

/*
 * Check whether the given normalized ISBN is valid.
 * 
 * You must first normalize the ISBN with normISBN() before passing to
 * this function or non-normalized forms will fail validation.
 * 
 * To be valid, the given variable must be a string that is either 10
 * characters or 13 characters long.
 * 
 * Furthermore, if it is 10 characters, then the first 9 characters must
 * be decimal digits and the last character may either be a decimal
 * digit or uppercase letter X.
 * 
 * If it is 13 characters, then all 13 characters must decimal digits.
 * 
 * Finally, this function extracts the last digit, which is the check
 * digit, and uses it to check for small typos.  If the check digit is
 * not correct, validation will fail.
 * 
 * Parameters:
 * 
 *   $str : string | mixed - the normalized ISBN string to validate
 * 
 * Return:
 * 
 *   true if valid, false if not
 */
function checkISBN($str) {
  
  // If not a string, fail validation
  if (is_string($str) !== true) {
    return false;
  }
  
  // Get length and check that it is either 10 or 13
  $slen = strlen($str);
  if (($slen !== 10) && ($slen !== 13)) {
    return false;
  }
  
  // Result starts out true
  $result = true;
  
  // Check that digits are valid
  for($x = 0; $x < $slen; $x++) {
    
    // Get current character code
    $c = ord($str[$x]);
    
    // If this is last character AND 10-digit ISBN AND last character is
    // uppercase X, then allow it
    if (($x >= $slen - 1) && ($slen === 10) && ($c === ord('X'))) {
      continue;
    }
    
    // Otherwise, character must be a decimal digit
    if (($c < ord('0')) || ($c > ord('9'))) {
      $result = false;
      break;;
    }
  }
  
  // If we didn't pass the format check, fail
  if ($result !== true) {
    return false;
  }
  
  // If we got here, do check digit verification depending on type of
  // ISBN
  if ($slen === 10) {
    // ISBN-10 number, so get the check digit value with X used for a
    // check value of 10
    $checkv = ord($str[9]);
    if (($checkv >= ord('0')) && ($checkv <= ord('9'))) {
      $checkv = $checkv - ord('0');
    } else if ($checkv === ord('X')) {
      $checkv = 10;
    } else {
      // Shouldn't happen
      throw new Exception("isbn_util-" . strval(__LINE__));
    }
    
    // Compute the weighted sum of the non-check digits
    $wsum = 0;
    for($i = 0; $i < 9; $i++) {
      $wsum = $wsum + ((10 - $i) * (ord($str[$i]) - ord('0')));
    }
    
    // Add the value of the check digit to the weighted sum
    $wsum += $checkv;
    
    // If weighted sum is multiple of 11, check passes; else, check
    // fails
    if (($wsum % 11) === 0) {
      $result = true;
    } else {
      $result = false;
    }
    
  } else if ($slen === 13) {
    // ISBN-13 number, so compute the weighted sum
    $wsum = 0;
    for($i = 0; $i < 13; $i++) {
      // Get current digit value
      $d = ord($str[$i]) - ord('0');
      
      // If zero-based character index mod 2 is one, then weight is 3;
      // else, it is one
      $r = 1;
      if (($i % 2) === 1) {
        $r = 3;
      }

      // Update weighted sum
      $wsum += ($r * $d);
    }

    // If weighted sum is multiple of 10, check passes; else, check
    // fails
    if (($wsum % 10) === 0) {
      $result = true;
    } else {
      $result = false;
    }
    
  } else {
    // Shouldn't happen
    throw new Exception("isbn_util-" . strval(__LINE__));
  }
  
  // Return result
  return $result;
}
