<?php
# read the first couple of lines to find tags

$localDirectory = "/volumes/My Passport Ultra/reddit comments/";
$dir = dir($localDirectory);

# count how many times each tag appears
$tags = [];
$total = 0;

# read through every file to check for tags
while ($file = $dir->read()) {
	if (!preg_match('/\.(\S)*$/', $file)) {
		@$fp = fopen($localDirectory.$file, 'rb');
		while (!feof($fp)) {
            @$comment = json_decode(fgets($fp), true);
            # some lines are not in proper json format, so skip those
            if (!is_array($comment)) continue;
			foreach ($comment as $tag => $value)
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