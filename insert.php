<?php
require_once 'functions.php';
# bulk insert process per file, to be run in parallel

$file = $argv[1];
$tags = get('tags');

if (!($db = getConnection_db(get('db_name')))) exit ("Couldn't connect");

@$fp = fopen($file, 'rb');
while (!feof($fp)) {
    @$comment = json_decode(fgets($fp), true);
    # some lines are not in proper json format, so skip those
    if (!is_array($comment)) continue;
    $stmt1 = 'INSERT INTO Comments (';
    $stmt2 = 'VALUES (';
    foreach ($comment as $tag => $value) {
        # check if this tag exists as a column in the table
        if (!array_key_exists($tag, $tags)) continue;
        $stmt1 = $stmt1.$tag.', ';
        $stmt2 = $stmt2.$value.', ';
    }
    # append two pieces of insert statement and execute it for each comment
    $db->query(rtrim($stmt1, ', ').")\n".rtrim($stmt2, ', ').')');
}

if (get('delete_on_insert')) exec("rm $file");

# signal that the process is done
pool_done($db);
$db->close();