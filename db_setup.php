<?php
// db_setup.php - Auto schema migration for Attendance System
if (!isset($conn) || !$conn) {
    return;
}

// 1. Create attendance_logs table if missing
$sql_att = "CREATE TABLE IF NOT EXISTS `attendance_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `emp_id` VARCHAR(50) NOT NULL,
    `date` DATE NOT NULL,
    `clock_in` DATETIME DEFAULT NULL,
    `clock_out` DATETIME DEFAULT NULL,
    `work_hours` DECIMAL(5,2) DEFAULT 0.00,
    `status` VARCHAR(20) DEFAULT 'P',
    `notes` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_emp_date` (`emp_id`, `date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
mysqli_query($conn, $sql_att);

// 2. Create leave_requests table if missing
$sql_leave = "CREATE TABLE IF NOT EXISTS `leave_requests` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `emp_id` VARCHAR(50) NOT NULL,
    `leave_type` VARCHAR(50) NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `half_day_type` VARCHAR(10) DEFAULT 'N/A',
    `reason` TEXT DEFAULT NULL,
    `status` ENUM('pending', 'pending_hr', 'approved', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
mysqli_query($conn, $sql_leave);

// 3. Create public_holidays table if missing
$sql_ph = "CREATE TABLE IF NOT EXISTS `public_holidays` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `holiday_date` DATE NOT NULL,
    `holiday_name` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_holiday_date` (`holiday_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
mysqli_query($conn, $sql_ph);

// 3.5 Create shift_change_requests table if missing
$sql_scr = "CREATE TABLE IF NOT EXISTS `shift_change_requests` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `emp_id` VARCHAR(50) NOT NULL,
    `current_shift` VARCHAR(20) NOT NULL,
    `requested_shift` VARCHAR(20) NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
mysqli_query($conn, $sql_scr);

// 3.6 Create access_logs table if missing
$sql_access = "CREATE TABLE IF NOT EXISTS `access_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `emp_id` VARCHAR(50) NOT NULL,
    `login_time` DATETIME NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Success',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
mysqli_query($conn, $sql_access);

// 4. Ensure missing columns on employees table
$emp_columns = [
    'onboarding_date' => "ALTER TABLE employees ADD COLUMN onboarding_date DATE DEFAULT NULL",
    'department' => "ALTER TABLE employees ADD COLUMN department VARCHAR(100) DEFAULT 'General'",
    'annual_leave_balance' => "ALTER TABLE employees ADD COLUMN annual_leave_balance INT DEFAULT 8",
    'sick_leave_balance' => "ALTER TABLE employees ADD COLUMN sick_leave_balance INT DEFAULT 14",
    'shift_hour' => "ALTER TABLE employees ADD COLUMN shift_hour VARCHAR(20) DEFAULT '09:00-18:00'",
    'welabel_id' => "ALTER TABLE employees ADD COLUMN welabel_id VARCHAR(100) DEFAULT NULL",
    'group_leader_id' => "ALTER TABLE employees ADD COLUMN group_leader_id VARCHAR(50) DEFAULT NULL"
];

$cols_res = mysqli_query($conn, "SHOW COLUMNS FROM employees");
$existing_cols = [];
if ($cols_res) {
    while ($r = mysqli_fetch_assoc($cols_res)) {
        $existing_cols[] = $r['Field'];
    }
}

foreach ($emp_columns as $col => $alter_sql) {
    if (!in_array($col, $existing_cols)) {
        @mysqli_query($conn, $alter_sql);
    }
}

// 5. Ensure missing columns on attendance_logs table
$att_columns = [
    'work_hours' => "ALTER TABLE attendance_logs ADD COLUMN work_hours DECIMAL(5,2) DEFAULT 0.00",
    'notes' => "ALTER TABLE attendance_logs ADD COLUMN notes TEXT DEFAULT NULL",
    'flag' => "ALTER TABLE attendance_logs ADD COLUMN flag ENUM('None', 'Late', 'Early', 'Both') DEFAULT 'None'"
];

$att_cols_res = mysqli_query($conn, "SHOW COLUMNS FROM attendance_logs");
$att_existing_cols = [];
if ($att_cols_res) {
    while ($r = mysqli_fetch_assoc($att_cols_res)) {
        $att_existing_cols[] = $r['Field'];
    }
}

foreach ($att_columns as $col => $alter_sql) {
    if (!in_array($col, $att_existing_cols)) {
        @mysqli_query($conn, $alter_sql);
    }
}

// 6. Ensure missing columns on leave_requests table
$leave_columns = [
    'duration' => "ALTER TABLE leave_requests ADD COLUMN duration VARCHAR(50) DEFAULT NULL",
    'application_date' => "ALTER TABLE leave_requests ADD COLUMN application_date DATE DEFAULT NULL",
    'mc_attachment' => "ALTER TABLE leave_requests ADD COLUMN mc_attachment VARCHAR(255) DEFAULT NULL",
    'rejection_reason' => "ALTER TABLE leave_requests ADD COLUMN rejection_reason TEXT DEFAULT NULL",
    'start_time' => "ALTER TABLE leave_requests ADD COLUMN start_time TIME DEFAULT NULL",
    'end_time' => "ALTER TABLE leave_requests ADD COLUMN end_time TIME DEFAULT NULL"
];

$leave_cols_res = mysqli_query($conn, "SHOW COLUMNS FROM leave_requests");
$leave_existing_cols = [];
if ($leave_cols_res) {
    while ($r = mysqli_fetch_assoc($leave_cols_res)) {
        $leave_existing_cols[] = $r['Field'];
    }
}

foreach ($leave_columns as $col => $alter_sql) {
    if (!in_array($col, $leave_existing_cols)) {
        @mysqli_query($conn, $alter_sql);
    }
}
