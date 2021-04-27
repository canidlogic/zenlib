<?php

// Include the bootstrap configuration
//
require_once 'zenlib_bootstrap.php';

// If we got data POSTed, perform the generation operation
//
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // POST request -- begin by checking for the uploaded file parameter
  if (array_key_exists('userfile', $_FILES) !== true) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Uploaded file is missing!\n";
    exit;
  }
  
  // Check that uploaded file isn't empty
  if ($_FILES['userfile']['size'] < 1) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Uploaded file is empty!\n";
    exit;
  }
  
  // Next check that uploaded file isn't too large to parse
  if ($_FILES['userfile']['size'] > ZenLibPartial::MAX_XML_LENGTH) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Uploaded XML file is too large!\n";
    exit;
  }
  
  // Parse the file into the partial object
  $ivals = new ZenLibPartial();
  try {
    $ivals->readFromXML($_FILES['userfile']['tmp_name']);
  } catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Error while parsing XML file:\n";
    echo $e->getMessage() . "!\n";
    exit;
  }
  
  // Normalize the given parameter values
  $ivals->normalize();
  
  // Construct a configuration object
  $cfg = NULL;
  try {
    $cfg = ZenLibConfig::complete($ivals);
  } catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Input error:\n" . $e->getMessage() . "!\n";
    exit;
  }
  
  // Determine what kind of file we need to generate and generate it
  $ftype = '';
  if (array_key_exists('ftype', $_POST)) {
    $ftype = $_POST['ftype'];
  }
  if ($ftype === 'index') {
    // index.php generation -- determine full URL to the list page
    $list_url = $cfg->getBaseURL() . $cfg->getMapList();
    
    // Perform PHP escaping
    $list_url = phpEsc($list_url);
    
    // Generate the index page, which simply redirects to the list page
    header('Content-Type: text/plain');
    header(
      'Content-Disposition: attachment; filename="index.php"');
    echo <<<EOT
<?php

// Auto-generated by zenlib_gen.php

http_response_code(302);
header("Location: $list_url");
exit;

EOT;
    exit;
    
  } else if ($ftype === 'vars') {
    // zenlib_vars.php generation -- get all the variables that will be
    // inserted in the generated script
    $zenlib_path = $cfg->getBaseLib() . "zenlib.php";
    $db_path = $cfg->getDBPath();
    $db_covers = $cfg->getDBCovers();
    $dir_list = $cfg->getMapList();
    $dir_entry = $cfg->getMapEntry();
    $dir_detail = $cfg->getMapDetail();
    $dir_add = $cfg->getMapAdd();
    $custom_name = $cfg->getCustomName();
    
    // Drop the trailing slash from covers directory
    $db_covers = substr($db_covers, 0, strlen($db_covers) - 1);
    
    // For all but the custom name, escape so it can appear within a
    // double-quoted PHP literal
    $zenlib_path = phpEsc($zenlib_path);
    $db_path = phpEsc($db_path);
    $db_covers = phpEsc($db_covers);
    $dir_list = phpEsc($dir_list);
    $dir_entry = phpEsc($dir_entry);
    $dir_detail = phpEsc($dir_detail);
    $dir_add = phpEsc($dir_add);
    
    // For the custom name, base64 encode it so it can safely contain
    // Unicode
    $custom_name = base64_encode($custom_name);
    
    // Generate the vars script
    header('Content-Type: text/plain');
    header(
      'Content-Disposition: attachment; filename="zenlib_vars.php"');
    echo <<<EOT
<?php

// Auto-generated by zenlib_gen.php ////////////////////////////////////

/*
 * If this script was invoked directly by a client browser, return a 404
 * error to hide it.
 * 
 * This script may only be used when included from other PHP scripts.
 */
if (__FILE__ === \$_SERVER['SCRIPT_FILENAME']) {
  http_response_code(404);
  header('Content-Type: text/plain');
  echo "Error 404: Not Found\n";
  exit;
}

// Include the main zenlib library module
//
require_once "$zenlib_path";

/*
 * The name to display in the header over the book list.
 */
define('LISTNAME_TEXT', base64_decode('$custom_name'));

/*
 * The file name of the main entry form.
 * 
 * This file should be in the same directory as the other scripts.
 */
const MAINFORM_FILE_NAME = "$dir_entry";

/*
 * The file name of the ISBN detail form.
 * 
 * This file should be in the same directory as the other scripts.
 */
const ISBNDETAIL_FILE_NAME = "$dir_detail";

/*
 * The file name of the main book list.
 * 
 * This file should be in the same directory as the other scripts.
 */
const BOOKLIST_FILE_NAME = "$dir_list";

/*
 * The file name of the book entry script.
 * 
 * This file should be in the same directory as the other scripts.
 */
const ADDBOOK_FILE_NAME = "$dir_add";

/*
 * The full path on the server to the SQLite database for the books.
 */
const BOOKDB_PATH = "$db_path";

/*
 * The full path on the server to the directory holding cover image
 * files where the title is the ISBN-13 and there is no extension.
 * 
 * Do NOT include the trailing slash on this directory name.
 */
const BOOKDB_COVERS_DIR = "$db_covers";

EOT;
    exit;
    
  } else if ($ftype === 'sql') {
    // SQL generation -- get the API key and escape it for SQL
    $akey = sqlEsc($cfg->getISBNdbKey());
    
    // Generate the SQL script
    header('Content-Type: text/plain');
    header(
      'Content-Disposition: attachment; filename="build_zenlib.sql"');
    
    echo <<<EOT
BEGIN EXCLUSIVE TRANSACTION;

CREATE TABLE isbncache(
  id     INTEGER PRIMARY KEY ASC,
  isbn10 TEXT UNIQUE NOT NULL,
  isbn13 TEXT UNIQUE NOT NULL,
  json   TEXT NOT NULL
);

CREATE UNIQUE INDEX ix_isbncache_10
  ON isbncache(isbn10);

CREATE UNIQUE INDEX ix_isbncache_13
  ON isbncache(isbn13);

CREATE TABLE booklist(
  id       INTEGER PRIMARY KEY ASC,
  isbn13   TEXT UNIQUE NOT NULL,
  title    TEXT NOT NULL,
  entry    INTEGER NOT NULL
             CHECK (entry >= 0)
);

CREATE UNIQUE INDEX ix_booklist_13
  ON booklist(isbn13);

CREATE INDEX ix_booklist_title
  ON booklist(title);

CREATE INDEX ix_booklist_entry
  ON booklist(entry);

CREATE TABLE vars(
  id   INTEGER PRIMARY KEY ASC,
  name TEXT UNIQUE NOT NULL,
  val  TEXT NOT NULL
);

CREATE UNIQUE INDEX ix_vars_name
  ON vars(name);

INSERT INTO vars
  (name, val) VALUES
  ('isbndb_key', '$akey');

INSERT INTO vars
  (name, val) VALUES
  ('isbndb_url', 'https://api2.isbndb.com/book/');

END TRANSACTION;

EOT;
    exit;
    
  } else {
    // Unrecognized file type
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Unrecognized generation file type!\n";
    exit;
  }
}

