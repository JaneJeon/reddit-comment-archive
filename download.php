<?php
# download the reddit comment archive

# change local directory to your download location, preferably one with 1TB+ free space
$localDirectory = "/volumes/My Passport Ultra/reddit comments/";
$serverDirectory = "http://files.pushshift.io/reddit/comments/";
# the starting point for the archive downloads. If you want to start from the beginning, change to 'RC_2005-12.bz2'
$downloadList = ['RC_2015-05.bz2'];

# first, get the html for the download page to figure out which files are available
$ch = curl_init($serverDirectory);
curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
$html = curl_exec($ch);
curl_close($ch);

# then, add each month's download link to our queue
$dom = new DOMDocument();
@$dom->loadHTML($html);
foreach($dom->getElementsByTagName('a') as $node) {
    $link = substr($node->getAttribute('href'), 2);
    # only interested in .bz2 files (the comment archive for each month)
    if (preg_match('/\.bz2$/', $link) && !in_array($link, $downloadList)
            && (strcmp($link, end($downloadList)) > 0))
        $downloadList[] = $link;
}

# use curl to directly save each month's archive into file to save memory
foreach($downloadList as $file) {
    @$fp = fopen($localDirectory.$file, 'wb');
    $ch = curl_init($serverDirectory.$file);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}