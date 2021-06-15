/*
 * isbn_query.js
 * =============
 * 
 * Client-side JavaScript for searching for book.
 * 
 * This won't work with Internet Explorer because of passing additional
 * parameters through setInterval().
 */

/*
 * The number of milliseconds to delay between attempts.
 * 
 * Note that Gary does a few attempts in close succession, so this delay
 * should be relatively long to space out the clusters of attempts.
 */
var ATTEMPT_DELAY = 10000;

/*
 * The total number of client-side retries of the query.
 * 
 * Note that Gary does multiple retries for each client-side retry, and
 * that the delay between client-side retries is rather long, so this
 * shouldn't be too large.
 */
var ATTEMPT_MAX = 3;

/*
 * Check whether the given parameter is alphanumeric string.
 * 
 * This is only true if s is a string and every character is a US-ASCII
 * alphanumeric.  Empty strings pass.
 * 
 * Parameters:
 * 
 *   s : String | mixed - the value to check
 * 
 * Return:
 * 
 *   true if alphanumeric string, false otherwise
 */
function alphanum_str(s) {
  // Check type
  if (typeof(s) != "string") {
    return false;
  }
  
  // Check each character
  for(i = 0; i < s.length; i++) {
    c = s.charCodeAt(i);
    if (((c < 0x30) || (c > 0x39)) &&
        ((c < 0x41) || (c > 0x5a)) &&
        ((c < 0x61) || (c > 0x7a))) {
      return false;
    }
  }
  
  // If we got here, check passed
  return true;
}

/*
 * Make an attempt at locating the book.
 * 
 * The countdown is the total number of attempts left.  If this function
 * is called with a countdown less than one, then no more attempts are
 * left and the user will be forwarded to the ISBN detail form even
 * though the query wasn't successful.  The ISBN detail form will then
 * likely report that there was an error.
 * 
 * Otherwise, this function will asynchronously attempt to use Gary to
 * query for the ISBN.  If the attempt succeeds, the user will be
 * forwarded to the ISBN detail form.  If the attempt fails, another
 * call to attempt_query() will be asynchronously scheduled in the
 * future with the countdown one less.
 * 
 * The jump parameter gives the URL of the ISBN detail form, not
 * including any query string.
 * 
 * The ISBN parameter must pass alphanum_str().
 * 
 * Parameters:
 * 
 *   isbn : String - the ISBN number to query for
 * 
 *   jump : String - the URL of the ISBN detail page
 * 
 *   countdown : Number - the number of attempts left
 */
function attempt_query(isbn, jump, countdown) {
  
console.log("begin attempt");
  
  // Check parameters
  if (!alphanum_str(isbn) ||
      (typeof(jump) != "string") ||
      (typeof(countdown) != "number")) {
    throw new Error();
  }
  
  // If countdown is not at least one, forward to the ISBN detail form
  // even though we haven't successfully queried
  if (!(countdown >= 1)) {
    location.replace(jump + "?isbn=" + isbn + "&action=add");
    return;
  }
  
  // Begin an HTTP request
  var request = new XMLHttpRequest();
  
  // We'll be calling a PHP script that will call through to the Gary
  // script
  request.open("POST", "query_gary.php");
  
  // We'll be encoding our request as if it were submitted through a
  // form
  request.setRequestHeader(
    "Content-Type", "application/x-www-form-urlencoded");
  
  // Set an asynchronous handler for when the request completes
  request.onreadystatechange = function() {
    // Only proceed if the request is done (whether successfully or in
    // error)
    if (request.readyState === 4) {

      // Set the request status flag to true
      var request_status = true;
      
      // If status code isn't 200, then something went wrong, so forward
      // to ISBN detail page instead of further retries
      if (request.status !== 200) {
        location.replace(jump + "?isbn=" + isbn + "&action=add");
        return;
      }
      
      // Proceed only if we got a successful response
      if (request_status) {
        // Read the text we got in response
        var result = request.responseText;
        
        // The result text is either "true" "false" or something else
        result = result.trim();
        if (result == "true") {
          // Request succeeded
          request_status = true;
          
        } else if (result == "false") {
          // Request failed
          request_status = false;
        
        } else {
          // If we got some other result, then something went wrong, so
          // forward to ISBN detail page instead of further retries
          location.replace(jump + "?isbn=" + isbn + "&action=add");
          return;
        }
      }
      
      // If our request was successful, forward to the ISBN detail form;
      // else, schedule another attempt in the future
      if (request_status) {
        location.replace(jump + "?isbn=" + isbn + "&action=add");
      } else {
        window.setTimeout(
                attempt_query,
                ATTEMPT_DELAY,
                isbn,
                jump,
                countdown - 1);
      }
    }
  };
  
  // Send our query off asynchronously
  request.send("isbn=" + isbn);
}

/*
 * This is the function that is called from the window onload handler on
 * the query page.
 * 
 * The ISBN parameter is the ISBN number that was passed in through the
 * GET parameter of the form.  It must pass alphanum_str().
 * 
 * The jump parameter is the URL of the ISBN detail page, not including
 * any query string.
 * 
 * Parameters:
 * 
 *   isbn : String - the ISBN number to query for
 * 
 *   jump : String - the URL of the ISBN detail page
 */
function isbn_query(isbn, jump) {
  
  // Check parameters
  if (!alphanum_str(isbn) || (typeof(jump) != "string")) {
    throw new Error();
  }
  
  // Asynchronously schedule the first attempt to run as soon as event
  // processing is done, with a countdown of ATTEMPT_MAX
  window.setTimeout(attempt_query, 0, isbn, jump, ATTEMPT_MAX);
}
