<?php

// Include the bootstrap configuration
//
require_once 'zenlib_bootstrap.php';

// Define a partial object that stores the values that will be used as
// initial values in the form; by default, everything is an empty string
//
$ivals = new ZenLibPartial();

// If we got data POSTed, determine what to do -- note that the file
// upload option will continue on to show the HTML form
//
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // POST request, determine kind of operation
  if (array_key_exists('upload_form', $_POST)) {
    // Fill in form with data from a file upload -- begin by checking
    // for the uploaded file parameter
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
    try {
      $ivals->readFromXML($_FILES['userfile']['tmp_name']);
    } catch (Exception $e) {
      http_response_code(400);
      header('Content-Type: text/plain');
      echo "Error while parsing XML file:\n";
      echo $e->getMessage() . "!\n";
      exit;
    }
    
    // Continue on to show the HTML form
    
  } else if (array_key_exists('gen_param', $_POST)) {
    // Generate XML parameters file -- begin by getting all the given
    // parameter values
    $params = new ZenLibPartial();
    $params->readPosted();
    
    // Normalize the given parameter values
    $params->normalize();
    
    // Construct a configuration object
    $cfg = NULL;
    try {
      $cfg = ZenLibConfig::complete($params);
    } catch (Exception $e) {
      http_response_code(400);
      header('Content-Type: text/plain');
      echo "Input error:\n" . $e->getMessage() . "!\n";
      exit;
    }
    
    // Write the serialized XML
    $xml = $cfg->serialXML();
    
    header('Content-Type: text/xml');
    header(
      'Content-Disposition: attachment; filename="zenlib_config.xml"');
    echo $xml;
    exit;
    
  } else {
    // Unknown request type
    http_response_code(400);
    header('Content-Type: text/plain');
    echo "Invalid request!\n";
    exit;
  }
}

// Function for escaping initial values for use in double-quoted HTML
// attributes
//
function escval($val) {
  return htmlspecialchars($val, ENT_COMPAT | ENT_HTML5, 'UTF-8', true);
}

// Determine the maximum upload size
//
$formval_limit = strval(getMaxUploadSize());

// Determine initial values of each form element
//
$iv_isbndb_key = escval($ivals->getISBNdbKey());
$iv_db_path = escval($ivals->getDBPath());
$iv_covers = escval($ivals->getDBCovers());
$iv_base_url = escval($ivals->getBaseURL());
$iv_base_lib = escval($ivals->getBaseLib());
$iv_fn_list = escval($ivals->getMapList());
$iv_fn_entry = escval($ivals->getMapEntry());
$iv_fn_detail = escval($ivals->getMapDetail());
$iv_fn_add = escval($ivals->getMapAdd());
$iv_listname = JCQTypes::decodeUniString(
                  $ivals->getCustomName(), false);

?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <title>Zenlib configuration</title>
    <meta name="viewport" 
        content="width=device-width, initial-scale=1.0"/>
