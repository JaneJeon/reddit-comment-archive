<?php
# read the first couple of lines to find tags

$localDirectory = "/volumes/My Passport Ultra/reddit comments/";
$dir = dir($localDirectory);

$tags = [];
$total = 0;

while ($file = $dir->read()) {
	if ($file != "." && $file != ".." && !preg_match('/\.bz2$/', $file)) {
		@$fp = fopen($file, 'rb');
		while (!feof($fp)) {
			# count how many times each tag appears
			foreach (@json_decode(fgets($fp), true) as $tag => $value)
				array_key_exists($tag, $tags) ? $tags[$tag]++ : $tags[$tag] = 1;
			$total++;
		}
		fclose($fp);
	}
}

$dir->close();

# convert tally to percentage
foreach ($tags as $tag => $tally)
	$tags[$tag] = sprintf('%.2f%%', 100.0*$tally/$total);

print_r($tags);