<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';
/** @var mysqli $conn */

$role = $_SESSION['role'];
$is_admin = in_array($role, ['admin', 'hr']);
$msg = "";
$error = "";

if ($is_admin) {
    if (isset($_POST['add_holiday'])) {
        $date = mysqli_real_escape_string($conn, $_POST['holiday_date']);
        $name = mysqli_real_escape_string($conn, $_POST['holiday_name']);
        
        // check duplicate
        $check = mysqli_query($conn, "SELECT id FROM public_holidays WHERE holiday_date='$date' AND holiday_name='$name'");
        if (mysqli_num_rows($check) > 0) {
            $error = "This exact holiday already exists on this date!";
        } else {
            if (mysqli_query($conn, "INSERT INTO public_holidays (holiday_date, holiday_name) VALUES ('$date', '$name')")) {
                $msg = "Public holiday added successfully!";
            } else {
                $error = "Failed to add holiday.";
            }
        }
    }
    
    if (isset($_POST['delete_holiday'])) {
        $del_id = mysqli_real_escape_string($conn, $_POST['del_id']);
        mysqli_query($conn, "DELETE FROM public_holidays WHERE id='$del_id'");
        $msg = "Holiday deleted.";
    }

    if (isset($_POST['delete_multiple_holidays']) && !empty($_POST['del_ids'])) {
        $ids = array_map('intval', $_POST['del_ids']);
        $ids_str = implode(',', $ids);
        if (mysqli_query($conn, "DELETE FROM public_holidays WHERE id IN ($ids_str)")) {
            $msg = count($ids) . " holidays deleted successfully.";
        } else {
            $error = "Failed to delete holidays.";
        }
    }

    // Handle File Upload (CSV or PDF)
    if (isset($_POST['upload_file'])) {
        if (isset($_FILES['memo_file']) && $_FILES['memo_file']['error'] == 0) {
            $file_tmp = $_FILES['memo_file']['tmp_name'];
            $file_name = basename($_FILES['memo_file']['name']);
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            if ($ext === 'csv') {
                $handle = fopen($file_tmp, "r");
                if ($handle) {
                    fgetcsv($handle); // skip header
                    $count = 0;
                    while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                        if (count($data) >= 2) {
                            $d = mysqli_real_escape_string($conn, trim($data[0]));
                            $n = mysqli_real_escape_string($conn, trim($data[1]));
                            if (preg_match("/^\d{4}-\d{2}-\d{2}$/", $d)) {
                                mysqli_query($conn, "INSERT IGNORE INTO public_holidays (holiday_date, holiday_name) VALUES ('$d', '$n')");
                                $count++;
                            }
                        }
                    }
                    fclose($handle);
                    $msg = "Successfully imported $count holidays from CSV!";
                } else {
                    $error = "Error opening CSV file.";
                }
            } else if ($ext === 'pdf') {
                $upload_dir = 'uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $dest = $upload_dir . 'holiday_reference.pdf';
                if (move_uploaded_file($file_tmp, $dest)) {
                    
                    // --- Auto OCR Extraction using OCR.space ---
                    $apiKey = 'K83907768588957'; 
                    $cFile = new CURLFile($dest, 'application/pdf', 'holiday_reference.pdf');
                    $post = ['file' => $cFile, 'language' => 'eng', 'isTable' => 'true']; 
                    $ch = curl_init();
                    $ocrUrl = 'https://api.ocr.space/parse/image';
                    curl_setopt($ch, CURLOPT_URL, $ocrUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['apikey: ' . $apiKey]);
                    $response = curl_exec($ch);
                    
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
                        $msg = "Reference PDF uploaded and automatically extracted $imported_count holidays!";
                    } else {
                        $msg = "Reference PDF uploaded successfully! (Note: No tabular holiday data could be automatically extracted).";
                    }
                } else {
                    $error = "Failed to upload PDF.";
                }
            } else {
                $error = "Unsupported file type. Please upload a CSV or PDF file.";
            }
        } else {
            $error = "Please select a valid file to upload.";
        }
    }
}

