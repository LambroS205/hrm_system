<?php
// database/seed_full_demo_data.php - Seeder dữ liệu mẫu toàn diện cho hệ thống AURA HRM

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/PayrollService.php';

echo "=========================================================\n";
echo "BẮT ĐẦU NẠP DỮ LIỆU MẪU TOÀN DIỆN CHO AURA HRM\n";
echo "=========================================================\n\n";

// 1. LẤY DANH SÁCH NHÂN SỰ VÀ PHÒNG BAN
$employees = $pdo->query("SELECT id, employee_code, fullname, branch_id, department_id, position_id FROM employees ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$totalEmps = count($employees);
echo "1. Tìm thấy {$totalEmps} nhân sự trong hệ thống.\n";

$departments = $pdo->query("SELECT id, name, code, manager_id FROM departments")->fetchAll(PDO::FETCH_ASSOC);
$branches = $pdo->query("SELECT id, name, code, manager_id FROM branches")->fetchAll(PDO::FETCH_ASSOC);
$positions = $pdo->query("SELECT id, name FROM positions")->fetchAll(PDO::FETCH_ASSOC);
$kpiTemplates = $pdo->query("SELECT id, department_id FROM kpi_templates")->fetchAll(PDO::FETCH_ASSOC);

// Map template theo department
$deptTemplateMap = [];
foreach ($kpiTemplates as $t) {
    if (!isset($deptTemplateMap[$t['department_id']])) {
        $deptTemplateMap[$t['department_id']] = $t['id'];
    }
}
$defaultTemplateId = !empty($kpiTemplates) ? $kpiTemplates[0]['id'] : 1;

// -------------------------------------------------------------
// 2. TẠO DỮ LIỆU ĐIỂM DANH THÁNG 10/2026 (TỪ 2026-10-01 ĐẾN 2026-10-13)
// -------------------------------------------------------------
echo "2. Khởi tạo dữ liệu chấm công Tháng 10/2026 (từ 01/10 đến 13/10/2026)...\n";

$octWorkingDays = [
    '2026-10-01', // Thứ 5
    '2026-10-02', // Thứ 6
    '2026-10-03', // Thứ 7 (khối cửa hàng / bán lẻ làm việc)
    '2026-10-05', // Thứ 2
    '2026-10-06', // Thứ 3
    '2026-10-07', // Thứ 4
    '2026-10-08', // Thứ 5
    '2026-10-09', // Thứ 6
    '2026-10-10', // Thứ 7
    '2026-10-12', // Thứ 2
    '2026-10-13', // Thứ 3 (hôm nay)
];

$attStmt = $pdo->prepare("
    INSERT INTO attendance (employee_id, date, check_in, check_out, overtime_hours, late_minutes, early_minutes, status, leave_type, note)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        check_in = VALUES(check_in),
        check_out = VALUES(check_out),
        overtime_hours = VALUES(overtime_hours),
        late_minutes = VALUES(late_minutes),
        early_minutes = VALUES(early_minutes),
        status = VALUES(status),
        leave_type = VALUES(leave_type),
        note = VALUES(note)
");

$attCount = 0;
foreach ($octWorkingDays as $dayIndex => $dateStr) {
    $dayOfWeek = date('N', strtotime($dateStr)); // 6 = Saturday
    $isSaturday = ($dayOfWeek == 6);

    foreach ($employees as $idx => $emp) {
        $empId = $emp['id'];
        
        // Seed ngẫu nhiên theo tỷ lệ thực tế
        $rand = ($empId * 17 + $dayIndex * 23) % 100;

        if ($isSaturday && $emp['department_id'] <= 3) {
            // Khối văn phòng tổng bộ nghỉ thứ 7
            continue;
        }

        if ($rand < 84) {
            // Đi làm đúng giờ
            $inMin = str_pad(($empId + $dayIndex * 3) % 25 + 40, 2, '0', STR_PAD_LEFT); // 07:40 - 08:04
            $inHour = '07';
            if ($inMin >= 60) {
                $inHour = '08';
                $inMin = str_pad($inMin - 60, 2, '0', STR_PAD_LEFT);
            }
            $checkIn = "{$inHour}:{$inMin}:12";

            $outMin = str_pad(($empId * 2 + $dayIndex) % 45 + 15, 2, '0', STR_PAD_LEFT);
            $checkOut = "17:{$outMin}:35";
            $ot = (($empId + $dayIndex) % 7 === 0) ? 1.5 : 0.0;
            $status = 'present';
            $late = 0;
            $early = 0;
            $leaveType = null;
            $note = ($ot > 0) ? 'Tăng ca hoàn thành công việc' : 'Đi làm đúng giờ';
        } elseif ($rand < 92) {
            // Đi muộn
            $lateMin = 10 + (($empId + $dayIndex) % 35);
            $checkIn = "08:" . str_pad($lateMin, 2, '0', STR_PAD_LEFT) . ":20";
            $checkOut = "17:35:00";
            $ot = 0.0;
            $status = 'late';
            $late = $lateMin;
            $early = 0;
            $leaveType = null;
            $note = "Đi muộn {$lateMin} phút do kẹt xe";
        } elseif ($rand < 97) {
            // Nghỉ phép có phép
            $checkIn = null;
            $checkOut = null;
            $ot = 0.0;
            $status = 'leave_with_permit';
            $late = 0;
            $early = 0;
            $leaveType = ($rand % 2 === 0) ? 'annual' : 'sick';
            $note = ($leaveType === 'annual') ? 'Nghỉ phép năm đã được duyệt' : 'Nghỉ ốm theo chỉ định y tế';
        } else {
            // Về sớm hoặc vắng
            $checkIn = "07:55:00";
            $checkOut = "16:30:00";
            $ot = 0.0;
            $status = 'early_leave';
            $late = 0;
            $early = 30;
            $leaveType = null;
            $note = 'Về sớm có lý do đột xuất';
        }

        $attStmt->execute([
            $empId,
            $dateStr,
            $checkIn,
            $checkOut,
            $ot,
            $late,
            $early,
            $status,
            $leaveType,
            $note
        ]);
        $attCount++;
    }
}
echo "   -> Đã nạp thành công {$attCount} lượt chấm công tháng 10/2026.\n";

// -------------------------------------------------------------
// 3. TẠO DỮ LIỆU ĐỀ XUẤT DOANH NGHIỆP (`proposals`)
// -------------------------------------------------------------
echo "3. Bổ sung các đề xuất duyệt phép & công tác phong phú (`proposals`)...\n";

$sampleProposals = [
    [
        'code' => 'DX-2026-008',
        'emp_id' => 8,
        'type' => 'leave',
        'title' => 'Xin nghỉ phép năm về thăm gia đình miền Trung',
        'desc' => 'Tôi làm đơn này xin phép nghỉ phép thường niên 2 ngày để về giải quyết việc gia đình. Công việc bàn giao cho đồng nghiệp Trần Thị Hương Giang.',
        'start' => '2026-10-14',
        'end' => '2026-10-15',
        'leave_type' => 'annual',
        'days' => 2.0,
        'amount' => 0,
        'prio' => 'normal',
        'status' => 'pending',
        'approved_by' => null,
        'created_at' => '2026-10-12 08:30:00'
    ],
    [
        'code' => 'DX-2026-009',
        'emp_id' => 12,
        'type' => 'overtime',
        'title' => 'Đề xuất tăng ca kiểm kê kho hàng định kỳ đầu Quý 4',
        'desc' => 'Kiểm kê toàn diện số lượng hàng hóa và thiết bị tại Trung tâm tiếp vận Cần Thơ phục vụ báo cáo tài chính.',
        'start' => '2026-10-10',
        'end' => '2026-10-10',
        'leave_type' => null,
        'days' => 1.0,
        'amount' => 1800000,
        'prio' => 'high',
        'status' => 'approved',
        'approved_by' => 1,
        'created_at' => '2026-10-08 14:15:00'
    ],
    [
        'code' => 'DX-2026-010',
        'emp_id' => 15,
        'type' => 'business_trip',
        'title' => 'Công tác kiểm tra chất lượng dịch vụ chi nhánh Hải Phòng',
        'desc' => 'Đoàn công tác 3 ngày đánh giá quy trình phục vụ khách hàng VIP và kiểm định tiêu chuẩn nhận diện thương hiệu.',
        'start' => '2026-10-18',
        'end' => '2026-10-20',
        'leave_type' => null,
        'days' => 3.0,
        'amount' => 6500000,
        'prio' => 'high',
        'status' => 'pending',
        'approved_by' => null,
        'created_at' => '2026-10-11 10:20:00'
    ],
    [
        'code' => 'DX-2026-011',
        'emp_id' => 22,
        'type' => 'equipment',
        'title' => 'Đề xuất trang bị máy in hóa đơn nhiệt tốc độ cao cho quầy thu ngân',
        'desc' => 'Máy in cũ tại cửa hàng Flagship Bitexco hay kẹt giấy giờ cao điểm, ảnh hưởng trải nghiệm khách hàng.',
        'start' => '2026-10-09',
        'end' => '2026-10-09',
        'leave_type' => null,
        'days' => 0.0,
        'amount' => 4200000,
        'prio' => 'urgent',
        'status' => 'approved',
        'approved_by' => 2,
        'created_at' => '2026-10-09 09:00:00'
    ],
    [
        'code' => 'DX-2026-012',
        'emp_id' => 30,
        'type' => 'salary_raise',
        'title' => 'Đề xuất tăng lương vượt bậc cho Kỹ sư AI Data Senior',
        'desc' => 'Đồng chí đã hoàn thiện hệ thống dự báo nhu cầu tồn kho giảm 18% chi phí lưu kho trong Quý 3/2026.',
        'start' => '2026-10-01',
        'end' => '2026-10-01',
        'leave_type' => null,
        'days' => 0.0,
        'amount' => 5000000,
        'prio' => 'normal',
        'status' => 'approved',
        'approved_by' => 1,
        'created_at' => '2026-10-05 16:40:00'
    ],
    [
        'code' => 'DX-2026-013',
        'emp_id' => 5,
        'type' => 'leave',
        'title' => 'Xin nghỉ thai sản chế độ 6 tháng',
        'desc' => 'Kính gửi Ban Giám đốc và Phòng Nhân sự, tôi xin nghỉ chế độ thai sản theo quy định luật lao động từ giữa tháng 10.',
        'start' => '2026-10-20',
        'end' => '2027-04-20',
        'leave_type' => 'maternity',
        'days' => 180.0,
        'amount' => 0,
        'prio' => 'high',
        'status' => 'approved',
        'approved_by' => 1,
        'created_at' => '2026-10-07 11:30:00'
    ],
    [
        'code' => 'DX-2026-014',
        'emp_id' => 18,
        'type' => 'leave',
        'title' => 'Nghỉ điều trị ốm sốt theo đơn bác sĩ',
        'desc' => 'Tôi bị sốt xuất huyết cần nhập viện theo dõi 3 ngày, xin đính kèm giấy chỉ định của Bệnh viện Hoàn Mỹ.',
        'start' => '2026-10-06',
        'end' => '2026-10-08',
        'leave_type' => 'sick',
        'days' => 3.0,
        'amount' => 0,
        'prio' => 'normal',
        'status' => 'approved',
        'approved_by' => 2,
        'created_at' => '2026-10-06 07:15:00'
    ],
    [
        'code' => 'DX-2026-015',
        'emp_id' => 27,
        'type' => 'other',
        'title' => 'Đề xuất kinh phí tổ chức teambuilding gắn kết quý 4 phòng Marketing',
        'desc' => 'Chương trình dã ngoại cuối tuần tại Cần Giờ nhằm tái tạo năng lượng cho chiến dịch mua sắm Black Friday & Tết.',
        'start' => '2026-10-25',
        'end' => '2026-10-26',
        'leave_type' => null,
        'days' => 2.0,
        'amount' => 15000000,
        'prio' => 'normal',
        'status' => 'pending',
        'approved_by' => null,
        'created_at' => '2026-10-12 14:00:00'
    ],
    [
        'code' => 'DX-2026-016',
        'emp_id' => 35,
        'type' => 'overtime',
        'title' => 'Đề xuất trực vận hành hệ thống ERP & Máy chủ đêm Chủ Nhật',
        'desc' => 'Nâng cấp phiên bản bảo mật hệ thống thanh toán POS và database cluster.',
        'start' => '2026-10-11',
        'end' => '2026-10-11',
        'leave_type' => null,
        'days' => 1.0,
        'amount' => 2500000,
        'prio' => 'urgent',
        'status' => 'approved',
        'approved_by' => 1,
        'created_at' => '2026-10-09 17:00:00'
    ],
    [
        'code' => 'DX-2026-017',
        'emp_id' => 41,
        'type' => 'leave',
        'title' => 'Xin nghỉ việc riêng kết hôn',
        'desc' => 'Tôi xin nghỉ phép chế độ kết hôn 3 ngày theo Bộ luật Lao động 2019, từ ngày 15/10 đến 17/10/2026.',
        'start' => '2026-10-15',
        'end' => '2026-10-17',
        'leave_type' => 'personal',
        'days' => 3.0,
        'amount' => 0,
        'prio' => 'normal',
        'status' => 'approved',
        'approved_by' => 1,
        'created_at' => '2026-10-10 09:30:00'
    ],
    [
        'code' => 'DX-2026-018',
        'emp_id' => 50,
        'type' => 'equipment',
        'title' => 'Trang bị máy quét kiểm kho tự động Zebra TC21',
        'desc' => 'Phục vụ quy trình xuất nhập kho chi nhánh Đà Nẵng, đồng bộ tức thời với cơ sở dữ liệu trung tâm.',
        'start' => '2026-10-13',
        'end' => '2026-10-13',
        'leave_type' => null,
        'days' => 0.0,
        'amount' => 8500000,
        'prio' => 'high',
        'status' => 'pending',
        'approved_by' => null,
        'created_at' => '2026-10-13 08:00:00'
    ]
];

$propStmt = $pdo->prepare("
    INSERT INTO proposals (proposal_code, employee_id, type, title, description, start_date, end_date, leave_type, total_days, amount, priority, status, approved_by, approved_at, created_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        title = VALUES(title),
        status = VALUES(status),
        amount = VALUES(amount)
");

$propCount = 0;
foreach ($sampleProposals as $p) {
    $approvedAt = ($p['status'] === 'approved') ? date('Y-m-d H:i:s', strtotime($p['created_at'] . ' + 1 day')) : null;
    $propStmt->execute([
        $p['code'],
        $p['emp_id'],
        $p['type'],
        $p['title'],
        $p['desc'],
        $p['start'],
        $p['end'],
        $p['leave_type'],
        $p['days'],
        $p['amount'],
        $p['prio'],
        $p['status'],
        $p['approved_by'],
        $approvedAt,
        $p['created_at']
    ]);
    $propCount++;
}
echo "   -> Đã bổ sung {$propCount} đề xuất mới.\n";

// -------------------------------------------------------------
// 4. BỔ SUNG ĐÁNH GIÁ HIỆU SUẤT & KPI (`evaluations`)
// -------------------------------------------------------------
echo "4. Mở rộng đánh giá hiệu suất & KPI toàn diện cho 40+ cán bộ nhân viên...\n";

$evalStmt = $pdo->prepare("
    INSERT INTO evaluations (
        evaluation_code, period_id, employee_id, template_id, reviewer_id, status,
        self_score, self_scores_json, self_notes, self_submitted_at,
        manager_score, manager_scores_json, manager_notes, manager_submitted_at,
        final_score, rating, bonus_multiplier, promotion_recommendation,
        hr_approved_at, hr_approved_by, rating_grade, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?,
        ?, ?, ?, ?
    ) ON DUPLICATE KEY UPDATE 
        status = VALUES(status),
        final_score = VALUES(final_score),
        rating = VALUES(rating),
        rating_grade = VALUES(rating_grade)
");

$evalCount = 0;
// Tạo đánh giá cho 40 nhân viên đầu tiên trong Period 1 (Q3/2026)
for ($i = 0; $i < min(45, $totalEmps); $i++) {
    $emp = $employees[$i];
    $empId = $emp['id'];
    $periodId = 1; // Q3/2026
    $code = sprintf('DG-2026-Q3-%03d', $empId);
    $templateId = $deptTemplateMap[$emp['department_id']] ?? $defaultTemplateId;
    $reviewerId = 1; // HR Director hoặc Branch Manager

    // Phân bố điểm số thực tế
    $baseScore = 75 + (($empId * 13) % 23); // 75 đến 97 điểm
    $selfScore = min(99.0, $baseScore + (($empId % 5) - 2));
    $managerScore = min(98.5, $baseScore + (($empId % 3) - 1));
    $finalScore = round($selfScore * 0.3 + $managerScore * 0.7, 1);

    if ($finalScore >= 92) {
        $rating = 'outstanding';
        $grade = 'A+';
        $multiplier = 1.35;
        $promo = 'bonus';
    } elseif ($finalScore >= 85) {
        $rating = 'excellent';
        $grade = 'A';
        $multiplier = 1.20;
        $promo = 'none';
    } elseif ($finalScore >= 75) {
        $rating = 'good';
        $grade = 'B';
        $multiplier = 1.00;
        $promo = 'none';
    } else {
        $rating = 'average';
        $grade = 'C';
        $multiplier = 0.85;
        $promo = 'training';
    }

    $statusMod = $empId % 10;
    if ($statusMod === 1) {
        $evalStatus = 'pending';
        $selfScore = null; $managerScore = null; $finalScore = null; $rating = null;
    } elseif ($statusMod === 2) {
        $evalStatus = 'self_review';
        $managerScore = null; $finalScore = null; $rating = null;
    } elseif ($statusMod === 3) {
        $evalStatus = 'manager_review';
        $finalScore = null; $rating = null;
    } else {
        $evalStatus = 'completed';
    }

    $selfJson = json_encode([
        ['criteria' => 'Hoàn thành chỉ tiêu doanh thu & KPI công việc', 'score' => round($selfScore * 0.98, 1)],
        ['criteria' => 'Tuân thủ nội quy lao động & an toàn thông tin', 'score' => 95.0],
        ['criteria' => 'Kỹ năng làm việc nhóm & tinh thần trách nhiệm', 'score' => round($selfScore * 1.02, 1)]
    ]);

    $mgrJson = json_encode([
        ['criteria' => 'Hoàn thành chỉ tiêu doanh thu & KPI công việc', 'score' => round($managerScore * 0.97, 1)],
        ['criteria' => 'Tuân thủ nội quy lao động & an toàn thông tin', 'score' => 96.0],
        ['criteria' => 'Kỹ năng làm việc nhóm & tinh thần trách nhiệm', 'score' => round($managerScore * 1.01, 1)]
    ]);

    $selfNotes = "Tôi đã nỗ lực hoàn thành 100% các mục tiêu công việc được giao trong Quý 3/2026, hỗ trợ tích cực đồng nghiệp trong các dự án cao điểm.";
    $mgrNotes = "Nhân sự có tinh thần chủ động cao, xử lý công việc chuẩn xác và đạt hiệu quả vượt trội. Đề xuất ban giám đốc xem xét khen thưởng.";

    $evalStmt->execute([
        $code,
        $periodId,
        $empId,
        $templateId,
        $reviewerId,
        $evalStatus,
        $selfScore,
        $selfJson,
        $selfNotes,
        '2026-09-28 14:00:00',
        $managerScore,
        $mgrJson,
        $mgrNotes,
        '2026-09-30 16:30:00',
        $finalScore,
        $rating,
        $multiplier,
        $promo,
        ($evalStatus === 'completed' ? '2026-10-02 10:00:00' : null),
        ($evalStatus === 'completed' ? 1 : null),
        $grade,
        '2026-09-20 09:00:00'
    ]);
    $evalCount++;
}
echo "   -> Đã cập nhật {$evalCount} hồ sơ đánh giá KPI Quý 3/2026.\n";

// -------------------------------------------------------------
// 5. BỔ SUNG QUYẾT ĐỊNH KHEN THƯỞNG & KỶ LUẬT (`rewards`, `disciplines`)
// -------------------------------------------------------------
echo "5. Bổ sung các quyết định Khen thưởng & Kỷ luật mới nhất...\n";

$sampleRewards = [
    [
        'code' => 'KT-2026-016',
        'emp_id' => 3,
        'type' => 'achievement',
        'title' => 'Khen thưởng Sáng kiến Tối ưu Hóa Quy trình Vận hành Chuỗi Bán lẻ',
        'desc' => 'Đã có sáng kiến cải tiến quy trình giao vận nội bộ, tiết kiệm hơn 450 triệu đồng chi phí logistics trong 9 tháng đầu năm 2026.',
        'amount' => 15000000,
        'dec_num' => 'QD-KT-2026/016',
        'date' => '2026-10-05',
        'status' => 'executed'
    ],
    [
        'code' => 'KT-2026-017',
        'emp_id' => 7,
        'type' => 'bonus',
        'title' => 'Thưởng Nóng Đạt Doanh Số Kỷ Lục Tuần Khai Trương Cửa Hàng Flagship',
        'desc' => 'Tập thể quản lý và chuyên viên tư vấn chi nhánh TP.HCM hoàn thành 185% chỉ tiêu doanh thu tuần mở bán đầu tiên.',
        'amount' => 10000000,
        'dec_num' => 'QD-KT-2026/017',
        'date' => '2026-10-08',
        'status' => 'approved'
    ],
    [
        'code' => 'KT-2026-018',
        'emp_id' => 14,
        'type' => 'certificate',
        'title' => 'Vinh danh Nhân Viên Xuất Sắc Nhất Tháng 9/2026 Khối Dịch Vụ Khách Hàng',
        'desc' => 'Đạt điểm đánh giá mức độ hài lòng khách hàng CSAT 99.4%, giải quyết 100% khiếu nại trong vòng 2 giờ làm việc.',
        'amount' => 5000000,
        'dec_num' => 'QD-KT-2026/018',
        'date' => '2026-10-02',
        'status' => 'executed'
    ],
    [
        'code' => 'KT-2026-019',
        'emp_id' => 25,
        'type' => 'bonus',
        'title' => 'Thưởng Đạt Chứng Chỉ Chuyên Nghiệp Quốc Tế AWS DevOps Pro',
        'desc' => 'Khuyến khích tinh thần tự học tập và nâng cao năng lực chuyên môn theo chính sách phát triển nhân tài của Aura Group.',
        'amount' => 8000000,
        'dec_num' => 'QD-KT-2026/019',
        'date' => '2026-10-11',
        'status' => 'pending'
    ]
];

$rewStmt = $pdo->prepare("
    INSERT INTO rewards (reward_code, employee_id, reward_type, title, description, amount, decision_number, reward_date, status, created_by, approved_by, approved_at, executed_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW(), NOW())
    ON DUPLICATE KEY UPDATE title = VALUES(title), amount = VALUES(amount), status = VALUES(status)
");
foreach ($sampleRewards as $r) {
    $rewStmt->execute([
        $r['code'], $r['emp_id'], $r['type'], $r['title'], $r['desc'], $r['amount'], $r['dec_num'], $r['date'], $r['status']
    ]);
}

$sampleDisciplines = [
    [
        'code' => 'KL-2026-010',
        'emp_id' => 19,
        'type' => 'reprimand',
        'title' => 'Khiển trách vi phạm quy trình bàn giao ca và đối soát tiền mặt',
        'desc' => 'Vi phạm quy định không khóa két sắt cuối ngày ca tối ngày 08/10/2026 tại chi nhánh Đà Nẵng, gây rủi ro an toàn tài sản.',
        'amount' => 500000,
        'dec_num' => 'QD-KL-2026/010',
        'date' => '2026-10-10',
        'status' => 'approved'
    ],
    [
        'code' => 'KL-2026-011',
        'emp_id' => 33,
        'type' => 'warning',
        'title' => 'Cảnh cáo nhắc nhở đi làm muộn 4 lần liên tiếp trong tuần',
        'desc' => 'Đi muộn không báo cáo quản lý trực tiếp trong tuần đầu tháng 10/2026, vi phạm điều 14 Nội quy Lao động.',
        'amount' => 200000,
        'dec_num' => 'QD-KL-2026/011',
        'date' => '2026-10-12',
        'status' => 'pending'
    ]
];

$discStmt = $pdo->prepare("
    INSERT INTO disciplines (discipline_code, employee_id, discipline_type, title, description, penalty_amount, decision_number, discipline_date, status, created_by, approved_by, approved_at, executed_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, NOW(), NOW())
    ON DUPLICATE KEY UPDATE title = VALUES(title), penalty_amount = VALUES(penalty_amount), status = VALUES(status)
");
foreach ($sampleDisciplines as $d) {
    $discStmt->execute([
        $d['code'], $d['emp_id'], $d['type'], $d['title'], $d['desc'], $d['amount'], $d['dec_num'], $d['date'], $d['status']
    ]);
}
echo "   -> Đã bổ sung các quyết định khen thưởng & kỷ luật mới.\n";

// -------------------------------------------------------------
// 6. BỔ SUNG ỨNG VIÊN TUYỂN DỤNG (`candidates`)
// -------------------------------------------------------------
echo "6. Bổ sung ứng viên mới vào Pipeline Tuyển dụng (`candidates`)...\n";

$sampleCandidates = [
    ['UV-2026-041', 1, 'Trần Minh Đức', 'duc.tran@gmail.com', '0912345671', 'Nam', '1996-04-12', 'Đại học Bách Khoa TP.HCM - Khoa học Máy tính', 5, 'headhunt', 'interview', 5, '2026-10-15 14:00:00', 'Ứng viên có chuyên môn sâu về MLOps và Computer Vision, phù hợp vị trí Lead.'],
    ['UV-2026-042', 1, 'Nguyễn Thanh Thảo', 'thao.nguyen@yahoo.com', '0987654321', 'Nu', '1998-08-20', 'Đại học Khoa học Tự nhiên - Toán Tin', 3, 'linkedin', 'offer', 4, '2026-10-10 09:30:00', 'Đã phỏng vấn đạt 4/5 sao, đã gửi thư mời nhận việc mức lương 35M.'],
    ['UV-2026-043', 2, 'Hoàng Khắc Tiệp', 'tiep.hoang@gmail.com', '0903112233', 'Nam', '1993-11-05', 'Đại học Kinh tế TP.HCM - Quản trị Kinh doanh', 7, 'referral', 'screening', 4, null, 'Kinh nghiệm 4 năm làm Store Manager tại Uniqlo, hồ sơ rất tiềm năng.'],
    ['UV-2026-044', 3, 'Lâm Hải Yến', 'yen.lam@gmail.com', '0934556677', 'Nu', '1995-02-18', 'Đại học Cần Thơ - Logistics & Chuỗi Cung Ứng', 4, 'website', 'applied', 3, null, 'Hồ sơ mới nộp qua cổng tuyển dụng, đang chờ HR duyệt CV.'],
    ['UV-2026-045', 4, 'Phan Hữu Thắng', 'thang.phan@gmail.com', '0918776655', 'Nam', '1994-07-29', 'Đại học FPT - Kỹ thuật Phần mềm', 6, 'job_board', 'interview', 4, '2026-10-14 10:00:00', 'Hẹn phỏng vấn vòng 2 Technical với Giám đốc Công nghệ.'],
    ['UV-2026-046', 6, 'Đỗ Quỳnh Chi', 'chi.do@gmail.com', '0988223344', 'Nu', '1997-09-14', 'Đại học Ngoại Thương - Marketing', 4, 'social', 'hired', 5, '2026-10-01 15:00:00', 'Đã tiếp nhận nhận việc chính thức từ ngày 12/10/2026.']
];

$candStmt = $pdo->prepare("
    INSERT INTO candidates (
        candidate_code, job_position_id, fullname, email, phone, gender, birth_date,
        education, experience_years, source, stage, rating, interview_date, interview_notes
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?,
        ?, ?, ?, ?, ?, ?, ?
    ) ON DUPLICATE KEY UPDATE stage = VALUES(stage), rating = VALUES(rating), interview_notes = VALUES(interview_notes)
");

foreach ($sampleCandidates as $c) {
    $candStmt->execute($c);
}
echo "   -> Đã bổ sung 6 hồ sơ ứng viên chất lượng cao vào các giai đoạn Pipeline.\n";

// -------------------------------------------------------------
// 7. TÍNH TOÁN BẢNG LƯƠNG THÁNG 10/2026 CHUẨN XÁC & ĐẦY ĐỦ
// -------------------------------------------------------------
echo "7. Xóa số liệu lương trống (0đ) và TÍNH TOÁN TOÀN DIỆN BẢNG LƯƠNG THÁNG 10/2026...\n";

// Xóa bản ghi lương tháng 10 đã bị khởi tạo rỗng trước đó để tính toán mới dựa trên chấm công
$pdo->exec("DELETE FROM payrolls WHERE month = 10 AND year = 2026");

// Gọi PayrollService::calculateMonth để chạy thuật toán chuẩn Luật Lao Động 2026
$payrollResult = PayrollService::calculateMonth($pdo, 10, 2026, null, 1);
echo "   -> Kết quả: Đã tính toán thành công cho {$payrollResult['calculated_count']} cán bộ nhân viên!\n";
echo "   -> Số ngày công chuẩn: {$payrollResult['standard_days']} ngày, Ngày lễ: {$payrollResult['holiday_count']} ngày.\n";

// Cập nhật trạng thái thanh toán cho một số nhân sự đã chi lương tạm ứng hoặc duyệt lương ban lãnh đạo
$pdo->exec("
    UPDATE payrolls 
    SET payment_status = 'paid', locked_at = NOW() 
    WHERE month = 10 AND year = 2026 AND employee_id IN (1, 2, 3, 4, 6, 7)
");

// Kiểm tra tổng số tiền quỹ lương tháng 10
$summaryStmt = $pdo->query("
    SELECT 
        COUNT(*) as total_emps,
        SUM(gross_salary) as total_gross,
        SUM(final_salary) as total_net,
        SUM(pit_amount) as total_pit,
        SUM(bhxh_employee + bhyt_employee + bhtn_employee) as total_ins,
        COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_emps
    FROM payrolls 
    WHERE month = 10 AND year = 2026
");
$sum = $summaryStmt->fetch(PDO::FETCH_ASSOC);

echo "\n=========================================================\n";
echo "BÁO CÁO TỔNG KẾT BẢNG LƯƠNG THÁNG 10/2026 SAU KHI TÍNH TOÁN:\n";
echo "- Tổng nhân sự có bảng lương: {$sum['total_emps']} người\n";
echo "- Tổng Quỹ Lương Gross       : " . number_format($sum['total_gross']) . " VNĐ\n";
echo "- Tổng Lương Thực Nhận (Net) : " . number_format($sum['total_net']) . " VNĐ\n";
echo "- Tổng Thuế TNCN (PIT) Khấu Trừ: " . number_format($sum['total_pit']) . " VNĐ\n";
echo "- Tổng Bảo Hiểm Trích Nộp    : " . number_format($sum['total_ins']) . " VNĐ\n";
echo "- Đã thanh toán chi trả     : {$sum['paid_emps']} / {$sum['total_emps']} nhân sự\n";
echo "=========================================================\n";
echo "HOÀN TẤT NẠP DỮ LIỆU MẪU TOÀN DIỆN THÀNH CÔNG!\n";
