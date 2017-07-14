<?php
# set up the database, then insert comments

$var = parse_ini_file('config.ini');

$tags = $var['tags'];
# fields you want to index
$i_fields = ['parent_id', 'author', 'created_utc'];

# first, connect w/o specifying db
$db = new mysqli($var['host'], $var['username'], $var['password']) or die ('Failed to connect: '.mysqli_error($db));

# switch db to Reddit to access comments table
$db->query('CREATE DATABASE IF NOT EXISTS Reddit');
mysqli_select_db($db, 'Reddit');

# form table creation query from the tags
$table = 'CREATE TABLE IF NOT EXISTS Comments (';
foreach ($tags as $field => $type)
    $table = $table."\n".$field.' '.$type.',';
$table = rtrim($table, ',').')';

$db->query($table);

# add indices
foreach ($i_fields as $field) {
    $idx = "idx_$field";
    $idx_exists = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_NAME = 'Comments'
                   AND INDEX_NAME = '$idx' AND TABLE_SCHEMA = DATABASE()";
    # check if the index exists before creating one
    if (!$db->query($idx_exists)->fetch_row()[0])
        $db->query("CREATE INDEX $idx ON Comments ($field)");
}

$localDirectory = $var['localDirectory'];
$dir = dir($localDirectory) or die ('Not a valid directory');

# get logical core number to determine the size of process/thread pool
if (PHP_OS == 'Darwin') { # macOS
    $max_processes = (int) shell_exec("sysctl hw.logicalcpu | sed 's/hw.logicalcpu: //g'") + 1;
} else if (PHP_OS == 'Linux') {
    $max_processes = (int) shell_exec("cat /proc/cpuinfo | grep processor | wc -l") + 1;
} else exit ('OS not supported'); # Windows, etc

# create temporary table for keeping track of thread pool
$db->query('CREATE TABLE Progress (task_id INT PRIMARY KEY AUTO_INCREMENT, done BOOL NOT NULL)');

# add data from each file
while ($file = $dir->read()) {
    # skip directory "files", files that are still zipped, and .DS_Store if you're using a Mac
    if (preg_match('/\.(\S)*$/', $file)) continue;
    # wait till the thread pool has a space
    while ($db->query('SELECT COUNT(*) FROM Progress WHERE done = FALSE') > $max_processes)
        sleep(5);
    $db->query('INSERT INTO Progress (done) VALUES (FALSE)');
    # parallelize the execution because we have lots of files to sort through (works on macOS & Linux)
    # http://php.net/manual/en/function.exec.php#86329
    exec("php insert.php $localDirectory.$file > /dev/null &");
}

# delete temp table
$db->query('DROP TABLE Progress');
$db->close();