$holidays = mysqli_query($conn, "SELECT * FROM public_holidays ORDER BY holiday_date ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Public Holidays - WeRide</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { display: flex; height: 100vh; background-color: #f8fafc; color: #0f172a; }
        .main-content { flex: 1; padding: 40px; overflow-y: auto; }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .page-title { font-size: 28px; font-weight: 700; color: #1e293b; }
        
        .card { background: white; padding: 25px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 30px; }
        .card-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; color: #1e293b; }
        
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; font-size: 14px; font-weight: 600; color: #475569; margin-bottom: 6px; }
        .form-control { width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 14px; outline: none; transition: 0.2s; }
        .form-control:focus { border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
        
        .btn { display: inline-flex; justify-content: center; align-items: center; padding: 10px 20px; background: #3b82f6; color: white; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; transition: 0.2s; }
        .btn:hover { background: #2563eb; }
        .btn-delete { background: #fee2e2; color: #991b1b; padding: 6px 12px; }
        .btn-delete:hover { background: #fecaca; }

        .toast { padding: 12px 20px; border-radius: 8px; font-weight: 600; margin-bottom: 20px; }
        .toast-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .toast-error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { padding: 14px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { color: #64748b; font-weight: 600; background: #f8fafc; text-transform: uppercase; font-size:13px; }
        
        <?php 
          $grid_cols = '1fr';
          if ($is_admin) {
              $grid_cols = '300px 1fr';
          }
        ?>
        .layout-grid { display: grid; grid-template-columns: <?= $grid_cols ?>; gap: 30px; align-items: start; }

        body.dark-mode { background-color: #0f172a; color: #f8fafc; }
        body.dark-mode .card { background: #1e293b; border-color: #334155; }
        body.dark-mode .form-control { background: #0f172a; border-color: #475569; color: white; }
        body.dark-mode th { background: #0f172a; color: #94a3b8; }
        body.dark-mode td, body.dark-mode th, body.dark-mode .card-title { border-color: #334155; }
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">

    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Public Holidays</h1>
        </div>

        <?php if($msg): ?><div class="toast toast-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
        <?php if($error): ?><div class="toast toast-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="layout-grid">
            <?php if($is_admin): ?>
            <div class="left-column">
                <div class="card">
                    <h2 class="card-title">Add New Holiday</h2>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label class="form-label">Holiday Date</label>
                            <input type="date" name="holiday_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Holiday Name</label>
                            <input type="text" name="holiday_name" class="form-control" required placeholder="e.g., Chinese New Year">
                        </div>
                        <button type="submit" name="add_holiday" class="btn" style="width: 100%;">Save Holiday</button>
                    </form>
                </div>
                
                <div class="card">
                    <h2 class="card-title">Batch Import (CSV / PDF)</h2>
                    <form method="POST" action="" enctype="multipart/form-data">
                        <div class="form-group">
                            <label class="form-label">Upload File (CSV or PDF Table)</label>
                            <input type="file" name="memo_file" class="form-control" accept=".csv,.pdf" required>
                        </div>
                        <button type="submit" name="upload_file" class="btn" style="width: 100%; background: #475569;">Upload & Import File</button>
                        <div class="text-slate-500" style="margin-top: 10px; font-size: 12px;  text-align: center;">
                            CSV Format: <code>YYYY-MM-DD, Holiday Name</code>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <div class="card">
                <h2 class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
                    Holiday Calendar
                    <?php if($is_admin && mysqli_num_rows($holidays) > 0): ?>
                    <button type="submit" form="bulk-delete-form" name="delete_multiple_holidays" class="btn btn-delete" style="font-size:13px;" onclick="return confirm('Are you sure you want to delete all selected holidays?');">Delete Selected</button>
                    <?php endif; ?>
                </h2>
                <?php if (mysqli_num_rows($holidays) > 0): ?>
                    <?php if($is_admin): ?><form method="POST" action="" id="bulk-delete-form"><?php endif; ?>
                    <table>
                        <thead>
                            <tr>
                                <?php if($is_admin): ?>
                                <th style="width: 40px; text-align:center;">
                                    <input type="checkbox" onclick="document.querySelectorAll('.chk-holiday').forEach(cb => cb.checked = this.checked);" style="cursor:pointer;" title="Select All">
                                </th>
                                <?php endif; ?>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Holiday Name</th>
                                <?php if($is_admin): ?><th>Action</th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while($row = mysqli_fetch_assoc($holidays)): ?>
                            <tr>
                                <?php if($is_admin): ?>
                                <td style="text-align:center;">
                                    <input type="checkbox" name="del_ids[]" value="<?= $row['id'] ?>" class="chk-holiday" style="cursor:pointer;">
                                </td>
                                <?php endif; ?>
                                <td><strong><?= date('d M Y', strtotime($row['holiday_date'])) ?></strong></td>
                                <td><?= date('l', strtotime($row['holiday_date'])) ?></td>
                                <td><?= htmlspecialchars($row['holiday_name']) ?></td>
                                <?php if($is_admin): ?>
                                <td>
                                    <!-- Keep the single delete button but prevent form nesting issues by making it outside the bulk form conceptually, 
                                         but HTML doesn't allow nested forms. So we use a button with form="" attribute -->
                                    <button type="submit" name="delete_holiday" class="btn btn-delete" form="delete-form-<?= $row['id'] ?>">Delete</button>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php if($is_admin): ?></form><?php endif; ?>

                    <?php if($is_admin): ?>
                        <?php 
                        mysqli_data_seek($holidays, 0);
                        while($row = mysqli_fetch_assoc($holidays)): ?>
                        <form method="POST" action="" id="delete-form-<?= $row['id'] ?>" onsubmit="return confirm('Delete this holiday?');" style="display:none;">
                            <input type="hidden" name="del_id" value="<?= $row['id'] ?>">
                        </form>
                        <?php endwhile; ?>
                    <?php endif; ?>

                <?php else: ?>
                    <p class="text-slate-500" style=" font-size: 14px;">No public holidays set up yet.</p>
                <?php endif; ?>
            </div>
            </div>
        </div>
    </div>
</body>
</html>
