<?php
include_once __DIR__ . '/db_connect.php';
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}
/** @var mysqli $conn */

$current_emp_id = $_SESSION['emp_id'] ?? '';
$current_role   = $_SESSION['role'] ?? 'employee';

// Fetch current user details
$cur_user_query = mysqli_query($conn, "SELECT name, role, department FROM employees WHERE emp_id = '" . mysqli_real_escape_string($conn, $current_emp_id) . "' LIMIT 1");
$cur_user = mysqli_fetch_assoc($cur_user_query);
$current_user_name = $cur_user['name'] ?? 'Team Member';
$current_department = $cur_user['department'] ?? 'General';

// Helper: Initials
function get_initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
    return strtoupper(mb_substr($name, 0, 2) ?: 'WR');
}

// Helper: Relative time
function get_relative_time($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . "m ago";
    if ($diff < 86400) return floor($diff / 3600) . "h ago";
    if ($diff < 604800) return floor($diff / 86400) . "d ago";
    return date("M j, Y", $time);
}

// Check moderation permissions (Admin / HR / TL can moderate any post/comment)
$is_moderator = in_array(strtolower($current_role), ['admin', 'hr', 'tl']);

// Helper: Friendly Name (extracts personal name before BIN, BINTI, A/L, A/P)
function get_friendly_name($full_name) {
    $clean = trim($full_name);
    if (preg_match('/^(.*?)\s+(BIN|BINTI|A\/L|A\/P)\s+/i', $clean, $m)) {
        return ucwords(strtolower(trim($m[1])));
    }
    return ucwords(strtolower($clean));
}

// ============================================================================
// EMPLOYEE MENTIONS MAP & DATA PREPARATION
// ============================================================================
$mention_map = [];
$mention_employees_js = [];

$emp_list_res = mysqli_query($conn, "SELECT emp_id, name, role, department, profile_picture FROM employees WHERE emp_id NOT LIKE 'TEST%' ORDER BY name ASC");
if ($emp_list_res) {
    $nickname_map = [
        'JSMY014' => 'Serene',
        'JSMY003' => 'Mageswary',
        'JSMY021' => 'Salma',
        'JSMY020' => 'Thananiy',
        'JSMY030' => 'Shivanesha',
        'JSMY022' => 'Dania',
        'TL001' => 'Serene',
        'GL001' => 'Mageswary',
        'GL002' => 'Salma',
        'GL003' => 'Thananiy',
        'GL004' => 'Shivanesha',
        'WR025' => 'Dania'
    ];

    while ($e = mysqli_fetch_assoc($emp_list_res)) {
        $eid = strtoupper(trim($e['emp_id']));
        $raw_name = trim($e['name']);
        $friendly = get_friendly_name($raw_name);
        $role_formatted = ucwords(strtolower(trim($e['role'] ?? 'Staff')));
        $initials = get_initials($raw_name);

        $emp_entry = [
            'emp_id' => $e['emp_id'],
            'name' => $raw_name,
            'friendly_name' => $friendly,
            'role' => $role_formatted,
            'initials' => $initials
        ];

        // Map by exact Employee ID
        $mention_map[$eid] = $emp_entry;

        // Map by single friendly word or nickname if unique
        $friendly_first = strtoupper(explode(' ', $friendly)[0] ?? '');
        if (!empty($friendly_first) && !isset($mention_map[$friendly_first])) {
            $mention_map[$friendly_first] = $emp_entry;
        }

        if (isset($nickname_map[$eid])) {
            $mention_map[strtoupper($nickname_map[$eid])] = $emp_entry;
        }

        $mention_employees_js[] = [
            'id' => $e['emp_id'],
            'name' => $friendly,
            'fullName' => $raw_name,
            'role' => $role_formatted,
            'initials' => $initials,
            'pic' => (!empty($e['profile_picture']) && file_exists($e['profile_picture'])) ? $e['profile_picture'] : '',
            'searchKey' => strtolower($e['emp_id'] . ' ' . $raw_name . ' ' . $friendly . ' ' . $role_formatted . ' ' . ($nickname_map[$eid] ?? ''))
        ];
    }
}

// Function to format post/comment content with styled @mention pills
function format_post_content($raw_text, $emp_map) {
    if (empty($raw_text)) return '';
    $text = htmlspecialchars($raw_text, ENT_QUOTES, 'UTF-8');
    $text = nl2br($text);

    $text = preg_replace_callback('/@([A-Za-z0-9_-]+)/', function($matches) use ($emp_map) {
        $tag = strtoupper($matches[1]);
        if (isset($emp_map[$tag])) {
            $emp = $emp_map[$tag];
            $display = htmlspecialchars($emp['friendly_name'], ENT_QUOTES, 'UTF-8');
            $emp_id = htmlspecialchars($emp['emp_id'], ENT_QUOTES, 'UTF-8');
            $full_name = htmlspecialchars($emp['name'], ENT_QUOTES, 'UTF-8');
            $role = htmlspecialchars($emp['role'], ENT_QUOTES, 'UTF-8');
            return '<span class="ci-mention" data-emp-id="' . $emp_id . '" title="' . $emp_id . ' • ' . $full_name . ' (' . $role . ')">@' . $display . '</span>';
        }
        return $matches[0];
    }, $text);

    return $text;
}

// Function to notify mentioned users
function send_mention_notifications($conn, $content, $emp_map, $sender_id, $sender_name, $context_type = 'post') {
    if (empty($content)) return;
    preg_match_all('/@([A-Za-z0-9_-]+)/', $content, $matches);
    if (empty($matches[1])) return;

    $notified = [];
    $snippet = mb_substr(strip_tags($content), 0, 75);
    if (mb_strlen(strip_tags($content)) > 75) $snippet .= '...';

    foreach ($matches[1] as $tag_raw) {
        $tag = strtoupper($tag_raw);
        if (isset($emp_map[$tag])) {
            $target_id = $emp_map[$tag]['emp_id'];
            if ($target_id === $sender_id || isset($notified[$target_id])) continue;
            $notified[$target_id] = true;

            $msg = ($context_type === 'comment')
                ? $sender_name . " mentioned you in a comment: \"" . $snippet . "\""
                : $sender_name . " mentioned you in a WeRide Insides post: \"" . $snippet . "\"";

            $stmt = mysqli_prepare($conn, "INSERT INTO employee_notifications (emp_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "ss", $target_id, $msg);
                mysqli_stmt_execute($stmt);
            }

            // Direct Push Notification with Company Logo
            if (file_exists(__DIR__ . '/vapid_config.php')) {
                require_once __DIR__ . '/vapid_config.php';
                WebPushSender::sendToEmployee($conn, $target_id, "WeRide Insides", $msg, "weride_insides.php", "feed-mention-" . time());
            }
        }
    }
}

