<?php
session_start();
require 'db_connect.php';

// Ensure user is management
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'hr', 'tl', 'gl'])) {
    header("Location: index.php");
    exit();
}

$role_slug = $_SESSION['role'];
$is_admin = ($role_slug === 'admin');
$current_page = 'admin_holidays.php';

$success = '';
$error = '';

// Handle File Upload (CSV or Memo)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_file'])) {
    if (isset($_FILES['memo_file']) && $_FILES['memo_file']['error'] === 0) {
        $file_tmp = $_FILES['memo_file']['tmp_name'];
        $file_name = basename($_FILES['memo_file']['name']);
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        if ($ext === 'csv') {
            // Process CSV automatically
            $handle = fopen($file_tmp, "r");
            if ($handle) {
                fgetcsv($handle); // skip header
                $count = 0;
                while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                    if (count($data) >= 2) {
                        $d = mysqli_real_escape_string($conn, trim($data[0]));
                        $n = mysqli_real_escape_string($conn, trim($data[1]));
                        // Basic format check YYYY-MM-DD
                        if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $d)) {
                            mysqli_query($conn, "INSERT IGNORE INTO public_holidays (holiday_date, holiday_name) VALUES ('$d', '$n')");
                            $count++;
                        }
                    }
                }
                fclose($handle);
                $success = "Successfully imported $count holidays from CSV!";
            } else {
                $error = "Error opening CSV file.";
            }
        } else if ($ext === 'pdf') {
            // --- Auto OCR Extraction using OCR.space ---
            $apiKey = 'K83907768588957'; 
            $cFile = new CURLFile($file_tmp, 'application/pdf', $file_name);
            $post = ['file' => $cFile, 'language' => 'eng', 'isTable' => 'true']; 
            $ch = curl_init();
            $ocrUrl = 'https://api.ocr.space/parse/image';
            curl_setopt($ch, CURLOPT_URL, $ocrUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $apiKey]);
            $response = curl_exec($ch);
            // curl_close is deprecated in PHP 8.0+, no longer needed

            $imported_count = 0;
            $json = json_decode($response, true);
            if (!empty($json['ParsedResults'][0]['ParsedText'])) {
                $text = $json['ParsedResults'][0]['ParsedText'];
                $lines = explode("\n", $text);
                foreach ($lines as $line) {
                    $cols = explode("\t", trim($line));
                    if (count($cols) >= 3) { 
                        $date_col = trim($cols[0]);
                        $name_col = trim($cols[2]);
                        
                        if (preg_match('/^(\d{1,2})\s+(January|February|March|April|Mav|May|June|July|August|September|October|November|December)\s+(\d{4})$/i', $date_col, $matches)) {
                            $month = str_ireplace('Mav', 'May', $matches[2]); // fix OCR typo
                            $date_str = $matches[1] . ' ' . $month . ' ' . $matches[3];
                            $timestamp = strtotime($date_str);
                            
                            if ($timestamp && !empty($name_col)) {
                                $d = date('Y-m-d', $timestamp);
                                $n = mysqli_real_escape_string($conn, $name_col);
                                mysqli_query($conn, "INSERT IGNORE INTO public_holidays (holiday_date, holiday_name) VALUES ('$d', '$n')");
                                $imported_count++;
                            }
                        }
                    }
                }
            }

            if ($imported_count > 0) {
                $success = "Successfully imported $imported_count holidays directly from the PDF using OCR!";
            } else {
                $error = "File processed, but no tabular holiday data could be automatically extracted from the PDF.";
            }

            // Save a copy of the PDF as reference
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            move_uploaded_file($file_tmp, $upload_dir . 'holiday_reference_memo.pdf');

        } else {
            $error = "Unsupported file type. Please upload a CSV or PDF file.";
        }
    } else {
        $error = "Please select a valid file to upload.";
    }
}

// Handle Add Holiday Manually
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_holiday'])) {
    $date = mysqli_real_escape_string($conn, trim($_POST['holiday_date']));
    $name = mysqli_real_escape_string($conn, trim($_POST['holiday_name']));
    
    if (!empty($date) && !empty($name)) {
        $check = mysqli_query($conn, "SELECT id FROM public_holidays WHERE holiday_date = '$date' AND holiday_name = '$name'");
        if (mysqli_num_rows($check) > 0) {
            $error = "This exact holiday already exists on this date.";
        } else {
            $insert = mysqli_query($conn, "INSERT INTO public_holidays (holiday_date, holiday_name) VALUES ('$date', '$name')");
            if ($insert) {
                $success = "Holiday added successfully!";
            } else {
                $error = "Error adding holiday: " . mysqli_error($conn);
            }
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

// Handle Delete Holiday
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_holiday'])) {
    $id = (int)$_POST['holiday_id'];
    if (mysqli_query($conn, "DELETE FROM public_holidays WHERE id = $id")) {
        $success = "Holiday deleted successfully!";
    } else {
        $error = "Failed to delete holiday.";
    }
}

// Handle Bulk Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_multiple_holidays']) && !empty($_POST['del_ids'])) {
    $ids = array_map('intval', $_POST['del_ids']);
    $ids_str = implode(',', $ids);
    if (mysqli_query($conn, "DELETE FROM public_holidays WHERE id IN ($ids_str)")) {
        $success = count($ids) . " holidays deleted successfully!";
    } else {
        $error = "Failed to delete holidays.";
    }
}

