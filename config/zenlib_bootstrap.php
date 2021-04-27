<?php

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                        zenlib_bootstrap.php                        //
//                                                                    //
////////////////////////////////////////////////////////////////////////

// This is the configuration script used by the zenlib configuration
// utility scripts.
//
// To avoid a circular dependency, the configuration scripts can't rely
// on themselves for configuration.  Hence the need for this script.
//
// CONFIGURATION
// =============
//
// - You must set BOOTSTRAP_TYPES_MODULE to the path to the
//   Jacques-Types PHP script:
//
// define('BOOTSTRAP_TYPES_MODULE', '/path/to/jcqtypes.php');

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

/*
 * Check that BOOTSTRAP_TYPES_MODULE has been defined.
 */
if (defined('BOOTSTRAP_TYPES_MODULE') !== true) {
  http_response_code(500);
  header('Content-Type: text/plain');
  echo "zenlib_bootstrap.php hasn't been configured yet!\n";
  exit;
}

/*
 * Include the Jacques-Types module
 */
require_once BOOTSTRAP_TYPES_MODULE;

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                       Configuration sitemap                        //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * ZENLIB_CFG_DIR_CSS
 * 
 * The path to the configuration CSS stylesheet.
 * 
 * It is recommended that all configuration utilities be in a single
 * directory and that the path be set to just the name of the script.
 */
define('ZENLIB_CFG_DIR_CSS', "zenlib_config_css.php");

/*
 * ZENLIB_CFG_DIR_CONFIG
 * 
 * The path to the configuration script page.
 * 
 * It is recommended that all configuration utilities be in a single
 * directory and that the path be set to just the name of the script.
 */
define('ZENLIB_CFG_DIR_CONFIG', "zenlib_config.php");

/*
 * ZENLIB_CFG_DIR_GEN
 * 
 * The path to the generation script page.
 * 
 * It is recommended that all configuration utilities be in a single
 * directory and that the path be set to just the name of the script.
 */
define('ZENLIB_CFG_DIR_GEN', "zenlib_gen.php");

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                         Font configuration                         //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * ZENLIB_CFG_WEBFONT
 * 
 * This constant defines text that is inserted in the HTML header before
 * any style declarations, which is intended for importing web fonts.
 */
define('ZENLIB_CFG_WEBFONT',
  "    " .
  "<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\"/>\n" .
  "    " .
  "<link href=\"https://fonts.googleapis.com/css2?" .
  "family=Merriweather" .
  "&family=Roboto" .
  "&family=Roboto+Mono" .
  "&display=swap\" rel=\"stylesheet\"/>\n");

/*
 * ZENLIB_CFG_SERIF
 * 
 * The CSS value to use for font-family declarations using a serif 
 * style.  This is just the CSS value, not including the semicolon.
 */
define('ZENLIB_CFG_SERIF', "'Merriweather', serif");

/*
 * ZENLIB_CFG_SANS_SERIF
 * 
 * The CSS value to use for font-family declarations using a sans-serif 
 * style.  This is just the CSS value, not including the semicolon.
 */
define('ZENLIB_CFG_SANS_SERIF', "'Roboto', sans-serif");

/*
 * ZENLIB_CFG_MONOSPACE
 * 
 * The CSS value to use for font-family declarations using a monospace
 * style.  This is just the CSS value, not including the semicolon.
 */
define('ZENLIB_CFG_MONOSPACE', "'Roboto Mono', monospace");

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                              Functions                             //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * Determine the maximum upload size in bytes by looking at the PHP
 * configuration.
 * 
 * Return:
 * 
 *   an integer value for the maximum upload size
 */
