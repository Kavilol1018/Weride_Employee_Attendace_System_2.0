<?php
$current_employee_id = 'IN-2606-011';
$start_date = '2026-07-01';
$end_date = '2026-09-17';

$where_clauses_lb = ["lb.status = 'returned'", "lb.employee_id = '$current_employee_id'"];
$where_clauses_lb[] = "DATE(lb.break_start) BETWEEN '$start_date' AND '$end_date'";
$where_sql_lb = implode(' AND ', $where_clauses_lb);

$where_clauses_sb = ["sb.status = 'returned'", "sb.emp_id = '$current_employee_id'"];
$where_clauses_sb[] = "DATE(sb.break_start) BETWEEN '$start_date' AND '$end_date'";
$where_sql_sb = implode(' AND ', $where_clauses_sb);

$history_sql = "
    SELECT * FROM (
        SELECT 
            lb.break_id, lb.break_start, lb.break_end,
            TIMESTAMPDIFF(MINUTE, lb.break_start, lb.break_end) as duration_min,
            lb.category, lb.is_edited
        FROM lunch_breaks lb
        WHERE $where_sql_lb
        
        UNION ALL
        
        SELECT 
            sb.id as break_id, sb.break_start, sb.break_end,
            sb.duration_min,
            sb.category, sb.is_edited
        FROM short_breaks sb
        WHERE $where_sql_sb
    ) combined_breaks
    ORDER BY break_start DESC
";
echo $history_sql;
?>
