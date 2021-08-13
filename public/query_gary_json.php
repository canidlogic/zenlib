<?php

/*
 * Include Gary PHP bridge
 */
require_once __DIR__ . DIRECTORY_SEPARATOR . 'gary.php';

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                           PHP entrypoint                           //
//                                                                    //
////////////////////////////////////////////////////////////////////////

// We only support POST requests
//
if (($_SERVER['REQUEST_METHOD'] !== 'POST')) {
  http_response_code(503);
  header('Content-Type: text/plain');
  echo "Unsupported request method!\n";
  exit;
}

// Check that we got our parameter
//
if (array_key_exists('isbn', $_POST) !== true) {
  http_response_code(400);
  header('Content-Type: text/plain');
  echo "Missing parameter!\n";
  exit;
}

// Invoke a gary query for JSON
//
$result = gary_invoke_python('json', $_POST['isbn']);

// Print the result
//
header('Content-Type: application/json');
echo $result;
