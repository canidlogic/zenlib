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
    <title>Search for book</title>
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
//                           PHP entrypoint                           //
//                                                                    //
////////////////////////////////////////////////////////////////////////

// We only support GET and HEAD requests
//
if (($_SERVER['REQUEST_METHOD'] !== 'GET') &&
    ($_SERVER['REQUEST_METHOD'] !== 'HEAD')) {
  report_err('Invalid request method!', 405);
}

// Check that we got our parameter
//
if (array_key_exists('isbn', $_GET) !== true) {
  report_err('Missing parameters!', 400);
}

// Grab the parameter
//
$isbn_param = $_GET['isbn'];

// Transfer only ASCII alphanumerics over to the actual ISBN parameter
// that is used to avoid escaping issues
//
$isbn = '';
$slen = strlen($isbn_param);
for($x = 0; $x < $slen; $x++) {
  $c = ord(substr($isbn_param, $x, 1));
  if ((($c >= ord('0')) && ($c <= ord('9'))) ||
      (($c >= ord('A')) && ($c <= ord('Z'))) ||
      (($c >= ord('a')) && ($c <= ord('z')))) {
    $isbn = $isbn . substr($isbn_param, $x, 1);
  }
}

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                            Dynamic HTML                            //
//                                                                    //
////////////////////////////////////////////////////////////////////////

?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <title>Searching...</title>
    <meta name="viewport" 
        content="width=device-width, initial-scale=1.0"/>
    <link rel="preconnect" href="https://fonts.gstatic.com"/>
    <link
      href="https://fonts.googleapis.com/css2?<?php echo CSS_FAMILIES;
              ?>&display=swap"
      rel="stylesheet"/>
    <link href="main.css" rel="stylesheet"/>
    <style>

#searchline {
  font-weight: bold;
  font-style: italic;
  font-size: larger;
  margin-top: 1.5em;
  margin-bottom: 1.5em;
}

    </style>
    <script src="isbn_query.js"></script>
    <script>

/*
 * Called when the "Cancel" button is pressed.
 *
 * This replaces the current page with the ISBN page.
 *
 * Returns:
 *
 *   false, indicating event has been handled
 */
function cancel_script() {
  location.replace("<?php echo MAINFORM_FILE_NAME; ?>");
  return false;
}

/*
 * Call into the dynamic query script with the ISBN number when the
 * document has loaded.
 *
 * Also, install cancel_script() as the handler for the cancel button.
 */
window.onload = function() {
  
  // Install cancel_script() as handler for cancel button
  var a = document.getElementById("cancel_btn");
  a.onclick = cancel_script;
  
  // Call into the query handler
  isbn_query("<?php echo $isbn; ?>",
              "<?php echo ISBNDETAIL_FILE_NAME; ?>");
};

    </script>
  </head>
  <body>
  
  <p id="searchline">Searching for book...</p>
  
  <p>
    <a 
      class="ctlbtn"
      href="<?php echo MAINFORM_FILE_NAME; ?>"
      id="cancel_btn">Cancel</a>
  </p>
  
  </body>
</html>
