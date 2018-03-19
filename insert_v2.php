<?php
require_once 'functions.php';
# use json to csv conversion, then use mysql to directly load data from file

//$start = microtime(true);
//$row = 0;
$file = $argv[1];
$tmp_name = $file.'_tmp';
$tags = val('tags');
$db = getConnection_db(val('db_name'));
$row_limit = val('row_limit');
optimize($db);

# we need to correct the file a bit first before loading into mysql
$fp = fopen($file, 'rb');
while (!feof($fp)) {
    $tmp = fopen($tmp_name, 'wb');
    # chunk up data
    for ($i = 0; $i < $row_limit && !feof($fp); $i++) {
        if (($line = json_decode(str_replace('\r\n', '\n', fgets($fp)), true)) === NULL) {
            $i--;
            continue;
        }
        $vars = [];
        foreach ($tags as $tag => $field) {
            # only uncomment this if you have a field/tag that permits null values
            # e.g. 'ups' field exists for older data, but doesn't exist in newer ones
//            if (!isset($line[$tag])) {
//                $i--;
//                break;
//            }
            $vars[] = $tag == 'body' ? mysqli_real_escape_string($db, $line[$tag]) : $line[$tag];
        }
//        if (sizeof($vars) == sizeof($tags))
            fputs($tmp, '"'.implode('","', $vars).'"'."\n");
    }
    fclose($tmp);
    # feed database with tmp - implicitly ignore errors by specifying local
    # this deals with the LOTS of duplicates in the archives (the data is dirty AF!)
    # apparently I don't need to escape by \
    $query = <<<LI
LOAD DATA LOCAL INFILE '$tmp_name' INTO TABLE Comments
FIELDS TERMINATED BY ',' ENCLOSED BY '"'
LINES TERMINATED BY '\n'
LI;
    // keep trying until server gets back
//    while (!$db->ping()) {
//        sleep(10 * val('interval'));
//        if (!$db->ping()) {
//            $db->close();
//            $db = getConnection_db(val('db_name'));
//        }
//    }
    $db->query($query);
//    $row += $i;
}

fclose($fp);
unlink($tmp_name);
if (val('cleanup')) exec("rm $file");

//$num_rows = $db->query("SELECT table_rows FROM information_schema.tables
//                              WHERE table_name = 'Comments'")->fetch_row()[0];
notify_pool_done($db, $file);

//$duration = microtime(true) - $start;
//@printf("Inserted %d out of %d rows from [%s] in [%.2f]s.\n",
//        $num_rows, $row, end(explode('/', $file)), $duration);