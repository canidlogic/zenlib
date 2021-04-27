<?php

// Include the bootstrap configuration
//
require_once 'zenlib_bootstrap.php';

// Set the output type to CSS stylesheet
//
header('Content-Type: text/css');

?>body {
  max-width: 35em;
  font-family: <?php echo ZENLIB_CFG_SERIF; ?>;
}

:link {
  text-decoration: none;
  color: blue
}

:visited {
  text-decoration: none;
  color: blue
}

h1 {
  font-family: <?php echo ZENLIB_CFG_SANS_SERIF; ?>;
}

th {
  text-align: left;
  font-weight: bold;
}

.ctl {
  padding-left: 0.5em;
}

.secthead {
  padding-top: 1.5em;
  padding-bottom: 1em;
  text-align: center;
  text-decoration: underline;
  font-family: <?php echo ZENLIB_CFG_SANS_SERIF; ?>;
  font-size: larger;
}

.explain {
  padding-left: 0.5em;
  padding-bottom: 1em;
  font-size: smaller;
  font-style: italic;
}

.submitrow {
  padding-top: 1.5em;
}

.submitbtn {
  font-size: larger;
  padding: 0.5em;
  border: medium outset silver;
  background-color: WhiteSmoke;
  color: blue;
  cursor: pointer;
}

.filebtn {
  font-size: larger;
}

.mtext {
  font-family: <?php echo ZENLIB_CFG_MONOSPACE; ?>;
  font-size: larger;
  width: 20em;
}

.stext {
  font-family: <?php echo ZENLIB_CFG_SANS_SERIF; ?>;
  font-size: larger;
  width: 20em;
}

.tt {
  font-family: <?php echo ZENLIB_CFG_MONOSPACE; ?>;
}
