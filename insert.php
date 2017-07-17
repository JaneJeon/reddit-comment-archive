<?php
require_once 'functions.php';
# bulk insert process per file, to be run in parallel

$file = $argv[1];
$tags = get('tags');

$db = getConnection_db(get('db_name'));
if (!(@$fp = fopen($file, 'rb'))) {
    cleanup_process($db);
    exit ("File $file could not be opened");
}

$tags = get('tags');
$buffer_size = get('insert_batch');
$rows = 0;
$start = microtime(true);
const value_types = ['BOOL', 'VARCHAR', 'PRIMARY KEY'];
const valid_bool = [0, 1, '0', '1', 'true', 'false'];

# used to form batch insert query
$question_marks = '(';
# needed to bind values
$type_string = '';
# keeps track of which restraint check we should do for each field
$type_restraint = [];

foreach ($tags as $field => $type) {
    $question_marks = $question_marks.'?,';
    $type_string = $type_string.(preg_match('/(INT|BOOL)/i', $type) ? 'i' : 's');
    foreach (value_types as $val)
        if (stripos($type, $val) !== false)
            $type_restraint[$field] = $val;
}

$question_marks = rtrim($question_marks, ',').'),';
# used to check whether we're inserting the right number of values for each row
$num_fields = strlen($type_string);
# similar, but to check for right tags - the reason we're not using array_key_exists for $tags is because we need
# the relative position of the tag
$lookup = array_keys($tags);

# note that the data is ridiculously 'dirty', so we'll be doing a lot of manual checks!
# used to check for any duplicate primary keys
$primary_keys = [];

# stage transaction, since we disabled auto-commit
//$db->query('START TRANSACTION');

while (!feof($fp)) {
    # read buffer-size lines at a time - the first parameter will be the type string
    for ($i = 0, $params = [''], $prep_stmt = 'INSERT INTO Comments VALUES '; $i < $buffer_size && !feof($fp); $i++) {
        @$comment = json_decode(fgets($fp), true);
        # because the data is so fucked, sometimes a line of file isn't even a proper json
        if (!is_array($comment)) continue;
        # build query
        $prep_stmt = $prep_stmt.$question_marks;
        # extend type string
        $params[0] = $params[0].$type_string;
        # prepare values to bind for each comment
        foreach ($comment as $tag => $value) {
            # see if this is a tag we care about
            if (($key = array_search($tag, $lookup)) === false) continue;
            $valid = true;
            # check the validity of each entry, according to its tag
            if (array_key_exists($tag, $type_restraint)) {
                switch ($type_restraint[$tag]) {
                    case 'PRIMARY KEY':
                        # search for any duplicate primary key
                        if (array_search($value, $primary_keys) !== false) {
                            $valid = false;
                        } else {
                            $primary_keys[] = $value;
                        }
                        break;
                    case 'VARCHAR':
                        # the input strings shouldn't be longer than the varchar length limit
                        preg_match('/\((.*?)\)/', $tags[$tag], $lim);
                        if (strlen($value) > (int) $lim[1]) $valid = false;
                        break;
                    case 'BOOL':
                        # for some reason, the data sometimes contain non-boolean values in BOOL fields
                        if (array_search($value, valid_bool) === false) $valid = false;
                        break;
                }
            }
            if ($valid) {
                # we put all the values into one array, so we need to take care of the relative order
                $params[$num_fields * $i + $key + 1] = $value;
                continue;
            }
            # invalidate this entire comment row if there's an error
            $prep_stmt = substr($prep_stmt, 0, -strlen($question_marks));
            $params[0] = substr($params[0], $num_fields);
            # delete the values for this comment row
            for ($j = $num_fields * $i + 1; $j <= ($i + 1) * $num_fields; $j++)
                if (isset($params[$j])) unset($params[$j]);
            # reset the line pointer
            $i--;
            break;
        }
    }
    $rows += $i;
    
    try {
        # execute the giant insert query
        $prep_stmt = rtrim($prep_stmt, ',');
        @$stmt = $db->prepare($prep_stmt) or die ('Failed to prepare: '.$db->errno);
        # to bind params in order, we need to sort them, then hand in the reference via refValues
        ksort($params);
        @call_user_func_array([$stmt, 'bind_param'], refValues($params));
        $stmt->execute();
    } catch (Exception $e) {
        cleanup_process($db);
        exit("Failed to execute query. Error $e");
    }
}

//$db->query('COMMIT');
exec("rm $file");

$num_rows = $db->query("SELECT COUNT(*) FROM Comments")->fetch_row()[0];
$duration = (int) microtime(true) - $start;
cleanup_process($db);
exit ("Inserted $num_rows rows out of $rows from $file in $duration s.");

# http://php.net/manual/en/mysqli-stmt.bind-param.php#100879
function refValues($arr){
    $refs = [];
    foreach ($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;
}