// ============================================================================
// AJAX ACTION HANDLERS
// ============================================================================
$action = $_GET['action'] ?? $_POST['action'] ?? null;
if ($action) {
    header('Content-Type: application/json; charset=utf-8');

    // 1. CREATE POST
    if ($action === 'create_post') {
        $content = trim($_POST['content'] ?? '');
        $media_type = 'none';
        $media_url = null;

        // Process file upload if provided
        if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['media'];
            $file_name = $file['name'];
            $file_tmp  = $file['tmp_name'];
            $file_size = $file['size'];
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

            $photo_exts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $video_exts = ['mp4', 'webm', 'mov'];

            if (in_array($ext, $photo_exts)) {
                // PHOTO HANDLING
                $media_type = 'photo';
                $dest_dir = __DIR__ . '/uploads/community/photos/';
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                }
                $new_filename = 'photo_' . time() . '_' . bin2hex(random_bytes(6)) . '.jpg';
                $dest_path = $dest_dir . $new_filename;

                // Auto-compress photo using GD if available
                $compressed = false;
                if (extension_loaded('gd')) {
                    $src_img = null;
                    if ($ext === 'jpg' || $ext === 'jpeg') {
                        $src_img = @imagecreatefromjpeg($file_tmp);
                    } elseif ($ext === 'png') {
                        $src_img = @imagecreatefrompng($file_tmp);
                    } elseif ($ext === 'webp') {
                        $src_img = @imagecreatefromwebp($file_tmp);
                    }

                    if ($src_img) {
                        $orig_w = imagesx($src_img);
                        $orig_h = imagesy($src_img);
                        $max_w = 1400;

                        if ($orig_w > $max_w) {
                            $new_w = $max_w;
                            $new_h = intval($orig_h * ($max_w / $orig_w));
                            $scaled = imagecreatetruecolor($new_w, $new_h);
                            imagecopyresampled($scaled, $src_img, 0, 0, 0, 0, $new_w, $new_h, $orig_w, $orig_h);
                            imagedestroy($src_img);
                            $src_img = $scaled;
                        }
                        imagejpeg($src_img, $dest_path, 82);
                        imagedestroy($src_img);
                        $compressed = true;
                    }
                }

                if (!$compressed) {
                    move_uploaded_file($file_tmp, $dest_path);
                }
                $media_url = 'uploads/community/photos/' . $new_filename;

            } elseif (in_array($ext, $video_exts)) {
                // VIDEO HANDLING (Capped at 20MB)
                $max_video_size = 20 * 1024 * 1024; // 20MB
                if ($file_size > $max_video_size) {
                    echo json_encode(['success' => false, 'message' => 'Video exceeds 20MB limit. Please choose a smaller clip.']);
                    exit();
                }

                $media_type = 'video';
                $dest_dir = __DIR__ . '/uploads/community/videos/';
                if (!is_dir($dest_dir)) {
                    mkdir($dest_dir, 0755, true);
                }
                $new_filename = 'video_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . ($ext === 'mov' ? 'mp4' : $ext);
                $dest_path = $dest_dir . $new_filename;

                if (move_uploaded_file($file_tmp, $dest_path)) {
                    $media_url = 'uploads/community/videos/' . $new_filename;
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save video file.']);
                    exit();
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Unsupported file type. Use JPG, PNG, WEBP or MP4.']);
                exit();
            }
        }

        if (empty($content) && empty($media_url)) {
            echo json_encode(['success' => false, 'message' => 'Post cannot be empty.']);
            exit();
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO community_posts (emp_id, content, media_type, media_url) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssss", $current_emp_id, $content, $media_type, $media_url);
        if (mysqli_stmt_execute($stmt)) {
            // Send notifications to any mentioned users
            send_mention_notifications($conn, $content, $mention_map, $current_emp_id, $current_user_name, 'post');

            // Broadcast push notification to all employees
            if (file_exists(__DIR__ . '/vapid_config.php')) {
                require_once __DIR__ . '/vapid_config.php';
                $snippet = mb_substr(strip_tags($content), 0, 70);
                if (mb_strlen(strip_tags($content)) > 70) $snippet .= '...';
                $push_body = !empty($snippet) ? "$current_user_name: \"$snippet\"" : "$current_user_name shared a photo/video update.";

                WebPushSender::sendToEmployee($conn, 'all', "WeRide Insides", $push_body, "weride_insides.php", "feed-post-" . time());
            }

            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error creating post.']);
        }
        exit();
    }

    // 2. TOGGLE LIKE
    if ($action === 'toggle_like' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $post_id = intval($_POST['post_id'] ?? 0);
        if ($post_id <= 0) {
            echo json_encode(['success' => false]);
            exit();
        }

        $check = mysqli_query($conn, "SELECT id FROM community_likes WHERE post_id = $post_id AND emp_id = '" . mysqli_real_escape_string($conn, $current_emp_id) . "'");
        $liked = false;
        if (mysqli_num_rows($check) > 0) {
            mysqli_query($conn, "DELETE FROM community_likes WHERE post_id = $post_id AND emp_id = '" . mysqli_real_escape_string($conn, $current_emp_id) . "'");
            $liked = false;
        } else {
            mysqli_query($conn, "INSERT INTO community_likes (post_id, emp_id) VALUES ($post_id, '" . mysqli_real_escape_string($conn, $current_emp_id) . "')");
            $liked = true;

            // Notify post author if not self
            $p_author_res = mysqli_query($conn, "SELECT emp_id FROM community_posts WHERE id = $post_id LIMIT 1");
            if ($p_author_res && ($p_row = mysqli_fetch_assoc($p_author_res))) {
                $post_author = $p_row['emp_id'];
                if ($post_author !== $current_emp_id) {
                    $like_msg = "$current_user_name liked your post in WeRide Insides.";
                    $n_stmt = mysqli_prepare($conn, "INSERT INTO employee_notifications (emp_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                    if ($n_stmt) {
                        mysqli_stmt_bind_param($n_stmt, "ss", $post_author, $like_msg);
                        mysqli_stmt_execute($n_stmt);
                    }
                    if (file_exists(__DIR__ . '/vapid_config.php')) {
                        require_once __DIR__ . '/vapid_config.php';
                        WebPushSender::sendToEmployee($conn, $post_author, "WeRide Insides", $like_msg, "weride_insides.php", "feed-like-" . $post_id);
                    }
                }
            }
        }

        $count_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM community_likes WHERE post_id = $post_id"));
        echo json_encode([
            'success' => true,
            'liked' => $liked,
            'likes_count' => (int)$count_res['cnt']
        ]);
        exit();
    }

    // 3. ADD COMMENT
    if ($action === 'add_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $post_id = intval($_POST['post_id'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');

        if ($post_id <= 0 || empty($comment)) {
            echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
            exit();
        }

        $stmt = mysqli_prepare($conn, "INSERT INTO community_comments (post_id, emp_id, content) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "iss", $post_id, $current_emp_id, $comment);
        if (mysqli_stmt_execute($stmt)) {
            $new_id = mysqli_insert_id($conn);
            // Send notifications to mentioned users
            send_mention_notifications($conn, $comment, $mention_map, $current_emp_id, $current_user_name, 'comment');

            // Notify post author if not commenting on own post
            $p_author_res = mysqli_query($conn, "SELECT emp_id FROM community_posts WHERE id = $post_id LIMIT 1");
            if ($p_author_res && ($p_row = mysqli_fetch_assoc($p_author_res))) {
                $post_author = $p_row['emp_id'];
                if ($post_author !== $current_emp_id) {
                    $comm_snippet = mb_substr(strip_tags($comment), 0, 75);
                    if (mb_strlen(strip_tags($comment)) > 75) $comm_snippet .= '...';
                    $comment_msg = "$current_user_name commented on your post: \"$comm_snippet\"";
                    $n_stmt = mysqli_prepare($conn, "INSERT INTO employee_notifications (emp_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
                    if ($n_stmt) {
                        mysqli_stmt_bind_param($n_stmt, "ss", $post_author, $comment_msg);
                        mysqli_stmt_execute($n_stmt);
                    }
                    if (file_exists(__DIR__ . '/vapid_config.php')) {
                        require_once __DIR__ . '/vapid_config.php';
                        WebPushSender::sendToEmployee($conn, $post_author, "WeRide Insides", $comment_msg, "weride_insides.php", "feed-comment-" . time());
                    }
                }
            }

            $count_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM community_comments WHERE post_id = $post_id"));
            echo json_encode([
                'success' => true,
                'comment' => [
                    'id' => $new_id,
                    'name' => htmlspecialchars($current_user_name),
                    'initials' => get_initials($current_user_name),
                    'pic' => (!empty($current_user_pic) && file_exists($current_user_pic)) ? $current_user_pic : '',
                    'content' => format_post_content($comment, $mention_map),
                    'time' => 'Just now',
                    'can_delete' => true
                ],
                'comments_count' => (int)$count_res['cnt']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding comment.']);
        }
        exit();
    }

    // 4. DELETE POST
    if ($action === 'delete_post' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $post_id = intval($_POST['post_id'] ?? 0);
        $post_q = mysqli_query($conn, "SELECT emp_id, media_url FROM community_posts WHERE id = $post_id");
        $post = mysqli_fetch_assoc($post_q);

        if (!$post) {
            echo json_encode(['success' => false, 'message' => 'Post not found.']);
            exit();
        }

        // Must be author or moderator
        if ($post['emp_id'] !== $current_emp_id && !$is_moderator) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit();
        }

        // Delete media file if exists
        if (!empty($post['media_url']) && file_exists(__DIR__ . '/' . $post['media_url'])) {
            @unlink(__DIR__ . '/' . $post['media_url']);
        }

        mysqli_query($conn, "DELETE FROM community_comments WHERE post_id = $post_id");
        mysqli_query($conn, "DELETE FROM community_likes WHERE post_id = $post_id");
        mysqli_query($conn, "DELETE FROM community_posts WHERE id = $post_id");

        echo json_encode(['success' => true]);
        exit();
    }

    // 5. DELETE COMMENT
    if ($action === 'delete_comment' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $comment_id = intval($_POST['comment_id'] ?? 0);
        $comm_q = mysqli_query($conn, "SELECT post_id, emp_id FROM community_comments WHERE id = $comment_id");
        $comm = mysqli_fetch_assoc($comm_q);

        if (!$comm) {
            echo json_encode(['success' => false, 'message' => 'Comment not found.']);
            exit();
        }

        if ($comm['emp_id'] !== $current_emp_id && !$is_moderator) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
            exit();
        }

        mysqli_query($conn, "DELETE FROM community_comments WHERE id = $comment_id");
        $count_res = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM community_comments WHERE post_id = " . intval($comm['post_id'])));
        echo json_encode(['success' => true, 'comments_count' => (int)$count_res['cnt']]);
        exit();
    }

    // 6. MARK NOTIFICATIONS AS READ
    if ($action === 'mark_notifications_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        mysqli_query($conn, "UPDATE employee_notifications SET is_read = 1 WHERE emp_id = '" . mysqli_real_escape_string($conn, $current_emp_id) . "'");
        echo json_encode(['success' => true]);
        exit();
    }

    // 7. REAL-TIME HEARTBEAT & LIVE ONLINE COUNT
    if ($action === 'heartbeat') {
        if (!empty($current_emp_id)) {
            mysqli_query($conn, "UPDATE employees SET last_activity = NOW() WHERE emp_id = '" . mysqli_real_escape_string($conn, $current_emp_id) . "'");
        }

        $online_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM employees WHERE last_activity >= NOW() - INTERVAL 150 SECOND");
        $online_cnt = (int)(mysqli_fetch_assoc($online_res)['cnt'] ?? 0);
        if ($online_cnt === 0 && !empty($current_emp_id)) $online_cnt = 1;

        $notif_cnt_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM employee_notifications WHERE emp_id = '" . mysqli_real_escape_string($conn, $current_emp_id) . "' AND is_read = 0");
        $unread_cnt = (int)(mysqli_fetch_assoc($notif_cnt_res)['cnt'] ?? 0);

        echo json_encode([
            'success' => true,
            'online_count' => $online_cnt,
            'unread_notifications' => $unread_cnt
        ]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    exit();
}

// ============================================================================
// DATA FETCHING FOR FEED VIEW
// ============================================================================
// Record current user activity
$curr_user_pic_res = @mysqli_query($conn, "SELECT profile_picture FROM employees WHERE emp_id = '" . mysqli_real_escape_string($conn, $current_emp_id) . "' LIMIT 1");
$current_user_pic = ($curr_user_pic_res && $upr = mysqli_fetch_assoc($curr_user_pic_res)) ? $upr['profile_picture'] : null;

if (!empty($current_emp_id)) {
    mysqli_query($conn, "UPDATE employees SET last_activity = NOW() WHERE emp_id = '" . mysqli_real_escape_string($conn, $current_emp_id) . "'");
}

// Real-time online count (active within last 2.5 minutes)
$online_res = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM employees WHERE last_activity >= NOW() - INTERVAL 150 SECOND");
$online_count = (int)(mysqli_fetch_assoc($online_res)['cnt'] ?? 0);
if ($online_count === 0 && !empty($current_emp_id)) $online_count = 1;

$total_posts_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM community_posts"))['cnt'] ?? 0;
$total_likes_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM community_likes"))['cnt'] ?? 0;
$total_comments_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM community_comments"))['cnt'] ?? 0;
$total_employees_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM employees"))['cnt'] ?? 0;

// Fetch Posts with Employee data
$posts_query = "
    SELECT p.id, p.emp_id, p.content, p.media_type, p.media_url, p.created_at,
           e.name as author_name, e.department as author_dept, e.role as author_role, e.profile_picture as author_pic,
           (SELECT COUNT(*) FROM community_likes l WHERE l.post_id = p.id) as likes_count,
           (SELECT COUNT(*) FROM community_likes l2 WHERE l2.post_id = p.id AND l2.emp_id = '" . mysqli_real_escape_string($conn, $current_emp_id) . "') as has_liked,
           (SELECT COUNT(*) FROM community_comments c WHERE c.post_id = p.id) as comments_count
    FROM community_posts p
    LEFT JOIN employees e ON p.emp_id = e.emp_id
    ORDER BY p.created_at DESC
    LIMIT 60
";
$posts_res = mysqli_query($conn, $posts_query);
$posts = [];
$post_ids = [];
if ($posts_res) {
    while ($row = mysqli_fetch_assoc($posts_res)) {
        $posts[] = $row;
        $post_ids[] = (int)$row['id'];
    }
}

// Fetch Comments for all retrieved posts
$comments_by_post = [];
if (!empty($post_ids)) {
    $ids_in = implode(',', $post_ids);
    $comments_res = mysqli_query($conn, "
        SELECT c.id, c.post_id, c.emp_id, c.content, c.created_at,
               e.name as author_name, e.profile_picture as author_pic
        FROM community_comments c
        LEFT JOIN employees e ON c.emp_id = e.emp_id
        WHERE c.post_id IN ($ids_in)
        ORDER BY c.created_at ASC
    ");
    if ($comments_res) {
        while ($cr = mysqli_fetch_assoc($comments_res)) {
            $comments_by_post[$cr['post_id']][] = $cr;
        }
    }
}

// Upcoming Holidays for the widget
$holidays_res = mysqli_query($conn, "SELECT holiday_date, holiday_name FROM public_holidays WHERE holiday_date >= CURDATE() ORDER BY holiday_date ASC LIMIT 3");
$upcoming_holidays = [];
if ($holidays_res) {
    while ($h = mysqli_fetch_assoc($holidays_res)) {
        $upcoming_holidays[] = $h;
    }
}

// Fetch notifications for current user
$notif_q = mysqli_query($conn, "
    SELECT id, message, is_read, created_at 
    FROM employee_notifications 
    WHERE emp_id = '" . mysqli_real_escape_string($conn, $current_emp_id) . "'
    ORDER BY created_at DESC 
    LIMIT 12
");
$notifications = [];
$unread_notifs_cnt = 0;
if ($notif_q) {
    while ($n = mysqli_fetch_assoc($notif_q)) {
        $notifications[] = $n;
        if ((int)$n['is_read'] === 0) $unread_notifs_cnt++;
    }
}

$user_initials = get_initials($current_user_name);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" type="image/png" href="pic/logo_only.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <title>WeRide Insides - Company Community</title>
    <?php include_once __DIR__ . '/pwa_head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,600;0,9..40,700;0,9..40,800;1,9..40,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dark_mode.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/global_fixes.css?v=<?php echo time(); ?>">
    <script src="assets/js/theme_toggle.js"></script>

    <style>
        :root {
            --ci-font: 'DM Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            --ci-bg: #f1f5f9;
            --ci-card: #ffffff;
            --ci-card-sec: #f8fafc;
            --ci-text: #0f172a;
            --ci-muted: #64748b;
            --ci-border: #e2e8f0;
            --ci-accent: #00a6d6;
            --ci-accent-dark: #075273;
            --ci-accent-bright: #32d5e8;
            --ci-accent-soft: rgba(0, 166, 214, 0.1);
        }

        body.dark-mode {
            --ci-bg: #0b131e;
            --ci-card: #152232;
            --ci-card-sec: #101a26;
            --ci-text: #f1f5f9;
            --ci-muted: #94a3b8;
            --ci-border: #223447;
            --ci-accent: #32d5e8;
            --ci-accent-dark: #00a6d6;
            --ci-accent-bright: #67e8f9;
            --ci-accent-soft: rgba(50, 213, 232, 0.15);
        }

        * {
            box-sizing: border-box;
            font-family: var(--ci-font);
        }

        body {
            margin: 0;
            display: flex;
            height: 100vh;
            background-color: var(--ci-bg);
            color: var(--ci-text);
            overflow-x: hidden;
        }

        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow-y: auto;
            min-width: 0;
        }

        .ci-topbar {
            padding: 24px 36px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid var(--ci-border);
            background: var(--ci-card);
        }

        .ci-breadcrumb {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 2px;
            color: var(--ci-muted);
            text-transform: uppercase;
        }

        .ci-topbar h1 {
            margin: 4px 0 0;
            font-size: 24px;
            font-weight: 800;
            color: var(--ci-text);
        }

        .ci-topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .ci-online-badge {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 12px;
            font-weight: 600;
            color: var(--ci-muted);
        }

        .ci-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 8px rgba(16, 185, 129, 0.5);
        }

        /* LIVE STEADY LIGHT INDICATOR */
        .ci-live-beacon {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
            box-shadow: 0 0 6px rgba(16, 185, 129, 0.7);
            flex-shrink: 0;
            vertical-align: middle;
        }

        .ci-stat-card.ci-stat-online {
            border-color: rgba(16, 185, 129, 0.25);
            background: linear-gradient(180deg, rgba(16, 185, 129, 0.05) 0%, var(--ci-card) 100%);
        }

        .ci-stat-card.ci-stat-online strong {
            color: #10b981;
        }

        /* MENTIONS PILL */
        .ci-mention {
            display: inline-flex;
            align-items: center;
            background: var(--ci-accent-soft);
            color: var(--ci-accent);
            font-weight: 600;
            padding: 1px 7px;
            border-radius: 6px;
            font-size: 13px;
            border: 1px solid rgba(0, 166, 214, 0.25);
            margin: 0 2px;
            cursor: pointer;
            transition: all 0.15s ease;
            vertical-align: baseline;
            text-decoration: none;
        }

        .ci-mention:hover {
            background: var(--ci-accent);
            color: #ffffff;
            border-color: var(--ci-accent);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 166, 214, 0.25);
        }

        /* MENTION AUTOCOMPLETE DROPDOWN */
        .ci-mention-dropdown {
            position: absolute;
            z-index: 9999;
            background: var(--ci-card);
            border: 1px solid var(--ci-border);
            border-radius: 12px;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.2);
            max-height: 250px;
            overflow-y: auto;
            width: 290px;
            padding: 6px;
        }

        .ci-mention-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: background 0.12s ease;
        }

        .ci-mention-item:hover,
        .ci-mention-item.selected {
            background: var(--ci-accent-soft);
        }

        .ci-mention-avatar {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #00a6d6, #32d5e8);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .ci-mention-info {
            flex: 1;
            min-width: 0;
        }

        .ci-mention-name {
            font-size: 13px;
            font-weight: 600;
            color: var(--ci-text);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .ci-mention-meta {
            font-size: 11px;
            color: var(--ci-muted);
            margin-top: 1px;
        }

        .ci-mention-id-tag {
            font-weight: 700;
            color: var(--ci-accent);
        }

        /* NOTIFICATION BELL & DROPDOWN */
        .ci-notif-wrapper {
            position: relative;
            display: inline-block;
            margin-right: 8px;
        }

        .ci-notif-btn {
            position: relative;
            background: var(--ci-card);
            border: 1px solid var(--ci-border);
            color: var(--ci-text);
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .ci-notif-btn:hover {
            background: var(--ci-accent-soft);
            border-color: var(--ci-accent);
            color: var(--ci-accent);
        }

        .ci-notif-btn.spinning svg {
            animation: ciSpin 0.7s linear infinite;
        }

        @keyframes ciSpin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }

        .ci-notif-badge {
            position: absolute;
            top: -5px;
            right: -6px;
            background: #ef4444;
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            height: 18px;
            min-width: 18px;
            padding: 0 5px;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--ci-card);
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.45);
            z-index: 2;
        }

        .ci-notif-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            width: 330px;
            max-height: 400px;
            background: var(--ci-card);
            border: 1px solid var(--ci-border);
            border-radius: 14px;
            box-shadow: 0 12px 36px rgba(0, 0, 0, 0.2);
            z-index: 10000;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            text-align: left;
        }

        @media (max-width: 640px) {
            .ci-notif-dropdown {
                position: fixed !important;
                top: 72px;
                left: 12px !important;
                right: 12px !important;
                width: auto !important;
                max-width: calc(100vw - 24px) !important;
                max-height: 70vh !important;
                z-index: 999999 !important;
                box-shadow: 0 16px 40px rgba(0, 0, 0, 0.45) !important;
            }
        }

        .ci-notif-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 16px;
            border-bottom: 1px solid var(--ci-border);
        }

        .ci-notif-header h4 {
            margin: 0;
            font-size: 14px;
            font-weight: 700;
            color: var(--ci-text);
        }

        .ci-notif-mark {
            background: none;
            border: none;
            color: var(--ci-accent);
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            padding: 0;
        }

        .ci-notif-mark:hover {
            text-decoration: underline;
        }

        .ci-notif-list {
            overflow-y: auto;
            max-height: 330px;
        }

        .ci-notif-item {
            padding: 11px 15px;
            border-bottom: 1px solid var(--ci-border);
            transition: background 0.15s ease;
        }

        .ci-notif-item:last-child {
            border-bottom: none;
        }

        .ci-notif-item.unread {
            background: var(--ci-accent-soft);
            border-left: 3px solid var(--ci-accent);
        }

        .ci-notif-item:hover {
            background: rgba(0, 166, 214, 0.08);
        }

        .ci-notif-msg {
            font-size: 12px;
            line-height: 1.45;
            color: var(--ci-text);
            margin-bottom: 3px;
        }

        .ci-notif-time {
            font-size: 10px;
            color: var(--ci-muted);
        }

        .ci-notif-empty {
            padding: 24px;
            text-align: center;
            font-size: 13px;
            color: var(--ci-muted);
        }

        .ci-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 13px;
            font-weight: 800;
            color: #ffffff;
            background: linear-gradient(135deg, #0284c7, #38bdf8);
            flex-shrink: 0;
            overflow: hidden;
        }

        .ci-avatar img, .ci-mention-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
            display: block;
        }

        /* GRID LAYOUT */
        .ci-content {
            padding: 28px 36px 60px;
            max-width: 1400px;
            width: 100%;
            margin: 0 auto;
        }

        .ci-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 320px;
            gap: 28px;
            align-items: start;
        }

        @media (max-width: 1024px) {
            .ci-topbar {
                padding: max(20px, calc(16px + env(safe-area-inset-top, 0px))) 16px 14px 16px !important;
                box-sizing: border-box !important;
            }
            .ci-topbar h1 {
                font-size: 20px !important;
            }
            .ci-grid {
                grid-template-columns: 1fr;
            }
            .ci-sidebar-col {
                order: 2;
            }
        }

        /* BANNER */
        .ci-banner {
            position: relative;
            padding: 28px 32px;
            border-radius: 16px;
            overflow: hidden;
            background: radial-gradient(circle at 90% 20%, rgba(50, 213, 232, 0.25), transparent 45%),
                        linear-gradient(125deg, #075273 0%, #087b9c 50%, #033649 100%);
            color: #ffffff;
            margin-bottom: 24px;
            box-shadow: 0 4px 20px rgba(0, 166, 214, 0.15);
        }

        .ci-banner-tag {
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 2px;
            color: #a5f3fc;
            text-transform: uppercase;
        }

        .ci-banner h2 {
            margin: 8px 0;
            font-size: 26px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .ci-banner p {
            margin: 0;
            max-width: 500px;
            font-size: 13px;
            line-height: 1.6;
            color: #e0f2fe;
        }

        .ci-banner-watermark {
            position: absolute;
            right: 20px;
            top: -40px;
            font-size: 220px;
            font-weight: 900;
            color: rgba(255, 255, 255, 0.05);
            pointer-events: none;
            user-select: none;
            line-height: 1;
        }

        /* STATS BAR */
        .ci-stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }

        @media (max-width: 640px) {
            .ci-stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .ci-stat-card {
            background: var(--ci-card);
            border: 1px solid var(--ci-border);
            border-radius: 12px;
            padding: 16px 18px;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .ci-stat-card span {
            font-size: 11px;
            font-weight: 700;
            color: var(--ci-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .ci-stat-card strong {
            font-size: 24px;
            font-weight: 800;
            color: var(--ci-text);
        }

        /* POST COMPOSER */
        .ci-composer {
            background: var(--ci-card);
            border: 1px solid var(--ci-border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.02);
        }

        .ci-composer-head {
            display: flex;
            gap: 14px;
            align-items: flex-start;
        }

        .ci-composer textarea {
            flex: 1;
            border: none;
            outline: none;
            background: transparent;
            color: var(--ci-text);
            font-size: 14px;
            resize: vertical;
            min-height: 65px;
            padding: 4px 0;
            line-height: 1.5;
        }

        .ci-composer textarea::placeholder {
            color: var(--ci-muted);
        }

        /* File Preview Container - Auto Layout */
        .ci-preview-box {
            display: none;
            position: relative;
            margin: 12px 0 8px;
            border-radius: 12px;
            overflow: hidden;
            background: var(--ci-card-sec);
            border: 1px solid var(--ci-border);
            max-width: 100%;
            text-align: center;
        }

        .ci-preview-box img {
            max-width: 100%;
            height: auto;
            max-height: 380px;
            object-fit: contain;
            display: inline-block;
            border-radius: 10px;
        }

        .ci-preview-box video {
            width: 100%;
            height: auto;
            max-height: 380px;
            display: block;
            border-radius: 10px;
            background: #000;
        }

        .ci-preview-remove {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 26px;
            height: 26px;
            background: rgba(15, 23, 42, 0.75);
            color: #ffffff;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
            display: grid;
            place-items: center;
        }

        .ci-composer-foot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--ci-border);
            padding-top: 14px;
            margin-top: 12px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .ci-composer-tools {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .ci-type-btn {
            border: 1px solid transparent;
            background: transparent;
            color: var(--ci-muted);
            padding: 7px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }

        .ci-type-btn:hover,
        .ci-type-btn.active {
            color: var(--ci-accent);
            background: var(--ci-accent-soft);
            border-color: var(--ci-accent);
        }

        .ci-file-input {
            display: none;
        }

        .ci-post-btn {
            background: linear-gradient(135deg, #00a6d6, #0284c7);
            color: #ffffff;
            border: none;
            padding: 9px 20px;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: 0.2s;
        }

        .ci-post-btn:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }

        .ci-post-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Upload Progress */
        .ci-progress-wrap {
            display: none;
            width: 100%;
            background: var(--ci-border);
            height: 4px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }

        .ci-progress-bar {
            height: 100%;
            width: 0%;
            background: var(--ci-accent);
            transition: width 0.2s;
        }

        /* FEED FILTERS */
        .ci-filters {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            overflow-x: auto;
            padding-bottom: 4px;
        }

        .ci-filter-pill {
            padding: 7px 16px;
            border-radius: 20px;
            border: 1px solid var(--ci-border);
            background: var(--ci-card);
            color: var(--ci-muted);
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            white-space: nowrap;
        }

        .ci-filter-pill:hover,
        .ci-filter-pill.active {
            background: var(--ci-accent);
            color: #ffffff;
            border-color: var(--ci-accent);
        }

        /* POST CARDS */
        .ci-posts-list {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .ci-post-card {
            background: var(--ci-card);
            border: 1px solid var(--ci-border);
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            transition: border-color 0.2s;
        }

        .ci-post-card:hover {
            border-color: rgba(0, 166, 214, 0.4);
        }

        .ci-post-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 14px;
        }

        .ci-post-meta {
            flex: 1;
            min-width: 0;
        }

        .ci-post-author {
            font-size: 14px;
            font-weight: 800;
            color: var(--ci-text);
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .ci-post-badge {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            background: var(--ci-card-sec);
            color: var(--ci-muted);
            border: 1px solid var(--ci-border);
        }

        .ci-post-time {
            font-size: 11px;
            color: var(--ci-muted);
            margin-top: 2px;
        }

        .ci-del-btn {
            background: transparent;
            border: none;
            color: var(--ci-muted);
            cursor: pointer;
            padding: 6px;
            border-radius: 6px;
            font-size: 14px;
            transition: 0.2s;
        }

        .ci-del-btn:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .ci-post-content {
            font-size: 14px;
            line-height: 1.6;
            color: var(--ci-text);
            margin-bottom: 14px;
            white-space: pre-wrap;
            word-break: break-word;
        }

        /* Media display - Auto Layout */
        .ci-post-media {
            margin-bottom: 14px;
            border-radius: 14px;
            overflow: hidden;
            border: 1px solid var(--ci-border);
            background: var(--ci-card-sec);
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            cursor: zoom-in;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        .ci-post-media:hover {
            border-color: rgba(0, 166, 214, 0.4);
            box-shadow: 0 4px 18px rgba(0, 166, 214, 0.1);
        }

        .ci-post-media img {
            max-width: 100%;
            width: auto;
            height: auto;
            max-height: 580px;
            object-fit: contain;
            display: block;
            border-radius: 13px;
            transition: transform 0.22s ease;
        }

        .ci-post-media:hover img {
            transform: scale(1.008);
        }

        .ci-post-media video {
            width: 100%;
            max-height: 560px;
            height: auto;
            display: block;
            outline: none;
            border-radius: 13px;
            background: #000000;
        }

        /* Hover Zoom Pill */
        .ci-media-zoom-hint {
            position: absolute;
            bottom: 12px;
            right: 12px;
            background: rgba(15, 23, 42, 0.72);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            color: #ffffff;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            opacity: 0;
            transform: translateY(4px);
            transition: all 0.2s ease;
            pointer-events: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
        }

        .ci-post-media:hover .ci-media-zoom-hint {
            opacity: 1;
            transform: translateY(0);
        }

        /* FULLSCREEN LIGHTBOX */
        .ci-lightbox {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 999999;
            background: rgba(8, 14, 23, 0.95);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            display: flex;
            flex-direction: column;
            opacity: 0;
            transition: opacity 0.2s ease;
            cursor: pointer;
            box-sizing: border-box;
        }

        .ci-lightbox.active {
            opacity: 1;
        }

        .ci-lightbox-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: max(60px, calc(20px + env(safe-area-inset-top, 44px)));
            padding-bottom: 14px;
            padding-left: 20px;
            padding-right: 20px;
            background: linear-gradient(180deg, rgba(0, 0, 0, 0.92) 0%, rgba(0, 0, 0, 0.6) 80%, transparent 100%);
            z-index: 10;
            cursor: pointer;
            box-sizing: border-box;
            width: 100%;
        }

        .ci-lightbox-info {
            color: #ffffff;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 0.2px;
            padding-right: 12px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            text-shadow: 0 1px 3px rgba(0,0,0,0.8);
        }

        .ci-lightbox-tools {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .ci-lightbox-btn {
            width: 44px;
            height: 44px;
            min-width: 44px;
            min-height: 44px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
            -webkit-tap-highlight-color: transparent;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.35);
        }

        .ci-lightbox-btn:hover,
        .ci-lightbox-btn:active {
            background: rgba(255, 255, 255, 0.32);
            transform: scale(1.06);
        }

        .ci-lightbox-close {
            background: rgba(239, 68, 68, 0.85) !important;
            border-color: rgba(255, 255, 255, 0.4) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.5) !important;
        }

        .ci-lightbox-close:hover,
        .ci-lightbox-close:active {
            background: #ef4444 !important;
            transform: scale(1.08);
        }

        .ci-lightbox-content {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 10px 16px;
            cursor: pointer;
            box-sizing: border-box;
            position: relative;
        }

        .ci-lightbox-content img {
            max-width: 94vw;
            max-height: 75vh;
            object-fit: contain;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
            cursor: default;
        }

        .ci-lightbox-hint {
            padding-bottom: max(22px, calc(16px + env(safe-area-inset-bottom, 0px)));
            text-align: center;
            color: rgba(255, 255, 255, 0.65);
            font-size: 13px;
            font-weight: 600;
            letter-spacing: 0.3px;
            cursor: pointer;
            user-select: none;
        }

        /* Post Action Row */
        .ci-post-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px solid var(--ci-border);
            padding-top: 12px;
        }

        .ci-action-btn {
            background: transparent;
            border: none;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            color: var(--ci-muted);
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            transition: 0.2s;
        }

        .ci-action-btn:hover {
            background: var(--ci-card-sec);
            color: var(--ci-text);
        }

        .ci-action-btn.liked {
            color: #ef4444;
        }

        .ci-action-btn.liked svg {
            fill: #ef4444;
            stroke: #ef4444;
        }

        /* COMMENT DRAWER */
        .ci-comments-drawer {
            border-top: 1px solid var(--ci-border);
            margin-top: 12px;
            padding-top: 14px;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .ci-comment-item {
            display: flex;
            gap: 10px;
            background: var(--ci-card-sec);
            padding: 10px 14px;
            border-radius: 10px;
            border: 1px solid var(--ci-border);
            align-items: flex-start;
        }

        .ci-comment-body {
            flex: 1;
            min-width: 0;
        }

        .ci-comment-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2px;
        }

        .ci-comment-author {
            font-size: 12px;
            font-weight: 800;
            color: var(--ci-text);
        }

        .ci-comment-time {
            font-size: 10px;
            color: var(--ci-muted);
        }

        .ci-comment-text {
            font-size: 12px;
            color: var(--ci-text);
            line-height: 1.4;
            word-break: break-word;
        }

        .ci-comment-input-row {
            display: flex;
            gap: 8px;
            margin-top: 6px;
        }

        .ci-comment-input {
            flex: 1;
            border: 1px solid var(--ci-border);
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            outline: none;
            background: var(--ci-card);
            color: var(--ci-text);
        }

        .ci-comment-input:focus {
            border-color: var(--ci-accent);
        }

        .ci-comment-send {
            background: var(--ci-accent);
            color: #ffffff;
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        /* SIDEBAR WIDGETS */
        .ci-widget {
            background: var(--ci-card);
            border: 1px solid var(--ci-border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .ci-widget-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
        }

        .ci-widget-head h3 {
            margin: 0;
            font-size: 15px;
            font-weight: 800;
            color: var(--ci-text);
        }

        .ci-holiday-item {
            padding: 10px 0;
            border-bottom: 1px dashed var(--ci-border);
        }

        .ci-holiday-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .ci-holiday-date {
            font-size: 11px;
            font-weight: 700;
            color: var(--ci-accent);
            display: block;
        }

        .ci-holiday-name {
            font-size: 12px;
            font-weight: 600;
            color: var(--ci-text);
        }

        .ci-guidelines {
            font-size: 12px;
            line-height: 1.6;
            color: var(--ci-muted);
        }

        .ci-guidelines li {
            margin-bottom: 6px;
        }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['theme']) && $_COOKIE['theme'] === 'dark' ? 'dark-mode' : ''; ?>">
    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <!-- TOPBAR -->
        <header class="ci-topbar">
            <div>
                <span class="ci-breadcrumb">WERIDE / COMMUNITY</span>
                <h1>WeRide Insides</h1>
            </div>
            <div class="ci-topbar-right">
                <!-- REFRESH FEED BUTTON (Standard topbar icon button) -->
                <button type="button" class="ci-notif-btn" id="feed-refresh-btn" onclick="refreshFeed(this)" title="Refresh Feed" aria-label="Refresh">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21.5 2v6h-6M2.5 22v-6h6M2 11.5a10 10 0 0 1 18.8-4.3M22 12.5a10 10 0 0 1-18.8 4.2"/>
                    </svg>
                </button>

                <!-- NOTIFICATION BELL -->
                <div class="ci-notif-wrapper">
                    <button type="button" class="ci-notif-btn" id="notif-btn" onclick="toggleNotifDropdown()" title="Notifications">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <?php if ($unread_notifs_cnt > 0): ?>
                            <span class="ci-notif-badge" id="notif-badge"><?php echo $unread_notifs_cnt; ?></span>
                        <?php endif; ?>
                    </button>
                    <div class="ci-notif-dropdown" id="notif-dropdown" style="display: none;">
                        <div class="ci-notif-header">
                            <h4>Notifications</h4>
                            <?php if ($unread_notifs_cnt > 0): ?>
                                <button type="button" class="ci-notif-mark" id="btn-mark-all" onclick="markAllNotifsRead()">Mark read</button>
                            <?php endif; ?>
                        </div>
                        <div class="ci-notif-list" id="notif-list">
                            <?php if (empty($notifications)): ?>
                                <div class="ci-notif-empty">No notifications yet.</div>
                            <?php else: ?>
                                <?php foreach ($notifications as $notif): ?>
                                    <div class="ci-notif-item <?php echo (int)$notif['is_read'] === 0 ? 'unread' : ''; ?>">
                                        <div class="ci-notif-msg"><?php echo htmlspecialchars($notif['message']); ?></div>
                                        <div class="ci-notif-time"><?php echo get_relative_time($notif['created_at']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="ci-online-badge" title="Active on WeRide Insides">
                    <span class="ci-live-beacon"></span>
                    <span id="topbar-online-text"><?php echo $online_count; ?> Online</span>
                </div>
                <a href="profile.php" class="ci-avatar" title="<?php echo htmlspecialchars($current_user_name); ?>" style="text-decoration:none;">
                    <?php if (!empty($current_user_pic) && file_exists($current_user_pic)): ?>
                        <img src="<?php echo htmlspecialchars($current_user_pic); ?>" alt="Profile">
                    <?php else: ?>
                        <?php echo $user_initials; ?>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <div class="ci-content">
            <div class="ci-grid">
                <!-- MAIN FEED COLUMN -->
                <div class="ci-feed-col">
                    <!-- BANNER -->
                    <div class="ci-banner">
                        <span class="ci-banner-tag">WELCOME TO WERIDE</span>
                        <h2>One team. One community.</h2>
                        <p>Share daily moments, project updates, shoutouts and stay connected with everyone at WeRide.</p>
                        <div class="ci-banner-watermark">W</div>
                    </div>

                    <!-- QUICK STATS -->
                    <div class="ci-stats-row">
                        <div class="ci-stat-card ci-stat-online" title="<?php echo $online_count; ?> members active right now">
                            <span style="display:flex; align-items:center; gap:6px;">
                                <span class="ci-live-beacon"></span> Online Now
                            </span>
                            <strong id="stat-online-count"><?php echo $online_count; ?></strong>
                        </div>
                        <div class="ci-stat-card">
                            <span>Posts</span>
                            <strong id="stat-posts-count"><?php echo $total_posts_cnt; ?></strong>
                        </div>
                        <div class="ci-stat-card">
                            <span>Likes</span>
                            <strong id="stat-likes-count"><?php echo $total_likes_cnt; ?></strong>
                        </div>
                        <div class="ci-stat-card">
                            <span>Comments</span>
                            <strong id="stat-comments-count"><?php echo $total_comments_cnt; ?></strong>
                        </div>
                    </div>

                    <!-- PUSH NOTIFICATION PROMPT -->
                    <div id="feed-push-prompt" style="display:none; background: linear-gradient(135deg, rgba(0, 166, 214, 0.12), rgba(50, 213, 232, 0.05)); border: 1px solid rgba(0, 166, 214, 0.35); border-radius: 14px; padding: 14px 18px; margin-bottom: 20px; align-items: center; justify-content: space-between; gap: 14px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 240px;">
                            <div style="width: 40px; height: 40px; border-radius: 10px; background: rgba(0, 166, 214, 0.18); display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;">
                                🔔
                            </div>
                            <div>
                                <div style="font-weight: 700; font-size: 14px; color: var(--ci-text);">Enable Mobile Push Notifications</div>
                                <div style="font-size: 12px; color: var(--ci-muted);">Get instant notifications with the WeRide logo when anyone posts in the feed, comments, or mentions you.</div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <button type="button" id="btn-enable-push" onclick="enablePushAlertsWithTest(this)" style="background: linear-gradient(135deg, #00a6d6, #0284c7); color: #fff; border: none; padding: 9px 18px; border-radius: 8px; font-weight: 700; font-size: 13px; cursor: pointer; white-space: nowrap; box-shadow: 0 4px 14px rgba(0,166,214,0.35); display: flex; align-items: center; gap: 6px;">
                                <span>Turn On Notifications</span>
                            </button>
                        </div>
                    </div>

                    <!-- POST COMPOSER -->
                    <div class="ci-composer">
                        <div class="ci-composer-head">
                            <div class="ci-avatar">
                                <?php if (!empty($current_user_pic) && file_exists($current_user_pic)): ?>
                                    <img src="<?php echo htmlspecialchars($current_user_pic); ?>" alt="Profile">
                                <?php else: ?>
                                    <?php echo $user_initials; ?>
                                <?php endif; ?>
                            </div>
                            <textarea id="composer-text" placeholder="What's happening at WeRide? (Type @ to mention colleagues)"></textarea>
                        </div>

                        <!-- Media Preview Box -->
                        <div class="ci-preview-box" id="preview-box">
                            <img id="preview-img" style="display:none;" alt="Preview" />
                            <video id="preview-video" style="display:none;" controls></video>
                            <button type="button" class="ci-preview-remove" onclick="clearSelectedMedia()" title="Remove file">&times;</button>
                        </div>

                        <div class="ci-progress-wrap" id="upload-progress">
                            <div class="ci-progress-bar" id="upload-progress-bar"></div>
                        </div>

                        <div class="ci-composer-foot">
                            <div class="ci-composer-tools">
                                <button type="button" class="ci-type-btn active" id="btn-mode-text" onclick="setPostMode('text')">
                                    Tweet
                                </button>
                                <label class="ci-type-btn" id="btn-mode-photo">
                                    Photo
                                    <input type="file" id="input-photo" class="ci-file-input" accept="image/*" onchange="handleFileSelect(this, 'photo')">
                                </label>
                                <label class="ci-type-btn" id="btn-mode-video">
                                    Video (Max 20MB)
                                    <input type="file" id="input-video" class="ci-file-input" accept="video/mp4,video/webm,video/quicktime" onchange="handleFileSelect(this, 'video')">
                                </label>
                            </div>

                            <button type="button" class="ci-post-btn" id="btn-publish" onclick="submitPost()">
                                <span>Publish</span> &uarr;
                            </button>
                        </div>
                    </div>

                    <!-- FEED FILTER TABS -->
                    <div class="ci-filters">
                        <button class="ci-filter-pill active" onclick="filterPosts('all', this)">All Posts</button>
                        <button class="ci-filter-pill" onclick="filterPosts('photo', this)">Photos</button>
                        <button class="ci-filter-pill" onclick="filterPosts('video', this)">Videos</button>
                        <button class="ci-filter-pill" onclick="filterPosts('none', this)">Tweets</button>
                    </div>

                    <!-- POSTS LIST -->
                    <div class="ci-posts-list" id="posts-container">
                        <?php if (empty($posts)): ?>
                            <div class="ci-post-card" style="text-align: center; padding: 40px 20px; color: var(--ci-muted);">
                                <div style="font-size: 32px; margin-bottom: 8px;">💬</div>
                                <h3 style="margin: 0 0 4px; color: var(--ci-text);">No posts yet</h3>
                                <p style="margin: 0; font-size: 13px;">Be the first to share an update with the WeRide team!</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($posts as $post): 
                                $author_name = $post['author_name'] ?: 'Former Staff (' . $post['emp_id'] . ')';
                                $post_initials = get_initials($author_name);
                                $is_author = ($post['emp_id'] === $current_emp_id);
                                $can_delete = $is_author || $is_moderator;
                                $p_comments = $comments_by_post[$post['id']] ?? [];
                            ?>
                                <article class="ci-post-card" id="post-card-<?php echo $post['id']; ?>" data-media-type="<?php echo $post['media_type']; ?>">
                                    <div class="ci-post-header">
                                        <div class="ci-avatar">
                                            <?php if (!empty($post['author_pic']) && file_exists($post['author_pic'])): ?>
                                                <img src="<?php echo htmlspecialchars($post['author_pic']); ?>" alt="Profile">
                                            <?php else: ?>
                                                <?php echo $post_initials; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ci-post-meta">
                                            <div class="ci-post-author">
                                                <span><?php echo htmlspecialchars($author_name); ?></span>
                                                <span class="ci-post-badge"><?php echo htmlspecialchars($post['author_dept'] ?: 'General'); ?></span>
                                            </div>
                                            <div class="ci-post-time">
                                                <?php echo get_relative_time($post['created_at']); ?>
                                            </div>
                                        </div>

                                        <?php if ($can_delete): ?>
                                            <button type="button" class="ci-del-btn" title="Delete Post" onclick="deletePost(<?php echo $post['id']; ?>)">
                                                🗑️
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <?php if (!empty($post['content'])): ?>
                                        <div class="ci-post-content"><?php echo format_post_content($post['content'], $mention_map); ?></div>
                                    <?php endif; ?>

                                    <?php if ($post['media_type'] === 'photo' && !empty($post['media_url'])): ?>
                                        <div class="ci-post-media" onclick="openLightbox('<?php echo htmlspecialchars($post['media_url']); ?>', '<?php echo htmlspecialchars(addslashes($post['author_name'])); ?>')">
                                            <img src="<?php echo htmlspecialchars($post['media_url']); ?>" alt="Photo post" loading="lazy">
                                            <div class="ci-media-zoom-hint">
                                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                                                <span>View Full Size</span>
                                            </div>
                                        </div>
                                    <?php elseif ($post['media_type'] === 'video' && !empty($post['media_url'])): ?>
                                        <div class="ci-post-media">
                                            <video controls playsinline preload="metadata">
                                                <source src="<?php echo htmlspecialchars($post['media_url']); ?>">
                                                Your browser does not support the video tag.
                                            </video>
                                        </div>
                                    <?php endif; ?>

                                    <div class="ci-post-foot">
                                        <button type="button" class="ci-action-btn <?php echo $post['has_liked'] ? 'liked' : ''; ?>" id="like-btn-<?php echo $post['id']; ?>" onclick="toggleLike(<?php echo $post['id']; ?>)">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                                            </svg>
                                            <span id="like-count-<?php echo $post['id']; ?>"><?php echo (int)$post['likes_count']; ?></span>
                                        </button>

                                        <button type="button" class="ci-action-btn" onclick="toggleComments(<?php echo $post['id']; ?>)">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                            </svg>
                                            <span id="comment-count-<?php echo $post['id']; ?>"><?php echo count($p_comments); ?> comments</span>
                                        </button>
                                    </div>

                                    <!-- Comments Drawer -->
                                    <div class="ci-comments-drawer" id="comments-drawer-<?php echo $post['id']; ?>">
                                        <div class="ci-comment-list" id="comments-list-<?php echo $post['id']; ?>">
                                            <?php foreach ($p_comments as $c): 
                                                $c_is_author = ($c['emp_id'] === $current_emp_id);
                                                $c_can_delete = $c_is_author || $is_moderator;
                                                $c_name = $c['author_name'] ?: 'Staff (' . $c['emp_id'] . ')';
                                            ?>
                                                <div class="ci-comment-item" id="comment-item-<?php echo $c['id']; ?>">
                                                    <div class="ci-avatar" style="width:28px;height:28px;font-size:10px;">
                                                        <?php if (!empty($c['author_pic']) && file_exists($c['author_pic'])): ?>
                                                            <img src="<?php echo htmlspecialchars($c['author_pic']); ?>" alt="Profile">
                                                        <?php else: ?>
                                                            <?php echo get_initials($c_name); ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="ci-comment-body">
                                                        <div class="ci-comment-head">
                                                            <span class="ci-comment-author"><?php echo htmlspecialchars($c_name); ?></span>
                                                            <span class="ci-comment-time"><?php echo get_relative_time($c['created_at']); ?></span>
                                                        </div>
                                                        <div class="ci-comment-text"><?php echo format_post_content($c['content'], $mention_map); ?></div>
                                                    </div>
                                                    <?php if ($c_can_delete): ?>
                                                        <button type="button" class="ci-del-btn" style="font-size:11px;padding:2px;" onclick="deleteComment(<?php echo $c['id']; ?>, <?php echo $post['id']; ?>)" title="Delete comment">&times;</button>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="ci-comment-input-row">
                                            <input type="text" class="ci-comment-input" id="input-comment-<?php echo $post['id']; ?>" placeholder="Write a reply... (type @ to mention)" onkeydown="if(event.key === 'Enter') addComment(<?php echo $post['id']; ?>)">
                                            <button type="button" class="ci-comment-send" onclick="addComment(<?php echo $post['id']; ?>)">Reply</button>
                                        </div>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- RIGHT SIDEBAR COLUMN -->
                <div class="ci-sidebar-col">
                    <!-- OUR PEOPLE -->
                    <div class="ci-widget">
                        <div class="ci-widget-head">
                            <h3>Our Community</h3>
                            <span style="font-size: 16px;">👥</span>
                        </div>
                        <div style="display: flex; align-items: baseline; gap: 8px; margin-bottom: 4px;">
                            <span class="ci-live-beacon" style="margin-bottom: 2px;"></span>
                            <span style="font-size: 32px; font-weight: 800; color: #10b981;" id="widget-online-count"><?php echo $online_count; ?></span>
                            <span style="font-size: 14px; font-weight: 600; color: var(--ci-muted);">/ <?php echo $total_employees_cnt; ?> online</span>
                        </div>
                        <div style="font-size: 12px; color: var(--ci-muted); margin-bottom: 12px;">Active now on WeRide Insides</div>
                        <div style="height: 6px; border-radius: 6px; background: var(--ci-border); overflow: hidden; margin-bottom: 12px;">
                            <div style="height: 100%; width: 100%; background: linear-gradient(90deg, #10b981, #34d399);"></div>
                        </div>
                        <div style="font-size: 11px; color: var(--ci-muted);">Stay connected across all departments.</div>
                    </div>

                    <!-- UPCOMING HOLIDAYS / ANNOUNCEMENTS -->
                    <div class="ci-widget">
                        <div class="ci-widget-head">
                            <h3>Upcoming Holidays</h3>
                            <span style="font-size: 16px;">📅</span>
                        </div>
                        <?php if (empty($upcoming_holidays)): ?>
                            <div style="font-size: 12px; color: var(--ci-muted);">No upcoming holidays listed.</div>
                        <?php else: ?>
                            <?php foreach ($upcoming_holidays as $uh): ?>
                                <div class="ci-holiday-item">
                                    <span class="ci-holiday-date"><?php echo date("D, d M Y", strtotime($uh['holiday_date'])); ?></span>
                                    <span class="ci-holiday-name"><?php echo htmlspecialchars($uh['holiday_name']); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- GUIDELINES -->
                    <div class="ci-widget">
                        <div class="ci-widget-head">
                            <h3>Feed Guidelines</h3>
                            <span style="font-size: 16px;">📌</span>
                        </div>
                        <ul class="ci-guidelines" style="padding-left: 18px; margin: 0;">
                            <li>Be positive, respectful, and supportive.</li>
                            <li>Share company events, team achievements, and celebrations.</li>
                            <li>Keep video uploads under 20MB.</li>
                            <li>HR and Admins actively moderate all shared content.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- FULLSCREEN IMAGE LIGHTBOX -->
    <div id="ci-lightbox" class="ci-lightbox" style="display: none;" onclick="closeLightbox(event)">
        <div class="ci-lightbox-bar" onclick="event.stopPropagation()">
            <div class="ci-lightbox-info" id="lightbox-info">Photo View</div>
            <div class="ci-lightbox-tools">
                <a id="lightbox-download" href="#" download class="ci-lightbox-btn" title="Download Image" target="_blank" onclick="event.stopPropagation()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                </a>
                <button type="button" class="ci-lightbox-btn ci-lightbox-close" onclick="closeLightbox()" title="Close" aria-label="Close">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                </button>
            </div>
        </div>
        <div class="ci-lightbox-content" onclick="closeLightbox(event)">
            <img id="lightbox-img" src="" alt="Full view" onclick="event.stopPropagation()" />
        </div>
        <div class="ci-lightbox-hint" onclick="closeLightbox(event)">
            <span>Tap anywhere to close &times;</span>
        </div>
    </div>

    <!-- FLOATING MENTION AUTOCOMPLETE DROPDOWN -->
    <div id="mention-dropdown" class="ci-mention-dropdown" style="display: none;"></div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        // Employee data for @mention autocomplete
        const mentionEmployees = <?php echo json_encode($mention_employees_js); ?>;

        let activeMentionInput = null;
        let mentionStartIndex = -1;
        let selectedMentionIndex = 0;
        let currentFilteredMentions = [];

        let currentMode = 'text';
        let selectedFile = null;

        function setPostMode(mode) {
            currentMode = mode;
            document.getElementById('btn-mode-text').classList.toggle('active', mode === 'text');
            if (mode === 'text') {
                clearSelectedMedia();
            }
        }

        function handleFileSelect(input, type) {
            if (!input.files || !input.files[0]) return;
            const file = input.files[0];

            // Size Guardrail for video
            if (type === 'video' && file.size > 20 * 1024 * 1024) {
                alert("This video is " + (file.size / (1024 * 1024)).toFixed(1) + "MB, which exceeds the 20MB limit.\n\nPlease select a shorter video clip (under 45 seconds).");
                input.value = "";
                return;
            }

            selectedFile = file;
            currentMode = type;

            const previewBox = document.getElementById('preview-box');
            const previewImg = document.getElementById('preview-img');
            const previewVideo = document.getElementById('preview-video');

            previewBox.style.display = 'block';

            if (type === 'photo') {
                previewVideo.style.display = 'none';
                previewVideo.pause();
                previewImg.style.display = 'block';
                previewImg.src = URL.createObjectURL(file);
                document.getElementById('btn-mode-photo').classList.add('active');
                document.getElementById('btn-mode-video').classList.remove('active');
                document.getElementById('btn-mode-text').classList.remove('active');
            } else if (type === 'video') {
                previewImg.style.display = 'none';
                previewVideo.style.display = 'block';
                previewVideo.src = URL.createObjectURL(file);
                document.getElementById('btn-mode-video').classList.add('active');
                document.getElementById('btn-mode-photo').classList.remove('active');
                document.getElementById('btn-mode-text').classList.remove('active');
            }
        }

        function clearSelectedMedia() {
            selectedFile = null;
            document.getElementById('input-photo').value = "";
            document.getElementById('input-video').value = "";
            document.getElementById('preview-box').style.display = 'none';
            document.getElementById('preview-img').src = "";
            document.getElementById('preview-video').src = "";
            document.getElementById('btn-mode-photo').classList.remove('active');
            document.getElementById('btn-mode-video').classList.remove('active');
            document.getElementById('btn-mode-text').classList.add('active');
            currentMode = 'text';
        }

        // Compress image on client-side before sending
        async function compressImageIfNeeded(file) {
            if (!file.type.startsWith('image/')) return file;
            // Only compress if larger than 800KB
            if (file.size <= 800 * 1024) return file;

            return new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const img = new Image();
                    img.onload = () => {
                        const canvas = document.createElement('canvas');
                        let w = img.width;
                        let h = img.height;
                        const maxDim = 1400;
                        if (w > maxDim || h > maxDim) {
                            if (w > h) {
                                h = Math.round((h * maxDim) / w);
                                w = maxDim;
                            } else {
                                w = Math.round((w * maxDim) / h);
                                h = maxDim;
                            }
                        }
                        canvas.width = w;
                        canvas.height = h;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(img, 0, 0, w, h);
                        canvas.toBlob((blob) => {
                            if (blob && blob.size < file.size) {
                                resolve(new File([blob], file.name.replace(/\.[^/.]+$/, ".jpg"), { type: 'image/jpeg' }));
                            } else {
                                resolve(file);
                            }
                        }, 'image/jpeg', 0.82);
                    };
                    img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            });
        }

        async function submitPost() {
            const textarea = document.getElementById('composer-text');
            const content = textarea.value.trim();
            const btn = document.getElementById('btn-publish');

            if (!content && !selectedFile) {
                alert("Please write something or attach a photo/video.");
                return;
            }

            btn.disabled = true;
            btn.innerText = "Publishing...";

            const progressWrap = document.getElementById('upload-progress');
            const progressBar = document.getElementById('upload-progress-bar');
            progressWrap.style.display = 'block';
            progressBar.style.width = '15%';

            let fileToSend = selectedFile;
            if (selectedFile && currentMode === 'photo') {
                fileToSend = await compressImageIfNeeded(selectedFile);
            }

            const formData = new FormData();
            formData.append('action', 'create_post');
            formData.append('content', content);
            if (fileToSend) {
                formData.append('media', fileToSend);
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', window.location.pathname + '?action=create_post', true);

            xhr.upload.onprogress = (e) => {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    progressBar.style.width = pct + '%';
                }
            };

            xhr.onload = () => {
                btn.disabled = false;
                btn.innerText = "Publish ↑";
                progressWrap.style.display = 'none';
                progressBar.style.width = '0%';

                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        window.location.reload();
                    } else {
                        alert(res.message || "Failed to publish post.");
                    }
                } catch (e) {
                    alert("Unexpected server error.");
                }
            };

            xhr.onerror = () => {
                btn.disabled = false;
                btn.innerText = "Publish ↑";
                progressWrap.style.display = 'none';
                alert("Network error during upload.");
            };

            xhr.send(formData);
        }

        function toggleLike(postId) {
            const btn = document.getElementById('like-btn-' + postId);
            const countSpan = document.getElementById('like-count-' + postId);

            const formData = new FormData();
            formData.append('action', 'toggle_like');
            formData.append('post_id', postId);

            fetch(window.location.pathname + '?action=toggle_like', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    btn.classList.toggle('liked', res.liked);
                    countSpan.innerText = res.likes_count;
                }
            });
        }

        function toggleComments(postId) {
            const drawer = document.getElementById('comments-drawer-' + postId);
            drawer.style.display = (drawer.style.display === 'none' || drawer.style.display === '') ? 'flex' : 'none';
            if (drawer.style.display === 'flex') {
                const input = document.getElementById('input-comment-' + postId);
                if (input) input.focus();
            }
        }

        function addComment(postId) {
            const input = document.getElementById('input-comment-' + postId);
            const text = input.value.trim();
            if (!text) return;

            const formData = new FormData();
            formData.append('action', 'add_comment');
            formData.append('post_id', postId);
            formData.append('comment', text);

            fetch(window.location.pathname + '?action=add_comment', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    input.value = "";
                    const countSpan = document.getElementById('comment-count-' + postId);
                    if (countSpan) countSpan.innerText = res.comments_count + " comments";

                    const list = document.getElementById('comments-list-' + postId);
                    const c = res.comment;
                    const itemHtml = `
                        <div class="ci-comment-item" id="comment-item-${c.id}">
                            <div class="ci-avatar" style="width:28px;height:28px;font-size:10px;">${c.pic ? `<img src="${c.pic}" alt="">` : c.initials}</div>
                            <div class="ci-comment-body">
                                <div class="ci-comment-head">
                                    <span class="ci-comment-author">${c.name}</span>
                                    <span class="ci-comment-time">${c.time}</span>
                                </div>
                                <div class="ci-comment-text">${c.content}</div>
                            </div>
                            <button type="button" class="ci-del-btn" style="font-size:11px;padding:2px;" onclick="deleteComment(${c.id}, ${postId})" title="Delete comment">&times;</button>
                        </div>
                    `;
                    list.insertAdjacentHTML('beforeend', itemHtml);
                } else {
                    alert(res.message || "Could not post comment.");
                }
            });
        }

        function deletePost(postId) {
            if (!confirm("Are you sure you want to delete this post?")) return;

            const formData = new FormData();
            formData.append('action', 'delete_post');
            formData.append('post_id', postId);

            fetch(window.location.pathname + '?action=delete_post', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const card = document.getElementById('post-card-' + postId);
                    if (card) {
                        card.style.opacity = '0';
                        setTimeout(() => card.remove(), 200);
                    }
                } else {
                    alert(res.message || "Failed to delete post.");
                }
            });
        }

        function deleteComment(commentId, postId) {
            if (!confirm("Delete this comment?")) return;

            const formData = new FormData();
            formData.append('action', 'delete_comment');
            formData.append('comment_id', commentId);

            fetch(window.location.pathname + '?action=delete_comment', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const item = document.getElementById('comment-item-' + commentId);
                    if (item) item.remove();
                    const countSpan = document.getElementById('comment-count-' + postId);
                    if (countSpan) countSpan.innerText = res.comments_count + " comments";
                }
            });
        }

        function filterPosts(type, btn) {
            document.querySelectorAll('.ci-filter-pill').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');

            const cards = document.querySelectorAll('.ci-post-card[data-media-type]');
            cards.forEach(card => {
                const cardType = card.getAttribute('data-media-type');
                if (type === 'all' || cardType === type) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // ====================================================================
        // @MENTION AUTOCOMPLETE ENGINE
        // ====================================================================
        function handleMentionInput(el) {
            const cursorPos = el.selectionStart;
            const textBefore = el.value.substring(0, cursorPos);

            const lastAt = textBefore.lastIndexOf('@');
            if (lastAt === -1) {
                hideMentionDropdown();
                return;
            }

            // Check if @ is start of text or preceded by whitespace
            if (lastAt > 0 && !/\s/.test(textBefore[lastAt - 1])) {
                hideMentionDropdown();
                return;
            }

            const query = textBefore.substring(lastAt + 1);
            // If query contains spaces or linebreaks, mention tag is closed
            if (/\s/.test(query)) {
                hideMentionDropdown();
                return;
            }

            activeMentionInput = el;
            mentionStartIndex = lastAt;

            const qLower = query.toLowerCase();
            currentFilteredMentions = mentionEmployees.filter(emp => emp.searchKey.includes(qLower)).slice(0, 6);

            if (currentFilteredMentions.length === 0) {
                hideMentionDropdown();
                return;
            }

            selectedMentionIndex = 0;
            renderMentionDropdown(el);
        }

        function renderMentionDropdown(el) {
            const dropdown = document.getElementById('mention-dropdown');
            if (!dropdown) return;

            dropdown.innerHTML = currentFilteredMentions.map((emp, idx) => `
                <div class="ci-mention-item ${idx === selectedMentionIndex ? 'selected' : ''}" data-emp-id="${emp.id}" onmousedown="selectMention('${emp.id}')">
                    <div class="ci-mention-avatar">${emp.pic ? `<img src="${emp.pic}" alt="">` : emp.initials}</div>
                    <div class="ci-mention-info">
                        <div class="ci-mention-name">${emp.name}</div>
                        <div class="ci-mention-meta"><span class="ci-mention-id-tag">${emp.id}</span> • ${emp.role}</div>
                    </div>
                </div>
            `).join('');

            positionMentionDropdown(el);
            dropdown.style.display = 'block';
        }

        function positionMentionDropdown(el) {
            const dropdown = document.getElementById('mention-dropdown');
            if (!dropdown) return;

            const rect = el.getBoundingClientRect();
            let top = window.scrollY + rect.bottom + 6;
            let left = window.scrollX + rect.left;

            const dropdownWidth = 290;
            if (left + dropdownWidth > window.innerWidth - 20) {
                left = window.innerWidth - dropdownWidth - 20;
            }

            const dropdownHeight = 220;
            if (rect.bottom + dropdownHeight > window.innerHeight && rect.top > dropdownHeight) {
                top = window.scrollY + rect.top - dropdownHeight - 6;
            }

            dropdown.style.top = top + 'px';
            dropdown.style.left = left + 'px';
        }

        function handleMentionKeydown(e, el) {
            const dropdown = document.getElementById('mention-dropdown');
            if (!dropdown || dropdown.style.display === 'none' || currentFilteredMentions.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                e.stopPropagation();
                selectedMentionIndex = (selectedMentionIndex + 1) % currentFilteredMentions.length;
                renderMentionDropdown(el);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                e.stopPropagation();
                selectedMentionIndex = (selectedMentionIndex - 1 + currentFilteredMentions.length) % currentFilteredMentions.length;
                renderMentionDropdown(el);
            } else if (e.key === 'Enter' || e.key === 'Tab') {
                e.preventDefault();
                e.stopPropagation();
                if (currentFilteredMentions[selectedMentionIndex]) {
                    selectMention(currentFilteredMentions[selectedMentionIndex].id);
                }
            } else if (e.key === 'Escape') {
                hideMentionDropdown();
            }
        }

        function selectMention(empId) {
            if (!activeMentionInput) return;
            const el = activeMentionInput;
            const cursorPos = el.selectionStart;
            const text = el.value;

            const beforeAt = text.substring(0, mentionStartIndex);
            const afterCursor = text.substring(cursorPos);

            const insertText = '@' + empId + ' ';
            el.value = beforeAt + insertText + afterCursor;

            const newPos = beforeAt.length + insertText.length;
            el.setSelectionRange(newPos, newPos);
            el.focus();

            hideMentionDropdown();
        }

        function hideMentionDropdown() {
            const dropdown = document.getElementById('mention-dropdown');
            if (dropdown) dropdown.style.display = 'none';
            activeMentionInput = null;
            currentFilteredMentions = [];
        }

        // Attach global listeners for composer and all comment inputs
        document.addEventListener('DOMContentLoaded', () => {
            const composer = document.getElementById('composer-text');
            if (composer) {
                composer.addEventListener('input', () => handleMentionInput(composer));
                composer.addEventListener('keydown', (e) => handleMentionKeydown(e, composer));
            }

            // Event delegation for comment inputs (covers existing and dynamic inputs)
            document.addEventListener('input', (e) => {
                if (e.target.classList.contains('ci-comment-input')) {
                    handleMentionInput(e.target);
                }
            });

            document.addEventListener('keydown', (e) => {
                if (e.target.classList.contains('ci-comment-input')) {
                    handleMentionKeydown(e, e.target);
                }
            });

            // Dismiss mention dropdown on outside click
            document.addEventListener('click', (e) => {
                if (!e.target.closest('#mention-dropdown') && e.target !== activeMentionInput) {
                    hideMentionDropdown();
                }
                // Dismiss notif dropdown if clicked outside
                if (!e.target.closest('.ci-notif-wrapper')) {
                    const nd = document.getElementById('notif-dropdown');
                    if (nd) nd.style.display = 'none';
                }
            });
        });

        // ====================================================================
        // NOTIFICATIONS BELL & MARK READ
        // ====================================================================
        function toggleNotifDropdown() {
            const dropdown = document.getElementById('notif-dropdown');
            const btn = document.getElementById('notif-btn');
            if (!dropdown) return;
            const isShown = dropdown.style.display === 'flex' || dropdown.style.display === 'block';
            if (!isShown) {
                if (window.innerWidth <= 640 && btn) {
                    const rect = btn.getBoundingClientRect();
                    if (rect && rect.bottom) {
                        dropdown.style.top = (rect.bottom + 8) + 'px';
                    }
                }
                dropdown.style.display = 'flex';
            } else {
                dropdown.style.display = 'none';
            }
        }

        function markAllNotifsRead() {
            const formData = new FormData();
            formData.append('action', 'mark_notifications_read');

            fetch(window.location.pathname + '?action=mark_notifications_read', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    const badge = document.getElementById('notif-badge');
                    if (badge) badge.remove();
                    const btnMark = document.getElementById('btn-mark-all');
                    if (btnMark) btnMark.remove();
                    document.querySelectorAll('.ci-notif-item.unread').forEach(item => {
                        item.classList.remove('unread');
                    });
                }
            });
        }
        // ====================================================================
        // FULLSCREEN LIGHTBOX
        // ====================================================================
        function openLightbox(src, author) {
            const lb = document.getElementById('ci-lightbox');
            const img = document.getElementById('lightbox-img');
            const info = document.getElementById('lightbox-info');
            const dl = document.getElementById('lightbox-download');

            if (!lb || !img) return;

            img.src = src;
            if (info) info.innerText = author ? `Photo shared by ${author}` : 'Photo View';
            if (dl) dl.href = src;

            lb.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            setTimeout(() => lb.classList.add('active'), 10);
        }

        function closeLightbox(e) {
            if (e && e.target && e.target.closest('#lightbox-download')) return;
            const lb = document.getElementById('ci-lightbox');
            if (!lb) return;

            lb.classList.remove('active');
            document.body.style.overflow = '';
            setTimeout(() => {
                lb.style.display = 'none';
                const img = document.getElementById('lightbox-img');
                if (img) img.src = '';
            }, 180);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                const lb = document.getElementById('ci-lightbox');
                if (lb && lb.style.display === 'flex') {
                    closeLightbox();
                }
            }
        });

        // ====================================================================
        // REFRESH FEED HELPER
        // ====================================================================
        function refreshFeed(btn) {
            if (!btn) btn = document.getElementById('feed-refresh-btn');
            if (btn) btn.classList.add('spinning');

            setTimeout(() => {
                window.location.reload();
            }, 150);
        }

        // ====================================================================
        // REAL-TIME LIVE ONLINE COUNT & UNREAD NOTIFICATIONS (Heartbeat every 20s)
        // ====================================================================
        function pingHeartbeat() {
            const formData = new FormData();
            formData.append('action', 'heartbeat');

            fetch(window.location.pathname + '?action=heartbeat', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(res => {
                if (res && res.success) {
                    if (typeof res.online_count === 'number') {
                        const count = res.online_count;
                        const statEl = document.getElementById('stat-online-count');
                        if (statEl) statEl.innerText = count;

                        const widgetEl = document.getElementById('widget-online-count');
                        if (widgetEl) widgetEl.innerText = count;

                        const topbarEl = document.getElementById('topbar-online-text');
                        if (topbarEl) topbarEl.innerText = count + ' Online';
                    }

                    if (typeof res.unread_notifications === 'number') {
                        const unread = res.unread_notifications;
                        const notifBtn = document.getElementById('notif-btn');
                        let badge = document.getElementById('notif-badge');

                        if (unread > 0) {
                            if (!badge && notifBtn) {
                                badge = document.createElement('span');
                                badge.className = 'ci-notif-badge';
                                badge.id = 'notif-badge';
                                notifBtn.appendChild(badge);
                            }
                            if (badge) badge.innerText = unread > 99 ? '99+' : unread;
                        } else if (badge) {
                            badge.remove();
                        }
                    }
                }
            })
            .catch(() => {});
        }

        // Live heartbeat interval
        setInterval(pingHeartbeat, 20000);

        // Immediate refresh when tab regains focus
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) pingHeartbeat();
        });

        // ====================================================================
        // MOBILE PUSH NOTIFICATIONS HELPERS
        // ====================================================================
        function checkPushUIState() {
            const promptBox = document.getElementById('feed-push-prompt');
            if (!('Notification' in window) || !('serviceWorker' in navigator)) {
                if (promptBox) promptBox.style.display = 'none';
                return;
            }

            if (Notification.permission === 'granted') {
                if (promptBox) promptBox.style.display = 'none';
            } else {
                if (promptBox) promptBox.style.display = 'flex';
            }
        }

        async function enablePushAlertsWithTest(btn) {
            const isIos = /iphone|ipad|ipod/.test(window.navigator.userAgent.toLowerCase());
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

            if (isIos && !isStandalone) {
                alert("📱 To get mobile notifications on iPhone:\n\n1. Tap the Share icon (square with arrow ↑) at the bottom of Safari.\n2. Tap 'Add to Home Screen' (+).\n3. Open WeRide from your Home Screen and tap 'Turn On Notifications'!");
                return;
            }

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = `<span>Connecting...</span>`;
            }

            if (window.subscribeWeRidePush) {
                const ok = await window.subscribeWeRidePush(true);
                if (ok) {
                    checkPushUIState();
                    alert("✅ Mobile push notifications are active! You will now receive instant alerts on your phone.");
                } else {
                    alert("Notification permission was not granted. Please check your browser site settings and allow notifications.");
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = `<span>Turn On Notifications</span>`;
                    }
                }
            } else {
                alert("Push notifications are not supported by this browser.");
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = `<span>Turn On Notifications</span>`;
                }
            }
        }

        document.addEventListener('DOMContentLoaded', checkPushUIState);
    </script>
</body>
</html>

