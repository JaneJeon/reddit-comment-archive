<?php
require_once 'functions.php';
# decompress every archive - note that the original zip's will not be preserved

$localDirectory = val('localDirectory');
$dir = dir($localDirectory);

if (!($db = getConnection_db(val('db_name')))) exit ('Failed to connect');
createPool($db);

# thread pool size is 1 more than logical core count to account for any overheads
if (!($max_process = num_logical_cores() + 1)) exit ('OS not supported');

while ($file = $dir->read()) {
    # skip files that are already decompressed
    if (!preg_match('/^[^.][[:word:]-]*\.bz2$/', $file)) continue;
    wait_pool($db, $max_process);
    exec('php decompress.php '.$localDirectory.$file.' > /dev/null &');
}

destroy_pool($db);
$db->close();