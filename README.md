# Zenlib

Zenlib is a simple library management system for cataloging books and other media, and tracking loans of the media to library patrons.

It is designed for relatively small libraries for which existing library management solutions would be too complex to implement.

Zenlib is written in PHP and uses SQLite as its database.

## Integrations

Zenlib is integrated with a SQLite database.  See the README in the sql project folder for how to set up an appropriate SQLite database.

Zenlib is integrated with ISBNdb (https://isbndb.com/) for automatically retrieving information about books.  You must register there and get an API key, then store the API key in the SQLite database as described in the README in the sql project folder.

Zenlib can optionally use an iOS or Android smartphone as a barcode scanner.  To do this, you need the Scan to Web app (https://scantoweb.net/).  You can then use the web browser within that app to navigate your Zenlib website.  Zenlib will detect that this special browser is being used and offer "Scan barcode" functions.  For efficiency, it is recommended that you set the app to automatically do a form submit after a barcode scan, though this is optional.

## Contact information

Zenlib is being developed by Noah Johnson.  To reach the developer, email noah.johnson@loupmail.com
