<?php
# class with configuration files, instead of using parse_ini_file because it's fucking whacky

class config_example {
    # choose a directory to locally save the archive, preferably one with 1TB+ of free space
    # need absolute path, and should have / at the end. Put single quotes around directories with spaces
    public $localDirectory = "/path/to/directory/";
    # MySQL connection settings
    public $host = '$host';
    public $username = '$username';
    public $password = '$password';
    public $db_name = 'Reddit';
    # first file to start downloading from - the below starts download from the very first file
    # check http://files.pushshift.io/reddit/comments/ to see where you want to start downloading
    public $begin = 'RC_2005-12.bz2';
    # change the comment fields you are interested in as you see fit
    public $tags = ['created_utc' => 'INT UNSIGNED NOT NULL',
                    'score' => 'INT NOT NULL',
                    'ups' => 'INT NOT NULL',
                    'subreddit' => 'VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL',
                    'link_id' => 'VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL PRIMARY KEY',
                    'stickied' => 'BOOL NOT NULL',
                    'subreddit_id' => 'VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL',
                    'controversiality' => 'INT NOT NULL',
                    'body' => 'TEXT CHARACTER SET utf8mb4 NOT NULL',
                    'edited' => 'BOOL NOT NULL',
                    'gilded' => 'BOOL NOT NULL',
                    'id' => 'VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL',
                    'parent_id' => 'VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL',
                    'author' => 'VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL'];
    # fields to index
    public $index = ['parent_id', 'created_utc'];
    # delete the (huge) comment files as you insert them into the database to free up space
    public $delete_on_insert = true;
}