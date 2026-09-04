<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $emp_id = $_SESSION['emp_id'];
        $table = $_POST['table'] === 'short_breaks' ? 'short_breaks' : 'lunch_breaks';
        $record_id = (int)$_POST['record_id'];
        
        $new_start_time = mysqli_real_escape_string($conn, $_POST['new_start_time']); // format: HH:MM
        $new_end_time = mysqli_real_escape_string($conn, $_POST['new_end_time']); // format: HH:MM
        $category = mysqli_real_escape_string($conn, $_POST['category']);
        
        $fallback_url = $table === 'short_breaks' ? 'breaktime_report.php' : 'lunchtime_report.php';
        $return_url = !empty($_POST['return_url']) ? mysqli_real_escape_string($conn, $_POST['return_url']) : $fallback_url;

        $id_col = $table === 'lunch_breaks' ? 'break_id' : 'id';
        $emp_col = $table === 'lunch_breaks' ? 'employee_id' : 'emp_id';
        $user_role = $_SESSION['role'] ?? 'employee';

        // Verify ownership and get the date part of the existing record
        $can_edit_others = in_array(strtolower($user_role), ['hr', 'admin', 'tl', 'group_leader', 'gl']);
        
        if ($can_edit_others) {
            $check_sql = "SELECT DATE(break_start) as record_date FROM $table WHERE $id_col = $record_id";
        } else {
            $check_sql = "SELECT DATE(break_start) as record_date FROM $table WHERE $id_col = $record_id AND $emp_col = '$emp_id'";
        }
        
        $check_res = mysqli_query($conn, $check_sql);
        
        if ($check_res && mysqli_num_rows($check_res) > 0) {
            $row = mysqli_fetch_assoc($check_res);
            $record_date = $row['record_date'];

            // Combine date with new times
            $start_datetime = $record_date . ' ' . $new_start_time . ':00';
            
            $end_datetime = null;
            $end_datetime_sql = "NULL";
            if (!empty($new_end_time)) {
                $end_datetime = $record_date . ' ' . $new_end_time . ':00';
                $end_datetime_sql = "'$end_datetime'";
            }

            $duration_sql = "";
            if ($table === 'short_breaks') {
                // Need to update duration_min for short_breaks
                if (!empty($new_end_time)) {
                    $duration_sql = ", duration_min = TIMESTAMPDIFF(MINUTE, '$start_datetime', '$end_datetime')";
                } else {
                    $duration_sql = ", duration_min = NULL";
                }
            }

            $update_sql = "UPDATE $table SET 
                break_start = '$start_datetime', 
                break_end = $end_datetime_sql, 
                category = '$category', 
                is_edited = 1 
                $duration_sql
                WHERE $id_col = $record_id";
                
            mysqli_query($conn, $update_sql);
            
            header("Location: " . $return_url . "?msg=" . urlencode("Record updated successfully."));
            exit();
        } else {
            header("Location: " . $return_url . "?error=" . urlencode("Record not found or access denied."));
            exit();
        }
    }
} catch (Exception $e) {
    echo "Fatal error in edit_break_record: " . $e->getMessage();
}
?>
