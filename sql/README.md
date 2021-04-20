# Zenlib SQL Scripts

This directory contains SQL scripts for use in setting up the SQLite database.

The database_schema.txt file contains all the SQL commands necessary to create a new database from scratch.  Use the sqlite3 tool to create a new SQLite database file and then use the .read command to read and execute all the SQL commands from database_schema.txt

The database_vars.txt file contains the SQL commands necessary to set up access to the external ISBNdb API (https://isbndb.com).  You need to edit this file to replace YOUR_API_KEY with your actual API key.  Then, run it after you've created the database with the previous script.