function getMaxUploadSize() {

  // Get the INI value for the maximum upload size
  $str = ini_get('upload_max_filesize');

  // If INI option not found, set to 6M for six megabytes
  if (is_string($str) !== true) {
    $str = '6M';
  }

  // Trim whitespace from INI option
  $str = trim($str);
  
  // If empty after trimming, set to 6M for six megatbytes
  if (strlen($str) < 1) {
    $str = '6M';
  }

  // Get last character numeric value
  $lc = ord($str[strlen($str) - 1]);

  // If last character is alphabetic, set suffix to it as lowercase and
  // drop from string; else, set suffix to -
  $suffix = '-';
  if (($lc >= ord('A')) && ($lc <= ord('Z'))) {
    $suffix = strtolower(chr($lc));
    $str = substr($str, 0, strlen($str) - 1);
  
  } else if (($lc >= ord('a')) && ($lc <= ord('z'))) {
    $suffix = chr($lc);
    $str = substr($str, 0, strlen($str) - 1);
  }

  // Trim string again
  $str = trim($str);
  
  // If string empty after removing suffix, set to one
  if (strlen($str) < 1) {
    $str = '1';
  }

  // If string is not numeric, set to one
  if (is_numeric($str) !== true) {
    $str = '1';
  }
  
  // Get integer value of string
  $result = intval($str);
  
  // If suffix (made lowercase) is recognized, adjust result
  if ($suffix === 'g') {
    $result *= 1024 * 1024 * 1024;
  
  } else if ($suffix === 'm') {
    $result *= 1024 * 1024;
    
  } else if ($suffix === 'k') {
    $result *= 1024;
  }
  
  // If result less than 1K, set to 1K
  if ($result < 1024) {
    $result = 1024;
  }
  
  // Return result
  return $result;
}

/*
 * Check whether the given parameter is a string AND it only contains
 * printing US-ASCII characters in range [0x20, 0x7E].
 * 
 * Parameters:
 * 
 *   $str : string | mixed - the value to check
 * 
 * Return:
 * 
 *   true if a plain ASCII string, false else
 */
function isPlainASCII($str) {
  
  // Check whether parameter is string
  if (is_string($str) !== true) {
    return false;
  }
  
  // Check all characters within the string
  $slen = strlen($str);
  for($x = 0; $x < $slen; $x++) {
    $c = ord($str[$x]);
    if (($c < 0x20) || ($c > 0x7e)) {
      return false;
    }
  }
  
  // If we got here, string value checks out
  return true;
}

/*
 * Escape a string so it can be included within a double-quoted string
 * literal in PHP source code.
 * 
 * The string must pass isPlainASCII or an Exception is thrown.
 * 
 * Parameters:
 * 
 *   $str : string - the string to escape
 * 
 * Return:
 * 
 *   the escaped string
 */
function phpEsc($str) {
  
  // Check parameter
  if (isPlainASCII($str) !== true) {
    throw new Exception("zenlib_bootstrap-" . strval(__LINE__));
  }
  
  // Escape backslashes
  $str = str_replace("\\", "\\\\", $str);
  
  // Escape dollar signs and double quotes
  $str = str_replace('$', "\\\$", $str);
  $str = str_replace('"', "\\\"", $str);
  
  // Return escaped value
  return $str;
}

/*
 * Escape a string so it can be included within a string literal in
 * generated SQL statements.
 * 
 * The string must pass isPlainASCII or an Exception is thrown.
 * 
 * Parameters:
 * 
 *   $str : string - the string to escape
 * 
 * Return:
 * 
 *   the escaped string
 */
function sqlEsc($str) {
  
  // Check parameter
  if (isPlainASCII($str) !== true) {
    throw new Exception("zenlib_bootstrap-" . strval(__LINE__));
  }
  
  // Escape single quotes as doubled single quotes
  $str = str_replace("'", "''", $str);
  
  // Return escaped value
  return $str;
}

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                         ZenLibCfgXML class                         //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * Class to aid in parsing XML configuration files.
 * 
 * This contains both a start and end handler.
 */
class ZenLibCfgXML {
  
  // Fields
  //
  private $m_depth;
  private $m_wrong_type;
  private $m_map;
  
  /*
   * Constructor.
   */
  public function __construct() {
    // Start the depth off at zero and the wrong type flag cleared and
    // the map as an empty array
    $this->m_depth = 0;
    $this->m_wrong_type = false;
    $this->m_map = array();
  }
  
