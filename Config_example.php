<?php
# class with configuration files, instead of using parse_ini_file, because that shit's fucking whacky

class Config_example {
    # absolute path of the directory to locally save the archive (preferably one with 1TB+ of free space)
    # to make your life easier, choose a directory without any spaces in the name
    const localDirectory = "/path/to/directory/";
    # MySQL connection settings
    const host = '$host';
    const username = '$username';
    const password = '$password';
    const db_name = 'Reddit';
    # first file to start downloading from - the given value starts download from the very first file
    # check http://files.pushshift.io/reddit/comments/ to see from which month you want to start downloading
    const begin = 'RC_2005-12.bz2';
    # change the comment fields you are interested in as you see fit
    const tags = ['created_utc' => 'INT UNSIGNED NOT NULL',
                  'score' => 'INT NOT NULL',
                  'subreddit' => 'VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL',
                  'link_id' => 'VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL PRIMARY KEY',
                  'controversiality' => 'INT NOT NULL',
                  'body' => 'TEXT CHARACTER SET utf8mb4 NOT NULL',
                  'edited' => 'BOOL NOT NULL',
                  'gilded' => 'BOOL NOT NULL',
                  'parent_id' => 'VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL',
                  'author' => 'VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL'];
    # fields to index
    const index = ['parent_id', 'created_utc', 'author'];
    # how many rows to insert at one time (batched insert). The bigger, the fewer back-and-forth.
    # since MySQL only supports up to 65,536 placeholders in a stored procedure, the maximum value would be:
    # (int) 65,536 / sizeof(tags)
    const insert_batch = 6553;
    # how frequently should php check if the pool is free? (in seconds)
    const interval = 10;
    # different modes of building query
    const prep_stmt = true;
    # delete the original archive after inserting data into the database?
    const cleanup = true;
    # how many queries should we cram into a single transaction?
    const num_trans = 10000;
}