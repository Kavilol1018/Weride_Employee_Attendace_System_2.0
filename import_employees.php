<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] === 'employee') {
    header("Location: index.php");
    exit();
}
include 'db_connect.php';

$role = $_SESSION['role'];
$imported_accounts = [];
$error_message = "";

// Helper to pre-fetch Group Leaders for dynamic assignment
$gl_map = [];
$gl_query = mysqli_query($conn, "SELECT id, emp_id, name, role FROM employees WHERE role IN ('gl', 'tl', 'hr', 'admin')");
while ($row = mysqli_fetch_assoc($gl_query)) {
    // Quick and dirty mapping for the known teams based on names
    if (stripos($row['name'], 'mageswary') !== false) $gl_map['Team Mages'] = $row['emp_id'];
    if (stripos($row['name'], 'salma') !== false) $gl_map['Team Salma'] = $row['emp_id'];
    if (stripos($row['name'], 'thananiy') !== false) $gl_map['Team Thana'] = $row['emp_id'];
    if (stripos($row['name'], 'shivanesha') !== false) $gl_map['Team Shiva'] = $row['emp_id'];
    
    // Also map their direct names and emp_ids in case the CSV uses those instead
    $gl_map[strtolower(trim($row['name']))] = $row['emp_id'];
    $gl_map[strtolower(trim($row['emp_id']))] = $row['emp_id'];
}

