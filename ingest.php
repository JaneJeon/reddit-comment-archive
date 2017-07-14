<?php
# insert comment values into database

$var = parse_ini_file('config.ini');

# change the fields you are interested in as you see fit
$tags = ['created_utc' => 'INT unsigned NOT NULL',
        'score' => 'INT NOT NULL',
        'ups' => 'INT',
        'subreddit' => 'VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL',
        'link_id' => 'VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL PRIMARY KEY',
        'stickied' => 'BOOL', # BOOL is alias for TINYINT(1) - a value of 0 is false. Nonzero is true.
        'subreddit_id' => 'VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL',
        'controversiality' => 'INT',
        'body' => 'TEXT CHARACTER SET utf8mb4 NOT NULL',
        'edited' => 'BOOL',
        'gilded' => 'BOOL',
        'id' => 'VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL UNIQUE',
        'parent_id' => 'VARCHAR(10) CHARACTER SET utf8mb4 NOT NULL',
        'author' => 'VARCHAR(20) CHARACTER SET utf8mb4 NOT NULL'];

# fields you want to index
$i_fields = ['parent_id', 'author'];

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

while ($file = $dir->read()) {
    # skip directory "files", files that are still zipped, and .DS_Store if you're using a Mac
    if (preg_match('/\.(\S)*$/', $file)) continue;
    @$fp = fopen($localDirectory.$file, 'rb');
    while (!feof($fp)) {
        @$comment = json_decode(fgets($fp), true);
        # some lines are not in proper json format, so skip those
        if (!is_array($comment)) continue;
        $stmt1 = 'INSERT INTO Comments (';
        $stmt2 = 'VALUES (';
        foreach ($comment as $tag => $value) {
            $stmt1 = $stmt1.$tag.', ';
            $stmt2 = $stmt2.$value.', ';
        }
        # append two pieces of insert statement and execute it
        $db->query(rtrim($stmt1, ', ').")\n".rtrim($stmt2, ', ').')');
    }
}

$db->close();