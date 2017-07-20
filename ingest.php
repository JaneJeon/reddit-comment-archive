<?php
require_once 'functions.php';
error_reporting(E_ERROR);
# set up the database, then insert comments
# if you have already inserted comments from some files, or any duplicates, move them to another directory

# getConnection function handles creating the database if it doesn't exist already
if (!(@$db = getConnection_db(val('db_name')))) exit ('Failed to connect');

$localDirectory = val('localDirectory');
$dir = dir($localDirectory) or die ('Not a valid directory');

# track which ones are done without sharing any states with the individual child processes
$archive_files = [];

# get logical core number to determine the size of process/thread pool
if (!($max_process = num_logical_cores() + 1)) exit ('OS not supported');

create_compressed_table($db);

# create temporary table for keeping track of thread pool
createPool($db);

# add data from each file
while ($file = $dir->read()) {
    # skip directory "files", files that are still zipped, and .DS_Store if you're using a Mac
    if (preg_match('/tmp$/', $file) || strpos($file, '.') !== false
        || strpos($file, 'RC_') === false) continue;
    $archive_files[] = $localDirectory.$file;
    # wait till the thread pool has a space
    wait_pool($db, $max_process);
    # parallelize the execution because we have lots of files to sort through
    # http://php.net/manual/en/function.exec.php#86329
    exec('php insert_v2.php '.$localDirectory.$file.' > /dev/null &');
}

# need to keep this process alive after we've sent off all jobs to close pool and create indexes
while ($archive_files) {
    foreach ($archive_files as $archive)
        if ($db->query("SELECT COUNT(*) FROM Progress WHERE file = '$archive'")->fetch_row()[0]) {
            unset($archive_files[array_search($archive, $archive_files)]);
            break 2;
        }
    sleep(val('interval'));
}

# delete temp table
destroy_pool($db);

# add indices after bulk insert for performance optimization
# http://www.tocker.ca/2013/10/24/improving-the-performance-of-large-tables-in-mysql.html (8)
foreach (val('index') as $field) {
    $idx = "idx_$field";
    $idx_exists = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_NAME = 'Comments'
                   AND INDEX_NAME = '$idx' AND TABLE_SCHEMA = DATABASE()";
    # check if the index exists before creating one
    if (!$db->query($idx_exists)->fetch_row()[0])
        $db->query("CREATE INDEX $idx ON Comments ($field)");
}

$db->close();

# resources:
# https://dev.mysql.com/doc/refman/5.7/en/load-data.html
# http://derwiki.tumblr.com/post/24490758395/loading-half-a-billion-rows-into-mysql