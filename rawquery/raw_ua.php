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

// Echo the user-agent string
//
header('Content-Type: text/plain');
echo $_SERVER['HTTP_USER_AGENT'] . "\n";

