<?php
require_once 'functions.php';
# decompresses a file, to be used in parallel by extract.php

if (!($db = getConnection_db(val('db_name')))) exit ("Couldn't connect");

exec("bzip2 -d $argv[1]");

cleanup_process($db, $argv[1]);