<?php

/*
 * Include the common utilities script
 */
require_once 'isbn_util.php';

// Determine whether the barcode scanning browser is used
//
$barcodes = usingScanner();

?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <title>Book ISBN input</title>
    <meta name="viewport" 
        content="width=device-width, initial-scale=1.0"/>
    <link rel="preconnect" href="https://fonts.gstatic.com"/>
    <link
      href="https://fonts.googleapis.com/css2?<?php echo CSS_FAMILIES;
              ?>&display=swap"
      rel="stylesheet"/>
    <link href="main.css" rel="stylesheet"/>
    <style>

.scanbox {
  margin-top: 2.5em;
  margin-bottom: 2.5em;
}

.tctl {
  margin-top: 1.5em;
}

.btd {
  padding-left: 1em;
}
    
    </style>
  </head>
  <body>

    <h1>Book ISBN input</h1>

<?php if ($barcodes) { ?>
    <p class="scanbox">
      <a
          href="bwstw://startscanner?field=isbn"
          class="ctlbtn">Scan barcode</a>
    </p>
<?php } ?>

    <form
        action="<?php echo ISBNDETAIL_FILE_NAME; ?>"
        method="get"
        accept-charset="utf-8">
      <input type="hidden" name="action" id="action" value="add"/>
      <table class="tctl">
        <tr>
          <td>ISBN number:</td>
          <td class="btd">
            <input name="isbn" id="isbn" class="entryctl"/>
          </td>
        </tr>
      </table>
      <table class="tctl">
        <tr>
          <td><input type="submit" value="Submit" class="ctlbtn"/></td>
          <td class="btd">
            <a class="ctlbtn" href="<?php echo BOOKLIST_FILE_NAME;
                    ?>">Return to list</a>
          </td>
        </tr>
      </table>
    </form>
  </body>
</html>
