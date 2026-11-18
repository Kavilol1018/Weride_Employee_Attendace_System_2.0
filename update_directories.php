<?php

$files = ['admin_employees.php', 'hr_employees.php', 'tl_employees.php'];

foreach ($files as $file) {
    if (!file_exists($file)) continue;
    $content = file_get_contents($file);

    // Update stats query
    $content = preg_replace(
        "/SELECT employment_type, COUNT\(\*\) as count FROM employees GROUP BY employment_type/i",
        "SELECT employment_type, COUNT(*) as count FROM employees WHERE role NOT IN ('admin', 'hr') GROUP BY employment_type",
        $content
    );

    // Update main query
    if ($file === 'admin_employees.php') {
        $content = str_replace(
            '$query = "SELECT * FROM employees";',
            '$query = "SELECT * FROM employees WHERE role NOT IN (\'admin\', \'hr\')";',
            $content
        );
        $content = str_replace(
            '$query .= " WHERE ',
            '$query .= " AND ',
            $content
        );
    } else {
        $content = str_replace(
            '$query = "SELECT * FROM employees";',
            '$query = "SELECT * FROM employees WHERE role NOT IN (\'admin\', \'hr\')";',
            $content
        );
        $content = str_replace(
            '$query .= " WHERE " . implode(" AND ", $where_clauses);',
            '$query .= " AND " . implode(" AND ", $where_clauses);',
            $content
        );
    }

    file_put_contents($file, $content);
    echo "Updated $file\n";
}
?>
