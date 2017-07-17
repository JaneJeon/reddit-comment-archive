<?php
require_once 'functions.php';
# set up the database, then insert comments
# if you have already inserted comments from some files, or any duplicates, move them to another directory

# getConnection function handles creating the database if it doesn't exist already
if (!(@$db = getConnection_db(get('db_name')))) exit ('Failed to connect');

# to enable table compression
$db->query('SET GLOBAL innodb_file_per_table = 1');
$db->query('SET GLOBAL innodb_file_format = Barracuda');

# form table creation query from the tags
$table = 'CREATE TABLE IF NOT EXISTS Comments (';
foreach (get('tags') as $field => $type)
    $table = $table."\n".$field.' '.$type.',';
$table = rtrim($table, ',').")\n".'ROW_FORMAT = COMPRESSED';

$db->query($table);

$localDirectory = get('localDirectory');
$dir = dir($localDirectory) or die ('Not a valid directory');
# track which ones are done without sharing any states with the individual child processes
$archive_files = [];

# get logical core number to determine the size of process/thread pool
if (!($max_process = get_logical_cores() + 1)) exit ('OS not supported');

# create temporary table for keeping track of thread pool
createPool($db);

# turn off primary key uniqueness checking since we'll be doing that manually
$db->query('SET unique_checks = 0');
# https://dev.mysql.com/doc/refman/5.6/en/optimizing-innodb-bulk-data-loading.html
//$db->query('SET autocommit = 0');

# add data from each file
while ($file = $dir->read()) {
    # skip directory "files", files that are still zipped, and .DS_Store if you're using a Mac
    if (!preg_match('/^(RC_)/', $file) || strpos($file, '.') !== false) continue;
    $archive_files[] = $localDirectory.$file;
    # wait till the thread pool has a space
    wait_pool($db, $max_process);
    # parallelize the execution because we have lots of files to sort through
    # http://php.net/manual/en/function.exec.php#86329
    exec('php insert.php '.$localDirectory.$file.' > /dev/null &');
}

# need to keep this process alive after we've sent off all jobs to close pool and create indexes
# keeping track of which archive files are not deleted is the easiest way to do so
while ($archive_files) {
    foreach ($archive_files as $archive)
        if (!file_exists($archive) && ($val_key = array_search($archive, $archive_files)) !== false) {
            unset($archive_files[$val_key]);
            break 2;
        }
    sleep(get('interval'));
}

# restore settings as they were
$db->query('SET unique_checks = 1');
$db->query('SET autocommit = 1');

# delete temp table
destroy_pool($db);

# add indices after bulk insert for performance optimization
# http://www.tocker.ca/2013/10/24/improving-the-performance-of-large-tables-in-mysql.html (8)
foreach (get('index') as $field) {
    $idx = "idx_$field";
    $idx_exists = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_NAME = 'Comments'
                   AND INDEX_NAME = '$idx' AND TABLE_SCHEMA = DATABASE()";
    # check if the index exists before creating one
    if (!$db->query($idx_exists)->fetch_row()[0])
        $db->query("CREATE INDEX $idx ON Comments ($field)");
}

$db->close();