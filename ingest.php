<?php
require_once 'functions.php';
# set up the database, then insert comments

# first, connect w/o specifying db
if (!($db = getConnection_db(get('db_name')))) exit ('Failed to connect');

# form table creation query from the tags
$table = 'CREATE TABLE IF NOT EXISTS Comments (';
foreach (get('tags') as $field => $type)
    $table = $table."\n".$field.' '.$type.',';
$table = rtrim($table, ',').')';

$db->query($table);

$localDirectory = get('localDirectory');
$dir = dir($localDirectory) or die ('Not a valid directory');

# get logical core number to determine the size of process/thread pool
if (!($max_process = get_logical_cores() + 1)) exit ('OS not supported');

# create temporary table for keeping track of thread pool
createPool($db);

# add data from each file
while ($file = $dir->read()) {
    # skip directory "files", files that are still zipped, and .DS_Store if you're using a Mac
    if (preg_match('/\.(\S)*$/', $file)) continue;
    # wait till the thread pool has a space
    wait_pool($db, $max_process);
    # parallelize the execution because we have lots of files to sort through
    # http://php.net/manual/en/function.exec.php#86329
    exec('php insert.php '.$localDirectory.$file.' > /dev/null &');
}

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