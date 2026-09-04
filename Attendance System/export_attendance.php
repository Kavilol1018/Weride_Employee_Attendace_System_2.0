<?php
use Shuchkin\SimpleXLSX;
session_start();

// Ensure user is management
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    header("Location: ../index.php");
    exit();
}

require_once 'SimpleXLSX.php';

$file_to_parse = '';

// Check which file to parse based on where the export was triggered
if (isset($_GET['source']) && $_GET['source'] === 'history') {
    if (isset($_SESSION['history_loaded_file']) && file_exists($_SESSION['history_loaded_file'])) {
        $file_to_parse = $_SESSION['history_loaded_file'];
    }
} else {
    if (isset($_SESSION['temp_upload_path']) && file_exists($_SESSION['temp_upload_path'])) {
        $file_to_parse = $_SESSION['temp_upload_path'];
    }
}

if (empty($file_to_parse)) {
    die("No file data available for export. Please load a file first.");
}

$view = isset($_GET['view']) ? $_GET['view'] : 'check-in-out';

$report_data = [];
if ($xlsx = SimpleXLSX::parse($file_to_parse)) {
    $isFirst = true;
    $colMap = [];
    $emptyCount = 0;
    
    foreach ($xlsx->readRows() as $row) {
        $row = (array)$row;
        if (empty(array_filter($row))) {
            $emptyCount++;
            if ($emptyCount > 20) break;
            continue;
        }
        $emptyCount = 0;

        if ($isFirst) {
            foreach ($row as $idx => $colName) {
                $colMap[strtolower(trim($colName))] = $idx;
            }
            $isFirst = false;
            continue;
        }

        $person_id = isset($colMap['person id']) ? $row[$colMap['person id']] : ($row[1] ?? '');
        $name = isset($colMap['name']) ? $row[$colMap['name']] : ($row[2] ?? '');
        $date = isset($colMap['attendance date']) ? $row[$colMap['attendance date']] : ($row[3] ?? '');
        $raw_period = isset($colMap['period']) ? $row[$colMap['period']] : ($row[5] ?? '');
        $raw_check_in = isset($colMap['check in time']) ? $row[$colMap['check in time']] : ($row[6] ?? '');

        $preferred_hours = '';
        if (preg_match('/\((\d{2}:\d{2}-\d{2}:\d{2})\)/', $raw_period, $matches)) {
            $preferred_hours = $matches[1];
        } else {
            $preferred_hours = $raw_period;
        }

        $check_in = '';
        if (strtolower(trim($raw_check_in)) === 'not checked in') {
            $check_in = 'Not Checked In';
        } else if (preg_match('/\d{2}:\d{2}/', $raw_check_in, $matches)) {
            $check_in = substr($matches[0], 0, 5); 
        } else {
            $check_in = trim($raw_check_in);
        }
        
        $raw_check_out = isset($colMap['check out time']) ? $row[$colMap['check out time']] : ($row[7] ?? '');
        $check_out = '';
        if (strtolower(trim($raw_check_out)) === 'not checked out' || empty(trim($raw_check_out))) {
            $check_out = 'Not Checked Out';
        } else if (preg_match('/\d{2}:\d{2}/', $raw_check_out, $matches)) {
            $check_out = substr($matches[0], 0, 5); 
        } else {
            $check_out = trim($raw_check_out);
        }

        $shift_start = '';
        $shift_end = '';
        if (strpos($preferred_hours, '-') !== false) {
            list($start, $end) = explode('-', $preferred_hours);
            $shift_start = trim($start);
            $shift_end = trim($end);
            
            if (strlen($shift_start) < 5 && strpos($shift_start, ':') !== false) $shift_start = '0' . $shift_start;
            if (strlen($shift_end) < 5 && strpos($shift_end, ':') !== false) $shift_end = '0' . $shift_end;
        }

        $status = '';
        if (empty($check_in) || strtolower($check_in) === 'not checked in') {
            $status = 'Absent';
            $check_in = 'Not Checked In';
        } else {
            if (!empty($shift_start)) {
                if (strtotime($check_in) > strtotime($shift_start)) {
                    $status = 'Late';
                } else {
                    $status = 'On Time';
                }
            }
        }
        
        $checkout_status = '';
        if (empty($check_out) || strtolower($check_out) === 'not checked out' || $check_out === 'Not Checked Out') {
            $checkout_status = 'Missed';
            $check_out = 'Not Checked Out';
        } else {
            if (!empty($shift_end)) {
                if (strtotime($check_out) < strtotime($shift_end)) {
                    $checkout_status = 'Early Leave';
                } else {
                    $checkout_status = 'On Time';
                }
            }
        }

        $report_data[] = [
            'person_id' => $person_id,
            'name' => $name,
            'date' => $date,
            'check_in' => $check_in,
            'status' => $status,
            'check_out' => $check_out,
            'checkout_status' => $checkout_status
        ];
    }
}

// Filter data based on view
$filtered_data = [];
foreach ($report_data as $row) {
    if ($view === 'late' && $row['status'] !== 'Late' && $row['checkout_status'] !== 'Early Leave') continue;
    $filtered_data[] = $row;
}
$show_checkout = ($view === 'check-in-out');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Attendance_Export_' . date('Ymd_His') . '.csv');

$output = fopen('php://output', 'w');

// Headers
$headers = ['#', 'Employee ID', 'Employee Name', 'Date', 'Check-in', 'Status'];
if ($show_checkout) {
    $headers[] = 'Check-out';
    $headers[] = 'Out Status';
}
fputcsv($output, $headers);

$row_num = 1;
foreach ($filtered_data as $row) {
    $csv_row = [
        $row_num++,
        $row['person_id'],
        $row['name'],
        $row['date'],
        $row['check_in'],
        $row['status']
    ];
    if ($show_checkout) {
        $csv_row[] = $row['check_out'];
        $csv_row[] = $row['checkout_status'];
    }
    fputcsv($output, $csv_row);
}
fclose($output);
exit();
?>