  /*
   * Callback function for handling start element events.
   * 
   * The function prototype matches that of xml_set_element_handler for
   * the start element handler.
   * 
   * Parameters:
   * 
   *   $parser : ? - (parameter ignored)
   * 
   *   $name : string - the name of the element
   * 
   *   $attribs : array - keys are the attribute names, values are the
   *   attribute values
   */
  public function start_element($parser, $name, $attribs) {
    
    // Ignore call if wrong type flag set
    if ($this->m_wrong_type) {
      return;
    }
    
    // Check parameters
    if (is_string($name) !== true) {
      throw new Exception('zenlib_bootstrap-' . strval(__LINE__));
    }
    if (is_array($attribs) !== true) {
      throw new Exception('zenlib_bootstrap-' . strval(__LINE__));
    }
    
    // Increase element depth
    $this->m_depth += 1;
    
    // Handling depends on the element depth -- also, ignore all
    // elements at a depth of 3 or greater
    if ($this->m_depth < 1) {
      // Shouldn't happen
      throw new Exception('zenlib_bootstrap-' . strval(__LINE__));
    
    } else if ($this->m_depth === 1) {
      // Top-level element, so check that root element has proper name
      $name = trim(strtolower($name));
      if ($name !== 'zenlib-config') {
        // Wrong top-level element
        $this->m_wrong_type = true;
      }
    
    } else if ($this->m_depth === 2) {
      // Immediate descendant of top-level element, so only proceed if
      // its element name is "var"
      $name = trim(strtolower($name));
      if ($name === 'var') {
        // var element, so convert all attribute names to lowercase
        $attribs = array_change_key_case($attribs, CASE_LOWER);
        if (is_null($attribs)) {
          throw new Exception('zenlib_bootstrap-' . strval(__LINE__));
        }
        
        // Only proceed if 'name' attribute exists
        if (array_key_exists('name', $attribs)) {
        
          // Get name and normalize atom name
          $vname = $attribs['name'];
          $vname = JCQTypes::normAtom($vname);
          
          // Only proceed if name is valid atom
          if (JCQTypes::checkAtom($vname)) {
          
            // Set value to empty string to begin with
            $val = '';
            
            // If 'value' attribute exists, set value to that
            if (array_key_exists('value', $attribs)) {
              $val = $attribs['value'];
            }
            
            // If value is now not a string, set to empty string
            if (is_string($val) !== true) {
              $val = '';
            }
            
            // Add to the mapping, overwriting an existing value if
            // present
            $this->m_map[$vname] = $val;
          }
        }
      }
    }
  }
  
  /*
   * Callback function for handling end element events.
   * 
   * The function prototype matches that of xml_set_element_handler for
   * the end element handler.
   * 
   * Parameters:
   * 
   *   $parser : ? - (parameter ignored)
   * 
   *   $name : ? - (parameter ignored)
   */
  public function end_element($parser, $name) {
    
    // Ignore call if wrong type flag set
    if ($this->m_wrong_type) {
      return;
    }
    
    // Decrease depth
    $this->m_depth -= 1;
    if ($this->m_depth < 0) {
      throw new Exception('zenlib_bootstrap-' . strval(__LINE__));
    }
  }
  
  /*
   * Get the current result of processing callbacks.
   * 
   * This is normally an array mapping normalized atom names of
   * configuration variables to their values.
   * 
   * If the top-level element in the XML file was not correct, this
   * function just returns false.
   * 
   * Return:
   * 
   *   associative array mapping configuration variable names to string
   *   values, or false if top-level element in XML was not correct
   */
  public function getResult() {
    if ($this->m_wrong_type) {
      return false;
    } else {
      return $this->m_map;
    }
  }
}

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                         ZenLibPartial class                        //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * This class represents a partial, unchecked set of configuration
 * options.
 * 
 * For a full, checked set of configuration options, use ZenLibConfig.
 */
class ZenLibPartial {
  
  // Maximum length in bytes of the uploaded XML file
  //
  const MAX_XML_LENGTH = 1048576;
  
  // Fields representing the parameter values
  //
  private $m_isbndb_key;
  
  private $m_db_path;
  private $m_db_covers;
  
  private $m_base_url;
  private $m_base_lib;
  
  private $m_map_list;
  private $m_map_entry;
  private $m_map_detail;
  private $m_map_add;
  
  private $m_custom_name;
  
  /*
   * Read a POSTed variable.
   * 
   * This assumes the request method is POST.  Undefined behavior occurs
   * if this is not the case.
   * 
   * varname is the variable name to read.  It must be a string or an
   * Exception is thrown.
   * 
   * If the variable exists as a POSTed variable AND it is a string,
   * then this returns the string value.  Otherwise, it returns an empty
   * string.
   * 
   * Parameters:
   * 
   *   $varname : string - the POSTed variable name
   * 
   * Return:
   * 
   *   the variable value or empty string
   */
  private static function readVarValue($varname) {
    
    // Check parameter
    if (is_string($varname) !== true) {
      throw new Exception('zenlib_bootstrap-' . strval(__LINE__));
    }
    
    // Start with empty string result
    $result = '';
    
    // Only proceed if variable is present
    if (array_key_exists($varname, $_POST)) {
      
      // Read the variable
      $result = $_POST[$varname];
      
      // If not a string, blank it to empty string
      if (is_string($result) !== true) {
        $result = '';
      }
    }
    
    // Return result
    return $result;
  }
  
