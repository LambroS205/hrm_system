<?php
// tests/LeaveServiceTest.php

require_once __DIR__ . '/../core/LeaveService.php';

function assert_equals($expected, $actual, $message) {
    if ($expected === $actual) {
        echo " [PASS] $message\n";
    } else {
        echo " [FAIL] $message: Expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
    }
}

echo "=== KIỂM THỬ LEAVE SERVICE (QUỸ PHÉP NĂM - BLLĐ 2019) ===\n";

// 1. Nhân viên tiêu chuẩn làm đủ năm (thâm niên 2 năm)
$emp1 = ['join_date' => '2024-03-01'];
$ent1 = LeaveService::getAnnualLeaveEntitlement($emp1, 2026);
assert_equals(12.0, $ent1, "Nhân viên thâm niên 2 năm được hưởng 12 ngày phép");

// 2. Nhân viên có thâm niên 5 năm (được cộng 1 ngày phép thâm niên)
$emp2 = ['join_date' => '2021-01-10'];
$ent2 = LeaveService::getAnnualLeaveEntitlement($emp2, 2026);
assert_equals(13.0, $ent2, "Nhân viên thâm niên 5 năm được hưởng 13 ngày phép (+1 thâm niên)");

// 3. Nhân viên có thâm niên 11 năm (được cộng 2 ngày phép thâm niên)
$emp3 = ['join_date' => '2015-05-20'];
$ent3 = LeaveService::getAnnualLeaveEntitlement($emp3, 2026);
assert_equals(14.0, $ent3, "Nhân viên thâm niên 11 năm được hưởng 14 ngày phép (+2 thâm niên)");

// 4. Nhân viên mới gia nhập giữa năm (tháng 5/2026)
$emp4 = ['join_date' => '2026-05-10'];
$ent4 = LeaveService::getAnnualLeaveEntitlement($emp4, 2026);
// Tháng 5 đến 12 = 8 tháng -> 8 ngày phép
assert_equals(8.0, $ent4, "Nhân viên vào làm ngày 10/05/2026 được hưởng 8 ngày phép tỷ lệ");

// 5. Nhân viên mới gia nhập sau ngày 15 của tháng (ngày 20/08/2026)
$emp5 = ['join_date' => '2026-08-20'];
$ent5 = LeaveService::getAnnualLeaveEntitlement($emp5, 2026);
// Tháng 8 sau ngày 15 -> tính từ tháng 9 (9, 10, 11, 12 = 4 tháng -> 4 ngày)
assert_equals(4.0, $ent5, "Nhân viên vào làm ngày 20/08/2026 được hưởng 4 ngày phép tỷ lệ");

echo "\n[✓] Hoàn thành kiểm thử LeaveService!\n";

