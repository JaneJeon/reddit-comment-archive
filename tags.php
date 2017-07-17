<?php
require_once 'functions.php';
# read the first couple of lines to find tags - you should have the files uncompressed at this point

$localDirectory = get('localDirectory');
$dir = dir($localDirectory) or die ('Not a valid directory');

# count how many times each tag appears
$tags = [];
$total = 0;

# get the latest file
while ($file = $dir->read())
    if (preg_match('/^(RC_)/', $file) && strpos($file, '.') === false) $latest = $file;

@$fp = fopen($localDirectory.$latest, 'rb') or die ('No archive detected');
$start = microtime(true);

# run for 5 minutes
while (!feof($fp) && (microtime(true) - $start < 5 * 60)) {
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
    $tags[$tag] = sprintf('%.2f%%', 100.0 * $tally / $total);

print_r($tags);

# as an example, the latest archive RC_2017-06 yields the following result:
# Array
# (
#     [author] => 100.00%
#     [author_flair_css_class] => 100.00%
#     [author_flair_text] => 100.00%
#     [body] => 100.00%
#     [can_gild] => 100.00%
#     [controversiality] => 100.00%
#     [created_utc] => 100.00%
#     [distinguished] => 100.00%
#     [edited] => 100.00%
#     [gilded] => 100.00%
#     [id] => 100.00%
#     [link_id] => 100.00%
#     [parent_id] => 100.00%
#     [retrieved_on] => 100.00%
#     [score] => 100.00%
#     [stickied] => 100.00%
#     [subreddit] => 100.00%
#     [subreddit_id] => 100.00%
#     [author_cakeday] => 0.33%
# )