  /*
   * Read a configuration variable received from the given associative
   * array.
   * 
   * varname is the variable name to read.  It must be a string or an
   * Exception is thrown.
   * 
   * vars is an associative array mapping variable names to variable
   * values.
   * 
   * Parameters:
   * 
   *   $varname : string - the variable name
   * 
   *   $vars : array - the associative array of variables
   * 
   * Return:
   * 
   *   the variable value or empty string
   */
  private static function readXMLValue($varname, $vars) {
    
    // Check parameters
    if (is_string($varname) !== true) {
      throw new Exception('zenlib_bootstrap-' . strval(__LINE__));
    }
    if (is_array($vars) !== true) {
      throw new Exception('zenlib_bootstrap-' . strval(__LINE__));
    }
    
    // Start with empty string result
    $result = '';
    
    // Only proceed if variable is present
    if (array_key_exists($varname, $vars)) {
      
      // Read the variable
      $result = $vars[$varname];
      
      // If not a string, blank it to empty string
      if (is_string($result) !== true) {
        $result = '';
      }
    }
    
    // Return result
    return $result;
  }
  
  /*
   * The constructor simply initializes all fields to empty strings,
   * indicating they have not been set yet.
   */
  public function __construct() {
    $this->m_isbndb_key = '';
    $this->m_db_path = '';
    $this->m_db_covers = '';
    $this->m_base_url = '';
    $this->m_base_lib = '';
    $this->m_map_list = '';
    $this->m_map_entry = '';
    $this->m_map_detail = '';
    $this->m_map_add = '';
    $this->m_custom_name = '';
  }
  
  /*
   * Access functions for each of the field values.
   * 
   * Note that the field values are NOT guaranteed to be valid.  Fields
   * that haven't been set will be empty strings.
   */
  public function getISBNdbKey() { return $this->m_isbndb_key; }
  public function getDBPath() { return $this->m_db_path; }
  public function getDBCovers() { return $this->m_db_covers; }
  public function getBaseURL() { return $this->m_base_url; }
  public function getBaseLib() { return $this->m_base_lib; }
  public function getMapList() { return $this->m_map_list; }
  public function getMapEntry() { return $this->m_map_entry; }
  public function getMapDetail() { return $this->m_map_detail; }
  public function getMapAdd() { return $this->m_map_add; }
  public function getCustomName() { return $this->m_custom_name; }
  
  /*
   * Read all the fields from POSTed variables.
   * 
   * The variable names are those defined in the zenlib_config form.
   * 
   * This can only be used when the request method is POST or an
   * Exception is thrown.
   * 
   * Fields that are not present as variables or are not strings will be
   * set to empty strings.
   */
  public function readPosted() {
    
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
      throw new Exception('zenlib_bootstrap-' . strval(__LINE__));
    }
    
    // Read available variables
    $this->m_isbndb_key = self::readVarValue('isbndb_key');
    $this->m_db_path = self::readVarValue('db_path');
    $this->m_db_covers = self::readVarValue('covers');
    $this->m_base_url = self::readVarValue('base_url');
    $this->m_base_lib = self::readVarValue('base_lib');
    $this->m_map_list = self::readVarValue('fn_list');
    $this->m_map_entry = self::readVarValue('fn_entry');
    $this->m_map_detail = self::readVarValue('fn_detail');
    $this->m_map_add = self::readVarValue('fn_add');
    
