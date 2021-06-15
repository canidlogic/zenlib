# Zenlib Changes

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