$holidays = [];
$h_query = mysqli_query($conn, "SELECT id, holiday_date, holiday_name FROM public_holidays ORDER BY holiday_date ASC");
while ($row = mysqli_fetch_assoc($h_query)) {
    $holidays[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Public Holidays - WeRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f8fafc; margin: 0; display: flex; color: #0f172a; }
        .main-content { flex: 1; padding: 30px; box-sizing: border-box; overflow-y: auto; height: 100vh; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 28px; color: #0f172a; }
        
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: 600; margin-bottom: 5px; font-size: 14px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 14px; }
        
        .btn { padding: 10px 16px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-block; }
        .btn:hover { background: #2563eb; }
        .btn-danger { background: #ef4444; }
        .btn-danger:hover { background: #dc2626; }
        .btn-success { background: #10b981; }
        .btn-success:hover { background: #059669; }
        
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; font-size: 14px; }
        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        table { border-collapse: collapse; width: 100%; white-space: nowrap; font-size: 14px; }
        th, td { border: 1px solid #e2e8f0; padding: 12px; text-align: left; }
        th { background: #f1f5f9; font-weight: 600; }
        
        .split-container { display: flex; gap: 20px; align-items: flex-start; }
        .split-left { flex: 1.5; min-width: 0; }
        .split-right { flex: 1; min-width: 0; }
        
        .memo-viewer { width: 100%; height: 75vh; border: 1px solid #cbd5e1; border-radius: 12px; overflow: hidden; background: #e2e8f0; display: flex; align-items: center; justify-content: center;}
        .memo-viewer img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .memo-viewer iframe { width: 100%; height: 100%; border: none; }
        
        /* Dark mode */
        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .header h1 { color: #f8fafc; }
        body.dark-mode .card { background: #1e293b; border: 1px solid #334155; }
        body.dark-mode .form-control { background: #0f172a; border-color: #334155; color: #f8fafc; }
        body.dark-mode th, body.dark-mode td { border-color: #334155; }
        body.dark-mode th { background: #334155; color: #f8fafc; }
        body.dark-mode .memo-viewer { border-color: #334155; background: #0f172a; }
    </style>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="header">
            <h1>Manage Public Holidays</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= $error ?></div>
        <?php endif; ?>

                <!-- Uploader Card -->
                <div class="card" style="background-color: rgba(59, 130, 246, 0.05); border: 1px dashed #3b82f6; max-width: 600px;">
                    <div class="card-header">
                        <h2 style="margin-top: 0; font-size: 18px; color: #3b82f6;">Batch Import (CSV / PDF)</h2>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Upload File (CSV or PDF Table)</label>
                            <input type="file" name="memo_file" accept=".csv,.pdf" required class="form-control">
                        </div>
                        <button type="submit" name="upload_file" class="btn btn-secondary">Import File</button>
                    </form>
                </div>

                <!-- Add Holiday Card -->
                <div class="card">
                    <h2 style="margin-top: 0; font-size: 18px;">Add Holiday Manually</h2>
                    <form method="POST" action="">
                        <div style="display: flex; gap: 10px;">
                            <div class="form-group" style="flex: 1; margin-bottom:0;">
                                <label>Date</label>
                                <input type="date" name="holiday_date" class="form-control" required>
                            </div>
                            <div class="form-group" style="flex: 2; margin-bottom:0;">
                                <label>Holiday Name</label>
                                <input type="text" name="holiday_name" class="form-control" placeholder="e.g. Christmas Day" required>
                            </div>
                            <div style="display: flex; align-items: flex-end;">
                                <button type="submit" name="add_holiday" class="btn">Add</button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Existing Holidays -->
                <div class="card">
                    <h2 style="margin-top: 0; font-size: 18px; display: flex; justify-content: space-between; align-items: center;">
                        Existing Holidays
                        <?php if (count($holidays) > 0): ?>
                        <button type="submit" form="bulk-delete-form-admin" name="delete_multiple_holidays" class="btn btn-danger" style="font-size:13px; padding: 6px 12px;" onclick="return confirm('Are you sure you want to delete all selected holidays?');">Delete Selected</button>
                        <?php endif; ?>
                    </h2>
                    <?php if (count($holidays) > 0): ?>
                        <?php if($is_admin): ?><form method="POST" action="" id="bulk-delete-form-admin"><?php endif; ?>
                        <div style="max-height: 60vh; overflow-y: auto;">
                            <table>
                                <thead>
                                    <tr>
                                        <?php if($is_admin): ?>
                                        <th style="width: 40px; text-align:center;">
                                            <input type="checkbox" onclick="document.querySelectorAll('.chk-holiday-admin').forEach(cb => cb.checked = this.checked);" style="cursor:pointer;" title="Select All">
                                        </th>
                                        <?php endif; ?>
                                        <th>Date</th>
                                        <th>Holiday Name</th>
                                        <th style="width: 80px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($holidays as $h): ?>
                                        <tr>
                                            <?php if($is_admin): ?>
                                            <td style="text-align:center;">
                                                <input type="checkbox" name="del_ids[]" value="<?= $h['id'] ?>" class="chk-holiday-admin" style="cursor:pointer;">
                                            </td>
                                            <?php endif; ?>
                                            <td><?= date('d M Y (l)', strtotime($h['holiday_date'])) ?></td>
                                            <td><?= htmlspecialchars($h['holiday_name']) ?></td>
                                            <td>
                                                <button type="submit" name="delete_holiday" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" form="delete-form-admin-<?= $h['id'] ?>">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if($is_admin): ?></form><?php endif; ?>

                        <?php if($is_admin): ?>
                            <?php foreach ($holidays as $h): ?>
                            <form method="POST" action="" id="delete-form-admin-<?= $h['id'] ?>" onsubmit="return confirm('Are you sure you want to delete this holiday?');" style="display:none;">
                                <input type="hidden" name="holiday_id" value="<?= $h['id'] ?>">
                            </form>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-slate-500" >No public holidays have been added yet.</p>
                    <?php endif; ?>
                </div>
    </div>
</body>
</html>

