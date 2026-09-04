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

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["import_csv"])) {
    if ($_FILES["import_csv"]["size"] > 0) {
        if (($handle = fopen($_FILES["import_csv"]["tmp_name"], "r")) !== FALSE) {
            $row = 0;
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $row++;
                // Skip first row (header)
                if ($row <= 1) continue; 

                $date_str = isset($data[0]) ? trim($data[0]) : '';
                $welabel_id = isset($data[1]) ? mysqli_real_escape_string($conn, trim($data[1])) : '';
                $raw_name = isset($data[2]) ? trim($data[2]) : '';
                
                // If Full Name is empty, try to extract it from the WeLabel ID
                if (empty($raw_name) && !empty($welabel_id)) {
                    $extracted = preg_replace('/^KL_/i', '', explode('@', $welabel_id)[0]);
                    $raw_name = ucwords(str_replace('_', ' ', $extracted));
                }
                
                if (empty($raw_name)) continue;
                
                // Detect and extract role from name suffix (e.g., " - QA", " - TL", " - HR", " - Admin")
                $role_val = 'employee';
                if (preg_match('/^(.*?)\s*-\s*(QA|TL|Team Lead|HR|HR - Human Resources|Admin)$/i', $raw_name, $matches)) {
                    $raw_name = trim($matches[1]);
                    $detected_role = strtolower($matches[2]);
                    if ($detected_role === 'qa') $role_val = 'qa';
                    elseif ($detected_role === 'tl' || $detected_role === 'team lead') $role_val = 'tl';
                    elseif ($detected_role === 'hr' || $detected_role === 'hr - human resources') $role_val = 'hr';
                    elseif ($detected_role === 'admin') $role_val = 'admin';
                }

                $name = mysqli_real_escape_string($conn, $raw_name);
                
                // Check if employee already exists by name or welabel_id
                $check_query = "SELECT id FROM employees WHERE name = '$name'";
                if (!empty($welabel_id)) {
                    $check_query .= " OR welabel_id = '$welabel_id'";
                }
                $check_dup = mysqli_query($conn, $check_query);
                if (mysqli_num_rows($check_dup) > 0) continue;

                // Read Employment Type from Column D
                $employment_type = (isset($data[3]) && !empty(trim($data[3]))) ? trim($data[3]) : 'Full time';
                
                // If the old CSV format had QA in the employment type column instead of name
                if (stripos($employment_type, 'QA') !== false) {
                    $role_val = 'qa';
                    $employment_type = 'Full time';
                }
                
                // Read System Role from Column E if available (overrides previous detections)
                if (isset($data[4]) && !empty(trim($data[4]))) {
                    $csv_role = strtolower(trim($data[4]));
                    if ($csv_role === 'qa') $role_val = 'qa';
                    elseif ($csv_role === 'tl' || $csv_role === 'team lead' || $csv_role === 'team-lead') $role_val = 'tl';
                    elseif ($csv_role === 'hr' || $csv_role === 'hr - human resources') $role_val = 'hr';
                    elseif ($csv_role === 'admin') $role_val = 'admin';
                    elseif ($csv_role === 'annotator' || $csv_role === 'employee') $role_val = 'employee';
                }
                
                $employment_type = mysqli_real_escape_string($conn, $employment_type);

                // Robust Date Parsing
                $timestamp_valid = false;
                $yy = date('y');
                $mm = date('m');
                if (!empty($date_str)) {
                    if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $date_str, $matches)) {
                        $mm = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                        $yy = substr($matches[3], -2);
                        $timestamp_valid = true;
                    } elseif (is_numeric($date_str)) {
                        $ts = $date_str > 10000000000 ? $date_str / 1000 : ($date_str - 25569) * 86400;
                        $yy = date('y', $ts);
                        $mm = date('m', $ts);
                        $timestamp_valid = true;
                    } else {
                        $ts = strtotime($date_str);
                        if ($ts) {
                            $yy = date('y', $ts);
                            $mm = date('m', $ts);
                            $timestamp_valid = true;
                        }
                    }
                }
                
                $type_prefix = "FT";
                if (stripos($employment_type, 'Intern') !== false) {
                    $type_prefix = "IN";
                }
                $prefix = "{$type_prefix}-$yy$mm-";
                
                $query = mysqli_query($conn, "SELECT emp_id FROM employees WHERE emp_id LIKE '$prefix%' ORDER BY emp_id DESC LIMIT 1");
                $new_num = 1;
                if (mysqli_num_rows($query) > 0) {
                    $db_row = mysqli_fetch_assoc($query);
                    $new_num = ((int) substr($db_row['emp_id'], -3)) + 1;
                }
                $emp_id = $prefix . str_pad($new_num, 3, '0', STR_PAD_LEFT);
                
                // Fixed password
                $generated_pwd = 'password123';
                $hashed_password = password_hash($generated_pwd, PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO employees (emp_id, welabel_id, name, password, employment_type, role, lunch_window) 
                        VALUES ('$emp_id', '$welabel_id', '$name', '$hashed_password', '$employment_type', '$role_val', '12:00-13:00')";
                
                if (mysqli_query($conn, $sql)) {
                    $imported_accounts[] = [
                        'name' => $name,
                        'emp_id' => $emp_id,
                        'welabel_id' => $welabel_id,
                        'password' => $generated_pwd
                    ];
                }
            }
        }
        fclose($handle);
    } else {
        $error_message = "Invalid or empty CSV file.";
    }
} elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'lark_sync') {
    // Direct Lark Sync using API
    $app_id = 'cli_aa95703992789e17';
    $app_secret = 'PiMbbC9Qsq3THtEXkd1BWL2pSWCjeZTv';
    $spreadsheet_token = 'PQEMs7oErhqLrntaeA4jR8Tfpnf';

    // 1. Get Token
    $ch = curl_init('https://open.larksuite.com/open-apis/auth/v3/tenant_access_token/internal');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['app_id' => $app_id, 'app_secret' => $app_secret]));
    $token_data = json_decode(curl_exec($ch), true);

    if (isset($token_data['tenant_access_token'])) {
        $token = $token_data['tenant_access_token'];

        // Determine if it's a wiki link or a spreadsheet link
        $lark_url = $_POST['lark_url'] ?? '';
        $spreadsheet_token = '';
        
        if (strpos($lark_url, '/wiki/') !== false) {
            // Extract wiki token
            $parts = explode('/wiki/', $lark_url);
            $wiki_token = explode('?', $parts[1])[0];
            
            // Resolve Wiki Node
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
            // 2. Get Sheet ID
            $ch = curl_init("https://open.larksuite.com/open-apis/sheets/v2/spreadsheets/$spreadsheet_token/metainfo");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json; charset=utf-8"]);
            $meta_data = json_decode(curl_exec($ch), true);

            if (isset($meta_data['data']['sheets'][0]['sheetId'])) {
                $sheet_id = $meta_data['data']['sheets'][0]['sheetId'];

                // 3. Get Values (Assuming Date in Col A, Welabel ID in B, Name in C, Type in D, Role in E)
                $ch = curl_init("https://open.larksuite.com/open-apis/sheets/v2/spreadsheets/$spreadsheet_token/values/$sheet_id!A2:E100");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token", "Content-Type: application/json; charset=utf-8"]);
                $values_response = json_decode(curl_exec($ch), true);

                if (isset($values_response['data']['valueRange']['values'])) {
                    $rows = $values_response['data']['valueRange']['values'];
                    $added_count = 0;
                    foreach ($rows as $data) {
                        $get_val = function($cell) {
                            if (is_array($cell)) {
                                if (isset($cell[0]['text'])) return $cell[0]['text'];
                                if (isset($cell['text'])) return $cell['text'];
                                // Some links in Lark might be returned in a different array format, try to extract first string
                                foreach($cell as $item) {
                                    if (is_string($item)) return $item;
                                    if (is_array($item) && isset($item['text'])) return $item['text'];
                                }
                                return json_encode($cell);
                            }
                            return (string)$cell;
                        };
                        
                        $date_str = isset($data[0]) && !is_null($data[0]) ? trim($get_val($data[0])) : '';
                        $welabel_id = isset($data[1]) && !is_null($data[1]) ? mysqli_real_escape_string($conn, trim($get_val($data[1]))) : '';
                        $raw_name = isset($data[2]) && !is_null($data[2]) ? trim($get_val($data[2])) : '';
                        
                        // If Full Name is empty, try to extract it from the WeLabel ID
                        if (empty($raw_name) && !empty($welabel_id)) {
                            $extracted = preg_replace('/^KL_/i', '', explode('@', $welabel_id)[0]);
                            $raw_name = ucwords(str_replace('_', ' ', $extracted));
                        }
                        
                        if (empty($raw_name)) continue;
                        
                        $name = mysqli_real_escape_string($conn, trim($raw_name));
                        
                        // Check if employee already exists by name or welabel_id
                        $check_query = "SELECT id FROM employees WHERE name = '$name'";
                        if (!empty($welabel_id)) {
                            $check_query .= " OR welabel_id = '$welabel_id'";
                        }
                        $check_dup = mysqli_query($conn, $check_query);
                        if (mysqli_num_rows($check_dup) > 0) continue;

                        // Read Employment Type from Column D
                        $employment_type = (isset($data[3]) && !is_null($data[3]) && !empty(trim($data[3]))) ? mysqli_real_escape_string($conn, trim($data[3])) : 'Full time';
                        
                        $role_val = 'employee';
                        // System Role from Column E
                        if (isset($data[4]) && !is_null($data[4]) && !empty(trim($data[4]))) {
                            $csv_role = strtolower(trim($data[4]));
                            if ($csv_role === 'qa') $role_val = 'qa';
                            elseif ($csv_role === 'tl' || $csv_role === 'team lead' || $csv_role === 'team-lead') $role_val = 'tl';
                            elseif ($csv_role === 'hr' || $csv_role === 'hr - human resources') $role_val = 'hr';
                            elseif ($csv_role === 'admin') $role_val = 'admin';
                        }

                        if (stripos($employment_type, 'QA') !== false) {
                            $role_val = 'qa';
                            $employment_type = 'Full time';
                        }

                        // Robust Date Parsing
                        $timestamp_valid = false;
                        $yy = date('y');
                        $mm = date('m');
                        if (!empty($date_str)) {
                            // Try to extract directly using Regex for formats like "6/8/2026" or "06-08-2026"
                            if (preg_match('/(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})/', $date_str, $matches)) {
                                // Assuming m/d/Y based on the spreadsheet
                                $mm = str_pad($matches[1], 2, '0', STR_PAD_LEFT);
                                $yy = substr($matches[3], -2);
                                $timestamp_valid = true;
                            } elseif (is_numeric($date_str)) {
                                // Lark might return Unix ms or Excel serial
                                $ts = $date_str > 10000000000 ? $date_str / 1000 : ($date_str - 25569) * 86400;
                                $yy = date('y', $ts);
                                $mm = date('m', $ts);
                                $timestamp_valid = true;
                            } else {
                                $ts = strtotime($date_str);
                                if ($ts) {
                                    $yy = date('y', $ts);
                                    $mm = date('m', $ts);
                                    $timestamp_valid = true;
                                }
                            }
                        }
                        
                        $type_prefix = "FT";
                        if (stripos($employment_type, 'Intern') !== false) {
                            $type_prefix = "IN";
                        }
                        $prefix = "{$type_prefix}-$yy$mm-";
                        
                        $query = mysqli_query($conn, "SELECT emp_id FROM employees WHERE emp_id LIKE '$prefix%' ORDER BY emp_id DESC LIMIT 1");
                        $new_num = 1;
                        if (mysqli_num_rows($query) > 0) {
                            $row = mysqli_fetch_assoc($query);
                            $new_num = ((int) substr($row['emp_id'], -3)) + 1;
                        }
                        $emp_id = $prefix . str_pad($new_num, 3, '0', STR_PAD_LEFT);
                        
                        // Fixed password
                        $generated_pwd = 'password123';
                        $hashed_password = password_hash($generated_pwd, PASSWORD_DEFAULT);
                        
                        $sql = "INSERT INTO employees (emp_id, welabel_id, name, password, employment_type, role, lunch_window) 
                                VALUES ('$emp_id', '$welabel_id', '$name', '$hashed_password', '$employment_type', '$role_val', '12:00-13:00')";
                        
                        if (mysqli_query($conn, $sql)) {
                            $imported_accounts[] = ['name' => $name, 'emp_id' => $emp_id, 'welabel_id' => $welabel_id, 'password' => $generated_pwd];
                            $added_count++;
                        }
                    }
                    if ($added_count == 0) {
                        $error_message = "No new names found to import. (Check if the sheet is empty or names already exist).";
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
                            ℹ️ The system will use your configured credentials to pull the latest rows directly via the Lark Open API.
                        </div>
                        
                        <button type="submit" class="btn-sync">Sync Now</button>
                    </form>
                </div>

                <div class="upload-card">
                    <div class="upload-icon">📄</div>
                    <h3 style="margin-bottom: 10px; text-align: center;">Manual CSV Upload</h3>
                      <p class="text-slate-500" style=" font-size: 14px; margin-bottom: 25px; text-align: center;">Export your list from Lark as a CSV file.<br>Format: Date (Col A), ID & Name (Col B), Type (Col C), Role (Col D)</p>
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
                    ✅ <?php echo count($imported_accounts); ?> accounts successfully generated! Please distribute these credentials to the staff.
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

