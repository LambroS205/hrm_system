<?php
// database/seed_full_workforce_and_history.php
// Script cập nhật & bổ sung toàn diện dữ liệu nhân sự, chi nhánh, phòng ban và dữ liệu lịch sử các năm/tháng

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/PayrollCalculator.php';

echo "========================================================\n";
echo "BẮT ĐẦU CẬP NHẬT DỮ LIỆU NHÂN SỰ & LỊCH SỬ DOANH NGHIỆP\n";
echo "========================================================\n\n";

$pdo->beginTransaction();

try {
    // -------------------------------------------------------------
    // BƯỚC 1: BỔ SUNG & CÂN ĐỐI CÁC PHÒNG BAN CHO CẢ 6 CHI NHÁNH
    // -------------------------------------------------------------
    echo "[1/6] Chuẩn hóa và bổ sung phòng ban cho các chi nhánh...\n";
    
    $new_depts = [
        [
            'code' => 'DN_TRANS',
            'name' => 'Đội Điều Vận & Giao Hàng Miền Trung',
            'branch_id' => 3,
            'target_headcount' => 9,
            'description' => 'Quản lý đội xe trung chuyển và giao nhận chuỗi WinMart/WinMart+ khu vực miền Trung'
        ],
        [
            'code' => 'CT_INV',
            'name' => 'Đội Kiểm Soát Tồn Kho & Chuỗi Cung Ứng Mekong',
            'branch_id' => 4,
            'target_headcount' => 7,
            'description' => 'Kiểm soát hàng tươi sống, nông sản Đồng bằng Sông Cửu Long'
        ],
        [
            'code' => 'HP_WH',
            'name' => 'Bộ Phận Kho Ngoại Quan & Cảng Biển Hải Phòng',
            'branch_id' => 5,
            'target_headcount' => 7,
            'description' => 'Lưu trữ và thông quan hàng nhập khẩu cảng Đình Vũ - Lạch Huyện'
        ],
        [
            'code' => 'BD_AUTO',
            'name' => 'Khối Tổng Kho Phân Phối Tự Động Bình Dương',
            'branch_id' => 6,
            'target_headcount' => 9,
            'description' => 'Vận hành robot AGV và băng chuyền phân loại hàng tự động quy mô lớn'
        ]
    ];

    $dept_map = []; // code -> id
    $all_depts = $pdo->query("SELECT id, code, branch_id FROM departments")->fetchAll();
    foreach ($all_depts as $d) {
        $dept_map[$d['code']] = (int)$d['id'];
    }

    $insert_dept_stmt = $pdo->prepare("
        INSERT INTO departments (code, name, branch_id, target_headcount, description)
        VALUES (?, ?, ?, ?, ?)
    ");

    foreach ($new_depts as $nd) {
        if (!isset($dept_map[$nd['code']])) {
            $insert_dept_stmt->execute([
                $nd['code'],
                $nd['name'],
                $nd['branch_id'],
                $nd['target_headcount'],
                $nd['description']
            ]);
            $dept_map[$nd['code']] = (int)$pdo->lastInsertId();
            echo "  + Đã tạo phòng ban mới: {$nd['name']} ({$nd['code']}) cho Chi nhánh #{$nd['branch_id']}\n";
        }
    }

    // Cập nhật target_headcount cho các phòng ban hiện hữu để cân đối định biên
    $pdo->exec("UPDATE departments SET target_headcount = 3 WHERE code = 'BGD'");
    $pdo->exec("UPDATE departments SET target_headcount = 12 WHERE code = 'HR'");
    $pdo->exec("UPDATE departments SET target_headcount = 15 WHERE code = 'IT'");
    $pdo->exec("UPDATE departments SET target_headcount = 14 WHERE code = 'FIN'");
    $pdo->exec("UPDATE departments SET target_headcount = 12 WHERE code = 'LEGAL'");
    $pdo->exec("UPDATE departments SET target_headcount = 20 WHERE code = 'RETAIL'");
    $pdo->exec("UPDATE departments SET target_headcount = 10 WHERE code = 'MKT'");
    $pdo->exec("UPDATE departments SET target_headcount = 8 WHERE code = 'CX'");
    $pdo->exec("UPDATE departments SET target_headcount = 15 WHERE code = 'SCM'");
    $pdo->exec("UPDATE departments SET target_headcount = 12 WHERE code = 'CT_LOG'");
    $pdo->exec("UPDATE departments SET target_headcount = 10 WHERE code = 'HP_XNK'");
    $pdo->exec("UPDATE departments SET target_headcount = 12 WHERE code = 'BD_RETAIL'");

    // Cập nhật lại dept_map
    $all_depts = $pdo->query("SELECT id, code, branch_id FROM departments")->fetchAll();
    $dept_map = [];
    foreach ($all_depts as $d) {
        $dept_map[$d['code']] = (int)$d['id'];
    }

    // -------------------------------------------------------------
    // BƯỚC 2: TÁI CẤU TRÚC PHÒNG BAN CHO NHÂN SỰ HIỆN TẠI
    // -------------------------------------------------------------
    echo "\n[2/6] Điều chỉnh phòng ban nhân viên hiện hữu cho đúng chi nhánh...\n";
    
    // Branch 4 (Cần Thơ) -> gán vào CT_LOG hoặc CT_INV
    $emps_b4 = $pdo->query("SELECT id FROM employees WHERE branch_id = 4")->fetchAll(PDO::FETCH_COLUMN);
    $ct_log_id = $dept_map['CT_LOG'];
    $ct_inv_id = $dept_map['CT_INV'] ?? $ct_log_id;
    foreach ($emps_b4 as $idx => $eid) {
        $did = ($idx % 2 === 0) ? $ct_log_id : $ct_inv_id;
        $pdo->prepare("UPDATE employees SET department_id = ? WHERE id = ?")->execute([$did, $eid]);
    }
    echo "  + Đã chuẩn hóa " . count($emps_b4) . " nhân sự Chi nhánh Cần Thơ vào phòng ban nội bộ.\n";

    // Branch 5 (Hải Phòng) -> gán vào HP_XNK hoặc HP_WH
    $emps_b5 = $pdo->query("SELECT id FROM employees WHERE branch_id = 5")->fetchAll(PDO::FETCH_COLUMN);
    $hp_xnk_id = $dept_map['HP_XNK'];
    $hp_wh_id  = $dept_map['HP_WH'] ?? $hp_xnk_id;
    foreach ($emps_b5 as $idx => $eid) {
        $did = ($idx % 2 === 0) ? $hp_xnk_id : $hp_wh_id;
        $pdo->prepare("UPDATE employees SET department_id = ? WHERE id = ?")->execute([$did, $eid]);
    }
    echo "  + Đã chuẩn hóa " . count($emps_b5) . " nhân sự Chi nhánh Hải Phòng vào phòng ban nội bộ.\n";

    // Branch 6 (Bình Dương) -> gán vào BD_RETAIL hoặc BD_AUTO
    $emps_b6 = $pdo->query("SELECT id FROM employees WHERE branch_id = 6")->fetchAll(PDO::FETCH_COLUMN);
    $bd_ret_id = $dept_map['BD_RETAIL'];
    $bd_aut_id = $dept_map['BD_AUTO'] ?? $bd_ret_id;
    foreach ($emps_b6 as $idx => $eid) {
        $did = ($idx % 2 === 0) ? $bd_ret_id : $bd_aut_id;
        $pdo->prepare("UPDATE employees SET department_id = ? WHERE id = ?")->execute([$did, $eid]);
    }
    echo "  + Đã chuẩn hóa " . count($emps_b6) . " nhân sự Chi nhánh Bình Dương vào phòng ban nội bộ.\n";

    // Branch 3 (Đà Nẵng) -> phân bổ một số vào DN_TRANS
    $emps_b3 = $pdo->query("SELECT id FROM employees WHERE branch_id = 3")->fetchAll(PDO::FETCH_COLUMN);
    $dn_trans_id = $dept_map['DN_TRANS'] ?? $dept_map['SCM'];
    foreach ($emps_b3 as $idx => $eid) {
        if ($idx >= 8) {
            $pdo->prepare("UPDATE employees SET department_id = ? WHERE id = ?")->execute([$dn_trans_id, $eid]);
        }
    }
    echo "  + Đã chuẩn hóa phân bổ nhân sự Chi nhánh Đà Nẵng.\n";

    // -------------------------------------------------------------
    // BƯỚC 3: BỔ SUNG NHÂN SỰ ĐỂ CÁC CHI NHÁNH ĐẠT 90-95% ĐỊNH BIÊN
    // -------------------------------------------------------------
    echo "\n[3/6] Bổ sung hồ sơ nhân sự đầy đủ cho từng chi nhánh...\n";

    // Lấy số lượng hiện tại
    $current_counts = $pdo->query("
        SELECT branch_id, COUNT(*) as cnt 
        FROM employees 
        WHERE employment_status != 'resigned' 
        GROUP BY branch_id
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Mục tiêu bổ sung:
    // Branch 1 (HCM, target 60): hiện 20 -> bổ sung 36 -> tổng 56 (93.3%)
    // Branch 2 (HN, target 40): hiện 15 -> bổ sung 22 -> tổng 37 (92.5%)
    // Branch 3 (DN, target 25): hiện 15 -> bổ sung 8  -> tổng 23 (92.0%)
    // Branch 4 (CT, target 20): hiện 13 -> bổ sung 5  -> tổng 18 (90.0%)
    // Branch 5 (HP, target 18): hiện 11 -> bổ sung 5  -> tổng 16 (88.9%)
    // Branch 6 (BD, target 22): hiện 10 -> bổ sung 10 -> tổng 20 (90.9%)
    // Tổng số thêm mới: 36 + 22 + 8 + 5 + 5 + 10 = 86 người. Tổng toàn hệ thống: 170 / 175 = 97.1%.

    $needed_by_branch = [
        1 => 36,
        2 => 22,
        3 => 8,
        4 => 5,
        5 => 5,
        6 => 10
    ];

    // Ngân hàng tên tiếng Việt phong phú & chuyên nghiệp
    $ho_list = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương', 'Lý', 'Đinh', 'Đoàn', 'Lâm', 'Mai'];
    $dem_nam = ['Văn', 'Đình', 'Hữu', 'Đức', 'Quang', 'Minh', 'Thành', 'Tuấn', 'Tiến', 'Xuân', 'Hoàng', 'Bảo', 'Gia', 'Nhật', 'Trọng'];
    $ten_nam = ['Anh', 'Bình', 'Cường', 'Dũng', 'Đạt', 'Hải', 'Huy', 'Khoa', 'Kiên', 'Long', 'Nam', 'Phong', 'Quân', 'Sơn', 'Thắng', 'Thịnh', 'Trung', 'Tùng', 'Vinh', 'Vũ'];
    $dem_nu  = ['Thị', 'Ngọc', 'Thanh', 'Phương', 'Mai', 'Thu', 'Hồng', 'Mỹ', 'Khánh', 'Ánh', 'Diệu', 'Tuyết', 'Bích', 'Trúc', 'Hải'];
    $ten_nu  = ['An', 'Chi', 'Dung', 'Hà', 'Hạnh', 'Hoa', 'Hương', 'Lan', 'Linh', 'Ly', 'Mai', 'Nga', 'Ngọc', 'Nhi', 'Oanh', 'Phương', 'Quỳnh', 'Thảo', 'Trang', 'Yến'];

    // Lấy max mã NV
    $last_code_num = 84;
    $max_code_str = $pdo->query("SELECT MAX(employee_code) FROM employees")->fetchColumn();
    if ($max_code_str && preg_match('/(\d+)/', $max_code_str, $m)) {
        $last_code_num = max($last_code_num, (int)$m[1]);
    }

    $default_hash = password_hash('WinMart@2026', PASSWORD_DEFAULT);

    // Danh sách phòng ban và vị trí phù hợp theo chi nhánh
    $branch_depts = [
        1 => [$dept_map['BGD'], $dept_map['HR'], $dept_map['IT'], $dept_map['FIN'], $dept_map['LEGAL']],
        2 => [$dept_map['RETAIL'], $dept_map['MKT'], $dept_map['CX']],
        3 => [$dept_map['SCM'], $dept_map['DN_TRANS']],
        4 => [$dept_map['CT_LOG'], $dept_map['CT_INV']],
        5 => [$dept_map['HP_XNK'], $dept_map['HP_WH']],
        6 => [$dept_map['BD_RETAIL'], $dept_map['BD_AUTO']]
    ];

    $dept_positions = [
        $dept_map['BGD']       => [1, 3],
        $dept_map['HR']        => [7, 7, 7, 7],
        $dept_map['IT']        => [5, 9, 5, 9],
        $dept_map['FIN']       => [11, 11, 11],
        $dept_map['LEGAL']     => [12, 12, 12],
        $dept_map['RETAIL']    => [4, 8, 8, 8, 8],
        $dept_map['MKT']       => [10, 10, 10],
        $dept_map['CX']        => [13, 13, 13],
        $dept_map['SCM']       => [6, 14, 14, 14],
        $dept_map['DN_TRANS']  => [14, 14, 8],
        $dept_map['CT_LOG']    => [6, 14, 14],
        $dept_map['CT_INV']    => [14, 14, 8],
        $dept_map['HP_XNK']    => [6, 12, 14],
        $dept_map['HP_WH']     => [14, 14, 8],
        $dept_map['BD_RETAIL'] => [4, 8, 8, 8],
        $dept_map['BD_AUTO']   => [6, 14, 5, 9]
    ];

    $pos_salaries = $pdo->query("SELECT id, base_salary FROM positions")->fetchAll(PDO::FETCH_KEY_PAIR);

    $stmt_user = $pdo->prepare("
        INSERT INTO users (role_id, username, password, fullname, email, is_superadmin, status)
        VALUES (4, ?, ?, ?, ?, 0, 'active')
    ");

    $stmt_emp = $pdo->prepare("
        INSERT INTO employees (
            employee_code, fullname, gender, birth_date, identity_card, phone, email, 
            bank_account, address, branch_id, department_id, position_id, user_id, 
            hire_date, employment_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'official')
    ");

    $stmt_contract = $pdo->prepare("
        INSERT INTO employee_contracts (
            employee_id, contract_number, contract_type, start_date, end_date, 
            base_salary, allowance, status
        ) VALUES (?, ?, 'fixed_term', ?, ?, ?, ?, 'active')
    ");

    $stmt_ins = $pdo->prepare("
        INSERT INTO employee_insurance (
            employee_id, insurance_type, insurance_number, start_date, hospital_name, status
        ) VALUES (?, 'social', ?, ?, ?, 'active')
    ");

    $hospitals = [
        1 => 'Bệnh viện Đa khoa Sài Gòn (TP.HCM)',
        2 => 'Bệnh viện Đa khoa Đống Đa (Hà Nội)',
        3 => 'Bệnh viện C Đà Nẵng',
        4 => 'Bệnh viện Đa khoa TP Cần Thơ',
        5 => 'Bệnh viện Hữu nghị Việt Tiệp (Hải Phòng)',
        6 => 'Bệnh viện Đa khoa Tỉnh Bình Dương'
    ];

    $new_emp_ids = [];
    $total_added = 0;

    foreach ($needed_by_branch as $b_id => $add_count) {
        $depts_for_branch = $branch_depts[$b_id];
        
        for ($i = 0; $i < $add_count; $i++) {
            $last_code_num++;
            $emp_code = sprintf('NV%05d', $last_code_num);
            
            // Giới tính
            $is_female = ($i % 2 === 1);
            $gender = $is_female ? 'Nu' : 'Nam';
            $ho = $ho_list[array_rand($ho_list)];
            $dem = $is_female ? $dem_nu[array_rand($dem_nu)] : $dem_nam[array_rand($dem_nam)];
            $ten = $is_female ? $ten_nu[array_rand($ten_nu)] : $ten_nam[array_rand($ten_nam)];
            $fullname = "{$ho} {$dem} {$ten}";

            // Email & username
            $clean_name = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $ten . '.' . $dem . $last_code_num));
            $clean_name = preg_replace('/[^a-z0-9\.]/', '', $clean_name);
            $username = 'nv' . $last_code_num;
            $email = "{$clean_name}@wincommerce.vn";

            // Ngày sinh: 1985 -> 2001
            $b_year = rand(1985, 2001);
            $b_month = rand(1, 12);
            $b_day = rand(1, 28);
            $birth_date = sprintf('%04d-%02d-%02d', $b_year, $b_month, $b_day);

            // Ngày vào làm: 2024-01 -> 2026-05 (để có dữ liệu lịch sử)
            $h_year = rand(2024, 2025);
            $h_month = rand(1, 12);
            $hire_date = sprintf('%04d-%02d-01', $h_year, $h_month);

            // CCID & SĐT & Bank
            $id_card = '0' . rand(10, 89) . rand(100000000, 999999999);
            $phone = '09' . rand(10000000, 99999999);
            $bank_acc = rand(1000000000, 9999999999) . ' (Techcombank)';
            $address = "Khu phố " . rand(1, 10) . ", Phường " . rand(1, 15);

            // Phòng ban & chức vụ
            $dept_id = $depts_for_branch[$i % count($depts_for_branch)];
            $avail_pos = $dept_positions[$dept_id] ?? [8, 14];
            $pos_id = $avail_pos[$i % count($avail_pos)];
            $base_salary = (float)($pos_salaries[$pos_id] ?? 12000000);
            $allowance = (float)(rand(15, 35) * 100000);

            // 1. Tạo user
            $stmt_user->execute([$username, $default_hash, $fullname, $email]);
            $user_id = (int)$pdo->lastInsertId();

            // 2. Tạo employee
            $stmt_emp->execute([
                $emp_code, $fullname, $gender, $birth_date, $id_card, $phone, $email,
                $bank_acc, $address, $b_id, $dept_id, $pos_id, $user_id,
                $hire_date
            ]);
            $emp_id = (int)$pdo->lastInsertId();
            $new_emp_ids[] = $emp_id;

            // 3. Tạo HĐLĐ
            $contract_num = "HDLD-WCM/{$h_year}/" . sprintf('%04d', $last_code_num);
            $end_date = sprintf('%04d-%02d-01', $h_year + 3, $h_month);
            $stmt_contract->execute([$emp_id, $contract_num, $hire_date, $end_date, $base_salary, $allowance]);

            // 4. Tạo BHXH
            $ins_num = '79' . rand(10000000, 99999999);
            $hosp = $hospitals[$b_id] ?? 'Bệnh viện Quận';
            $stmt_ins->execute([$emp_id, $ins_num, $hire_date, $hosp]);

            $total_added++;
        }
    }

    echo "  + Đã khởi tạo thành công {$total_added} nhân sự mới kèm đầy đủ User, HĐLĐ, BHXH!\n";

    // Tổng số nhân viên hiện tại
    $total_now = $pdo->query("SELECT COUNT(*) FROM employees WHERE employment_status != 'resigned'")->fetchColumn();
    echo "  + Tổng số lượng cán bộ công nhân viên toàn hệ thống: {$total_now} người.\n";

    // -------------------------------------------------------------
    // BƯỚC 4: CHẤM CÔNG HÔM NAY (2026-11-02) & CÁC THÁNG GẦN ĐÂY
    // -------------------------------------------------------------
    echo "\n[4/6] Khởi tạo dữ liệu chấm công hôm nay (02/11/2026) và lịch sử chấm công...\n";

    // Lấy toàn bộ danh sách nhân sự đang làm việc
    $all_active_emps = $pdo->query("SELECT id, hire_date FROM employees WHERE employment_status != 'resigned'")->fetchAll();

    $stmt_att_check = $pdo->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
    $stmt_att_insert = $pdo->prepare("
        INSERT INTO attendance (
            employee_id, date, check_in, check_out, overtime_hours, 
            late_minutes, early_minutes, status, leave_type, note
        ) VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?)
    ");

    $today = '2026-11-02';
    $today_count = 0;

    foreach ($all_active_emps as $idx => $e) {
        $eid = (int)$e['id'];
        $stmt_att_check->execute([$eid, $today]);
        if (!$stmt_att_check->fetch()) {
            if ($idx % 35 === 0) {
                // Nghỉ phép có đơn
                $stmt_att_insert->execute([$eid, $today, null, null, 0, 0, 'leave_with_permit', 'annual', 'Nghỉ phép năm đã duyệt']);
            } elseif ($idx % 18 === 0) {
                // Đi muộn 10 - 25 phút
                $late_m = rand(10, 25);
                $ci = sprintf('08:%02d:00', $late_m);
                $stmt_att_insert->execute([$eid, $today, $ci, '17:35:00', 0, $late_m, 'late', null, 'Giao thông tắc nghẽn']);
            } else {
                // Đúng giờ (07:45 - 08:00)
                $ci = sprintf('07:%02d:%02d', rand(45, 59), rand(10, 50));
                $co = sprintf('17:%02d:%02d', rand(30, 45), rand(10, 50));
                $stmt_att_insert->execute([$eid, $today, $ci, $co, 0, 0, 'present', null, null]);
            }
            $today_count++;
        }
    }
    echo "  + Đã chấm công trực tiếp cho {$today_count} nhân sự ngày hôm nay ({$today}). Tỷ lệ hiện diện đạt > 97%!\n";

    // Bổ sung lịch sử chấm công tháng 10/2026 (từ ngày 01 đến 31/10/2026) cho các nhân viên mới
    $oct_weekdays = [];
    $oct_start = new DateTime('2026-10-01');
    $oct_end = new DateTime('2026-10-31');
    while ($oct_start <= $oct_end) {
        if ((int)$oct_start->format('N') <= 5) {
            $oct_weekdays[] = $oct_start->format('Y-m-d');
        }
        $oct_start->modify('+1 day');
    }

    $oct_inserted = 0;
    foreach ($new_emp_ids as $nid) {
        foreach ($oct_weekdays as $d_str) {
            $stmt_att_check->execute([$nid, $d_str]);
            if (!$stmt_att_check->fetch()) {
                $stmt_att_insert->execute([$nid, $d_str, '07:55:00', '17:30:00', 0, 0, 'present', null, null]);
                $oct_inserted++;
            }
        }
    }
    echo "  + Đã bổ sung {$oct_inserted} lượt chấm công chuẩn tháng 10/2026 cho các nhân sự mới.\n";

    // -------------------------------------------------------------
    // BƯỚC 5: KHỞI TẠO BẢNG LƯƠNG LỊCH SỬ NĂM 2025 & NĂM 2026
    // -------------------------------------------------------------
    echo "\n[5/6] Tạo lập dữ liệu quỹ lương 12 tháng năm 2025 và 11 tháng năm 2026...\n";

    // Lấy thông tin HĐLĐ và chức vụ của tất cả nhân viên
    $emp_salary_profiles = $pdo->query("
        SELECT e.id, e.hire_date, c.base_salary, c.allowance, e.branch_id
        FROM employees e
        JOIN employee_contracts c ON e.id = c.employee_id AND c.status = 'active'
        WHERE e.employment_status != 'resigned'
    ")->fetchAll();

    $stmt_pay_check = $pdo->prepare("SELECT id FROM payrolls WHERE employee_id = ? AND month = ? AND year = ?");
    $stmt_pay_insert = $pdo->prepare("
        INSERT INTO payrolls (
            employee_id, month, year, contract_type, basic_salary, time_salary,
            work_days, standard_days, paid_leave_days, holiday_days, unpaid_days,
            late_early_minutes, allowance, contract_allowance, bonus,
            ot_weekday_hours, ot_weekend_hours, ot_holiday_hours, ot_pay, ot_tax_exempt,
            gross_salary, insurance_salary, bhxh_employee, bhyt_employee, bhtn_employee,
            dependents_count, family_deduction, assessable_income, tax_method,
            pit_amount, deduction, compensation_deduction, final_salary,
            employer_insurance, locked_at, locked_by, payment_status
        ) VALUES (
            ?, ?, ?, 'fixed_term', ?, ?,
            ?, 22.0, ?, ?, 0.0,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, 'progressive',
            ?, 0.0, 0.0, ?,
            ?, ?, ?, ?
        )
    ");

    $payroll_count_2025 = 0;
    $payroll_count_2026 = 0;

    // A. NĂM 2025: 12 tháng (Đã khóa & đã chi trả đầy đủ)
    for ($m = 1; $m <= 12; $m++) {
        $m_date = sprintf('2025-%02d-01', $m);
        $lock_time = sprintf('2025-%02d-05 17:00:00', ($m == 12 ? 1 : $m + 1));
        if ($m == 12) $lock_time = '2026-01-05 17:00:00';

        foreach ($emp_salary_profiles as $esp) {
            $eid = (int)$esp['id'];
            // Chỉ tính nếu ngày vào làm <= tháng tính lương
            if ($esp['hire_date'] <= $m_date) {
                $stmt_pay_check->execute([$eid, $m, 2025]);
                if (!$stmt_pay_check->fetch()) {
                    $base = (float)$esp['base_salary'];
                    $allow = (float)$esp['allowance'];
                    $work_days = 22.0;
                    $time_sal = $base;
                    $gross = $base + $allow;
                    
                    // BHXH (8%), BHYT (1.5%), BHTN (1%)
                    $ins_sal = min($base, 46800000); // Mức trần đóng BHXH
                    $bhxh = round($ins_sal * 0.08);
                    $bhyt = round($ins_sal * 0.015);
                    $bhtn = round($ins_sal * 0.01);
                    $total_ins = $bhxh + $bhyt + $bhtn;
                    
                    // Giảm trừ gia cảnh: Bản thân 11 triệu
                    $taxable = max(0, $gross - $total_ins - 11000000);
                    $pit = 0;
                    if ($taxable > 0) {
                        if ($taxable <= 5000000) $pit = $taxable * 0.05;
                        elseif ($taxable <= 10000000) $pit = 250000 + ($taxable - 5000000) * 0.10;
                        elseif ($taxable <= 18000000) $pit = 750000 + ($taxable - 10000000) * 0.15;
                        else $pit = 1950000 + ($taxable - 18000000) * 0.20;
                    }
                    $net = $gross - $total_ins - $pit;
                    $comp_ins = round($ins_sal * 0.215);

                    $stmt_pay_insert->execute([
                        $eid, $m, 2025, $base, $time_sal,
                        $work_days, 0, 0, 0, $allow, $allow, 0,
                        0, 0, 0, 0, 0,
                        $gross, $ins_sal, $bhxh, $bhyt, $bhtn,
                        0, 11000000, $taxable,
                        $pit, $net, $comp_ins, $lock_time, 1, 'paid'
                    ]);
                    $payroll_count_2025++;
                }
            }
        }
    }
    echo "  + Đã tạo và khóa sổ {$payroll_count_2025} bản ghi lương của 12 tháng năm 2025.\n";

    // B. NĂM 2026: Tháng 1 đến Tháng 10 (Đã khóa, đã thanh toán)
    for ($m = 1; $m <= 10; $m++) {
        $m_date = sprintf('2026-%02d-01', $m);
        $lock_time = sprintf('2026-%02d-05 17:00:00', $m + 1);

        foreach ($emp_salary_profiles as $esp) {
            $eid = (int)$esp['id'];
            if ($esp['hire_date'] <= $m_date) {
                $stmt_pay_check->execute([$eid, $m, 2026]);
                $existing = $stmt_pay_check->fetch();
                if (!$existing) {
                    $base = (float)$esp['base_salary'];
                    $allow = (float)$esp['allowance'];
                    $work_days = 22.0;
                    $time_sal = $base;
                    $gross = $base + $allow;
                    
                    $ins_sal = min($base, 46800000);
                    $bhxh = round($ins_sal * 0.08);
                    $bhyt = round($ins_sal * 0.015);
                    $bhtn = round($ins_sal * 0.01);
                    $total_ins = $bhxh + $bhyt + $bhtn;
                    
                    $taxable = max(0, $gross - $total_ins - 11000000);
                    $pit = 0;
                    if ($taxable > 0) {
                        if ($taxable <= 5000000) $pit = $taxable * 0.05;
                        elseif ($taxable <= 10000000) $pit = 250000 + ($taxable - 5000000) * 0.10;
                        elseif ($taxable <= 18000000) $pit = 750000 + ($taxable - 10000000) * 0.15;
                        else $pit = 1950000 + ($taxable - 18000000) * 0.20;
                    }
                    $net = $gross - $total_ins - $pit;
                    $comp_ins = round($ins_sal * 0.215);

                    $stmt_pay_insert->execute([
                        $eid, $m, 2026, $base, $time_sal,
                        $work_days, 0, 0, 0, $allow, $allow, 0,
                        0, 0, 0, 0, 0,
                        $gross, $ins_sal, $bhxh, $bhyt, $bhtn,
                        0, 11000000, $taxable,
                        $pit, $net, $comp_ins, $lock_time, 1, 'paid'
                    ]);
                    $payroll_count_2026++;
                } else {
                    // Cập nhật trạng thái paid & lock nếu đang null
                    $pdo->prepare("UPDATE payrolls SET payment_status = 'paid', locked_at = COALESCE(locked_at, ?) WHERE id = ?")
                        ->execute([$lock_time, $existing['id']]);
                }
            }
        }
    }
    echo "  + Đã hoàn thiện và khóa sổ lương từ Tháng 01 đến Tháng 10/2026 ({$payroll_count_2026} bản ghi mới).\n";

    // C. THÁNG 11/2026 (Tháng hiện tại - Dự thảo tính nháp sẵn cho toàn bộ nhân sự để demo)
    $m = 11;
    $nov_draft_count = 0;
    foreach ($emp_salary_profiles as $esp) {
        $eid = (int)$esp['id'];
        $stmt_pay_check->execute([$eid, $m, 2026]);
        $existing = $stmt_pay_check->fetch();
        
        $base = (float)$esp['base_salary'];
        $allow = (float)$esp['allowance'];
        $work_days = 22.0;
        $time_sal = $base;
        $gross = $base + $allow;
        $ins_sal = min($base, 46800000);
        $bhxh = round($ins_sal * 0.08);
        $bhyt = round($ins_sal * 0.015);
        $bhtn = round($ins_sal * 0.01);
        $total_ins = $bhxh + $bhyt + $bhtn;
        $taxable = max(0, $gross - $total_ins - 11000000);
        $pit = 0;
        if ($taxable > 0) {
            if ($taxable <= 5000000) $pit = $taxable * 0.05;
            elseif ($taxable <= 10000000) $pit = 250000 + ($taxable - 5000000) * 0.10;
            elseif ($taxable <= 18000000) $pit = 750000 + ($taxable - 10000000) * 0.15;
            else $pit = 1950000 + ($taxable - 18000000) * 0.20;
        }
        $net = $gross - $total_ins - $pit;
        $comp_ins = round($ins_sal * 0.215);

        if (!$existing) {
            $stmt_pay_insert->execute([
                $eid, $m, 2026, $base, $time_sal,
                $work_days, 0, 0, 0, $allow, $allow, 0,
                0, 0, 0, 0, 0,
                $gross, $ins_sal, $bhxh, $bhyt, $bhtn,
                0, 11000000, $taxable,
                $pit, $net, $comp_ins, null, null, 'pending'
            ]);
            $nov_draft_count++;
        } else {
            // Cập nhật lại số liệu net nếu trước đó đang = 0
            $pdo->prepare("
                UPDATE payrolls SET 
                    basic_salary = ?, time_salary = ?, gross_salary = ?, 
                    bhxh_employee = ?, bhyt_employee = ?, bhtn_employee = ?,
                    pit_amount = ?, final_salary = ?, employer_insurance = ?
                WHERE id = ? AND final_salary = 0
            ")->execute([$base, $time_sal, $gross, $bhxh, $bhyt, $bhtn, $pit, $net, $comp_ins, $existing['id']]);
        }
    }
    echo "  + Đã sẵn sàng bảng tính nháp Tháng 11/2026 ({$nov_draft_count} bản ghi mới) chờ C&B / HR Director phê duyệt!\n";

    // -------------------------------------------------------------
    // BƯỚC 6: BỔ SUNG ĐÁNH GIÁ KPI, ĐỀ XUẤT, THUYÊN CHUYỂN, KHEN THƯỞNG
    // -------------------------------------------------------------
    echo "\n[6/6] Khởi tạo đợt đánh giá KPI, đề xuất duyệt và lịch sử điều động...\n";

    // A. Bổ sung các kỳ đánh giá năm 2025 và 2026
    $periods_data = [
        ['PERIOD-2025-Q1', 'Đánh Giá Hiệu Suất & KPI Quý 1/2025', 'quarterly', 2025, 1, 'closed'],
        ['PERIOD-2025-Q2', 'Đánh Giá Hiệu Suất & KPI Quý 2/2025', 'quarterly', 2025, 2, 'closed'],
        ['PERIOD-2025-Q3', 'Đánh Giá Hiệu Suất & KPI Quý 3/2025', 'quarterly', 2025, 3, 'closed'],
        ['PERIOD-2025-Q4', 'Đánh Giá Hiệu Suất & KPI Toàn Diện Năm 2025', 'annual', 2025, 4, 'closed'],
        ['PERIOD-2026-Q1', 'Đánh Giá Hiệu Suất & KPI Quý 1/2026', 'quarterly', 2026, 1, 'closed'],
        ['PERIOD-2026-Q4', 'Kỳ Đánh Giá Hiệu Suất Cuối Năm Quý 4/2026', 'quarterly', 2026, 4, 'upcoming']
    ];

    $stmt_period_check = $pdo->prepare("SELECT id FROM evaluation_periods WHERE code = ?");
    $stmt_period_ins = $pdo->prepare("
        INSERT INTO evaluation_periods (code, name, type, year, quarter, start_date, end_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($periods_data as $p) {
        $stmt_period_check->execute([$p[0]]);
        if (!$stmt_period_check->fetch()) {
            $s_date = sprintf('%04d-%02d-01', $p[3], ($p[4] - 1) * 3 + 1);
            $e_date = sprintf('%04d-%02d-28', $p[3], $p[4] * 3);
            $stmt_period_ins->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $s_date, $e_date, $p[5]]);
            echo "  + Đã thêm kỳ đánh giá: {$p[1]}\n";
        }
    }

    // Lấy ID kỳ Q3/2026 để bổ sung phiếu đánh giá
    $q3_period_id = $pdo->query("SELECT id FROM evaluation_periods WHERE code = 'PERIOD-2026-Q3'")->fetchColumn() ?: 1;

    // Bổ sung phiếu đánh giá cho kỳ Q3/2026 nếu nhân viên chưa có
    $all_emps_for_eval = $pdo->query("SELECT id FROM employees WHERE employment_status != 'resigned'")->fetchAll(PDO::FETCH_COLUMN);
    $stmt_eval_check = $pdo->prepare("SELECT id FROM evaluations WHERE period_id = ? AND employee_id = ?");
    $stmt_eval_ins = $pdo->prepare("
        INSERT INTO evaluations (
            evaluation_code, period_id, employee_id, template_id, reviewer_id,
            status, self_score, manager_score, final_score, rating, rating_grade,
            bonus_multiplier, promotion_recommendation, hr_approved_at, hr_approved_by
        ) VALUES (?, ?, ?, 1, 1, 'completed', ?, ?, ?, ?, ?, ?, 'none', '2026-10-15 10:00:00', 1)
    ");

    $eval_added = 0;
    $rating_tiers = [
        ['outstanding', 'A+', 95.0, 1.30],
        ['excellent',   'A',  88.5, 1.15],
        ['good',        'B',  80.0, 1.00],
        ['average',     'C',  68.0, 0.85]
    ];

    foreach ($all_emps_for_eval as $idx => $eid) {
        $stmt_eval_check->execute([$q3_period_id, $eid]);
        if (!$stmt_eval_check->fetch()) {
            $tier = $rating_tiers[$idx % count($rating_tiers)];
            $score = $tier[2] + (rand(-30, 30) / 10);
            $eval_code = sprintf('DG-2026-Q3-%04d', $eid);
            
            $stmt_eval_ins->execute([
                $eval_code, $q3_period_id, $eid,
                $score - 1.5, $score + 0.5, $score,
                $tier[0], $tier[1], $tier[3]
            ]);
            $eval_added++;
        }
    }
    echo "  + Đã bổ sung {$eval_added} hồ sơ đánh giá KPI hoàn tất cho kỳ Q3/2026.\n";

    // B. Bổ sung các đề xuất nhân sự (Proposals) với nhiều trạng thái và thể loại
    $proposal_templates = [
        ['leave', 'Đơn xin nghỉ phép năm - Đi du lịch cùng gia đình', 'annual', 3, 'approved', '2026-09-10', '2026-09-12'],
        ['leave', 'Đơn xin nghỉ ốm - Khám sức khỏe định kỳ', 'sick', 1, 'approved', '2026-10-05', '2026-10-05'],
        ['overtime', 'Đăng ký làm thêm giờ (OT) - Kiểm kê kho hàng cuối tháng', null, 4, 'approved', '2026-10-25', '2026-10-25'],
        ['business_trip', 'Công tác hỗ trợ khai trương siêu thị WinMart mới', null, 5, 'approved', '2026-08-15', '2026-08-20'],
        ['equipment', 'Đề xuất cấp mới máy đọc mã vạch quét tồn kho', null, 0, 'approved', '2026-10-18', '2026-10-18'],
        ['leave', 'Đơn xin nghỉ việc riêng có hưởng lương', 'personal', 2, 'rejected', '2026-09-20', '2026-09-21'],
        // Đề xuất đang chờ duyệt tháng 11/2026 cho demo
        ['leave', 'Đơn xin nghỉ phép năm tháng 11/2026', 'annual', 2, 'pending', '2026-11-10', '2026-11-11'],
        ['overtime', 'Đăng ký trực ca đêm hỗ trợ đợt khuyến mãi Black Friday', null, 6, 'pending', '2026-11-20', '2026-11-20'],
        ['equipment', 'Đề xuất nâng cấp màn hình máy POS thu ngân', null, 0, 'pending', '2026-11-05', '2026-11-05']
    ];

    $stmt_prop_ins = $pdo->prepare("
        INSERT INTO proposals (
            proposal_code, employee_id, type, title, description,
            start_date, end_date, leave_type, total_days, priority, status,
            approved_by, approved_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'normal', ?, ?, ?)
    ");

    $prop_count = 0;
    foreach ($proposal_templates as $idx => $pt) {
        $p_code = sprintf('DX-DEMO-%03d', $idx + 1);
        $check_p = $pdo->query("SELECT id FROM proposals WHERE proposal_code = '{$p_code}'")->fetch();
        if (!$check_p) {
            $emp_id_sample = $all_emps_for_eval[($idx * 5) % count($all_emps_for_eval)];
            $appr_by = ($pt[4] === 'approved') ? 1 : null;
            $appr_at = ($pt[4] === 'approved') ? ($pt[5] . ' 14:00:00') : null;

            $stmt_prop_ins->execute([
                $p_code, $emp_id_sample, $pt[0], $pt[1], 'Hệ thống tự động đồng bộ theo kế hoạch vận hành.',
                $pt[5], $pt[6], $pt[2], $pt[3], $pt[4], $appr_by, $appr_at
            ]);
            $prop_count++;
        }
    }
    echo "  + Đã bổ sung {$prop_count} đề xuất liên thông (nghỉ phép, OT, công tác, thiết bị) đa trạng thái.\n";

    // C. Bổ sung các quyết định khen thưởng Masan Pride
    $reward_templates = [
        ['Chiến Binh Bán Lẻ Xuất Sắc Q2/2026', 'bonus', 5000000, '2026-07-15', 'QD-KT-2026-01'],
        ['Sáng Kiến Cải Tiến Vận Tải Giảm Chi Phí', 'achievement', 10000000, '2026-08-20', 'QD-KT-2026-02'],
        ['Gương Mặt Tiêu Biểu Masan Way Q3/2026', 'certificate', 3000000, '2026-10-10', 'QD-KT-2026-03'],
        ['Cán Bộ Quản Lý Kho Bãi Uy Tín Nhất 2025', 'achievement', 8000000, '2025-12-25', 'QD-KT-2025-12']
    ];

    $stmt_reward_ins = $pdo->prepare("
        INSERT INTO rewards (
            reward_code, employee_id, reward_type, title, description,
            amount, decision_number, reward_date, status, created_by, approved_by, approved_at, executed_at
        ) VALUES (?, ?, ?, ?, 'Thành tích nổi bật ghi nhận bởi Ban Lãnh Đạo WinCommerce.', ?, ?, ?, 'executed', 1, 1, ?, ?)
    ");

    $rew_count = 0;
    foreach ($reward_templates as $idx => $rt) {
        $r_code = sprintf('KT-DEMO-%03d', $idx + 1);
        $check_r = $pdo->query("SELECT id FROM rewards WHERE reward_code = '{$r_code}'")->fetch();
        if (!$check_r) {
            $eid = $all_emps_for_eval[($idx * 7) % count($all_emps_for_eval)];
            $dt = $rt[3] . ' 10:00:00';
            $stmt_reward_ins->execute([
                $r_code, $eid, $rt[1], $rt[0], $rt[2], $rt[4], $rt[3], $dt, $dt
            ]);
            $rew_count++;
        }
    }
    echo "  + Đã bổ sung {$rew_count} quyết định khen thưởng Masan Pride toàn hệ thống.\n";

    // Commit toàn bộ giao dịch an toàn
    $pdo->commit();

    echo "\n========================================================\n";
    echo "HOÀN TẤT THÀNH CÔNG TẤT CẢ DỮ LIỆU ĐỀ XUẤT CỦA BẠN!\n";
    echo "========================================================\n";

} catch (Throwable $e) {
    $pdo->rollBack();
    echo "\n[ERROR] Thao tác thất bại: " . $e->getMessage() . "\n";
    echo "Dòng: " . $e->getLine() . " tại " . $e->getFile() . "\n";
    echo $e->getTraceAsString() . "\n";
}

