<?php
# read the first couple of lines to find tags - you should have the files uncompressed at this point
# see ingest.php for more details

$localDirectory = parse_ini_file('config.ini')['localDirectory'];

# count how many times each tag appears
$tags = [];
$total = 0;

# get the latest file
while ($file = $dir->read())
    if (preg_match('/\.(\S)*$/', $file)) continue;

@$fp = fopen($localDirectory.$file, 'rb');
$start = microtime(true);

# run for 5 minutes
while (!feof($fp) && (microtime(true) - $start < 5*60)) {
    @$comment = json_decode(fgets($fp), true);
    if (!is_array($comment)) continue;
    foreach ($comment as $tag => $value)
        array_key_exists($tag, $tags) ? $tags[$tag]++ : $tags[$tag] = 1;
    $total++;
}
fclose($fp);

$dir->close();

# convert tally to percentage
foreach ($tags as $tag => $tally)
    $tags[$tag] = sprintf('%.2f%%', 100.0*$tally/$total);

print_r($tags);