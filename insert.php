<?php
$file = $argv[1];
$tags = parse_ini_file('config.ini')['tags'];

$db = new mysqli($var['host'], $var['username'], $var['password'], 'Reddit') or die ("Couldn't connect");

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

# signal that the process is done
$db->query('UPDATE Progress SET done = TRUE WHERE task_id = '
                  .$db->query('SELECT task_id FROM Progress ORDER BY task_id LIMIT 1')->fetch_row()[0]);

$db->close();