// Determine the maximum upload size
//
$formval_limit = strval(getMaxUploadSize());

?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <title>Zenlib generation</title>
    <meta name="viewport" 
        content="width=device-width, initial-scale=1.0"/>
<?php echo ZENLIB_CFG_WEBFONT; ?>
    <link href="<?php echo ZENLIB_CFG_DIR_CSS; ?>" rel="stylesheet"/>
  </head>
  <body>

    <h1>Zenlib generation</h1>

    <p>Upload a complete, valid parameters file that was created by the
    <a href="<?php echo ZENLIB_CFG_DIR_CONFIG; ?>">configuration
    utility</a> and specify what kind of configuration file you want to
    generate:</p>

    <form enctype="multipart/form-data"
        action="<?php echo ZENLIB_CFG_DIR_GEN; ?>" method="post">
      <input type="hidden" name="MAX_FILE_SIZE" id="MAX_FILE_SIZE"
          value="<?php echo $formval_limit; ?>"/>
      
      <p><input name="userfile" type="file" class="filebtn"/></p>
      
      <p>Generate:
        <select id="ftype" name="ftype" class="comboctl">
          <option
              value="index"
              label="index.php">index.php</option>
          <option
              value="vars"
              label="zenlib_vars.php">zenlib_vars.php</option>
          <option
              value="sql"
              label="SQL build script">SQL build script</option>
        </select>
      </p>
      
      <p><input type="submit" value="Upload" class="submitbtn"/></p>
    </form>

    <hr/>

    <p>The generated <b class="tt">index.php</b> page should be placed
    in the same directory as the public library scripts.  It simply
    redirects the user to the book list page.</p>
    
    <p>The generated <b class="tt">zenlib_vars.php</b> page should also
    be placed in the same directory as the public library scripts.  The
    public library scripts will load this page to get all their basic
    configuration information and figure out where the other scripts
    are.</p>
    
    <p>The generated <b>SQL build script</b> can be used to build a new
    local SQLite database from scratch.  Use the
    <span class="tt">sqlite3</span> utility to open and create a new,
    empty SQLite database file, and then use the
    <span class="tt">.read</span> command in that utility to read the
    generated SQL build script file so that the local database is set up
    properly for use with Zenlib.</p>

  </body>
</html>
