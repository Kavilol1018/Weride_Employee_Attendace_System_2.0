<?php
$old_sql = file('C:\\Users\\PC\\Downloads\\u251904595_kavinesganesen (5).sql');

$mapping = [];
$in_employees = false;
foreach ($old_sql as $line) {
    if (strpos($line, 'INSERT INTO employees') !== false) {
        $in_employees = true;
    }
    
    if ($in_employees) {
        if (preg_match('/^\((\d+),\s*\'([^\']+)\'/', trim($line), $match)) {
            $mapping[$match[2]] = $match[1];
        }
        if (strpos($line, ';') !== false && !preg_match('/VALUES\s*$/', trim($line))) {
            if (preg_match('/^\((\d+),\s*\'([^\']+)\'/', trim($line), $match)) {
                $in_employees = false;
            }
        }
    }
}
print_r($mapping);
?>