function process_employee_row(mysqli $conn, array $data_row, array &$imported_accounts, array $gl_map) {
    if (count($data_row) < 12) {
        // We only process if it has roughly the 12 columns, or at least Name and Welabel ID
        if (count($data_row) < 5) return false; 
    }
    
    $name = strtoupper(trim($data_row[0] ?? ''));
    $welabel_id = trim($data_row[1] ?? '');
    $assigned_gl = trim($data_row[2] ?? '');
    $onboarding_date_raw = trim($data_row[3] ?? '');
    $employment_type = trim($data_row[4] ?? 'Full time');
    if (stripos($employment_type, 'full') !== false) {
        $employment_type = 'Full time';
    } elseif (stripos($employment_type, 'intern') !== false) {
        $employment_type = 'Intern';
    }
    
    $is_intern = (stripos($employment_type, 'intern') !== false);
    $al_balance = $is_intern ? 0.0 : floatval($data_row[5] ?? 0);
    $sl_balance = $is_intern ? 0.0 : floatval($data_row[6] ?? 0);
    $shift_time = trim($data_row[7] ?? '');
    $lunch_time = trim($data_row[8] ?? ''); // Col 8
    $status = trim($data_row[9] ?? 'Active'); // Col 9
    $position = trim($data_row[11] ?? ''); // Col L
    
    if (empty($name)) return false;
    
    $name_safe = mysqli_real_escape_string($conn, $name);
    $welabel_safe = mysqli_real_escape_string($conn, $welabel_id);
    $dept_safe = mysqli_real_escape_string($conn, $assigned_gl);
    $type_safe = mysqli_real_escape_string($conn, $employment_type);
    $shift_safe = mysqli_real_escape_string($conn, $shift_time);
    $lunch_safe = mysqli_real_escape_string($conn, $lunch_time);
    $status_safe = mysqli_real_escape_string($conn, $status);
    
    // Group Leader ID Resolution
    $group_leader_id = '';
    $search_gl = strtolower($assigned_gl);
    if (isset($gl_map[$assigned_gl])) {
        $group_leader_id = $gl_map[$assigned_gl]; // Match "Team Mages" etc
    } elseif (isset($gl_map[$search_gl])) {
        $group_leader_id = $gl_map[$search_gl]; // Match direct name or emp_id
    }

    // Default new leaves based on employment type
    $unpaid_balance = 0.0;
    $hosp_balance = (stripos($employment_type, 'Intern') !== false) ? 0.0 : 60.0;
    $gl_safe = mysqli_real_escape_string($conn, $group_leader_id);

    // Determine Role from Position
    $role_val = 'employee';
    $pos_lower = strtolower($position);
    if (strpos($pos_lower, 'qa') !== false) $role_val = 'qa';
    if (strpos($pos_lower, 'group lead') !== false) $role_val = 'gl';
    if (strpos($pos_lower, 'team lead') !== false) $role_val = 'tl';
    if (strpos($pos_lower, 'hr') !== false) $role_val = 'hr';

    // Parse Onboarding Date
    $onboarding_date = null;
    $yy = date('y');
    $mm = date('m');
    if (!empty($onboarding_date_raw)) {
        if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $onboarding_date_raw, $matches)) {
            $mm = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            $yy = substr($matches[3], -2);
            $onboarding_date = "{$matches[3]}-{$mm}-" . str_pad($matches[2], 2, '0', STR_PAD_LEFT);
        } elseif (is_numeric($onboarding_date_raw)) {
            $ts = $onboarding_date_raw > 10000000000 ? $onboarding_date_raw / 1000 : ($onboarding_date_raw - 25569) * 86400;
            $yy = date('y', $ts);
            $mm = date('m', $ts);
            $onboarding_date = date('Y-m-d', $ts);
        } else {
            $ts = strtotime($onboarding_date_raw);
            if ($ts) {
                $yy = date('y', $ts);
                $mm = date('m', $ts);
                $onboarding_date = date('Y-m-d', $ts);
            }
        }
    }
    
    // Parse Shift Time (e.g. 8:30-17:30)
    $shift_start = null;
    $shift_end = null;
    if (!empty($shift_time) && strpos($shift_time, '-') !== false) {
        $parts = explode('-', $shift_time);
        if (count($parts) == 2) {
            $shift_start = date("H:i:s", strtotime(trim($parts[0])));
            $shift_end = date("H:i:s", strtotime(trim($parts[1])));
        }
    }
    
    // Check if employee already exists by name or welabel_id
    $check_query = "SELECT id, emp_id FROM employees WHERE name = '$name_safe'";
    if (!empty($welabel_id)) {
        $check_query .= " OR welabel_id = '$welabel_safe'";
    }
    $check_dup = mysqli_query($conn, $check_query);
    if (mysqli_num_rows($check_dup) > 0) {
        $row = mysqli_fetch_assoc($check_dup);
        $id = $row['id'];
        
        $update_q = "UPDATE employees SET 
            welabel_id='$welabel_safe',
            department='$dept_safe',
            employment_type='$type_safe',
            annual_leave_balance='$al_balance',
            sick_leave_balance='$sl_balance',
            shift_hour='$shift_safe',
            leave_status='$status_safe',
            role='$role_val',
            group_leader_id='$group_leader_id',
            lunch_window='$lunch_safe'";
            
        if ($onboarding_date) $update_q .= ", onboarding_date='$onboarding_date'";
        if ($shift_start) $update_q .= ", shift_start='$shift_start'";
        if ($shift_end) $update_q .= ", shift_end='$shift_end'";
            
        $update_q .= " WHERE id=$id";
        mysqli_query($conn, $update_q);
        
        $imported_accounts[] = [
            'name' => $name_safe,
            'emp_id' => $row['emp_id'],
            'welabel_id' => $welabel_id,
            'password' => 'Updated Existing'
        ];
        return true;
    }

    // Generate new emp_id
    $type_prefix = (stripos($employment_type, 'Intern') !== false) ? 'IN' : 'FT';
    $prefix = "{$type_prefix}-{$yy}{$mm}-";
    
    $query = mysqli_query($conn, "SELECT COUNT(*) as count FROM employees WHERE emp_id LIKE '$prefix%'");
    $db_row = mysqli_fetch_assoc($query);
    $new_num = $db_row['count'] + 1;
    
    $emp_id = $prefix . str_pad($new_num, 3, '0', STR_PAD_LEFT);
    while (mysqli_num_rows(mysqli_query($conn, "SELECT id FROM employees WHERE emp_id='$emp_id'")) > 0) {
        $new_num++;
        $emp_id = $prefix . str_pad($new_num, 3, '0', STR_PAD_LEFT);
    }
    
    // Fixed password
    $generated_pwd = 'welabel2026';
    $hashed_password = password_hash($generated_pwd, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO employees (emp_id, welabel_id, name, password, department, onboarding_date, employment_type, annual_leave_balance, sick_leave_balance, unpaid_leave_balance, hospitalization_leave_balance, shift_hour, shift_start, shift_end, lunch_window, leave_status, role, group_leader_id) 
            VALUES ('$emp_id', '$welabel_safe', '$name_safe', '$hashed_password', '$dept_safe', " . ($onboarding_date ? "'$onboarding_date'" : "NULL") . ", '$type_safe', '$al_balance', '$sl_balance', '$unpaid_balance', '$hosp_balance', '$shift_safe', " . ($shift_start ? "'$shift_start'" : "NULL") . ", " . ($shift_end ? "'$shift_end'" : "NULL") . ", '$lunch_safe', '$status_safe', '$role_val', '$group_leader_id')";
    
    if (mysqli_query($conn, $sql)) {
        $imported_accounts[] = [
            'name' => $name_safe,
            'emp_id' => $emp_id,
            'welabel_id' => $welabel_id,
            'password' => $generated_pwd
        ];
        return true;
    }
    return false;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["import_csv"])) {
    if ($_FILES["import_csv"]["size"] > 0) {
        if (($handle = fopen($_FILES["import_csv"]["tmp_name"], "r")) !== FALSE) {
            $row_count = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row_count++;
                if ($row_count <= 1) continue; // Skip header
                process_employee_row($conn, $data, $imported_accounts, $gl_map);
            }
            fclose($handle);
        }
    } else {
        $error_message = "Invalid or empty CSV file.";
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'lark_sync') {
    // Direct Lark Sync using API
    $app_id = getenv('LARK_APP_ID') ?: 'cli_aa95703992789e17';
    $app_secret = getenv('LARK_APP_SECRET') ?: 'YOUR_LARK_APP_SECRET';
    $spreadsheet_token = 'PQEMs7oErhqLrntaeA4jR8Tfpnf';

    $ch = curl_init('https://open.larksuite.com/open-apis/auth/v3/tenant_access_token/internal');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['app_id' => $app_id, 'app_secret' => $app_secret]));
    $token_data = json_decode(curl_exec($ch), true);

    if (isset($token_data['tenant_access_token'])) {
        $token = $token_data['tenant_access_token'];

        $lark_url = $_POST['lark_url'] ?? '';
        $spreadsheet_token = '';
        
        if (strpos($lark_url, '/wiki/') !== false) {
            $parts = explode('/wiki/', $lark_url);
            $wiki_token = explode('?', $parts[1])[0];
            
            $ch = curl_init("https://open.larksuite.com/open-apis/wiki/v2/spaces/get_node?token=$wiki_token");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json; charset=utf-8"]);
            $wiki_data = json_decode(curl_exec($ch), true);
            
            if (isset($wiki_data['data']['node']['obj_token'])) {
                $spreadsheet_token = $wiki_data['data']['node']['obj_token'];
            } else {
                $error_message = "Failed to resolve Wiki link. Check app permissions (wiki:wiki:readonly).";
            }
        } elseif (strpos($lark_url, '/sheets/') !== false) {
            $parts = explode('/sheets/', $lark_url);
            $spreadsheet_token = explode('?', $parts[1])[0];
        } else {
            $error_message = "Invalid Lark URL format.";
        }

        if ($spreadsheet_token) {
            $ch = curl_init("https://open.larksuite.com/open-apis/sheets/v2/spreadsheets/$spreadsheet_token/metainfo");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json; charset=utf-8"]);
            $meta_data = json_decode(curl_exec($ch), true);

            if (isset($meta_data['data']['sheets'][0]['sheetId'])) {
                $sheet_id = $meta_data['data']['sheets'][0]['sheetId'];

                // Get Values A to L (12 columns)
                $ch = curl_init("https://open.larksuite.com/open-apis/sheets/v2/spreadsheets/$spreadsheet_token/values/$sheet_id!A2:L200");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json; charset=utf-8"]);
                $values_response = json_decode(curl_exec($ch), true);

                if (isset($values_response['data']['valueRange']['values'])) {
                    $rows = $values_response['data']['valueRange']['values'];
                    
                    foreach ($rows as $data_row) {
                        $normalized_row = [];
                        foreach ($data_row as $cell) {
                            if (is_array($cell)) {
                                if (isset($cell[0]['text'])) $normalized_row[] = $cell[0]['text'];
                                elseif (isset($cell['text'])) $normalized_row[] = $cell['text'];
                                else {
                                    $found = false;
                                    foreach($cell as $item) {
                                        if (is_string($item)) { $normalized_row[] = $item; $found = true; break; }
                                        if (is_array($item) && isset($item['text'])) { $normalized_row[] = $item['text']; $found = true; break; }
                                    }
                                    if (!$found) $normalized_row[] = '';
                                }
                            } else {
                                $normalized_row[] = (string)$cell;
                            }
                        }
                        
                        // Fill up to 12 columns if short
                        $normalized_row = array_pad($normalized_row, 12, '');
                        
                        process_employee_row($conn, $normalized_row, $imported_accounts, $gl_map);
                    }
                    
                    if (count($imported_accounts) == 0) {
                        $error_message = "No names processed from Lark. Ensure format matches the 12-column layout.";
                    }
                } else {
                    $error_message = "Failed to fetch values. Response: " . json_encode($values_response);
                }
            } else {
                $error_message = "Failed to read sheet metadata. Check app permissions (sheets:spreadsheet:readonly).";
            }
        }
    } else {
        $error_message = "Failed to authenticate with Lark API. Check App ID and Secret.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Import Employees</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #eef2f6; color: #334155; }

        .sidebar { width: 260px; background-color: #fff; display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(0,0,0,0.02); z-index: 10; }
        .sidebar-header { padding: 30px 24px; font-size: 20px; font-weight: 800; color: #0f172a; text-transform: capitalize; }
        .nav-menu { list-style: none; padding: 10px 0; }
        .nav-item { padding: 14px 24px; cursor: pointer; color: #64748b; font-weight: 600; margin: 4px 16px; border-radius: 12px; transition: 0.2s; text-decoration: none; display: block; }
        .nav-item:hover { background-color: #f1f5f9; }
        .nav-item.active { background-color: #eff6ff; color: #3b82f6; }
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; } 
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .content { padding: 0 40px 40px 40px; }

        .upload-card { background: #ffffff; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.03); margin-bottom: 20px; border: 2px dashed #cbd5e1; flex: 1; }
        .sync-card { background: #ffffff; padding: 40px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.03); margin-bottom: 20px; border: 2px solid #e2e8f0; flex: 1; }
        .upload-icon { font-size: 48px; margin-bottom: 20px; text-align: center; }
        
        .btn-upload { background: #3b82f6; color: white; padding: 12px 24px; border-radius: 12px; font-weight: 700; border: none; cursor: pointer; font-size: 16px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); width: 100%; margin-top: 10px;}
        .btn-upload:hover { background: #2563eb; transform: translateY(-2px); }
        
        .btn-sync { background: #1e293b; color: white; padding: 12px 24px; border-radius: 12px; font-weight: 700; border: none; cursor: pointer; font-size: 16px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); width: 100%; margin-top: 10px; }
        .btn-sync:hover { background: #0f172a; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15); }

        .form-input { width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 8px; margin-bottom: 15px; outline: none; }
        .form-input:focus { border-color: #3b82f6; }

        .result-card { background: #ffffff; padding: 30px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.03); margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { padding: 12px; border-bottom: 2px solid #e2e8f0; color: #64748b; }
        td { padding: 12px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #0f172a; }
        .pwd-cell { font-family: monospace; background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 6px; letter-spacing: 1px; }

        .import-grid { display: flex; gap: 20px; max-width: 1000px; margin: 0 auto; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        @media (max-width: 768px) {
            .import-grid { flex-direction: column; }
            .sidebar { position: fixed; height: 100vh; transform: translateX(-100%); transition: transform 0.3s ease; }
            .sidebar.open { transform: translateX(0); }
            .topbar { flex-direction: column; align-items: flex-start; gap: 10px; padding: 15px 20px; }
            .content { padding: 0 20px 20px 20px; }
        }
    </style>
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>

        <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    </head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <div class="page-title">Bulk Import via CSV</div>
        </header>

        <div class="content">
            <?php if ($error_message): ?>
                <div class="bg-red-50 text-red-500" style="  padding: 16px; border-radius: 12px; font-weight: 600; margin-bottom: 24px; border: 1px solid #fecaca;">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="import-grid">
                <div class="sync-card">
                    <div class="upload-icon">🔗</div>
                    <h3 style="margin-bottom: 10px; text-align: center;">Direct Lark Sync</h3>
                    <p class="text-slate-500" style=" font-size: 14px; margin-bottom: 25px; text-align: center;">Automatically pull new employees directly from your Lark Excel sheet.</p>
                    
                    <form action="" method="post">
                        <input type="hidden" name="action" value="lark_sync">
                        <label class="text-slate-600" style="font-size: 13px; font-weight: 600;  margin-bottom: 5px; display: block;">Lark Wiki or Sheet URL</label>
                        <input type="text" name="lark_url" class="form-input" value="https://cjpxtnsrasp0.jp.larksuite.com/wiki/AtdpwjybCid6Y9kE4XwjTncop1b" required style="background: #f8fafc; color: #334155;">
                        
                        <div class="bg-blue-50" style=" padding: 12px; border-radius: 8px; font-size: 13px; color: #1e40af; margin-bottom: 20px; border: 1px solid #bfdbfe;">
                            ℹ️ The system will use your configured credentials to pull the latest 12-column rows directly via the Lark API.
                        </div>
                        
                        <button type="submit" class="btn-sync">Sync Now</button>
                    </form>
                </div>

                <div class="upload-card">
                    <div class="upload-icon">📄</div>
                    <h3 style="margin-bottom: 10px; text-align: center;">Manual CSV Upload</h3>
                      <p class="text-slate-500" style=" font-size: 14px; margin-bottom: 25px; text-align: center;">Export your 12-column list from Lark as a CSV file.<br>Format: Emp Name (Col A), Welabel ID (Col B), Team (Col C), Date (Col D)... Position (Col L)</p>
                      <form action="" method="post" enctype="multipart/form-data">
                          <input type="file" name="import_csv" accept=".csv" required style="margin-bottom: 20px; display: block; margin: 0 auto 20px auto;">
                          <button type="submit" class="btn-upload">Import CSV File</button>
                      </form>
                      <div style="text-align: center; margin-top: 15px;">
                          <a href="template.csv" download style="color: #3b82f6; font-size: 14px; text-decoration: underline; font-weight: 600;">Download CSV Template</a>
                      </div>
                  </div>
            </div>

            <?php if (!empty($imported_accounts)): ?>
            <div class="result-card">
                <div class="bg-emerald-50 text-emerald-600" style="  padding: 16px; border-radius: 12px; font-weight: 700; margin-bottom: 20px;">
                    ✅ <?php echo count($imported_accounts); ?> accounts successfully imported/updated! Please distribute these credentials to the staff.
                </div>
                <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Welabel ID</th>
                            <th>Name</th>
                            <th>Temporary Password</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($imported_accounts as $acc): ?>
                        <tr>
                            <td style="color: #3b82f6; font-weight: 700;">#<?php echo htmlspecialchars($acc['emp_id']); ?></td>
                            <td class="text-emerald-600" style=" font-weight: 600;"><?php echo htmlspecialchars($acc['welabel_id'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($acc['name']); ?></td>
                            <td><span class="pwd-cell"><?php echo htmlspecialchars($acc['password']); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
