<?php
// database/rebrand_wincommerce.php - Script chuyển đổi dữ liệu toàn diện sang WinCommerce (WinMart / Masan Group)
// Có thể chạy an toàn nhiều lần (Idempotent)

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/brand.php';

echo "====================================================================\n";
echo "BẮT ĐẦU CHUYỂN ĐỔI DỮ LIỆU SANG THƯƠNG HIỆU WINCOMMERCE (WINMART)\n";
echo "====================================================================\n";

try {
    $pdo->beginTransaction();

    // 1. CẬP NHẬT TÀI KHOẢN USERS & MẬT KHẨU MỚI (WinMart@2026)
    echo "1. Cập nhật tài khoản đăng nhập người dùng...\n";
    $new_password_hash = password_hash('WinMart@2026', PASSWORD_BCRYPT);

    $user_updates = [
        ['admin@auragroup.vn',   'admin@wincommerce.vn',   'Nguyễn Hoàng Nam'],
        ['tp.hcm@auragroup.vn',  'mienbac@wincommerce.vn', 'Trần Quốc Bảo'],
        ['nhanvien@auragroup.vn','nhanvien@wincommerce.vn','Phạm Thu Thảo'],
        ['danang.mgr@auragroup.vn','danang.mgr@wincommerce.vn','Đặng Hữu Phước'],
        ['cantho.mgr@auragroup.vn','cantho.mgr@wincommerce.vn','Võ Thành Trung']
    ];

    $u_stmt = $pdo->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE username = ? OR username = ?");
    foreach ($user_updates as $u) {
        $u_stmt->execute([$u[1], $u[1], $new_password_hash, $u[0], $u[1]]);
    }
    // Cập nhật bất kỳ email người dùng nào còn chứa auragroup.vn
    $pdo->exec("UPDATE users SET username = REPLACE(username, '@auragroup.vn', '@wincommerce.vn'), email = REPLACE(email, '@auragroup.vn', '@wincommerce.vn')");

    // 2. CẬP NHẬT EMAIL & MÃ NHÂN VIÊN EMPLOYEES
    echo "2. Cập nhật hồ sơ nhân sự (email @wincommerce.vn & mã WCM###)...\n";
    $pdo->exec("UPDATE employees SET email = REPLACE(email, '@auragroup.vn', '@wincommerce.vn')");
    $pdo->exec("UPDATE employees SET employee_code = CONCAT('WCM', SUBSTRING(employee_code, 4)) WHERE employee_code LIKE 'AUR%'");
    
    // Bảo hiểm nhân sự nếu có mã AUR
    $pdo->exec("UPDATE employee_insurance SET insurance_number = REPLACE(insurance_number, 'AUR-', 'WCM-') WHERE insurance_number LIKE 'AUR-%'");

    // 3. CẬP NHẬT MẠNG LƯỚI CHI NHÁNH & TRUNG TÂM PHÂN PHỐI (DC)
    echo "3. Cập nhật chi nhánh theo mô hình Hội sở TP.HCM, VP Hà Nội và các Hub DC...\n";
    $branches = [
        [
            1, 'HCM-HQ', 'Trụ Sở Chính WinCommerce - TP.HCM',
            'Số 23 Lê Duẩn, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh',
            '028-3821-6868', 'hcm.hq@wincommerce.vn', 1, 60, 1,
            'Trụ sở chính & Trung tâm điều hành chuỗi WinMart / WinMart+ / WiN toàn quốc'
        ],
        [
            2, 'HN-RO', 'Văn Phòng Điều Hành Miền Bắc - Hà Nội',
            'Tòa nhà International Centre, 17 Ngô Quyền, Q. Hoàn Kiếm, Hà Nội',
            '024-3998-8888', 'hanoi.office@wincommerce.vn', 3, 40, 0,
            'Trung tâm điều hành kinh doanh & phát triển mạng lưới bán lẻ khu vực phía Bắc'
        ],
        [
            3, 'DN-HUB', 'Trung Tâm Phân Phối (DC) Đà Nẵng - Miền Trung',
            'Số 125 Đường 2 Tháng 9, P. Hòa Cường Nam, Q. Hải Châu, TP. Đà Nẵng',
            '0236-388-9999', 'danang.dc@wincommerce.vn', 15, 25, 0,
            'Hub logistics & trung tâm điều phối chuỗi cung ứng miền Trung'
        ],
        [
            4, 'CT-HUB', 'Trung Tâm Phân Phối (DC) Cần Thơ - Mekong',
            'Khu Công Nghiệp Trà Nóc 1, P. Trà Nóc, Q. Bình Thủy, TP. Cần Thơ',
            '0292-376-8888', 'cantho.dc@wincommerce.vn', 20, 20, 0,
            'Trung tâm phân phối tổng vùng Đồng Bằng Sông Cửu Long'
        ],
        [
            5, 'HP-HUB', 'Trung Tâm Phân Phối (DC) Hải Phòng - Cảng Biển',
            'Số 288 Đường Lê Hồng Phong, P. Đông Khê, Q. Ngô Quyền, TP. Hải Phòng',
            '0225-382-9999', 'haiphong.dc@wincommerce.vn', 35, 18, 0,
            'Hub giao nhận vận tải biển và phân phối hàng hóa Duyên hải Bắc Bộ'
        ],
        [
            6, 'BD-HUB', 'Tổng Kho Phân Phối Trung Tâm (Central DC) Bình Dương',
            'Lô A11, Đường Hùng Vương, P. Hòa Phú, TP. Mới Bình Dương, Tỉnh Bình Dương',
            '0274-386-7777', 'binhduong.dc@wincommerce.vn', 45, 22, 0,
            'Tổng kho logistics, chuỗi lạnh & phân phối hàng tươi sống trọng điểm Đông Nam Bộ'
        ]
    ];

    $b_stmt = $pdo->prepare("
        UPDATE branches 
        SET code = ?, name = ?, address = ?, phone = ?, email = ?, manager_id = ?, target_headcount = ?, is_headquarter = ?, notes = ?
        WHERE id = ?
    ");
    foreach ($branches as $b) {
        $b_stmt->execute([$b[1], $b[2], $b[3], $b[4], $b[5], $b[6], $b[7], $b[8], $b[9], $b[0]]);
    }

    // 4. CẬP NHẬT KHỐI PHÒNG BAN CHUỖI SIÊU THỊ WINCOMMERCE
    echo "4. Cập nhật phòng ban và khối vận hành bán lẻ...\n";
    $departments = [
        [1, 'Ban Tổng Giám Đốc', 'BGD', 'Ban điều hành chiến lược kinh doanh và mở rộng mạng lưới 4.500+ siêu thị, cửa hàng WinMart/WinMart+/WiN'],
        [2, 'Khối Quản Trị Nhân Sự & Đào Tạo', 'HR', 'Quản trị nguồn nhân lực chuỗi bán lẻ, tiền lương C&B, phát triển nhân tài và văn hóa WinCommerce'],
        [3, 'Khối Công Nghệ Thông Tin & Số Hóa', 'IT', 'Phát triển hạ tầng bán lẻ đa kênh Omni-channel, Cloud ERP, POS và hệ sinh thái WiN Membership'],
        [4, 'Khối Vận Hành Chuỗi Siêu Thị (Retail Ops)', 'RETAIL', 'Quản lý và vận hành hệ thống đại siêu thị WinMart, chuỗi cửa hàng tiện ích WinMart+ và WiN'],
        [5, 'Khối Chuỗi Cung Ứng & Trung Tâm Phân Phối (SCM)', 'SCM', 'Quản trị chuỗi cung ứng lạnh, hệ thống trung tâm phân phối (DC) và điều phối giao nhận toàn quốc'],
        [6, 'Khối Tài Chính - Kế Toán', 'FIN', 'Quản trị dòng tiền, kiểm soát chi phí bán lẻ, kế toán chuỗi và quyết toán thuế tập đoàn'],
        [7, 'Khối Tiếp Thị, Thương Hiệu & WiN Membership', 'MKT', 'Chiến dịch thương hiệu WinMart, tiếp thị số, khuyến mại bán lẻ và chăm sóc 10+ triệu hội viên WiN'],
        [8, 'Khối Trải Nghiệm Khách Hàng (CX & Care)', 'CX', 'Chăm sóc khách hàng đa kênh, tiếp nhận phản hồi dịch vụ và nâng cao chỉ số hài lòng CSAT/NPS'],
        [9, 'Khối Pháp Chế, An Toàn & Kiểm Soát Tuân Thủ', 'LEGAL', 'Pháp chế thương mại, an toàn vệ sinh thực phẩm (ATVSTP) và kiểm soát rủi ro tuân thủ hệ thống'],
        [10, 'Bộ Phận Vận Hành Trung Tâm Phân Phối Cần Thơ', 'CT_LOG', 'Vận hành kho tổng và phân phối hàng hóa khu vực Tây Nam Bộ'],
        [11, 'Phòng Xuất Nhập Khẩu Cảng Biển Hải Phòng', 'HP_XNK', 'Giao nhận cảng biển quốc tế và thông quan hàng hóa phục vụ chuỗi siêu thị'],
        [12, 'Chi Nhánh Vận Hành Chuỗi Siêu Thị Bình Dương', 'BD_RETAIL', 'Quản lý cụm siêu thị WinMart và WinMart+ khu vực Đông Nam Bộ']
    ];

    $d_stmt = $pdo->prepare("UPDATE departments SET name = ?, code = ?, description = ? WHERE id = ?");
    foreach ($departments as $d) {
        $d_stmt->execute([$d[1], $d[2], $d[3], $d[0]]);
    }

    // 5. CẬP NHẬT CHỨC VỤ BÁN LẺ
    echo "5. Cập nhật chức danh vị trí công việc...\n";
    $positions = [
        [1, 'Tổng Giám Đốc (CEO)'],
        [2, 'Giám Đốc Chi Nhánh / Giám Đốc Khu Vực (Regional Director)'],
        [3, 'Giám Đốc Khối / CHRO / CIO / CFO / COO'],
        [4, 'Trưởng Phòng Kinh Doanh & Vận Hành Bán Lẻ (Retail Ops Manager)'],
        [5, 'Kỹ Sư Phần Mềm Senior (Tech Lead / Omni-channel Architect)'],
        [6, 'Trưởng Phòng Chuỗi Cung Ứng & Trung Tâm Phân Phối (DC Manager)'],
        [7, 'Chuyên Viên Quản Trị Nhân Sự & C&B Chuỗi'],
        [8, 'Quản Lý Siêu Thị WinMart (Store Manager)'],
        [9, 'Kỹ Sư DevOps / Cloud & An Toàn Thông Tin'],
        [10, 'Chuyên Viên Tiếp Thị & Marketing Số (Digital Marketing)'],
        [11, 'Kế Toán Trưởng & Kế Toán Trưởng Chuỗi Bán Lẻ'],
        [12, 'Chuyên Viên Pháp Chế & Kiểm Soát Tuân Thủ (Legal & Compliance)'],
        [13, 'Chuyên Viên Trải Nghiệm Khách Hàng (CX & WiN Care)'],
        [14, 'Giám Sát Vận Hành Trung Tâm Phân Phối (DC Supervisor)']
    ];

    $p_stmt = $pdo->prepare("UPDATE positions SET name = ? WHERE id = ?");
    foreach ($positions as $p) {
        $p_stmt->execute([$p[1], $p[0]]);
    }

    // 6. CẬP NHẬT MÃ QUYẾT ĐỊNH & VĂN BẢN (AURA -> WCM)
    echo "6. Cập nhật mã quyết định khen thưởng, kỷ luật, điều chuyển...\n";
    $pdo->exec("UPDATE rewards SET decision_number = REPLACE(decision_number, 'AURA-', 'WCM-') WHERE decision_number LIKE 'AURA-%'");
    $pdo->exec("UPDATE rewards SET description = REPLACE(description, 'Aura Group', 'WinCommerce')");
    $pdo->exec("UPDATE disciplines SET decision_number = REPLACE(decision_number, 'AURA-', 'WCM-') WHERE decision_number LIKE 'AURA-%'");
    $pdo->exec("UPDATE disciplines SET title = REPLACE(title, 'Aura', 'WinCommerce')");
    $pdo->exec("UPDATE transfers SET decision_number = REPLACE(decision_number, 'AURA-', 'WCM-') WHERE decision_number LIKE 'AURA-%'");

    // 7. CẬP NHẬT BỘ TIÊU CHÍ KPI KHỐI BÁN LẺ (RETAIL) THEO CHUẨN SIÊU THỊ THẬT
    echo "7. Cập nhật bộ tiêu chí KPI chuẩn chuỗi bán lẻ siêu thị...\n";
    $pdo->exec("UPDATE kpi_templates SET name = 'Bộ Tiêu Chí Quản Lý Siêu Thị & Cửa Hàng WinMart' WHERE id = 2");
    $pdo->exec("UPDATE kpi_templates SET description = 'Áp dụng cho Giám đốc vùng, Quản lý siêu thị WinMart và Cửa hàng trưởng WinMart+/WiN' WHERE id = 2");
    
    // Cập nhật 5 tiêu chí chi tiết của Template 2 (Retail) - Giữ nguyên item_id và weight
    $retail_kpi_items = [
        [
            'criteria_name'  => 'Doanh Thu & Tăng Trưởng LFL Cùng Cửa Hàng',
            'description'    => 'Đạt chỉ tiêu doanh thu thực tế và tốc độ tăng trưởng Like-for-Like so với cùng kỳ năm trước',
            'target_value'   => '≥ 100%',
            'data_source'    => 'Hệ thống POS & Báo cáo Doanh thu SAP',
            'sort_order'     => 1
        ],
        [
            'criteria_name'  => 'Chỉ Số Hài Lòng Khách Hàng (CSAT & WiN NPS)',
            'description'    => 'Tỷ lệ đánh giá tích cực của khách hàng tại quầy thanh toán và khảo sát hội viên WiN định kỳ',
            'target_value'   => '≥ 92%',
            'data_source'    => 'Hệ thống Khảo sát Hội viên WiN',
            'sort_order'     => 2
        ],
        [
            'criteria_name'  => 'Kiểm Soát Hao Hụt & Tỷ Lệ Hủy Hàng Tươi Sống',
            'description'    => 'Kiểm soát tỷ lệ thất thoát, hao hụt kho hàng hóa khô và hủy hàng tươi sống (Meat Deli, WinEco)',
            'target_value'   => '< 0.8%',
            'data_source'    => 'Biên bản Kiểm kê & Hủy hàng tháng',
            'sort_order'     => 3
        ],
        [
            'criteria_name'  => 'Tỷ Lệ Sẵn Sàng Hàng Hóa Trên Kệ (OSA) & 5S',
            'description'    => 'Duy trì tỷ lệ hàng hóa sẵn sàng trên quầy kệ (On-Shelf Availability) và chuẩn mực trưng bày 5S',
            'target_value'   => '≥ 97%',
            'data_source'    => 'Kiểm toán Tiêu chuẩn Chuỗi QCS',
            'sort_order'     => 4
        ],
        [
            'criteria_name'  => 'Phát Triển Hội Viên Mới & Đào Tạo Nhân Viên',
            'description'    => 'Đạt chỉ tiêu đăng ký hội viên WiN mới và hoàn thành 100% khóa đào tạo an toàn VSTP',
            'target_value'   => '≥ 95%',
            'data_source'    => 'Cổng LMS & App WiN Partner',
            'sort_order'     => 5
        ]
    ];

    $item_get_stmt = $pdo->prepare("SELECT id FROM kpi_template_items WHERE template_id = 2 ORDER BY sort_order ASC");
    $item_get_stmt->execute();
    $existing_item_ids = $item_get_stmt->fetchAll(PDO::FETCH_COLUMN);

    $item_up_stmt = $pdo->prepare("
        UPDATE kpi_template_items 
        SET criteria_name = ?, description = ?, target_value = ?, data_source = ? 
        WHERE id = ?
    ");
    foreach ($retail_kpi_items as $idx => $rki) {
        if (isset($existing_item_ids[$idx])) {
            $item_up_stmt->execute([
                $rki['criteria_name'],
                $rki['description'],
                $rki['target_value'],
                $rki['data_source'],
                $existing_item_ids[$idx]
            ]);
        }
    }

    $pdo->commit();
    echo "====================================================================\n";
    echo "CHUYỂN ĐỔI THƯƠNG HIỆU THÀNH CÔNG VỀ WINCOMMERCE (WINMART)!\n";
    echo "Mật khẩu quản trị chung mới: WinMart@2026\n";
    echo "Tài khoản admin chính: admin@wincommerce.vn\n";
    echo "====================================================================\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "LỖI CHUYỂN ĐỔI: " . $e->getMessage() . "\n";
    exit(1);
}
