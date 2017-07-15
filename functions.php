<?php
# some functions that are used repeatedly & syntax sugars

# is it inefficient? yes. do I care? no.
function get($field) {
    return parse_ini_file('config.ini')[$field];
}

function get_logical_cores() {
    if (PHP_OS == 'Darwin') { # macOS
        return (int) shell_exec("sysctl hw.logicalcpu | sed 's/hw.logicalcpu: //g'") + 1;
    } else if (PHP_OS == 'Linux') {
        return (int) shell_exec("cat /proc/cpuinfo | grep processor | wc -l") + 1;
    } else return -1; # Windows, etc
}

function getConnection_no_db() {
    try {
        $db = new mysqli(get('host'), get('username'), get('password'));
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
    $db->query('CREATE TABLE Progress (task_id INT PRIMARY KEY AUTO_INCREMENT, done BOOL NOT NULL)');
}

function wait_pool(mysqli $db, $max_process) {
    while ($db->query('SELECT COUNT(*) FROM Progress WHERE done = FALSE')->fetch_row()[0] > $max_process)
        sleep(5);
    # once a free space opens up, indicate that this task is coming in
    $db->query('INSERT INTO Progress (done) VALUES (FALSE)');
}

# notify the end of process
function pool_done(mysqli $db) {
    $db->query('UPDATE Progress SET done = TRUE WHERE task_id = '
            .$db->query('SELECT task_id FROM Progress ORDER BY task_id LIMIT 1')->fetch_row()[0]);
}

function destroy_pool(mysqli $db) {
    $db->query('DROP TABLE Progress');
}