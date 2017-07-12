<?php
/**
 * for downloading from: http://files.pushshift.io/reddit/comments/
 */

$startingDirectory = "/volumes/My Passport Ultra/reddit comments/"; // up to RC_2015-06.bz2 for now
$serverDirectory = "http://files.pushshift.io/reddit/comments/";
$downloadList = ['RC_2015-05.bz2'];

$ch = curl_init($serverDirectory);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
$html = curl_exec($ch);
curl_close($ch);

$dom = new DOMDocument();
@$dom->loadHTML($html);
foreach($dom->getElementsByTagName('a') as $node) {
	$link = substr($node->getAttribute('href'), 2);
	if (preg_match('/\.bz2$/', $link) && !in_array($link, $downloadList)
			&& (strcmp($link, $downloadList[count($downloadList) - 1]) > 0))
		$downloadList[] = $link;
}

while (count($downloadList)) {
	@$fp = fopen($startingDirectory.$downloadList[0], 'wb');
	$ch = curl_init($serverDirectory.$downloadList[0]);
	curl_setopt($ch, CURLOPT_FILE, $fp);
	curl_exec($ch);
	curl_close($ch);
	fclose($fp);
	unset($downloadList[0]);
}