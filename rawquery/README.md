# Zenlib raw query utilities

These utilities are intended for debugging.

## Raw ISBN query

This utility makes a query directly to ISBNdb (https://isbndb.com) and returns the result.

You must set the $query_key variable to your ISBNdb API key before using this utility.

This utility is based on the Zenlib 0.1 alpha code and so its ISBN normalization and verification functions are not complete.  In particular, it can't handle ISBN-10 numbers that end with an X digit.  All ISBN-13 numbers should work.  This limitation doesn't apply to the main Zenlib code, which has since been updated.

This utility doesn't retry failed queries, so ISBNdb may return error codes that it is too busy to handle the request.  In this case, try the request again by refreshing the page.

## Raw user agent

This utility simply reports the User-Agent string.
