<?php

// We only support GET and HEAD requests
//
if (($_SERVER['REQUEST_METHOD'] !== 'GET') &&
    ($_SERVER['REQUEST_METHOD'] !== 'HEAD')) {
  http_response_code(405);
  header('Content-Type: text/plain');
  echo "Invalid request method!\n";
  exit;
}

// If ISBN parameter not provided, error
//
if (array_key_exists('isbn', $_GET) !== true) {
  // Redirect to form
  http_response_code(400);
  header("Content-Type: text/plain");
  echo "Missing parameter";
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
  header('Content-Type: text/plain');
  echo "Invalid ISBN";
  exit;
}

// @@TODO: perform check digit test to reduce actual queries

// Define ISBN query information
//
$query_url = "https://api2.isbndb.com/book/$isbn";
$query_key = 'YOUR_API_KEY';
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

// Echo what we received
header('Content-Type: text/plain');
echo $query_result;
exit;