    // For the custom name, use Unicode string encoding without
    // multiline support
    $this->m_custom_name = JCQTypes::makeUniString(
                                self::readVarValue('listname'),
                                false);
  }
  
  /*
   * Clear all fields to empty strings and then read any applicable
   * fields from a given XML file.
   * 
   * The XML file must be an uploaded file or this function will fail.
   * 
   * The XML format matches the format generated by the serialXML()
   * method of ZenLibConfig.  However, this function does not perform
   * strict checking, and there is no guarantee that all variables are
   * present nor that all have valid values.
   * 
   * An Exception is thrown if there is a problem.
   * 
   * Parameters:
   * 
   *   $fpath : string - the path to the XML file to read
   */
  public function readFromXML($fpath) {
    
    // Check parameters
    if (is_string($fpath) !== true) {
      throw new Exception('zenlib_bootstrap-' . strval(__LINE__));
    }
    
    // Check whether path is to an uploaded file
    if (is_uploaded_file($fpath) !== true) {
      throw new Exception("Can't read uploaded file");
    }
    
    // Read the XML file into memory
    $data = file_get_contents(
              $fpath, false, NULL, 0, self::MAX_XML_LENGTH + 16);
    if ($data === false) {
      throw new Exception("Can't read uploaded file");
    }
    if (strlen($data) > self::MAX_XML_LENGTH) {
      throw new Exception("XML file is too large");
    }
    
    // Create an XML callback receiver
    $recv = new ZenLibCfgXML();
    
    // Create a new XML parser
    $parser = xml_parser_create('UTF-8');
    if ($parser === false) {
      throw new Exception('zenlib_bootstrap-' . strval(__LINE__));
    }
    
    // Wrap in a try-finally so that XML parser is always freed
    try {
      
      // Register the XML callback receiver
      if (xml_set_element_handler(
              $parser,
              array($recv, 'start_element'),
              array($recv, 'end_element')) !== true) {
        throw new Exception('zenlib_bootstrap-' . strval(__LINE__));
      }
      
      // Parse the XML data
      if (xml_parse($parser, $data, true) === 0) {
      
        // Get the error message
        $msg = xml_get_error_code($parser);
        if ($msg !== false) {
          $msg = xml_error_string($msg);
          if ($msg === false) {
            $msg = "Unknown error";
          }
        } else {
          $msg = "Unknown error";
        }
      
        // Get the line number, or -1 if unknown
        $lnum = xml_get_current_line_number($parser);
        if ($lnum === false) {
          $lnum = -1;
        } else if ($lnum < 1) {
          $lnum = -1;
        }
        
        // Add line number information to error message if available
        if ($lnum !== -1) {
          $msg = "XML line $lnum: $msg";
        }
        
        // Throw exception
        throw new Exception($msg);
      }
      
    } finally {
      // Free the parser
      xml_parser_free($parser);
      unset($parser);
    }
    
    // Get result from callback receiver
    $vars = $recv->getResult();
    if ($vars === false) {
      throw new Exception('XML file had wrong kind of root element');
    }
    
    // Read available variables
    $this->m_isbndb_key = self::readXMLValue('isbndb_key', $vars);
    $this->m_db_path = self::readXMLValue('db_path', $vars);
    $this->m_db_covers = self::readXMLValue('db_covers', $vars);
    $this->m_base_url = self::readXMLValue('base_url', $vars);
    $this->m_base_lib = self::readXMLValue('base_lib', $vars);
    $this->m_map_list = self::readXMLValue('map_list', $vars);
    $this->m_map_entry = self::readXMLValue('map_entry', $vars);
    $this->m_map_detail = self::readXMLValue('map_detail', $vars);
    $this->m_map_add = self::readXMLValue('map_add', $vars);
    $this->m_custom_name = self::readXMLValue('custom_name', $vars);
    
    // If custom name is not valid Unicode string, then set to empty
    // string
    if (JCQTypes::checkUniString($this->m_custom_name) !== true) {
      $this->m_custom_name = '';
    }
  }
  
  /*
   * Normalize all the field values currently in this object.
   * 
   * This does NOT guarantee that the normalized field values will be
   * valid.
   */
  public function normalize() {
    
    // Perform main normalization
    $this->m_isbndb_key = trim($this->m_isbndb_key);
    $this->m_db_path = trim($this->m_db_path);
    $this->m_db_covers = trim($this->m_db_covers);
    $this->m_base_url = JCQTypes::normURL($this->m_base_url);
    $this->m_base_lib = trim($this->m_base_lib);
    $this->m_map_list = JCQTypes::normFilename($this->m_map_list);
    $this->m_map_entry = JCQTypes::normFilename($this->m_map_entry);
    $this->m_map_detail = JCQTypes::normFilename($this->m_map_detail);
    $this->m_map_add = JCQTypes::normFilename($this->m_map_add);
    $this->m_custom_name = trim($this->m_custom_name);
    
    // If the covers directory is not empty, then add a trailing
    // separator if needed
    if (strlen($this->m_db_covers) > 0) {
    
      // Get last character of covers directory
      $lastchar = $this->m_db_covers[strlen($this->m_db_covers) - 1];
      
      // If last character not a separator or a forward slash, add a
      // separator
      if (($lastchar !== '/') && ($lastchar !== DIRECTORY_SEPARATOR)) {
        $this->m_db_covers = $this->m_db_covers . DIRECTORY_SEPARATOR;
      }
    }
    
    // If the base library directory is not empty, then add a trailing
    // separator if needed
    if (strlen($this->m_base_lib) > 0) {
    
      // Get last character of base library directory
      $lastchar = $this->m_base_lib[strlen($this->m_base_lib) - 1];
      
      // If last character not a separator or a forward slash, add a
      // separator
      if (($lastchar !== '/') && ($lastchar !== DIRECTORY_SEPARATOR)) {
        $this->m_base_lib = $this->m_base_lib . DIRECTORY_SEPARATOR;
      }
    }
  }
}

