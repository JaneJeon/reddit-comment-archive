<?php
require_once 'functions.php';
# decompress every archive - note that the original zip's will not be preserved

$localDirectory = get('localDirectory');
$dir = dir($localDirectory);

if (!($db = getConnection_db(get('db_name')))) exit ('Failed to connect');
createPool($db);

# thread pool size is 1 more than logical core count to account for any overheads
if (!($max_process = get_logical_cores() + 1)) exit ('OS not supported');

while ($file = $dir->read()) {
    # skip files that are already decompressed
    if (!preg_match('/\.bz2$/', $file)) continue;
    wait_pool($db, $max_process);
    exec('php decompress.php '.$localDirectory.$file.' > /dev/null/ &');
}

destroy_pool($db);
$db->close();