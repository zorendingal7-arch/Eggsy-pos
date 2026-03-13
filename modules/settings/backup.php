<?php
require_once '../../config/session.php';
require_once '../../config/db.php';
requireLogin();

if ($_SESSION['role'] !== 'admin') {
    header('Location: /pos-system/cashier_dashboard.php');
    exit;
}

$db_name  = 'eggsy_pos';
$filename = 'eggsy_backup_' . date('Y-m-d_H-i-s') . '.sql';

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache');

$output = '';

// Header
$output .= "-- Eggsy POS Database Backup\n";
$output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
$output .= "-- Database: $db_name\n";
$output .= "-- --------------------------------------------------------\n\n";
$output .= "SET FOREIGN_KEY_CHECKS=0;\n";
$output .= "SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n";
$output .= "SET NAMES utf8mb4;\n\n";

// Get all tables
$tables_result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $tables_result->fetch_row()) {
    $tables[] = $row[0];
}

foreach ($tables as $table) {
    $output .= "-- --------------------------------------------------------\n";
    $output .= "-- Table: `$table`\n";
    $output .= "-- --------------------------------------------------------\n\n";

    // Drop and create table
    $create_result = $conn->query("SHOW CREATE TABLE `$table`");
    $create_row    = $create_result->fetch_row();
    $output .= "DROP TABLE IF EXISTS `$table`;\n";
    $output .= $create_row[1] . ";\n\n";

    // Table data
    $rows_result = $conn->query("SELECT * FROM `$table`");
    if ($rows_result->num_rows === 0) {
        $output .= "-- No data in `$table`\n\n";
        continue;
    }

    $output .= "INSERT INTO `$table` VALUES\n";
    $row_strings = [];

    while ($row = $rows_result->fetch_row()) {
        $values = [];
        foreach ($row as $value) {
            if ($value === null) {
                $values[] = 'NULL';
            } else {
                $values[] = "'" . $conn->real_escape_string($value) . "'";
            }
        }
        $row_strings[] = '(' . implode(', ', $values) . ')';
    }

    $output .= implode(",\n", $row_strings) . ";\n\n";
}

$output .= "SET FOREIGN_KEY_CHECKS=1;\n";
$output .= "-- End of backup\n";

echo $output;
exit;