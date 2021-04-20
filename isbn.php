<?php

/*
 * Include the common utilities script
 */
require_once 'isbn_util.php';

?><!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8"/>
    <title>Book ISBN input</title>
    <meta name="viewport" 
        content="width=device-width, initial-scale=1.0"/>
  </head>
  <body>

    <h1>Book ISBN input</h1>

    <form
        action="<?php echo ISBNDETAIL_FILE_NAME; ?>"
        method="get"
        accept-charset="utf-8">
      <input type="hidden" name="action" id="action" value="add"/>
      <p>
        ISBN number:
        <input name="isbn" id="isbn"/>
      </p>
      <p>
        <input type="submit" value="Submit"/>
      </p>
    </form>

    <p>Return to <a href="<?php echo BOOKLIST_FILE_NAME;
                    ?>">book list</a></p>
  </body>
</html>
