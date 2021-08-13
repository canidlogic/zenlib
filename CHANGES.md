# Zenlib Changes

## Version 0.7 Alpha

This is a small update that simply adds the `query_gary_json.php` script to `/public`.  This script is just a minor alteration of `query_gary.php` that invokes the JSON query mode rather than the simple query mode.

Adding this script allows for automated export from the Zenlib database, using the `/public/isbn_export.php` script to get the ISBN numbers of all the books in the database, and the new `/public/query_gary_json.php` script to get the cached ISBNdb JSON information about the book.

## Version 0.6 Alpha

The big change in this version is integrating Gary to fix errors from querying ISBNdb directly.

Also added the `isbn_export.php` script so that the full list of ISBN numbers can be exported.

* `README.md`

_Updated description to mention Gary integration_

* `/config/zenlib_gen.php`

_Updated documentation on page for Gary configuration_

* `/dbutil/isbn_export.php`

_Added utility script for exporting list of ISBN numbers_

* `/public/isbn_detail.php`

_Updated to query Gary instead of ISBNdb directly_

* `/public/isbn.php`

_Updated to submit to new isbn_query page_

* `/public/isbn_query.js`
* `/public/isbn_query.php`

_Added client-side query retry page to fix query errors_

* `/public/query_gary.php`

_Added simple (unauthenticated!) bridge to Gary PHP script_