////////////////////////////////////////////////////////////////////////
//                                                                    //
//                         ZenLibConfig class                         //
//                                                                    //
////////////////////////////////////////////////////////////////////////

/*
 * This class represents a complete, checked set of configuration
 * options.
 * 
 * For a partial, unchecked set of configuration options, use
 * ZenLibPartial.
 * 
 * The constructor is protected.  Use a static factory method to
 * instantiate an instance of this class.
 */
class ZenLibConfig {
  
  // Fields representing the parameter values
  //
  private $m_isbndb_key;
  
  private $m_db_path;
  private $m_db_covers;
  
  private $m_base_url;
  private $m_base_lib;
  
  private $m_map_list;
  private $m_map_entry;
  private $m_map_detail;
  private $m_map_add;
  
  private $m_custom_name;
  
  /*
   * Serialize a variable to an XML <var> element.
   * 
   * Parameters:
   * 
   *   $varname : string - the variable name, which must pass
   *   JCQTypes::checkAtom()
   * 
   *   $varval : string - the variable value to store
   * 
   * Return:
   * 
   *   the <var> element as a string
   */
  private static function writeVar($varname, $varval) {
    
    // Check parameters
    if (JCQTypes::checkAtom($varname) !== true) {
      throw new Exception("zenlib_bootstrap-" . strval(__LINE__));
    }
    if (is_string($varval) !== true) {
      throw new Exception("zenlib_bootstrap-" . strval(__LINE__));
    }
    
    // Escape special characters in the value
    $v = htmlspecialchars(
          $varval, ENT_COMPAT | ENT_XML1, 'UTF-8', true);
    
    // Return the result
    return "<var name=\"$varname\" value=\"$v\"/>";
  }
  
