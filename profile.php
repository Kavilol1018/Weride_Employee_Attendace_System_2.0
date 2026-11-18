<?php
include_once __DIR__ . '/db_connect.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$current_employee_id = $_SESSION['emp_id'];
$user_id = $_SESSION['user_id'];
$status_message = "";
$error_message = "";

// Friday Rule: shift/lunch change requests only allowed on Fridays
$is_friday = (date('N') == 5); // 5 = Friday

// Fetch user data
$sql = "SELECT * FROM employees WHERE id = '$user_id'";
$result = mysqli_query($conn, $sql);
$user_data = mysqli_fetch_assoc($result);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['change_password'])) {
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (password_verify($old_password, $user_data['password'])) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_sql = "UPDATE employees SET password = '$hashed_password' WHERE id = '$user_id'";
                if (mysqli_query($conn, $update_sql)) {
                    session_destroy();
                    header("Location: index.php?msg=" . urlencode("Password changed successfully. Please log in again."));
                    exit();
                } else {
                    $error_message = "Database error. Please try again later.";
                }
            } else {
                $error_message = "New password and confirm password do not match.";
            }
        } else {
            $error_message = "Incorrect old password.";
        }
    } elseif (isset($_POST['remove_profile_picture'])) {
        $p = $user_data['profile_picture'];
        if (!empty($p)) {
            if (file_exists($p)) {
                @unlink($p);
            }
            if (file_exists(__DIR__ . '/' . $p)) {
                @unlink(__DIR__ . '/' . $p);
            }
        }
        mysqli_query($conn, "UPDATE employees SET profile_picture = NULL WHERE id = '$user_id'");
        $user_data['profile_picture'] = null;
        $_SESSION['profile_picture'] = null;
        $status_message = "Profile picture removed successfully.";
    } elseif (isset($_POST['update_profile'])) {
        $new_name = mysqli_real_escape_string($conn, $_POST['new_name']);
        $old_emp_id = $user_data['emp_id'];
        
        $emp_id_sql_part = "";
        // Only HR/Admin/TL can change employment type
        if (!in_array($user_data['role'], ['employee', 'qa', 'gl']) && !empty($_POST['emp_id'])) {
            $employment_type = !empty($_POST['employment_type']) ? mysqli_real_escape_string($conn, trim($_POST['employment_type'])) : '';
            $requested_emp_id = mysqli_real_escape_string($conn, $_POST['emp_id']);
            if ($requested_emp_id !== $old_emp_id) {
                $check_dup = mysqli_query($conn, "SELECT id FROM employees WHERE emp_id = '$requested_emp_id'");
                if (mysqli_num_rows($check_dup) > 0) {
                    $error_message = "This Employee ID is already in use.";
                } else {
                    $new_emp_id = $requested_emp_id;
                    $emp_id_sql_part = ", emp_id = '$new_emp_id'";
                }
            }
        }
        
        // Handle Profile Picture Upload / Cropping
        $pic_sql_part = "";
        
        // Check for upload error if a file was submitted
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            $upload_err = $_FILES['profile_picture']['error'];
            if ($upload_err === UPLOAD_ERR_INI_SIZE || $upload_err === UPLOAD_ERR_FORM_SIZE) {
                $error_message = "File exceeds the maximum upload limit (10MB). Please select a smaller file or GIF.";
            } else {
                $error_message = "File upload failed with error code: " . $upload_err;
            }
        }
        
        // Priority 1: Cropped image submitted from interactive framing tool (Base64)
        if (empty($error_message) && !empty($_POST['cropped_profile_image'])) {
            $cropped_data = $_POST['cropped_profile_image'];
            if (preg_match('/^data:image\/(jpeg|jpg|png|webp|gif);base64,/', $cropped_data, $match)) {
                $img_format = $match[1];
                $base64_data = substr($cropped_data, strpos($cropped_data, ',') + 1);
                $decoded_data = base64_decode($base64_data);
                
                if ($decoded_data !== false && strlen($decoded_data) > 0 && strlen($decoded_data) <= 10 * 1024 * 1024) {
                    $upload_dir = 'uploads/profile_pictures/';
                    if (!is_dir($upload_dir)) {
                        @mkdir($upload_dir, 0777, true);
                    }
                    $safe_clean_emp_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $user_data['emp_id']);
                    $ext = ($img_format === 'jpeg') ? 'jpg' : $img_format;
                    $new_filename = 'emp_' . $safe_clean_emp_id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $target_file = $upload_dir . $new_filename;
                    
                    if (file_put_contents($target_file, $decoded_data)) {
                        if (!empty($user_data['profile_picture'])) {
                            if (file_exists($user_data['profile_picture'])) { @unlink($user_data['profile_picture']); }
                            if (file_exists(__DIR__ . '/' . $user_data['profile_picture'])) { @unlink(__DIR__ . '/' . $user_data['profile_picture']); }
                        }
                        $pic_sql_part = ", profile_picture = '" . mysqli_real_escape_string($conn, $target_file) . "'";
                        $user_data['profile_picture'] = $target_file;
                        $_SESSION['profile_picture'] = $target_file;
                    } else {
                        $error_message = "Failed to save cropped picture. Please check directory permissions.";
                    }
                } else {
                    $error_message = "Invalid cropped picture data or size limit exceeded (max 10MB).";
                }
            } else {
                $error_message = "Invalid cropped picture format.";
            }
        }
        // Priority 2: Direct file upload fallback (used for raw file upload)
        elseif (empty($error_message) && isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['profile_picture']['tmp_name'];
            $file_size = $_FILES['profile_picture']['size'];
            $file_name = $_FILES['profile_picture']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            if (!in_array($file_ext, $allowed_exts)) {
                $error_message = "Invalid image format. Allowed formats: JPG, PNG, WEBP, GIF.";
            } elseif ($file_size > 10 * 1024 * 1024) {
                $error_message = "Image size exceeds 10MB limit.";
            } else {
                $check_img = @getimagesize($file_tmp);
                if ($check_img === false) {
                    $error_message = "Uploaded file is not a valid image.";
                } else {
                    $upload_dir = 'uploads/profile_pictures/';
                    if (!is_dir($upload_dir)) {
                        @mkdir($upload_dir, 0777, true);
                    }
                    $safe_clean_emp_id = preg_replace('/[^a-zA-Z0-9_-]/', '', $user_data['emp_id']);
                    $new_filename = 'emp_' . $safe_clean_emp_id . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $file_ext;
                    $target_file = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $target_file)) {
                        // Server-side animated GIF crop if Imagick is available and crop coords provided
                        if (class_exists('Imagick') && $file_ext === 'gif' && !empty($_POST['gif_crop_data'])) {
                            $crop_info = json_decode($_POST['gif_crop_data'], true);
                            if ($crop_info && isset($crop_info['w'], $crop_info['h'])) {
                                try {
                                    $imagick = new Imagick($target_file);
                                    $imagick = $imagick->coalesceImages();
                                    $cw = max(10, (int)$crop_info['w']);
                                    $ch = max(10, (int)$crop_info['h']);
                                    $cx = max(0, (int)$crop_info['x']);
                                    $cy = max(0, (int)$crop_info['y']);
                                    $origW = $imagick->getImageWidth();
                                    $origH = $imagick->getImageHeight();
                                    if ($cx + $cw > $origW) $cw = max(10, $origW - $cx);
                                    if ($cy + $ch > $origH) $ch = max(10, $origH - $cy);
                                    
                                    $out_dim = 240;
                                    foreach ($imagick as $frame) {
                                        $frame->cropImage($cw, $ch, $cx, $cy);
                                        $frame->thumbnailImage($out_dim, $out_dim, true);
                                        $frame->setImagePage($out_dim, $out_dim, 0, 0);
                                    }
                                    $imagick = $imagick->deconstructImages();
                                    $imagick->writeImages($target_file, true);
                                    $imagick->clear();
                                    $imagick->destroy();
                                } catch (Exception $e) {
                                    error_log("Imagick GIF crop error: " . $e->getMessage());
                                }
                            }
                        }

                        if (!empty($user_data['profile_picture'])) {
                            if (file_exists($user_data['profile_picture'])) { @unlink($user_data['profile_picture']); }
                            if (file_exists(__DIR__ . '/' . $user_data['profile_picture'])) { @unlink(__DIR__ . '/' . $user_data['profile_picture']); }
                        }
                        $pic_sql_part = ", profile_picture = '" . mysqli_real_escape_string($conn, $target_file) . "'";
                        $user_data['profile_picture'] = $target_file;
                        $_SESSION['profile_picture'] = $target_file;
                    } else {
                        $error_message = "Failed to save uploaded picture. Please check directory permissions.";
                    }
                }
            }
        }
        
        if (empty($error_message)) {
            $update_sql = "UPDATE employees SET name = '$new_name' $emp_id_sql_part $pic_sql_part WHERE id = '$user_id'";
            if (mysqli_query($conn, $update_sql)) {
                $status_message = "Profile updated successfully!";
                $user_data['name'] = $new_name;
                $_SESSION['name'] = $new_name;
                
                if (isset($new_emp_id) && $new_emp_id !== $old_emp_id) {
                    mysqli_query($conn, "UPDATE lunch_breaks SET employee_id = '$new_emp_id' WHERE employee_id = '$old_emp_id'");
                    mysqli_query($conn, "UPDATE break_change_requests SET emp_id = '$new_emp_id' WHERE emp_id = '$old_emp_id'");
                    mysqli_query($conn, "UPDATE break_override_requests SET emp_id = '$new_emp_id' WHERE emp_id = '$old_emp_id'");
                    
                    $user_data['emp_id'] = $new_emp_id;
                    $_SESSION['emp_id'] = $new_emp_id;
                    $current_employee_id = $new_emp_id;
                }
            } else {
                $error_message = "Database error. Please try again.";
            }
        }
    } elseif (isset($_POST['request_break_change'])) {
        if (!$is_friday) {
            $error_message = "Lunch change requests can only be submitted on Fridays.";
        } else {
        $requested_window = mysqli_real_escape_string($conn, $_POST['requested_window']);
        $current_window = $user_data['lunch_window'];
        
        $check_sql = "SELECT id FROM break_change_requests WHERE emp_id = '{$user_data['emp_id']}' AND status = 'pending'";
        $check_res = mysqli_query($conn, $check_sql);
        
        $cooldown_sql = "SELECT created_at FROM break_change_requests WHERE emp_id = '{$user_data['emp_id']}' AND status = 'approved' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY created_at DESC LIMIT 1";
        $cooldown_res = mysqli_query($conn, $cooldown_sql);

        if (mysqli_num_rows($check_res) > 0) {
            $error_message = "You already have a pending break change request.";
        } elseif (mysqli_num_rows($cooldown_res) > 0) {
            $row = mysqli_fetch_assoc($cooldown_res);
            $last_request_time = strtotime($row['created_at']);
            $next_allowed_time = $last_request_time + (7 * 24 * 60 * 60);
            $error_message = "You can only change your lunchtime once a week. You can request again on " . date('M d, Y', $next_allowed_time) . ".";
        } else {
            $insert_sql = "INSERT INTO break_change_requests (emp_id, current_window, requested_window) VALUES ('{$user_data['emp_id']}', '$current_window', '$requested_window')";
            if (mysqli_query($conn, $insert_sql)) {
                $status_message = "Lunch break change requested successfully. Pending approval.";
            } else {
                $error_message = "Database error. Please try again.";
            }
        }
        }
    } elseif (isset($_POST['request_shift_change'])) {
        if (!$is_friday) {
            $error_message = "Shift change requests can only be submitted on Fridays.";
        } else {
        $requested_shift = mysqli_real_escape_string($conn, $_POST['requested_shift']);
        $current_shift = $user_data['shift_hour'] ?? '09:00-18:00';
        
        $check_sql = "SELECT id FROM shift_change_requests WHERE emp_id = '{$user_data['emp_id']}' AND status = 'pending'";
        $check_res = mysqli_query($conn, $check_sql);
        
        if (mysqli_num_rows($check_res) > 0) {
            $error_message = "You already have a pending shift change request.";
        } else {
            $insert_sql = "INSERT INTO shift_change_requests (emp_id, current_shift, requested_shift) VALUES ('{$user_data['emp_id']}', '$current_shift', '$requested_shift')";
            if (mysqli_query($conn, $insert_sql)) {
                $emp_name_val = mysqli_real_escape_string($conn, $user_data['name'] ?? '');
                $emp_id_val = mysqli_real_escape_string($conn, $user_data['emp_id']);
                $alert_msg = mysqli_real_escape_string($conn, "$emp_name_val ($emp_id_val) requested a shift change from $current_shift to $requested_shift.");
                mysqli_query($conn, "INSERT INTO system_alerts (message) VALUES ('$alert_msg')");
                
                $status_message = "Shift hour change requested successfully. Pending approval.";
            } else {
                $error_message = "Database error. Please try again.";
            }
        }
        } // end else (is_friday)
    } // end elseif request_shift_change
} // end if POST

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WeRide - Profile & Settings</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { display: flex; height: 100vh; background-color: #eef2f6; color: #334155; }

        .sidebar { width: 260px; background-color: #fff; display: flex; flex-direction: column; box-shadow: 4px 0 24px rgba(0,0,0,0.02); z-index: 10; }
        .sidebar-header { padding: 30px 24px; font-size: 20px; font-weight: 800; color: #0f172a; }
        .nav-menu { list-style: none; padding: 10px 0; }
        .nav-item { padding: 14px 24px; cursor: pointer; color: #64748b; font-weight: 600; margin: 4px 16px; border-radius: 12px; transition: 0.2s; text-decoration: none; display: block; }
        .nav-item:hover { background-color: #f1f5f9; }
        .nav-item.active { background-color: #eff6ff; color: #3b82f6; }
        
        .main-content { flex: 1; display: flex; flex-direction: column; overflow-y: auto; }
        .topbar { padding: 25px 40px; display: flex; justify-content: space-between; align-items: center; }
        .page-title { font-size: 26px; font-weight: 700; color: #0f172a; }
        .content { padding: 0 40px 40px 40px; }

        .profile-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px; align-items: start; max-width: 1400px; }
        .profile-card { background: #ffffff; padding: 30px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.03); border: 1px solid rgba(226,232,240,0.5); width: 100%; }
        .card-title { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #e2e8f0; }
        
        .info-row { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .info-label { color: #64748b; font-weight: 600; font-size: 14px; }
        .info-value { color: #0f172a; font-weight: 700; font-size: 15px; }

        .form-group { margin-bottom: 20px; }
        .form-label { font-size: 13px; font-weight: 600; color: #64748b; display: block; margin-bottom: 8px; }
        .form-input { width: 100%; padding: 14px; border: 1px solid #cbd5e1; border-radius: 12px; font-size: 15px; outline: none; transition: border-color 0.2s, box-shadow 0.2s; background: #f8fafc; color: #334155; }
        .form-input:focus { border-color: #3b82f6; background: #fff; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        
        .btn-submit { background-color: #1e293b; color: white; border: none; padding: 14px 24px; border-radius: 12px; font-weight: 700; font-size: 15px; cursor: pointer; transition: 0.2s; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .btn-submit:hover { background-color: #0f172a; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0, 0, 0, 0.15); }
        
        .btn-outline { background: transparent; color: #475569; border: 1px solid #cbd5e1; box-shadow: none; }
        body.dark-mode .btn-outline { color: #f8fafc !important; border-color: rgba(255, 255, 255, 0.2) !important; background: rgba(255,255,255,0.05); }

        .custom-select {
            appearance: none;
            background-color: #fff;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%2352525b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>');
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 16px;
        }
        body.dark-mode .custom-select {
            background-color: #0f172a !important;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%2394a3b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>') !important;
            background-repeat: no-repeat !important;
            background-position: right 14px center !important;
            background-size: 16px !important;
        }
        
        body.dark-mode .info-value { color: #f8fafc !important; }
        body.dark-mode .info-label { color: #94a3b8 !important; }

        .toast-success { background: #ecfdf5; color: #059669; padding: 16px; border-radius: 12px; font-weight: 600; margin-bottom: 24px; border: 1px solid #a7f3d0; }
        .toast-error { background: #fef2f2; color: #ef4444; padding: 16px; border-radius: 12px; font-weight: 600; margin-bottom: 24px; border: 1px solid #fecaca; }
        

        body.dark-mode .profile-pic-container { border-bottom-color: #334155 !important; }
        body.dark-mode #avatar-preview-box { background: #0f172a !important; border-color: #334155 !important; }
        body.dark-mode .profile-pic-title { color: #f8fafc !important; }

        /* Instagram-Style Profile Photo Cropper */
        .insta-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(6px);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            animation: instaFadeIn 0.2s ease-out;
        }
        @keyframes instaFadeIn {
            from { opacity: 0; transform: scale(0.98); }
            to { opacity: 1; transform: scale(1); }
        }
        .insta-modal-card {
            background: #18181b;
            color: #f4f4f5;
            border-radius: 20px;
            width: 100%;
            max-width: 380px;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.6);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            border: 1px solid rgba(255, 255, 255, 0.12);
            user-select: none;
        }
        .insta-modal-header {
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .insta-modal-title {
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            margin: 0;
        }
        .insta-modal-desc {
            font-size: 12px;
            color: #a1a1aa;
            margin: 2px 0 0 0;
        }
        .insta-modal-close {
            background: transparent;
            border: none;
            color: #a1a1aa;
            font-size: 26px;
            line-height: 1;
            cursor: pointer;
            padding: 0 4px;
            transition: color 0.15s;
        }
        .insta-modal-close:hover {
            color: #ffffff;
        }
        .insta-viewport-wrapper {
            padding: 16px;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #09090b;
        }
        .insta-viewport {
            width: 280px;
            height: 280px;
            position: relative;
            overflow: hidden;
            background: #000000;
            border-radius: 12px;
            cursor: grab;
            touch-action: none;
        }
        .insta-viewport:active {
            cursor: grabbing;
        }
        #insta-crop-img {
            position: absolute;
            top: 0;
            left: 0;
            transform-origin: 0 0;
            pointer-events: none;
            display: block;
            max-width: none;
            max-height: none;
        }
        /* Instagram fixed circular mask with dark surround */
        .insta-circle-mask {
            position: absolute;
            top: 50%;
            left: 50%;
            width: 230px;
            height: 230px;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            border: 2px solid rgba(255, 255, 255, 0.9);
            box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.65);
            pointer-events: none;
        }
        .insta-controls {
            padding: 14px 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: #18181b;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }
        .insta-zoom-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .insta-zoom-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #e4e4e7;
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.15s;
            flex-shrink: 0;
        }
        .insta-zoom-btn:hover {
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
        }
        #insta-zoom-slider {
            flex: 1;
            height: 4px;
            border-radius: 2px;
            background: rgba(255, 255, 255, 0.2);
            outline: none;
            cursor: pointer;
            accent-color: #38bdf8;
        }
        .insta-tool-btn {
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.12);
            color: #e4e4e7;
            padding: 6px 10px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s;
            flex-shrink: 0;
        }
        .insta-tool-btn:hover {
            background: rgba(255, 255, 255, 0.16);
            color: #ffffff;
        }
        .insta-modal-footer {
            padding: 14px 20px 18px 20px;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #18181b;
        }
        .insta-btn-cancel {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #d4d4d8;
            padding: 9px 18px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.15s;
        }
        .insta-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
        }
        .insta-btn-save {
            background: #0284c7;
            border: none;
            color: #ffffff;
            padding: 9px 24px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: background 0.15s, transform 0.15s;
        }
        .insta-btn-save:hover {
            background: #0369a1;
            transform: translateY(-1px);
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
            <div class="page-title">Profile & Settings</div>
        </header>

        <div class="content">
            
            <?php if ($status_message): ?>
                <div class="toast-success"><?php echo $status_message; ?></div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="toast-error"><?php echo $error_message; ?></div>
            <?php endif; ?>

            <div class="profile-grid">
                <div class="profile-card">
                <div class="card-title">Personal Information</div>
                <form id="personal-info-form" method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile" value="1">
                    <input type="hidden" name="cropped_profile_image" id="cropped_profile_image" value="">
                    <input type="hidden" name="gif_crop_data" id="gif_crop_data" value="">

                    <!-- Profile Picture Upload & Framing -->
                    <?php
                    $saved_profile_pic = !empty($user_data['profile_picture']) ? $user_data['profile_picture'] : '';
                    $has_pic = !empty($saved_profile_pic);
                    ?>
                    <div class="profile-pic-container" style="display: flex; align-items: center; gap: 20px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #e2e8f0;">
                        <div style="position: relative;">
                            <div id="avatar-preview-box" style="width: 84px; height: 84px; border-radius: 50%; background: #1e293b; color: white; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: 700; overflow: hidden; border: 3px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.08); flex-shrink: 0;">
                                <img id="avatar-preview-img" src="<?php echo htmlspecialchars($saved_profile_pic); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover; display: <?php echo $has_pic ? 'block' : 'none'; ?>;" onerror="this.style.display='none'; document.getElementById('avatar-preview-initial').style.display='flex';">
                                <span id="avatar-preview-initial" style="display: <?php echo $has_pic ? 'none' : 'flex'; ?>; align-items: center; justify-content: center; width: 100%; height: 100%;"><?php echo strtoupper(substr($user_data['name'], 0, 1)); ?></span>
                            </div>
                            <label for="profile_pic_input" style="position: absolute; bottom: 0; right: 0; background: #0284c7; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; border: 2px solid white; box-shadow: 0 2px 6px rgba(0,0,0,0.2); transition: 0.2s;" title="Upload photo">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                    <circle cx="12" cy="13" r="4"></circle>
                                </svg>
                            </label>
                            <input type="file" id="profile_pic_input" name="profile_picture" accept="image/png, image/jpeg, image/jpg, image/webp, image/gif" style="display: none;" onchange="previewAvatar(this);">
                        </div>
                        <div style="flex: 1;">
                            <div style="font-size: 15px; font-weight: 700; color: #0f172a; margin-bottom: 3px;" class="profile-pic-title">Profile Photo</div>
                            <div style="font-size: 12px; color: #64748b; margin-bottom: 10px;">JPG, PNG, WebP, or animated GIF (Max 10MB).</div>
                            <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                                <button type="button" onclick="document.getElementById('profile_pic_input').click();" class="btn-outline" style="padding: 7px 14px; font-size: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="17 8 12 3 7 8"></polyline><line x1="12" y1="3" x2="12" y2="15"></line></svg>
                                    Choose Photo
                                </button>
                                <button type="button" id="btn-adjust-frame" onclick="openCropperWithCurrentPhoto()" class="btn-outline" style="padding: 7px 14px; font-size: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; display: <?php echo $has_pic ? 'inline-flex' : 'none'; ?>; align-items: center; gap: 6px;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="16"></line><line x1="8" y1="12" x2="16" y2="12"></line></svg>
                                    Adjust Frame
                                </button>
                                <button type="button" id="btn-remove-photo" onclick="handleRemoveOrClearPhoto(event)" style="background: none; border: 1px solid #fecaca; color: #ef4444; padding: 7px 12px; font-size: 12px; border-radius: 8px; font-weight: 600; cursor: pointer; display: <?php echo $has_pic ? 'inline-flex' : 'none'; ?>; align-items: center; gap: 5px;">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                                    Remove
                                </button>
                            </div>
                            <div id="crop-applied-hint" style="display: none; margin-top: 8px; font-size: 12px; color: #059669; font-weight: 600; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 6px 12px; border-radius: 8px;">
                                ✓ Photo framed nicely! Click <strong>Save Changes</strong> below to apply.
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <?php if (in_array($user_data['role'], ['employee', 'qa', 'gl'])): ?>
                            <label class="form-label">Employee ID (Fixed)</label>
                            <input type="text" value="<?php echo htmlspecialchars($user_data['emp_id']); ?>" class="form-input" disabled style="background: rgba(0,0,0,0.02); color: #94a3b8; cursor: not-allowed; border: 1px solid rgba(0,0,0,0.05);">
                        <?php else: ?>
                            <label class="form-label">Employee ID</label>
                            <input type="text" name="emp_id" class="form-input" required value="<?php echo htmlspecialchars($user_data['emp_id']); ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="new_name" class="form-input" required value="<?php echo htmlspecialchars($user_data['name']); ?>">
                    </div>

                    <?php
                    $role_map = [
                        'employee' => 'Data Annotator',
                        'qa'       => 'Quality Assurance',
                        'tl'       => 'Team Leader',
                        'gl'       => 'Group Leader',
                        'hr'       => 'Human Resources',
                        'admin'    => 'Administrator'
                    ];
                    $role_key = strtolower(trim($user_data['role'] ?? ''));
                    $full_role_title = $role_map[$role_key] ?? ucfirst($user_data['role'] ?? 'Employee');
                    ?>
                    <div class="info-row" style="margin-top: 10px;">
                        <span class="info-label">Role / Position</span>
                        <span class="info-value text-slate-600" style="background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 700;"><?php echo htmlspecialchars($full_role_title); ?></span>
                    </div>

                    <?php if (!empty($user_data['employment_type'])): ?>
                    <div class="info-row" style="margin-top: 10px;">
                        <span class="info-label">Employment Type</span>
                        <span class="info-value text-slate-600" style="background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); padding: 4px 10px; border-radius: 6px; font-size: 13px; font-weight: 700;"><?php echo htmlspecialchars($user_data['employment_type']); ?></span>
                    </div>
                    <?php endif; ?>

                    <button type="submit" name="update_profile" value="1" class="btn-submit" style="margin-top: 15px;">Save Changes</button>
                </form>
            </div>

            <?php if (in_array($user_data['role'], ['employee', 'qa', 'gl'])): ?>
            <?php
            // Check for pending/scheduled shift changes
            $pending_shift_data = null;
            $q_pend_shift = mysqli_query($conn, "SELECT requested_shift, created_at FROM shift_change_requests WHERE emp_id = '{$user_data['emp_id']}' AND status = 'pending' ORDER BY id DESC LIMIT 1");
            if ($q_pend_shift && mysqli_num_rows($q_pend_shift) > 0) {
                $pending_shift_data = mysqli_fetch_assoc($q_pend_shift);
            }

            $scheduled_shift_data = null;
            $q_sch_shift = @mysqli_query($conn, "SELECT new_value, effective_date FROM scheduled_changes WHERE emp_id = '{$user_data['emp_id']}' AND change_type = 'shift_hour' AND applied = 0 AND effective_date >= CURDATE() ORDER BY id DESC LIMIT 1");
            if ($q_sch_shift && mysqli_num_rows($q_sch_shift) > 0) {
                $scheduled_shift_data = mysqli_fetch_assoc($q_sch_shift);
            }

            // Check for pending/scheduled lunch changes
            $pending_break_data = null;
            $q_pend_break = mysqli_query($conn, "SELECT requested_window, created_at FROM break_change_requests WHERE emp_id = '{$user_data['emp_id']}' AND status = 'pending' ORDER BY id DESC LIMIT 1");
            if ($q_pend_break && mysqli_num_rows($q_pend_break) > 0) {
                $pending_break_data = mysqli_fetch_assoc($q_pend_break);
            }

            $scheduled_break_data = null;
            $q_sch_break = @mysqli_query($conn, "SELECT new_value, effective_date FROM scheduled_changes WHERE emp_id = '{$user_data['emp_id']}' AND change_type = 'lunch_window' AND applied = 0 AND effective_date >= CURDATE() ORDER BY id DESC LIMIT 1");
            if ($q_sch_break && mysqli_num_rows($q_sch_break) > 0) {
                $scheduled_break_data = mysqli_fetch_assoc($q_sch_break);
            }
            ?>
            <div class="profile-card">
                <div class="card-title">Lunchtime Settings</div>
                <div class="info-row" style="margin-bottom: 20px;">
                    <span class="info-label">Current Assigned Window</span>
                    <span class="info-value" style="background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); color: inherit; padding: 6px 12px; border-radius: 8px; font-weight: 700;"><?php echo htmlspecialchars($user_data['lunch_window']); ?></span>
                </div>
                
                <?php if ($scheduled_break_data): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); color: #059669; padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 16px;">
                    ✅ <strong>Approved!</strong> New window <strong><?php echo htmlspecialchars($scheduled_break_data['new_value']); ?></strong> takes effect on Monday (<?php echo date('M d, Y', strtotime($scheduled_break_data['effective_date'])); ?>).
                </div>
                <?php elseif ($pending_break_data): ?>
                <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25); color: #d97706; padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 16px;">
                    ⏳ Pending Request: <strong><?php echo htmlspecialchars($pending_break_data['requested_window']); ?></strong> (Awaiting manager approval)
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="request_break_change" value="1">
                    <div class="form-group">
                        <label class="form-label">Request New Lunchtime Window</label>
                        <select name="requested_window" class="form-input custom-select" required <?php echo $pending_break_data ? 'disabled' : ''; ?>>
                            <option value="12:00-13:00">12:00 PM - 1:00 PM</option>
                            <option value="13:00-14:00">1:00 PM - 2:00 PM</option>
                        </select>
                    </div>
                    <?php if ($pending_break_data): ?>
                    <button type="button" class="btn-submit btn-outline" disabled style="opacity:0.6; cursor:not-allowed;">Request Pending Approval</button>
                    <?php elseif ($is_friday): ?>
                    <button type="submit" class="btn-submit btn-outline">Request Change</button>
                    <?php else: ?>
                    <button type="button" class="btn-submit btn-outline" disabled style="opacity:0.5; cursor:not-allowed;">Request Change</button>
                    <p style="font-size:12px; color:#ef4444; margin-top:8px;">⚠️ Change requests can only be submitted on <strong>Fridays</strong>.</p>
                    <?php endif; ?>
                </form>
            </div>

            <div class="profile-card">
                <div class="card-title">Shift Hour Settings</div>
                <div class="info-row" style="margin-bottom: 20px;">
                    <span class="info-label">Current Assigned Window</span>
                    <span class="info-value" style="background: rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.05); color: inherit; padding: 6px 12px; border-radius: 8px; font-weight: 700;"><?php echo htmlspecialchars($user_data['shift_hour'] ?? '09:00-18:00'); ?></span>
                </div>

                <?php if ($scheduled_shift_data): ?>
                <div style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); color: #059669; padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 16px;">
                    ✅ <strong>Approved!</strong> New shift <strong><?php echo htmlspecialchars($scheduled_shift_data['new_value']); ?></strong> takes effect on Monday (<?php echo date('M d, Y', strtotime($scheduled_shift_data['effective_date'])); ?>).
                </div>
                <?php elseif ($pending_shift_data): ?>
                <div style="background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.25); color: #d97706; padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 600; margin-bottom: 16px;">
                    ⏳ Pending Request: <strong><?php echo htmlspecialchars($pending_shift_data['requested_shift']); ?></strong> (Awaiting manager approval)
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <input type="hidden" name="request_shift_change" value="1">
                    <div class="form-group">
                        <label class="form-label">Request New Shift Hour</label>
                        <select name="requested_shift" class="form-input custom-select" required <?php echo $pending_shift_data ? 'disabled' : ''; ?>>
                            <option value="08:00-17:00">08:00-17:00</option>
                            <option value="08:30-17:30">08:30-17:30</option>
                            <option value="09:00-18:00">09:00-18:00</option>
                        </select>
                    </div>
                    <?php if ($pending_shift_data): ?>
                    <button type="button" class="btn-submit btn-outline" disabled style="opacity:0.6; cursor:not-allowed;">Request Pending Approval</button>
                    <?php elseif ($is_friday): ?>
                    <button type="submit" class="btn-submit btn-outline">Request Change</button>
                    <?php else: ?>
                    <button type="button" class="btn-submit btn-outline" disabled style="opacity:0.5; cursor:not-allowed;">Request Change</button>
                    <p style="font-size:12px; color:#ef4444; margin-top:8px;">⚠️ Change requests can only be submitted on <strong>Fridays</strong>.</p>
                    <?php endif; ?>
                </form>
            </div>
            <?php endif; ?>

            <div class="profile-card">
                <div class="card-title">Change Password</div>
                <form method="POST" action="">
                    <input type="hidden" name="change_password" value="1">
                    
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="old_password" class="form-input" required placeholder="Enter current password">
                    </div>

                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-input" required placeholder="Enter new password" minlength="6">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-input" required placeholder="Confirm new password" minlength="6">
                    </div>

                    <button type="submit" class="btn-submit">Update Password</button>
                </form>
            </div>

            <div class="profile-card" style="border: 1px solid rgba(239, 68, 68, 0.25); background: rgba(239, 68, 68, 0.03);">
                <div class="card-title" style="color: #ef4444; display: flex; align-items: center; gap: 8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Account Session
                </div>
                <p style="font-size: 13px; color: #64748b; margin-bottom: 18px; line-height: 1.5;">Sign out of your WeRide account on this device. You will need to log back in next time.</p>
                <a href="logout.php" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 13px; background: #ef4444; color: white; border-radius: 10px; font-weight: 700; text-decoration: none; font-size: 14px; box-shadow: 0 4px 14px rgba(239, 68, 68, 0.3); transition: 0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    <span>Log Out of WeRide</span>
                </a>
            </div>
            
            </div> <!-- End of profile-grid -->

        </div>
    </main>

    <!-- Instagram-Style Fixed Circular Frame Modal -->
    <div id="insta-frame-modal" class="insta-modal-overlay" style="display: none;">
        <div class="insta-modal-card">
            <div class="insta-modal-header">
                <div>
                    <h3 class="insta-modal-title">Frame Profile Picture</h3>
                    <p class="insta-modal-desc">Drag to center your face. Zoom to fit inside the circle.</p>
                </div>
                <button type="button" class="insta-modal-close" id="insta-btn-close" aria-label="Close">&times;</button>
            </div>

            <div class="insta-viewport-wrapper">
                <!-- Fixed circular cutout frame -->
                <div id="insta-viewport" class="insta-viewport">
                    <img id="insta-crop-img" src="" alt="Frame preview">
                    <div class="insta-circle-mask"></div>
                </div>
            </div>

            <div class="insta-controls">
                <!-- Zoom slider with - and + -->
                <div class="insta-zoom-row">
                    <button type="button" class="insta-zoom-btn" id="insta-btn-zoom-out" title="Zoom Out">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    </button>
                    <input type="range" id="insta-zoom-slider" min="1" max="3" step="0.01" value="1">
                    <button type="button" class="insta-zoom-btn" id="insta-btn-zoom-in" title="Zoom In">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                    </button>
                    <button type="button" class="insta-tool-btn" id="insta-btn-rotate" title="Rotate 90 degrees">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l2.6 2.3"/></svg>
                        <span>Rotate</span>
                    </button>
                </div>
            </div>

            <div class="insta-modal-footer">
                <button type="button" class="insta-btn-cancel" id="insta-btn-cancel">Cancel</button>
                <button type="button" class="insta-btn-save" id="insta-btn-apply">Done</button>
            </div>
        </div>
    </div>

<script>
// Standalone Animated GIF Engine (omggif)
/**
 * Minified by jsDelivr using Terser v5.37.0.
 * Original file: /npm/omggif@1.0.10/omggif.js
 *
 * Do NOT use SRI with dynamically generated files! More information: https://www.jsdelivr.com/using-sri-with-dynamic-files
 */
"use strict";function GifWriter(r,e,t,n){var i=0,a=void 0===(n=void 0===n?{}:n).loop?null:n.loop,o=void 0===n.palette?null:n.palette;if(e<=0||t<=0||e>65535||t>65535)throw new Error("Width/Height invalid.");function f(r){var e=r.length;if(e<2||e>256||e&e-1)throw new Error("Invalid code/color length, must be power of 2 and 2 .. 256.");return e}r[i++]=71,r[i++]=73,r[i++]=70,r[i++]=56,r[i++]=57,r[i++]=97;var l=0,u=0;if(null!==o){for(var h=f(o);h>>=1;)++l;if(h=1<<l,--l,void 0!==n.background){if((u=n.background)>=h)throw new Error("Background index out of range.");if(0===u)throw new Error("Background index explicitly passed as 0.")}}if(r[i++]=255&e,r[i++]=e>>8&255,r[i++]=255&t,r[i++]=t>>8&255,r[i++]=(null!==o?128:0)|l,r[i++]=u,r[i++]=0,null!==o)for(var d=0,s=o.length;d<s;++d){var v=o[d];r[i++]=v>>16&255,r[i++]=v>>8&255,r[i++]=255&v}if(null!==a){if(a<0||a>65535)throw new Error("Loop count invalid.");r[i++]=33,r[i++]=255,r[i++]=11,r[i++]=78,r[i++]=69,r[i++]=84,r[i++]=83,r[i++]=67,r[i++]=65,r[i++]=80,r[i++]=69,r[i++]=50,r[i++]=46,r[i++]=48,r[i++]=3,r[i++]=1,r[i++]=255&a,r[i++]=a>>8&255,r[i++]=0}var c=!1;this.addFrame=function(e,t,n,a,l,u){if(!0===c&&(--i,c=!1),u=void 0===u?{}:u,e<0||t<0||e>65535||t>65535)throw new Error("x/y invalid.");if(n<=0||a<=0||n>65535||a>65535)throw new Error("Width/Height invalid.");if(l.length<n*a)throw new Error("Not enough pixels for the frame size.");var h=!0,d=u.palette;if(null==d&&(h=!1,d=o),null==d)throw new Error("Must supply either a local or global palette.");for(var s=f(d),v=0;s>>=1;)++v;s=1<<v;var w=void 0===u.delay?0:u.delay,p=void 0===u.disposal?0:u.disposal;if(p<0||p>3)throw new Error("Disposal out of range.");var g=!1,x=0;if(void 0!==u.transparent&&null!==u.transparent&&(g=!0,(x=u.transparent)<0||x>=s))throw new Error("Transparent color index.");if((0!==p||g||0!==w)&&(r[i++]=33,r[i++]=249,r[i++]=4,r[i++]=p<<2|(!0===g?1:0),r[i++]=255&w,r[i++]=w>>8&255,r[i++]=x,r[i++]=0),r[i++]=44,r[i++]=255&e,r[i++]=e>>8&255,r[i++]=255&t,r[i++]=t>>8&255,r[i++]=255&n,r[i++]=n>>8&255,r[i++]=255&a,r[i++]=a>>8&255,r[i++]=!0===h?128|v-1:0,!0===h)for(var k=0,b=d.length;k<b;++k){var E=d[k];r[i++]=E>>16&255,r[i++]=E>>8&255,r[i++]=255&E}return i=GifWriterOutputLZWCodeStream(r,i,v<2?2:v,l)},this.end=function(){return!1===c&&(r[i++]=59,c=!0),i},this.getOutputBuffer=function(){return r},this.setOutputBuffer=function(e){r=e},this.getOutputBufferPosition=function(){return i},this.setOutputBufferPosition=function(r){i=r}}function GifWriterOutputLZWCodeStream(r,e,t,n){r[e++]=t;var i=e++,a=1<<t,o=a-1,f=a+1,l=f+1,u=t+1,h=0,d=0;function s(t){for(;h>=t;)r[e++]=255&d,d>>=8,h-=8,e===i+256&&(r[i]=255,i=e++)}function v(r){d|=r<<h,h+=u,s(8)}var c=n[0]&o,w={};v(a);for(var p=1,g=n.length;p<g;++p){var x=n[p]&o,k=c<<8|x,b=w[k];if(void 0===b){for(d|=c<<h,h+=u;h>=8;)r[e++]=255&d,d>>=8,h-=8,e===i+256&&(r[i]=255,i=e++);4096===l?(v(a),l=f+1,u=t+1,w={}):(l>=1<<u&&++u,w[k]=l++),c=x}else c=b}return v(c),v(f),s(1),i+1===e?r[i]=0:(r[i]=e-i-1,r[e++]=0),e}function GifReader(r){var e=0;if(71!==r[e++]||73!==r[e++]||70!==r[e++]||56!==r[e++]||56!=(r[e++]+1&253)||97!==r[e++])throw new Error("Invalid GIF 87a/89a header.");var t=r[e++]|r[e++]<<8,n=r[e++]|r[e++]<<8,i=r[e++],a=i>>7,o=1<<(7&i)+1;r[e++];r[e++];var f=null,l=null;a&&(f=e,l=o,e+=3*o);var u=!0,h=[],d=0,s=null,v=0,c=null;for(this.width=t,this.height=n;u&&e<r.length;)switch(r[e++]){case 33:switch(r[e++]){case 255:if(11!==r[e]||78==r[e+1]&&69==r[e+2]&&84==r[e+3]&&83==r[e+4]&&67==r[e+5]&&65==r[e+6]&&80==r[e+7]&&69==r[e+8]&&50==r[e+9]&&46==r[e+10]&&48==r[e+11]&&3==r[e+12]&&1==r[e+13]&&0==r[e+16])e+=14,c=r[e++]|r[e++]<<8,e++;else for(e+=12;;){if(!((_=r[e++])>=0))throw Error("Invalid block size");if(0===_)break;e+=_}break;case 249:if(4!==r[e++]||0!==r[e+4])throw new Error("Invalid graphics extension block.");var w=r[e++];d=r[e++]|r[e++]<<8,s=r[e++],1&w||(s=null),v=w>>2&7,e++;break;case 254:for(;;){if(!((_=r[e++])>=0))throw Error("Invalid block size");if(0===_)break;e+=_}break;default:throw new Error("Unknown graphic control label: 0x"+r[e-1].toString(16))}break;case 44:var p=r[e++]|r[e++]<<8,g=r[e++]|r[e++]<<8,x=r[e++]|r[e++]<<8,k=r[e++]|r[e++]<<8,b=r[e++],E=b>>6&1,m=1<<(7&b)+1,y=f,G=l,I=!1;if(b>>7){I=!0;y=e,G=m,e+=3*m}var W=e;for(e++;;){var _;if(!((_=r[e++])>=0))throw Error("Invalid block size");if(0===_)break;e+=_}h.push({x:p,y:g,width:x,height:k,has_local_palette:I,palette_offset:y,palette_size:G,data_offset:W,data_length:e-W,transparent_index:s,interlaced:!!E,delay:d,disposal:v});break;case 59:u=!1;break;default:throw new Error("Unknown gif block: 0x"+r[e-1].toString(16))}this.numFrames=function(){return h.length},this.loopCount=function(){return c},this.frameInfo=function(r){if(r<0||r>=h.length)throw new Error("Frame index out of range.");return h[r]},this.decodeAndBlitFrameBGRA=function(e,n){var i=this.frameInfo(e),a=i.width*i.height,o=new Uint8Array(a);GifReaderLZWOutputIndexStream(r,i.data_offset,o,a);var f=i.palette_offset,l=i.transparent_index;null===l&&(l=256);var u=i.width,h=t-u,d=u,s=4*(i.y*t+i.x),v=4*((i.y+i.height)*t+i.x),c=s,w=4*h;!0===i.interlaced&&(w+=4*t*7);for(var p=8,g=0,x=o.length;g<x;++g){var k=o[g];if(0===d&&(d=u,(c+=w)>=v&&(w=4*h+4*t*(p-1),c=s+(u+h)*(p<<1),p>>=1)),k===l)c+=4;else{var b=r[f+3*k],E=r[f+3*k+1],m=r[f+3*k+2];n[c++]=m,n[c++]=E,n[c++]=b,n[c++]=255}--d}},this.decodeAndBlitFrameRGBA=function(e,n){var i=this.frameInfo(e),a=i.width*i.height,o=new Uint8Array(a);GifReaderLZWOutputIndexStream(r,i.data_offset,o,a);var f=i.palette_offset,l=i.transparent_index;null===l&&(l=256);var u=i.width,h=t-u,d=u,s=4*(i.y*t+i.x),v=4*((i.y+i.height)*t+i.x),c=s,w=4*h;!0===i.interlaced&&(w+=4*t*7);for(var p=8,g=0,x=o.length;g<x;++g){var k=o[g];if(0===d&&(d=u,(c+=w)>=v&&(w=4*h+4*t*(p-1),c=s+(u+h)*(p<<1),p>>=1)),k===l)c+=4;else{var b=r[f+3*k],E=r[f+3*k+1],m=r[f+3*k+2];n[c++]=b,n[c++]=E,n[c++]=m,n[c++]=255}--d}}}function GifReaderLZWOutputIndexStream(r,e,t,n){for(var i=r[e++],a=1<<i,o=a+1,f=o+1,l=i+1,u=(1<<l)-1,h=0,d=0,s=0,v=r[e++],c=new Int32Array(4096),w=null;;){for(;h<16&&0!==v;)d|=r[e++]<<h,h+=8,1===v?v=r[e++]:--v;if(h<l)break;var p=d&u;if(d>>=l,h-=l,p!==a){if(p===o)break;for(var g=p<f?p:w,x=0,k=g;k>a;)k=c[k]>>8,++x;var b=k;if(s+x+(g!==p?1:0)>n)return void console.log("Warning, gif stream longer than expected.");t[s++]=b;var E=s+=x;for(g!==p&&(t[s++]=b),k=g;x--;)k=c[k],t[--E]=255&k,k>>=8;null!==w&&f<4096&&(c[f++]=w<<8|b,f>=u+1&&l<12&&(++l,u=u<<1|1)),w=p}else f=o+1,u=(1<<(l=i+1))-1,w=null}return s!==n&&console.log("Warning, gif stream shorter than expected."),t}try{exports.GifWriter=GifWriter,exports.GifReader=GifReader}catch(r){}
//# sourceMappingURL=/sm/64893916a2a295923f98fd9d9dec5e1494eb2e1a2221bfae4e48498c0156bdcb.map
if (typeof GifWriter !== 'undefined' && typeof GifReader !== 'undefined') {
    window.omggif = { GifWriter: GifWriter, GifReader: GifReader };
}

window._savedProfilePictureUrl = <?php echo json_encode($saved_profile_pic); ?>;

window.handleRemoveOrClearPhoto = function(e) {
    if (e) e.preventDefault();
    
    const fileInput = document.getElementById('profile_pic_input');
    const hiddenCrop = document.getElementById('cropped_profile_image');
    const hiddenGifCrop = document.getElementById('gif_crop_data');
    const previewImg = document.getElementById('avatar-preview-img');
    const previewInitial = document.getElementById('avatar-preview-initial');
    const btnRemove = document.getElementById('btn-remove-photo');
    const btnAdjust = document.getElementById('btn-adjust-frame');
    const hint = document.getElementById('crop-applied-hint');

    // Check if user has an unsaved preview pending
    const hasUnsavedPreview = (fileInput && fileInput.files && fileInput.files.length > 0) || (hiddenCrop && hiddenCrop.value !== '');
    
    if (hasUnsavedPreview) {
        if (fileInput) fileInput.value = '';
        if (hiddenCrop) hiddenCrop.value = '';
        if (hiddenGifCrop) hiddenGifCrop.value = '';
        if (hint) hint.style.display = 'none';

        if (window._savedProfilePictureUrl && window._savedProfilePictureUrl !== '') {
            if (previewImg) {
                previewImg.src = window._savedProfilePictureUrl;
                previewImg.style.display = 'block';
            }
            if (previewInitial) previewInitial.style.display = 'none';
            if (btnAdjust) btnAdjust.style.display = 'inline-flex';
            if (btnRemove) btnRemove.style.display = 'inline-flex';
        } else {
            if (previewImg) {
                previewImg.src = '';
                previewImg.style.display = 'none';
            }
            if (previewInitial) previewInitial.style.display = 'flex';
            if (btnAdjust) btnAdjust.style.display = 'none';
            if (btnRemove) btnRemove.style.display = 'none';
        }
        return;
    }

    // Permanent removal of saved picture from database
    if (window._savedProfilePictureUrl && window._savedProfilePictureUrl !== '') {
        if (!confirm('Are you sure you want to remove your profile picture?')) {
            return;
        }
        const form = document.getElementById('personal-info-form');
        let removeInput = document.getElementById('remove_profile_picture_input');
        if (!removeInput) {
            removeInput = document.createElement('input');
            removeInput.type = 'hidden';
            removeInput.name = 'remove_profile_picture';
            removeInput.id = 'remove_profile_picture_input';
            removeInput.value = '1';
            form.appendChild(removeInput);
        } else {
            removeInput.value = '1';
        }
        form.submit();
    }
};

(function() {
    const STAGE_SIZE = 280;
    const CIRCLE_DIAMETER = 230;
    const CIRCLE_OFFSET = (STAGE_SIZE - CIRCLE_DIAMETER) / 2; // 25px

    let rawImage = new Image();
    let imgLoaded = false;
    let rotationDeg = 0;

    let baseScale = 1;
    let currentZoomMultiplier = 1;
    let offsetX = 0;
    let offsetY = 0;

    let isDragging = false;
    let dragStartX = 0;
    let dragStartY = 0;
    let dragStartOffsetX = 0;
    let dragStartOffsetY = 0;

    let initialPinchDist = 0;
    let initialPinchZoom = 1;

    const modal = document.getElementById('insta-frame-modal');
    const viewport = document.getElementById('insta-viewport');
    const imgEl = document.getElementById('insta-crop-img');
    const slider = document.getElementById('insta-zoom-slider');
    const btnZoomIn = document.getElementById('insta-btn-zoom-in');
    const btnZoomOut = document.getElementById('insta-btn-zoom-out');
    const btnRotate = document.getElementById('insta-btn-rotate');
    const btnCancel = document.getElementById('insta-btn-cancel');
    const btnApply = document.getElementById('insta-btn-apply');
    const btnClose = document.getElementById('insta-btn-close');

    function getEffectiveDims() {
        if (!rawImage.naturalWidth) return { w: STAGE_SIZE, h: STAGE_SIZE, origW: STAGE_SIZE, origH: STAGE_SIZE };
        const isRotated90 = (rotationDeg % 180 !== 0);
        return {
            w: isRotated90 ? rawImage.naturalHeight : rawImage.naturalWidth,
            h: isRotated90 ? rawImage.naturalWidth : rawImage.naturalHeight,
            origW: rawImage.naturalWidth,
            origH: rawImage.naturalHeight
        };
    }

    function computeBaseScale() {
        const dims = getEffectiveDims();
        const scaleX = CIRCLE_DIAMETER / dims.w;
        const scaleY = CIRCLE_DIAMETER / dims.h;
        return Math.max(scaleX, scaleY);
    }

    function getCurrentScale() {
        return baseScale * currentZoomMultiplier;
    }

    function clampOffsets() {
        const dims = getEffectiveDims();
        const scale = getCurrentScale();
        const renderedW = dims.w * scale;
        const renderedH = dims.h * scale;

        const minX = CIRCLE_OFFSET + CIRCLE_DIAMETER - renderedW;
        const maxX = CIRCLE_OFFSET;
        offsetX = Math.min(maxX, Math.max(minX, offsetX));

        const minY = CIRCLE_OFFSET + CIRCLE_DIAMETER - renderedH;
        const maxY = CIRCLE_OFFSET;
        offsetY = Math.min(maxY, Math.max(minY, offsetY));
    }

    function updateTransform() {
        if (!imgLoaded) return;
        clampOffsets();

        const scale = getCurrentScale();
        const dims = getEffectiveDims();
        const cx = offsetX + (dims.w * scale) / 2;
        const cy = offsetY + (dims.h * scale) / 2;

        imgEl.style.transform = 'translate(' + cx + 'px, ' + cy + 'px) rotate(' + rotationDeg + 'deg) scale(' + scale + ') translate(' + (-dims.origW / 2) + 'px, ' + (-dims.origH / 2) + 'px)';
    }

    function resetFraming() {
        rotationDeg = 0;
        baseScale = computeBaseScale();
        currentZoomMultiplier = 1;
        slider.value = '1';

        const dims = getEffectiveDims();
        const scale = getCurrentScale();
        offsetX = (STAGE_SIZE - dims.w * scale) / 2;
        offsetY = (STAGE_SIZE - dims.h * scale) / 2;
        updateTransform();
    }

    function setZoom(newZoomMultiplier, zoomCenterPt) {
        newZoomMultiplier = Math.min(3, Math.max(1, newZoomMultiplier));
        const oldScale = getCurrentScale();
        currentZoomMultiplier = newZoomMultiplier;
        slider.value = newZoomMultiplier.toFixed(2);
        const newScale = getCurrentScale();

        const zx = zoomCenterPt ? zoomCenterPt.x : (STAGE_SIZE / 2);
        const zy = zoomCenterPt ? zoomCenterPt.y : (STAGE_SIZE / 2);

        const ratio = newScale / oldScale;
        offsetX = zx - (zx - offsetX) * ratio;
        offsetY = zy - (zy - offsetY) * ratio;

        updateTransform();
    }

    // Fast Color Quantizer for GIF frames (histogram 5-bit RGB with optional transparency)
    function quantizeFrame(rgbaPixels, width, height) {
        const counts = new Uint16Array(32768);
        const n = width * height;
        let hasTrans = false;
        for (let i = 0; i < n; i++) {
            const a = rgbaPixels[i * 4 + 3];
            if (a < 128) {
                hasTrans = true;
                continue;
            }
            const r = rgbaPixels[i * 4] >> 3;
            const g = rgbaPixels[i * 4 + 1] >> 3;
            const b = rgbaPixels[i * 4 + 2] >> 3;
            const key = (r << 10) | (g << 5) | b;
            if (counts[key] < 65535) counts[key]++;
        }
        
        const maxColors = hasTrans ? 255 : 256;
        const sorted = [];
        for (let k = 0; k < 32768; k++) {
            if (counts[k] > 0) sorted.push(k);
        }
        sorted.sort((a, b) => counts[b] - counts[a]);
        if (sorted.length > maxColors) sorted.length = maxColors;
        
        const palette = [];
        const colorMap = new Int16Array(32768).fill(-1);
        let transIndex = -1;
        if (hasTrans) {
            transIndex = 0;
            palette.push(0);
        }
        for (let i = 0; i < sorted.length; i++) {
            const k = sorted[i];
            const r = (k >> 10) & 31;
            const g = (k >> 5) & 31;
            const b = k & 31;
            const r8 = (r << 3) | (r >> 2);
            const g8 = (g << 3) | (g >> 2);
            const b8 = (b << 3) | (b >> 2);
            const color24 = (r8 << 16) | (g8 << 8) | b8;
            const pIndex = palette.length;
            palette.push(color24);
            colorMap[k] = pIndex;
        }
        
        let pSize = 2;
        while (pSize < palette.length) pSize <<= 1;
        while (palette.length < pSize) palette.push(0);
        
        const indexed = new Uint8Array(n);
        for (let i = 0; i < n; i++) {
            const a = rgbaPixels[i * 4 + 3];
            if (a < 128) {
                indexed[i] = transIndex >= 0 ? transIndex : 0;
                continue;
            }
            const r = rgbaPixels[i * 4] >> 3;
            const g = rgbaPixels[i * 4 + 1] >> 3;
            const b = rgbaPixels[i * 4 + 2] >> 3;
            const k = (r << 10) | (g << 5) | b;
            let idx = colorMap[k];
            if (idx === -1) {
                let minDist = Infinity;
                let bestIdx = 0;
                for (let pi = 0; pi < sorted.length; pi++) {
                    const pk = sorted[pi];
                    const pr = (pk >> 10) & 31;
                    const pg = (pk >> 5) & 31;
                    const pb = pk & 31;
                    const dist = (r - pr) * (r - pr) + (g - pg) * (g - pg) + (b - pb) * (b - pb);
                    if (dist < minDist) {
                        minDist = dist;
                        bestIdx = colorMap[pk];
                    }
                }
                colorMap[k] = bestIdx;
                idx = bestIdx;
            }
            indexed[i] = idx;
        }
        
        return {
            indexed: indexed,
            palette: palette,
            transparent: transIndex >= 0 ? transIndex : null
        };
    }

    function cropAndEncodeAnimatedGif() {
        if (!window._currentCropBuffer || !window.omggif) {
            throw new Error('GIF data or omggif library not ready');
        }
        const reader = new window.omggif.GifReader(new Uint8Array(window._currentCropBuffer));
        const numFrames = reader.numFrames();
        if (numFrames <= 0) {
            throw new Error('No frames in GIF');
        }

        const origW = reader.width;
        const origH = reader.height;
        const OUT_SIZE = 240; // High resolution circle for avatars
        const dims = getEffectiveDims();
        const scale = getCurrentScale();
        const viewToOutScale = OUT_SIZE / CIRCLE_DIAMETER;

        // Store crop coordinates for server fallback as well
        const cropX = Math.round((CIRCLE_OFFSET - offsetX) / scale);
        const cropY = Math.round((CIRCLE_OFFSET - offsetY) / scale);
        const cropW = Math.round(CIRCLE_DIAMETER / scale);
        const cropH = Math.round(CIRCLE_DIAMETER / scale);
        const hiddenGifCrop = document.getElementById('gif_crop_data');
        if (hiddenGifCrop) {
            hiddenGifCrop.value = JSON.stringify({ x: cropX, y: cropY, w: cropW, h: cropH, outSize: OUT_SIZE });
        }

        const outBuffer = new Uint8Array(numFrames * OUT_SIZE * OUT_SIZE * 2 + 16384);
        const writer = new window.omggif.GifWriter(outBuffer, OUT_SIZE, OUT_SIZE, { loop: 0 });

        const fCanvas = document.createElement('canvas');
        fCanvas.width = origW;
        fCanvas.height = origH;
        const fCtx = fCanvas.getContext('2d');

        const outCanvas = document.createElement('canvas');
        outCanvas.width = OUT_SIZE;
        outCanvas.height = OUT_SIZE;
        const outCtx = outCanvas.getContext('2d');

        const frameRgba = new Uint8Array(origW * origH * 4);
        const frameStep = numFrames > 80 ? 2 : 1;

        for (let f = 0; f < numFrames; f += frameStep) {
            reader.decodeAndBlitFrameRGBA(f, frameRgba);
            const info = reader.frameInfo(f);
            
            const imgData = new ImageData(new Uint8ClampedArray(frameRgba.buffer), origW, origH);
            fCtx.putImageData(imgData, 0, 0);

            outCtx.clearRect(0, 0, OUT_SIZE, OUT_SIZE);
            outCtx.save();
            outCtx.translate(-CIRCLE_OFFSET * viewToOutScale, -CIRCLE_OFFSET * viewToOutScale);
            outCtx.scale(viewToOutScale, viewToOutScale);

            const cx = offsetX + (dims.w * scale) / 2;
            const cy = offsetY + (dims.h * scale) / 2;

            outCtx.translate(cx, cy);
            outCtx.rotate((rotationDeg * Math.PI) / 180);
            outCtx.scale(scale, scale);
            outCtx.drawImage(fCanvas, -origW / 2, -origH / 2);
            outCtx.restore();

            const croppedData = outCtx.getImageData(0, 0, OUT_SIZE, OUT_SIZE);
            const q = quantizeFrame(croppedData.data, OUT_SIZE, OUT_SIZE);

            const delay = (info.delay || 10) * frameStep;
            const frameOpts = {
                delay: Math.max(2, delay),
                palette: q.palette
            };
            if (q.transparent !== null) {
                frameOpts.transparent = q.transparent;
            }

            writer.addFrame(0, 0, OUT_SIZE, OUT_SIZE, q.indexed, frameOpts);
        }

        const finalLen = writer.end();
        const finalBytes = outBuffer.subarray(0, finalLen);

        let binary = '';
        const chunk = 8192;
        for (let i = 0; i < finalLen; i += chunk) {
            binary += String.fromCharCode.apply(null, finalBytes.subarray(i, Math.min(i + chunk, finalLen)));
        }
        const base64Gif = 'data:image/gif;base64,' + btoa(binary);

        const hiddenCrop = document.getElementById('cropped_profile_image');
        if (hiddenCrop) hiddenCrop.value = base64Gif;

        const previewImg = document.getElementById('avatar-preview-img');
        const previewInitial = document.getElementById('avatar-preview-initial');
        if (previewImg) {
            previewImg.src = base64Gif;
            previewImg.style.display = 'block';
        }
        if (previewInitial) previewInitial.style.display = 'none';

        const adjustBtn = document.getElementById('btn-adjust-frame');
        if (adjustBtn) adjustBtn.style.display = 'inline-flex';

        const removeBtn = document.getElementById('btn-remove-photo');
        if (removeBtn) removeBtn.style.display = 'inline-flex';

        const hint = document.getElementById('crop-applied-hint');
        if (hint) {
            hint.innerHTML = '🎬 <strong>Framed animated GIF ready!</strong> All frames centered and animated in your circular avatar. Click <strong>Save Changes</strong> below to apply.';
            hint.style.display = 'block';
        }
    }

    async function cropAndEncodeAnimatedWebp() {
        if (typeof ImageDecoder === 'undefined' || !window._currentCropBuffer || !window.omggif) {
            throw new Error('ImageDecoder or omggif not available');
        }
        const decoder = new ImageDecoder({ data: window._currentCropBuffer, type: 'image/webp' });
        await decoder.tracks.ready;
        const track = decoder.tracks.selectedTrack;
        const numFrames = track.frameCount || 1;
        if (numFrames <= 1) {
            throw new Error('Static WebP');
        }

        const OUT_SIZE = 240;
        const dims = getEffectiveDims();
        const scale = getCurrentScale();
        const viewToOutScale = OUT_SIZE / CIRCLE_DIAMETER;

        const cropX = Math.round((CIRCLE_OFFSET - offsetX) / scale);
        const cropY = Math.round((CIRCLE_OFFSET - offsetY) / scale);
        const cropW = Math.round(CIRCLE_DIAMETER / scale);
        const cropH = Math.round(CIRCLE_DIAMETER / scale);
        const hiddenGifCrop = document.getElementById('gif_crop_data');
        if (hiddenGifCrop) {
            hiddenGifCrop.value = JSON.stringify({ x: cropX, y: cropY, w: cropW, h: cropH, outSize: OUT_SIZE });
        }

        const outBuffer = new Uint8Array(numFrames * OUT_SIZE * OUT_SIZE * 2 + 16384);
        const writer = new window.omggif.GifWriter(outBuffer, OUT_SIZE, OUT_SIZE, { loop: 0 });

        const outCanvas = document.createElement('canvas');
        outCanvas.width = OUT_SIZE;
        outCanvas.height = OUT_SIZE;
        const outCtx = outCanvas.getContext('2d');

        const frameStep = numFrames > 80 ? 2 : 1;

        for (let f = 0; f < numFrames; f += frameStep) {
            const result = await decoder.decode({ frameIndex: f });
            const videoFrame = result.image;
            const frameW = videoFrame.displayWidth || videoFrame.codedWidth;
            const frameH = videoFrame.displayHeight || videoFrame.codedHeight;
            const durationUs = videoFrame.duration || 100000;
            const delayCentis = Math.max(2, Math.round(durationUs / 10000) * frameStep);

            outCtx.clearRect(0, 0, OUT_SIZE, OUT_SIZE);
            outCtx.save();
            outCtx.translate(-CIRCLE_OFFSET * viewToOutScale, -CIRCLE_OFFSET * viewToOutScale);
            outCtx.scale(viewToOutScale, viewToOutScale);

            const cx = offsetX + (dims.w * scale) / 2;
            const cy = offsetY + (dims.h * scale) / 2;

            outCtx.translate(cx, cy);
            outCtx.rotate((rotationDeg * Math.PI) / 180);
            outCtx.scale(scale, scale);
            outCtx.drawImage(videoFrame, -frameW / 2, -frameH / 2);
            outCtx.restore();
            videoFrame.close();

            const croppedData = outCtx.getImageData(0, 0, OUT_SIZE, OUT_SIZE);
            const q = quantizeFrame(croppedData.data, OUT_SIZE, OUT_SIZE);

            const frameOpts = {
                delay: delayCentis,
                palette: q.palette
            };
            if (q.transparent !== null) {
                frameOpts.transparent = q.transparent;
            }

            writer.addFrame(0, 0, OUT_SIZE, OUT_SIZE, q.indexed, frameOpts);
        }

        const finalLen = writer.end();
        const finalBytes = outBuffer.subarray(0, finalLen);

        let binary = '';
        const chunk = 8192;
        for (let i = 0; i < finalLen; i += chunk) {
            binary += String.fromCharCode.apply(null, finalBytes.subarray(i, Math.min(i + chunk, finalLen)));
        }
        const base64Gif = 'data:image/gif;base64,' + btoa(binary);

        const hiddenCrop = document.getElementById('cropped_profile_image');
        if (hiddenCrop) hiddenCrop.value = base64Gif;

        const previewImg = document.getElementById('avatar-preview-img');
        const previewInitial = document.getElementById('avatar-preview-initial');
        if (previewImg) {
            previewImg.src = base64Gif;
            previewImg.style.display = 'block';
        }
        if (previewInitial) previewInitial.style.display = 'none';

        const adjustBtn = document.getElementById('btn-adjust-frame');
        if (adjustBtn) adjustBtn.style.display = 'inline-flex';

        const removeBtn = document.getElementById('btn-remove-photo');
        if (removeBtn) removeBtn.style.display = 'inline-flex';

        const hint = document.getElementById('crop-applied-hint');
        if (hint) {
            hint.innerHTML = '🎬 <strong>Framed animation ready!</strong> All frames centered and animated in your circular avatar. Click <strong>Save Changes</strong> below to apply.';
            hint.style.display = 'block';
        }
    }

    function exportStaticCrop() {
        try {
            const OUT_SIZE = 400;
            const canvas = document.createElement('canvas');
            canvas.width = OUT_SIZE;
            canvas.height = OUT_SIZE;
            const ctx = canvas.getContext('2d');

            const dims = getEffectiveDims();
            const scale = getCurrentScale();
            const viewToOutScale = OUT_SIZE / CIRCLE_DIAMETER;

            const cropX = Math.round((CIRCLE_OFFSET - offsetX) / scale);
            const cropY = Math.round((CIRCLE_OFFSET - offsetY) / scale);
            const cropW = Math.round(CIRCLE_DIAMETER / scale);
            const cropH = Math.round(CIRCLE_DIAMETER / scale);
            const hiddenGifCrop = document.getElementById('gif_crop_data');
            if (hiddenGifCrop) {
                hiddenGifCrop.value = JSON.stringify({ x: cropX, y: cropY, w: cropW, h: cropH, outSize: OUT_SIZE });
            }

            ctx.translate(-CIRCLE_OFFSET * viewToOutScale, -CIRCLE_OFFSET * viewToOutScale);
            ctx.scale(viewToOutScale, viewToOutScale);

            const cx = offsetX + (dims.w * scale) / 2;
            const cy = offsetY + (dims.h * scale) / 2;

            ctx.translate(cx, cy);
            ctx.rotate((rotationDeg * Math.PI) / 180);
            ctx.scale(scale, scale);
            ctx.drawImage(rawImage, -rawImage.naturalWidth / 2, -rawImage.naturalHeight / 2);

            let croppedDataUrl;
            try {
                const srcStr = (rawImage.src || '').toLowerCase();
                const isPngOrWebp = srcStr.includes('.png') || srcStr.includes('.webp') || srcStr.startsWith('data:image/png') || srcStr.startsWith('data:image/webp');
                croppedDataUrl = canvas.toDataURL(isPngOrWebp ? 'image/png' : 'image/jpeg', 0.92);
            } catch (e) {
                croppedDataUrl = canvas.toDataURL('image/jpeg', 0.92);
            }

            const hiddenCrop = document.getElementById('cropped_profile_image');
            if (hiddenCrop) {
                hiddenCrop.value = croppedDataUrl;
            }

            const previewImg = document.getElementById('avatar-preview-img');
            const previewInitial = document.getElementById('avatar-preview-initial');
            if (previewImg) {
                previewImg.src = croppedDataUrl;
                previewImg.style.display = 'block';
            }
            if (previewInitial) {
                previewInitial.style.display = 'none';
            }

            const adjustBtn = document.getElementById('btn-adjust-frame');
            if (adjustBtn) adjustBtn.style.display = 'inline-flex';

            const removeBtn = document.getElementById('btn-remove-photo');
            if (removeBtn) removeBtn.style.display = 'inline-flex';

            const hint = document.getElementById('crop-applied-hint');
            if (hint) {
                hint.innerHTML = '✓ Photo framed nicely! Click <strong>Save Changes</strong> below to apply.';
                hint.style.display = 'block';
            }
        } catch (err) {
            console.error('Frame error:', err);
            alert('Failed to frame picture: ' + err.message);
        }
    }

    window.openInstaCropModal = function(imageSrc, isGif) {
        window._isGifModal = !!isGif;
        imgLoaded = false;
        rawImage = new Image();
        rawImage.crossOrigin = 'anonymous';
        rawImage.onload = function() {
            imgLoaded = true;
            imgEl.src = imageSrc;
            imgEl.style.width = rawImage.naturalWidth + 'px';
            imgEl.style.height = rawImage.naturalHeight + 'px';
            modal.style.display = 'flex';
            resetFraming();
        };
        rawImage.onerror = function() {
            if (rawImage.crossOrigin) {
                rawImage = new Image();
                rawImage.onload = function() {
                    imgLoaded = true;
                    imgEl.src = imageSrc;
                    imgEl.style.width = rawImage.naturalWidth + 'px';
                    imgEl.style.height = rawImage.naturalHeight + 'px';
                    modal.style.display = 'flex';
                    resetFraming();
                };
                rawImage.src = imageSrc;
            }
        };
        rawImage.src = imageSrc;
    };

    window.closeInstaCropModal = function() {
        modal.style.display = 'none';
        const fileInput = document.getElementById('profile_pic_input');
        const hiddenCrop = document.getElementById('cropped_profile_image');
        if (hiddenCrop && !hiddenCrop.value && fileInput && fileInput.files && fileInput.files.length > 0) {
            fileInput.value = '';
            const removeBtn = document.getElementById('btn-remove-photo');
            if (removeBtn && (!window._savedProfilePictureUrl || window._savedProfilePictureUrl === '')) {
                removeBtn.style.display = 'none';
            }
        }
    };

    window.previewAvatar = function(input) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            if (file.size > 10 * 1024 * 1024) {
                alert('File size must be under 10MB.');
                input.value = '';
                return;
            }
            if (!file.type.startsWith('image/') && !file.name.toLowerCase().endsWith('.gif') && !file.name.toLowerCase().endsWith('.webp')) {
                alert('Please select a valid image file (JPG, PNG, WebP, or GIF).');
                input.value = '';
                return;
            }

            const isGif = file.type === 'image/gif' || file.name.toLowerCase().endsWith('.gif');
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const buffer = e.target.result;
                
                // Detect Animated WebP (contains 'ANIM' or 'ANMF' chunk)
                let isAnimWebp = false;
                if (!isGif && (file.type === 'image/webp' || file.name.toLowerCase().endsWith('.webp'))) {
                    const u8 = new Uint8Array(buffer.slice(0, 2048));
                    let headerStr = '';
                    for (let i = 0; i < u8.length; i++) headerStr += String.fromCharCode(u8[i]);
                    if (headerStr.includes('ANIM') || headerStr.includes('ANMF')) {
                        isAnimWebp = true;
                    }
                }

                // Show remove button so user can cancel preview if desired
                const removeBtn = document.getElementById('btn-remove-photo');
                if (removeBtn) removeBtn.style.display = 'inline-flex';

                if (isGif) {
                    window._currentCropBuffer = buffer;
                    window._isWebpAnim = false;
                    const blob = new Blob([buffer], { type: 'image/gif' });
                    const blobUrl = URL.createObjectURL(blob);
                    window.openInstaCropModal(blobUrl, true);
                } else if (isAnimWebp) {
                    window._currentCropBuffer = buffer;
                    window._isWebpAnim = true;
                    const blob = new Blob([buffer], { type: 'image/webp' });
                    const blobUrl = URL.createObjectURL(blob);
                    window.openInstaCropModal(blobUrl, true);
                } else {
                    window._currentCropBuffer = null;
                    window._isWebpAnim = false;
                    const blob = new Blob([buffer], { type: file.type || 'image/png' });
                    const blobUrl = URL.createObjectURL(blob);
                    window.openInstaCropModal(blobUrl, false);
                }
            };
            reader.readAsArrayBuffer(file);
        }
    };

    window.openCropperWithCurrentPhoto = function() {
        const previewImg = document.getElementById('avatar-preview-img');
        if (previewImg && previewImg.src && previewImg.style.display !== 'none' && previewImg.src.trim() !== '') {
            const src = previewImg.src;
            const isGif = src.startsWith('data:image/gif') || src.toLowerCase().includes('.gif');

            if (src.startsWith('data:') || src.startsWith('blob:')) {
                if (isGif && window._currentCropBuffer) {
                    window.openInstaCropModal(src, true);
                } else {
                    window.openInstaCropModal(src, false);
                }
                return;
            }

            // Remote image: Fetch into blob URL to eliminate CORS tainted canvas restrictions
            fetch(src, { cache: 'no-cache' })
                .then(res => {
                    if (!res.ok) throw new Error('Fetch failed');
                    return res.blob();
                })
                .then(blob => blob.arrayBuffer().then(buf => {
                    const blobType = blob.type || '';
                    const isBlobGif = isGif || blobType === 'image/gif';
                    const blobUrl = URL.createObjectURL(blob);
                    if (isBlobGif) {
                        window._currentCropBuffer = buf;
                        window._isWebpAnim = false;
                        window.openInstaCropModal(blobUrl, true);
                    } else {
                        const u8 = new Uint8Array(buf.slice(0, 2048));
                        let headerStr = '';
                        for (let i = 0; i < u8.length; i++) headerStr += String.fromCharCode(u8[i]);
                        const isAnimWebp = headerStr.includes('ANIM') || headerStr.includes('ANMF');
                        if (isAnimWebp) {
                            window._currentCropBuffer = buf;
                            window._isWebpAnim = true;
                            window.openInstaCropModal(blobUrl, true);
                        } else {
                            window._currentCropBuffer = null;
                            window._isWebpAnim = false;
                            window.openInstaCropModal(blobUrl, false);
                        }
                    }
                }))
                .catch(err => {
                    console.warn('Blob fetch fallback to direct src:', err);
                    window.openInstaCropModal(src, isGif);
                });
        } else {
            document.getElementById('profile_pic_input').click();
        }
    };

    slider.addEventListener('input', function() {
        setZoom(parseFloat(this.value));
    });

    btnZoomIn.addEventListener('click', function(e) {
        e.preventDefault();
        setZoom(currentZoomMultiplier + 0.15);
    });

    btnZoomOut.addEventListener('click', function(e) {
        e.preventDefault();
        setZoom(currentZoomMultiplier - 0.15);
    });

    btnRotate.addEventListener('click', function(e) {
        e.preventDefault();
        rotationDeg = (rotationDeg + 90) % 360;
        baseScale = computeBaseScale();
        const dims = getEffectiveDims();
        const scale = getCurrentScale();
        offsetX = (STAGE_SIZE - dims.w * scale) / 2;
        offsetY = (STAGE_SIZE - dims.h * scale) / 2;
        updateTransform();
    });

    btnCancel.addEventListener('click', function(e) {
        e.preventDefault();
        window.closeInstaCropModal();
    });

    btnClose.addEventListener('click', function(e) {
        e.preventDefault();
        window.closeInstaCropModal();
    });

    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            window.closeInstaCropModal();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'flex') {
            window.closeInstaCropModal();
        }
    });

    viewport.addEventListener('mousedown', function(e) {
        if (!imgLoaded) return;
        isDragging = true;
        dragStartX = e.clientX;
        dragStartY = e.clientY;
        dragStartOffsetX = offsetX;
        dragStartOffsetY = offsetY;
    });

    window.addEventListener('mousemove', function(e) {
        if (!isDragging) return;
        const dx = e.clientX - dragStartX;
        const dy = e.clientY - dragStartY;
        offsetX = dragStartOffsetX + dx;
        offsetY = dragStartOffsetY + dy;
        updateTransform();
    });

    window.addEventListener('mouseup', function() {
        isDragging = false;
    });

    viewport.addEventListener('touchstart', function(e) {
        if (!imgLoaded) return;
        if (e.touches.length === 1) {
            isDragging = true;
            dragStartX = e.touches[0].clientX;
            dragStartY = e.touches[0].clientY;
            dragStartOffsetX = offsetX;
            dragStartOffsetY = offsetY;
        } else if (e.touches.length === 2) {
            isDragging = false;
            const t1 = e.touches[0];
            const t2 = e.touches[1];
            initialPinchDist = Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY);
            initialPinchZoom = currentZoomMultiplier;
        }
    }, { passive: false });

    viewport.addEventListener('touchmove', function(e) {
        if (!imgLoaded) return;
        e.preventDefault();
        if (e.touches.length === 1 && isDragging) {
            const dx = e.touches[0].clientX - dragStartX;
            const dy = e.touches[0].clientY - dragStartY;
            offsetX = dragStartOffsetX + dx;
            offsetY = dragStartOffsetY + dy;
            updateTransform();
        } else if (e.touches.length === 2) {
            const t1 = e.touches[0];
            const t2 = e.touches[1];
            const dist = Math.hypot(t2.clientX - t1.clientX, t2.clientY - t1.clientY);
            if (initialPinchDist > 0) {
                const factor = dist / initialPinchDist;
                setZoom(initialPinchZoom * factor);
            }
        }
    }, { passive: false });

    viewport.addEventListener('touchend', function(e) {
        if (e.touches.length < 2) {
            initialPinchDist = 0;
        }
        if (e.touches.length === 0) {
            isDragging = false;
        }
    });

    viewport.addEventListener('wheel', function(e) {
        if (!imgLoaded) return;
        e.preventDefault();
        const rect = viewport.getBoundingClientRect();
        const pt = { x: e.clientX - rect.left, y: e.clientY - rect.top };
        const delta = e.deltaY < 0 ? 0.08 : -0.08;
        setZoom(currentZoomMultiplier + delta, pt);
    }, { passive: false });

    btnApply.addEventListener('click', function(e) {
        e.preventDefault();
        if (!imgLoaded) return;

        // Animated WebP with ImageDecoder
        if (window._isWebpAnim && typeof ImageDecoder !== 'undefined' && window._currentCropBuffer && window.omggif) {
            btnApply.disabled = true;
            const originalText = btnApply.innerHTML;
            btnApply.innerHTML = '⏳ Framing Animation...';

            setTimeout(async function() {
                try {
                    await cropAndEncodeAnimatedWebp();
                    modal.style.display = 'none';
                } catch (err) {
                    console.error('WebP animation crop error:', err);
                    exportStaticCrop();
                    modal.style.display = 'none';
                } finally {
                    btnApply.disabled = false;
                    btnApply.innerHTML = originalText;
                }
            }, 30);
            return;
        }

        // Animated GIF Crop Mode
        if (window._isGifModal && window.omggif && window._currentCropBuffer) {
            btnApply.disabled = true;
            const originalText = btnApply.innerHTML;
            btnApply.innerHTML = '⏳ Framing GIF...';

            setTimeout(function() {
                try {
                    cropAndEncodeAnimatedGif();
                    modal.style.display = 'none';
                } catch (err) {
                    console.error('GIF crop error:', err);
                    exportStaticCrop();
                    modal.style.display = 'none';
                } finally {
                    btnApply.disabled = false;
                    btnApply.innerHTML = originalText;
                }
            }, 30);
            return;
        }

        // Static Image (JPG, PNG, WebP) Crop Mode
        exportStaticCrop();
        modal.style.display = 'none';
    });
})();
</script>
</body>
</html>
