Reddit Comment Archive
---
These scripts download comment archives from [here](http://files.pushshift.io/reddit/comments/), decompress them, 
extract comment tags, and insert the comments into the database.

First, make a copy of config_example.php into config.php and change any config as you see fit.

Then, run the scripts in this order:
1. download.php
2. extract.php
3. tags.php (optional - use to get an idea of which tags you care about)
4. ingest.php

Requirements:
* PHP 5.6+
* MySQL 5.6+
* Linux/macOS operating system
* directory with lots of free space