  /*
   * Construct an instance of this object given the values of each
   * parameter.
   * 
   * This constructor is protected.  Use a static factory method
   * instead.
   * 
   * An Exception is thrown if there is a problem.
   * 
   * Parameters:
   * 
   *   $isbndb_key : string - the API key for ISBNdb, which must pass
   *   JCQTypes::blankCheck() AND isPlainASCII()
   * 
   *   $db_path : string - the absolute file path to the SQLite
   *   database, which must pass JCQTypes::blankCheck() AND isPlainASCII
   * 
   *   $db_covers : string - the absolute file path to the directory
   *   holding the cached cover images; this must pass
   *   JCQTypes::blankCheck() AND it must end either in
   *   DIRECTORY_SEPARATOR or forward slash AND must pass isPlainASCII()
   * 
   *   $base_url : string - the absolute URL of the public scripts
   *   directory; this must pass JCQTypes::checkURL() with IP addresses
   *   allowed AND the last character must be a forward slash
   * 
   *   $base_lib : string - the absolute file path to the directory
   *   holding the private library scripts; this must pass
   *   JCQTypes::blankCheck() AND it must end either in
   *   DIRECTORY_SEPARATOR or forward slash AND must pass isPlainASCII()
   * 
   *   $map_list : string - the filename of the book list page; this
   *   must pass JCQTypes::checkFilename()
   * 
   *   $map_entry : string - the filename of the ISBN entry page; this
   *   must pass JCQTypes::checkFilename()
   * 
   *   $map_detail : string - the filename of the book detail page; this
   *   must pass JCQTypes::checkFilename()
   * 
   *   $map_add : string - filename of the add book page; this must pass
   *   JCQTypes::checkFilename()
   * 
   *   $custom_name : string - the custom name to display above the book
   *   list; this must pass JCQTypes::blankCheck() AND
   *   JCQTypes::checkUniString()
   */
  protected function __construct(
      $isbndb_key,
      $db_path,
      $db_covers,
      $base_url,
      $base_lib,
      $map_list,
      $map_entry,
      $map_detail,
      $map_add,
      $custom_name) {
    
    // Check parameters
    if ((JCQTypes::blankCheck($isbndb_key) !== true) ||
        (isPlainASCII($isbndb_key) !== true) ||
        (JCQTypes::blankCheck($db_path) !== true) ||
        (isPlainASCII($db_path) !== true) ||
        (JCQTypes::blankCheck($db_covers) !== true) ||
        (isPlainASCII($db_covers) !== true) ||
        (JCQTypes::checkURL($base_url, true) !== true) ||
        (JCQTypes::blankCheck($base_lib) !== true) ||
        (isPlainASCII($base_lib) !== true) ||
        (JCQTypes::checkFilename($map_list) !== true) ||
        (JCQTypes::checkFilename($map_entry) !== true) ||
        (JCQTypes::checkFilename($map_detail) !== true) ||
        (JCQTypes::checkFilename($map_add) !== true) ||
        (JCQTypes::blankCheck($custom_name) !== true) ||
        (JCQTypes::checkUniString($custom_name) !== true)) {
      throw new Exception("zenlib_bootstrap-" . strval(__LINE__));
    }
    
    // Check that base URL ends in a forward slash
    $lastchar = $base_url[strlen($base_url) - 1];
    if ($lastchar !== '/') {
      throw new Exception("zenlib_bootstrap-" . strval(__LINE__));
    }
    
    // Check that covers directory ends in separator or forward slash
    $lastchar = $db_covers[strlen($db_covers) - 1];
    if (($lastchar !== '/') && ($lastchar !== DIRECTORY_SEPARATOR)) {
      throw new Exception("zenlib_bootstrap-" . strval(__LINE__));
    }
    
    // Check that base library directory ends in separator or forward
    // slash
    $lastchar = $base_lib[strlen($base_lib) - 1];
    if (($lastchar !== '/') && ($lastchar !== DIRECTORY_SEPARATOR)) {
      throw new Exception("zenlib_bootstrap-" . strval(__LINE__));
    }
    
    // Save the parameter values
    $this->m_isbndb_key = $isbndb_key;
    $this->m_db_path = $db_path;
    $this->m_db_covers = $db_covers;
    $this->m_base_url = $base_url;
    $this->m_base_lib = $base_lib;
    $this->m_map_list = $map_list;
    $this->m_map_entry = $map_entry;
    $this->m_map_detail = $map_detail;
    $this->m_map_add = $map_add;
    $this->m_custom_name = $custom_name;
  }
  
