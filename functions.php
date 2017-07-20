<?php
require_once 'Config.php';
# some functions that are used repeatedly & syntax sugars

function val($field) {
    # account for directories without / at the end
    if ($field == 'localDirectory' && substr(constant("Config::$field"), -1) != '/')
        return constant("Config::$field").'/';
    return constant("Config::$field");
}

function num_logical_cores() {
    if (PHP_OS == 'Darwin') { # macOS
        return (int) shell_exec("sysctl hw.logicalcpu | sed 's/hw.logicalcpu: //g'");
    } else if (PHP_OS == 'Linux') {
        return (int) shell_exec("cat /proc/cpuinfo | grep processor | wc -l");
    } else return -1; # Windows, etc
}

function getConnection_no_db() {
    try {
        $db = new mysqli(val('host'), val('username'), val('password'));
    } catch (Exception $e) {
        return 0;
    }
    return $db;
}

function getConnection_db($db) {
    $conn = getConnection_no_db();
    if (!$conn) return 0;
    $conn->query("CREATE DATABASE IF NOT EXISTS $db");
    mysqli_select_db($conn, $db);
    return $conn;
}

function createPool(mysqli $db) {
    # first, clean up
    destroy_pool($db);
    $db->query('CREATE TABLE Progress
              (task_id INT PRIMARY KEY AUTO_INCREMENT, file TEXT CHARACTER SET utf8mb4)');
}

function wait_pool(mysqli $db, $max_process) {
    while ($db->query('SELECT COUNT(*) FROM Progress WHERE file IS NULL')->fetch_row()[0] >= $max_process)
        sleep(val('interval'));
    # once a free space opens up, indicate that this task is coming in (DRY)
    $db->query('INSERT INTO Progress (task_id) VALUES (NULL)');
}

function notify_pool_done(mysqli $db, $file) {
    # for some reason, this simple SELECT query will go apeshit rather than returning a number just... randomly
    while (!(@$row = $db->query("SELECT task_id FROM progress WHERE file IS NULL ORDER BY task_id LIMIT 1")
            ->fetch_row()[0])) {
        sleep(val('interval'));
    }
    $db->query("UPDATE Progress SET file = '$file' WHERE task_id = $row");
}

function destroy_pool(mysqli $db) {
    $db->query('DROP TABLE Progress');
}

function cleanup_process(mysqli $db, $file) {
    notify_pool_done($db, $file);
    $db->close();
}

function create_compressed_table(mysqli $db) {
    # to enable table compression
    $db->query('SET innodb_file_per_table = 1');
    $db->query('SET innodb_file_format = Barracuda');
    
    # form table creation query from the tags
    $table = 'CREATE TABLE IF NOT EXISTS Comments (';
    foreach (val('tags') as $field => $type)
        $table = $table."\n".$field.' '.$type.',';
    $table = rtrim($table, ',').")\n".'ROW_FORMAT = COMPRESSED';
    
    $db->query($table);
}

function optimize(mysqli $db) {
    $db->query('SET UNIQUE_CHECKS = 0');
    $db->query("SET SESSION tx_isolation='READ-UNCOMMITTED'");
    $db->query('SET sql_log_bin = 0');
}