<?php
// database/seed_data.php - Nạp dữ liệu mẫu Enterprise Grade vào cơ sở dữ liệu hrm_system

require_once __DIR__ . '/../config/database.php';

$sqlFile = __DIR__ . '/demo_seed_aura_enterprise.sql';
if (!file_exists($sqlFile)) {
    die("File SQL không tồn tại: {$sqlFile}\n");
}

echo "Bắt đầu nạp dữ liệu từ {$sqlFile}...\n";
$sql = file_get_contents($sqlFile);

try {
    $pdo->exec($sql);
    echo "Nạp dữ liệu mẫu Enterprise Grade thành công!\n";
} catch (Exception $e) {
    echo "Lỗi khi nạp dữ liệu: " . $e->getMessage() . "\n";
    exit(1);
}