  /*
   * Create an instance of this object given a ZenLibPartial instance
   * that has the parameters filled in.
   * 
   * You should call the normalize() function on the ZenLibPartial
   * instance before calling this function.
   * 
   * If there is a problem with any of the parameters, an Exception is
   * thrown.
   * 
   * Parameters:
   * 
   *   $p : ZenLibPartail - the parameters to use for the new instance
   * 
   * Return:
   * 
   *   a new ZenLibConfig instance
   */
  public static function complete($p) {
    
    // Check parameter
    if (($p instanceof ZenLibPartial) !== true) {
      throw new Exception("zenlib_bootstrap-" . strval(__LINE__));
    }
    
    // Check each parameter
    if ((JCQTypes::blankCheck($p->getISBNdbKey()) !== true) ||
        (isPlainASCII($p->getISBNdbKey()) !== true)) {
      throw new Exception("Invalid ISBNdb key");
    }
    
    if ((JCQTypes::blankCheck($p->getDBPath()) !== true) ||
        (isPlainASCII($p->getDBPath()) !== true)) {
      throw new Exception("Invalid database path");
    }
    
    if ((JCQTypes::blankCheck($p->getDBCovers()) !== true) ||
        (isPlainASCII($p->getDBCovers()) !== true)) {
      throw new Exception("Invalid covers directory");
    }
    
    $db_covers = $p->getDBCovers();
    $lastchar = $db_covers[strlen($db_covers) - 1];
    if (($lastchar !== '/') && ($lastchar !== DIRECTORY_SEPARATOR)) {
      throw new Exception("Invalid covers directory");
    }
    
    if (JCQTypes::checkURL($p->getBaseURL(), true) !== true) {
      throw new Exception("Invalid base URL");
    }
    
    $base_url = $p->getBaseURL();
    $lastchar = $base_url[strlen($base_url) - 1];
    if ($lastchar !== '/') {
      throw new Exception("Base URL must end in / character");
    }
    
    if ((JCQTypes::blankCheck($p->getBaseLib()) !== true) ||
        (isPlainASCII($p->getBaseLib()) !== true)) {
      throw new Exception("Invalid library script directory");
    }
    
    $base_lib = $p->getBaseLib();
    $lastchar = $base_lib[strlen($base_lib) - 1];
    if (($lastchar !== '/') && ($lastchar !== DIRECTORY_SEPARATOR)) {
      throw new Exception("Invalid library script directory");
    }
    
    if (JCQTypes::checkFilename($p->getMapList()) !== true) {
      throw new Exception("Invalid list script filename");
    }
    
    if (JCQTypes::checkFilename($p->getMapEntry()) !== true) {
      throw new Exception("Invalid entry script filename");
    }
    
    if (JCQTypes::checkFilename($p->getMapDetail()) !== true) {
      throw new Exception("Invalid detail script filename");
    }
    
    if (JCQTypes::checkFilename($p->getMapAdd()) !== true) {
      throw new Exception("Invalid add script filename");
    }
    
    if (JCQTypes::blankCheck($p->getCustomName()) !== true) {
      throw new Exception("Custom name may not be empty");
    }
    
    if (JCQTypes::checkUniString($p->getCustomName()) !== true) {
      throw new Exception("Invalid custom name");
    }
    
    // Construct new object
    return new ZenLibConfig(
      $p->getISBNdbKey(),
      $p->getDBPath(),
      $p->getDBCovers(),
      $p->getBaseURL(),
      $p->getBaseLib(),
      $p->getMapList(),
      $p->getMapEntry(),
      $p->getMapDetail(),
      $p->getMapAdd(),
      $p->getCustomName());
  }
  
  /*
   * Access functions for each of the field values.
   * 
   * The field values ARE guaranteed to be valid in this object.
   */
  public function getISBNdbKey() { return $this->m_isbndb_key; }
  public function getDBPath() { return $this->m_db_path; }
  public function getDBCovers() { return $this->m_db_covers; }
  public function getBaseURL() { return $this->m_base_url; }
  public function getBaseLib() { return $this->m_base_lib; }
  public function getMapList() { return $this->m_map_list; }
  public function getMapEntry() { return $this->m_map_entry; }
  public function getMapDetail() { return $this->m_map_detail; }
  public function getMapAdd() { return $this->m_map_add; }
  public function getCustomName() { return $this->m_custom_name; }
  
  /*
   * Serialize this configuration object to XML.
   * 
   * Return:
   * 
   *   a string representing the serialized XML
   */
  public function serialXML() {

    // Begin with empty string
    $result = '';
    
    // Write the header
    $result = $result . '<?xml version="1.0" encoding="UTF-8"?>';
    $result = $result . "\n<zenlib-config>\n";
    
    // Write each variable
    $result = $result . '  ' .
                self::writeVar('isbndb_key', $this->m_isbndb_key) .
                "\n";
    $result = $result . '  ' .
                self::writeVar('db_path', $this->m_db_path) .
                "\n";
    $result = $result . '  ' .
                self::writeVar('db_covers', $this->m_db_covers) .
                "\n";
    $result = $result . '  ' .
                self::writeVar('base_url', $this->m_base_url) .
                "\n";
    $result = $result . '  ' .
                self::writeVar('base_lib', $this->m_base_lib) .
                "\n";
    $result = $result .  '  ' .
                self::writeVar('map_list', $this->m_map_list) .
                "\n";
    $result = $result . '  ' .
                self::writeVar('map_entry', $this->m_map_entry) .
                "\n";
    $result = $result . '  ' .
                self::writeVar('map_detail', $this->m_map_detail) .
                "\n";
    $result = $result . '  ' .
                self::writeVar('map_add', $this->m_map_add) .
                "\n";
    $result = $result . '  ' .
                self::writeVar('custom_name', $this->m_custom_name) .
                "\n";
    
    // Complete the document
    $result = $result . "</zenlib-config>\n";
    
    // Return result
    return $result;
  }
}