<?php echo ZENLIB_CFG_WEBFONT; ?>
    <link href="<?php echo ZENLIB_CFG_DIR_CSS; ?>" rel="stylesheet"/>
  </head>
  <body>

    <h1>Zenlib configuration</h1>

    <p>If you have an existing parameters file (partial or complete),
    you can upload it here to fill out the form with the given
    configuration:</p>

    <form enctype="multipart/form-data"
        action="<?php echo ZENLIB_CFG_DIR_CONFIG; ?>" method="post">
      <input type="hidden" name="upload_form" id="upload_form"
          value="true"/>
      <input type="hidden" name="MAX_FILE_SIZE" id="MAX_FILE_SIZE"
          value="<?php echo $formval_limit; ?>"/>
      
      <p><input name="userfile" type="file" class="filebtn"/></p>
      <p><input type="submit" value="Upload" class="submitbtn"/></p>
    </form>

    <hr/>

    <p>Enter the following information to generate a parameters file
    that can be used with the
    <a href="<?php echo ZENLIB_CFG_DIR_GEN; ?>">generation
    utility</a>:</p>
    
    <form
        action="<?php echo ZENLIB_CFG_DIR_CONFIG; ?>" method="post">
      <input type="hidden" name="gen_param" id="gen_param"
          value="true"/>
      <table>
        <tr>
          <td colspan="2" class="secthead">ISBNdb connection</td>
        </tr>
        <tr>
          <th>API&nbsp;key:</th>
          <td class="ctl">
            <input
                name="isbndb_key"
                id="isbndb_key"
                class="mtext"
                value="<?php echo $iv_isbndb_key; ?>"/>
          </td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td class="explain">
            Receive an API key by registering with
            <a href="https://isbndb.com/">ISBNdb</a>
          </td>
        </tr>
        
        <tr>
          <td colspan="2" class="secthead">Local database</td>
        </tr>
        <tr>
          <th>Path:</th>
          <td class="ctl">
            <input
                name="db_path"
                id="db_path"
                class="mtext"
                value="<?php echo $iv_db_path; ?>"/>
          </td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td class="explain">
            The absolute file path to the SQLite database on the server.
            You must set up a database at this location either by
            importing it from an existing snapshot or by using the
            database configuration utility with the parameters file
            created by this script.
          </td>
        </tr>
        <tr>
          <th>Covers:</th>
          <td class="ctl">
            <input
                name="covers"
                id="covers"
                class="mtext"
                value="<?php echo $iv_covers; ?>"/>
          </td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td class="explain">
            The absolute file path to the folder that will hold cached
            book cover images on the server.  This must simply be a
            folder that exists and can be written to by PHP.  Each cover
            image will be stored in a separate file with the file name
            equal to the ISBN-13 number, with no file extension.
          </td>
        </tr>
        
        <tr>
          <td colspan="2" class="secthead">Base locations</td>
        </tr>
        <tr>
          <th>URL:</th>
          <td class="ctl">
            <input
                name="base_url"
                id="base_url"
                class="mtext"
                value="<?php echo $iv_base_url; ?>"/>
          </td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td class="explain">
            The URL of the directory in which all the public Zenlib
            scripts will be published.  This must be an absolute URL
            according to the Jacques-Types URL definition <b>and</b> it
            must end in a forward slash so that the script names defined
            in the next section can be concatenated to this parameter to
            form the full, absolute URL.
          </td>
        </tr>
        <tr>
          <th>Lib:</th>
          <td class="ctl">
            <input
                name="base_lib"
                id="base_lib"
                class="mtext"
                value="<?php echo $iv_base_lib; ?>"/>
          </td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td class="explain">
            The absolute file path to the folder on the server in which
            all the private Zenlib library scripts will be stored.
          </td>
        </tr>
        
        <tr>
          <td colspan="2" class="secthead">Script names</td>
        </tr>
        <tr>
          <th>List:</th>
          <td class="ctl">
            <input
                name="fn_list"
                id="fn_list"
                class="mtext"
                value="<?php echo $iv_fn_list; ?>"/>
          </td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td class="explain">
            The file name of the script that displays the list of books.
            Must follow the Jacques-Types filename definition.
          </td>
        </tr>
        <tr>
          <th>Entry:</th>
          <td class="ctl">
            <input
                name="fn_entry"
                id="fn_entry"
                class="mtext"
                value="<?php echo $iv_fn_entry; ?>"/>
          </td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td class="explain">
            The file name of the script that allows a new book ISBN
            number to be entered into the database.  Must follow the
            Jacques-Types filename definition.
          </td>
        </tr>
        <tr>
          <th>Detail:</th>
          <td class="ctl">
            <input
                name="fn_detail"
                id="fn_detail"
                class="mtext"
                value="<?php echo $iv_fn_detail; ?>"/>
          </td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td class="explain">
            The file name of the script that displays details about a
            specific book given an ISBN number.  Must follow the
            Jacques-Types filename definition.
          </td>
        </tr>
        <tr>
          <th>Add:</th>
          <td class="ctl">
            <input
                name="fn_add"
                id="fn_add"
                class="mtext"
                value="<?php echo $iv_fn_add; ?>"/>
          </td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td class="explain">
            The file name of the script that adds new books into the
            database.  Must follow the Jacques-Types filename
            definition.
          </td>
        </tr>
        
        <tr>
          <td colspan="2" class="secthead">Customization</td>
        </tr>
        <tr>
          <th>Name:</th>
          <td class="ctl">
            <input
                name="listname"
                id="listname"
                class="stext"
                value="<?php echo $iv_listname; ?>"/>
          </td>
        </tr>
        <tr>
          <td>&nbsp;</td>
          <td class="explain">
            The name to display above the book list.
          </td>
        </tr>
        
        <tr>
          <td colspan="2" class="submitrow">
            <input type="submit" value="Submit" class="submitbtn"/>
          </td>
        </tr>
      </table>
    </form>

  </body>
</html>
