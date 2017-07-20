<?php
require_once 'functions.php';
# bulk insert process per file, to be run in parallel.
# this script checks for validity of all comment rows in an archive, manually builds the query,
# then batch insert them to the database using prepared statements.

$file = $argv[1];
$tags = val('tags');

@$db = getConnection_db(val('db_name')) or die ("Couldn't connect: ".$db->errno);
if (!(@$fp = fopen($file, 'rb'))) {
    cleanup_process($db, $file);
    exit ("File $file could not be opened\n");
}

create_compressed_table($db);

# a couple of tweaks to make the insert process faster
# http://derwiki.tumblr.com/post/24490758395/loading-half-a-billion-rows-into-mysql
optimize($db);

$tags = val('tags');
$buffer_size = val('insert_batch');
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
$num_queries = 0;

while (!feof($fp)) {
    # read buffer-size lines at a time - the first parameter will be the type string
    for ($i = 0, $params = [''], $prep_stmt = 'INSERT INTO Comments VALUES '; $i < $buffer_size && !feof($fp); $i++) {
        @$comment = json_decode(fgets($fp), true);
        # because the data is so fucked, sometimes a line of file isn't even a proper json
        if (!is_array($comment)) continue;
        # prepare values to bind for each comment
        foreach ($comment as $tag => $value) {
            # see if this is a tag we care about
            if (($key = array_search($tag, $lookup)) === false) continue;
            $valid = true;
            # check the validity of each entry, according to its tag
            if (array_key_exists($tag, $type_restraint))
                switch ($type_restraint[$tag]) {
                    case 'PRIMARY KEY':
                        # search for any duplicate primary key
                        array_search($value, $primary_keys) !== false
                                ? $valid = false
                                : $primary_keys[] = $value;
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
            if ($valid) {
//                if (!val('prep_stmt') && is_string($value))
//                    $value = mysqli_real_escape_string($db, $value);
                # we put all the values into one array, so we need to take care of the relative order
                $params[$num_fields * $i + $key + 1] = $value;
                continue;
            }
            # delete the values for this comment row
            for ($j = $num_fields * $i + 1; $j <= ($i + 1) * $num_fields; $j++)
                if (isset($params[$j])) unset($params[$j]);
            # reset the line pointer
            $i--;
            break;
        }
    }
    
    for ($k = 0; $k < $i; $k++) {
        if (val('prep_stmt')) {
            # build query
            $prep_stmt = $prep_stmt.$question_marks;
            # extend type string
            $params[0] = $params[0].$type_string;
        } else {
            $prep_stmt = $prep_stmt.'(';
            # $num_fields * $i + $key + 1
            for ($h = 0; $h < $num_fields; $h++)
                $prep_stmt = $prep_stmt.$params[$num_fields * $k + $h + 1].',';
            $prep_stmt = rtrim($prep_stmt, ',').'),';
        }
    }
    $prep_stmt = rtrim($prep_stmt, ',');
    $rows += $i;
    
    try {
        if (val('prep_stmt')) {
            # execute the giant insert query
            @$stmt = $db->prepare($prep_stmt) or die ('Failed to prepare: '.$db->errno."\n");
            # to bind params in order, we need to sort them, then hand in the reference via refValues
            ksort($params);
            @call_user_func_array([$stmt, 'bind_param'], refValues($params));
            $stmt->execute();
        } else {
            # stage transaction, since we disabled auto-commit
            if (!$num_queries++)
                $db->query('START TRANSACTION');
            $db->query($prep_stmt);
            if ($num_queries == val('num_trans') || feof($fp)) {
                $db->query('COMMIT');
                if ($num_queries == val('num_trans')) $num_queries -= val('num_trans');
            }
        }
    } catch (Error $e) {
        cleanup_process($db, $file);
        exit("Failed to execute query. Error $e\n");
    }
    echo "rows: $rows\n";
}

if (val('cleanup')) exec("rm $file");

$num_rows = $db->query("SELECT COUNT(*) FROM Comments")->fetch_row()[0];
cleanup_process($db, $file);
$duration = (int) (microtime(true) - $start);
printf('Inserted %d out of %d rows from [%s] in [%.6f]s.\n', $num_rows, $rows, $file, $duration);
exit();

# http://php.net/manual/en/mysqli-stmt.bind-param.php#100879
function refValues($arr){
    $refs = [];
    foreach ($arr as $key => $value)
        $refs[$key] = &$arr[$key];
    return $refs;
}