<?php
require_once 'functions.php';
# use json to csv conversion, then use mysql to directly load data from file

$start = microtime(true);
$row = 0;
$file = $argv[1];
$tmp_name = $file.'_tmp';
$tags = val('tags');
$db = getConnection_db(val('db_name'));
const row_limit = 1000000;
create_compressed_table($db);
createPool($db);
# optimize insertion for speed
optimize($db);

# we need to correct the file a bit first before loading into mysql
$fp = fopen($file, 'rb');
while (!feof($fp)) {
    $tmp = fopen($tmp_name, 'wb');
    # chunk up data
    for ($i = 0; $i < row_limit && !feof($fp); $i++) {
        # first, replace the filthy windows newline characters
        if (($arr = @json_decode(str_replace('\r\n', '\n', fgets($fp)), 1)) === NULL) continue;
        # then, form a line appropriate for csv
        $line = '';
        # assuming your tags do appear on every single entry - if you go with the default, it should be set properly
        foreach ($tags as $field => $type)
            $line = $line.'"'.$arr[$field].'",';
        fputs($tmp, substr($line, 0, -1)."\n");
    }
    fclose($tmp);
    # feed database with tmp
    # implicitly ignore errors by specifying local
    $query = <<<LI
LOAD DATA LOCAL INFILE '$tmp_name' INTO TABLE Comments
FIELDS TERMINATED BY ',' ENCLOSED BY '"' ESCAPED BY ''
LINES TERMINATED BY '\n'
LI;
    $db->query($query);
    $row += $i;
//    echo "row $row\n";
}

fclose($fp);
//unlink($tmp_name);
if (val('cleanup')) exec("rm $file");

$num_rows = $db->query("SELECT COUNT(*) FROM Comments")->fetch_row()[0];
cleanup_process($db, $file);

$duration = microtime(true) - $start;
printf("Inserted %d out of %d rows from [%s] in [%.2f]s.\n", $num_rows, $row, $file, $duration);