<?php
// database/seed_department_targets.php - Khởi tạo dữ liệu mục tiêu phòng ban theo đợt cho WinCommerce
require_once __DIR__ . '/../core/auth.php';

echo "=== KHỞI TẠO MỤC TIÊU PHÒNG BAN THEO ĐỢT (WINCOMMERCE KPI TARGETS) ===\n";

$period_id = 1; // Q3/2026

$targets = [
    [
        'department_id' => 4, // Retail Ops
        'target_title' => 'Tối Ưu Hóa Năng Suất Bán Lẻ & Chuẩn Mực Dịch Vụ WinMart',
        'target_description' => 'Đảm bảo doanh thu chuỗi siêu thị và cửa hàng tiện ích, giảm hao hụt hàng tươi sống, duy trì 100% tuân thủ quy chuẩn dịch vụ khách hàng.',
        'kpi_items' => [
            ['criteria_name' => 'Doanh thu bán lẻ chuỗi theo kế hoạch', 'target_value' => '≥ 100%', 'unit' => '%', 'weight' => 40, 'bsc_pillar' => 'financial', 'priority_group' => 'A'],
            ['criteria_name' => 'Tỷ lệ hao hụt hàng tươi sống & thực phẩm', 'target_value' => '≤ 0.35%', 'unit' => '%', 'weight' => 30, 'bsc_pillar' => 'internal_process', 'priority_group' => 'B'],
            ['criteria_name' => 'Chỉ số hài lòng khách hàng tại quầy (CSAT)', 'target_value' => '≥ 92%', 'unit' => '%', 'weight' => 20, 'bsc_pillar' => 'customer', 'priority_group' => 'B'],
            ['criteria_name' => 'Đào tạo & Kiểm tra kiến thức an toàn thực phẩm', 'target_value' => '100% nhân viên', 'unit' => '%', 'weight' => 10, 'bsc_pillar' => 'learning_growth', 'priority_group' => 'C']
        ]
    ],
    [
        'department_id' => 5, // SCM
        'target_title' => 'Đảm Bảo Chuỗi Cung Ứng Lạnh & Giao Hàng Siêu Thị Đúng Hẹn',
        'target_description' => 'Nâng cao năng lực xử lý tại các tổng kho DC (Sóng Thần, Hải Phòng), kiểm soát nhiệt độ chuỗi lạnh và tối ưu hóa chi phí vận chuyển chặng cuối.',
        'kpi_items' => [
            ['criteria_name' => 'Tỷ lệ giao hàng siêu thị đúng giờ (On-Time Delivery)', 'target_value' => '≥ 98.5%', 'unit' => '%', 'weight' => 40, 'bsc_pillar' => 'internal_process', 'priority_group' => 'A'],
            ['criteria_name' => 'Tuân thủ nhiệt độ bảo quản kho lạnh DC (2°C - 8°C)', 'target_value' => '100%', 'unit' => '%', 'weight' => 30, 'bsc_pillar' => 'internal_process', 'priority_group' => 'A'],
            ['criteria_name' => 'Tối ưu hóa chi phí logistics trên mỗi đơn vị hàng', 'target_value' => 'Giảm 2.5%', 'unit' => '%', 'weight' => 20, 'bsc_pillar' => 'financial', 'priority_group' => 'B'],
            ['criteria_name' => 'Sáng kiến cải tiến luân chuyển pallet kho hàng', 'target_value' => '≥ 2 sáng kiến', 'unit' => 'Sáng kiến', 'weight' => 10, 'bsc_pillar' => 'learning_growth', 'priority_group' => 'C']
        ]
    ],
    [
        'department_id' => 3, // IT
        'target_title' => 'Đảm Bảo 99.98% Uptime Hạ Tầng POS/ERP & Số Hóa WiN Membership',
        'target_description' => 'Duy trì kết nối thông suốt hệ thống tính tiền POS tại hơn 4.500 điểm bán, xử lý mượt mà tích điểm hội viên WiN và tích hợp Cloud ERP.',
        'kpi_items' => [
            ['criteria_name' => 'Độ sẵn sàng hệ thống máy tính tiền POS tại cửa hàng', 'target_value' => '≥ 99.98%', 'unit' => '%', 'weight' => 40, 'bsc_pillar' => 'internal_process', 'priority_group' => 'A'],
            ['criteria_name' => 'Tốc độ phản hồi giao dịch tích điểm hội viên WiN', 'target_value' => '< 1.2s', 'unit' => 'giây', 'weight' => 30, 'bsc_pillar' => 'customer', 'priority_group' => 'B'],
            ['criteria_name' => 'Tỷ lệ xử lý sự cố công nghệ cấp độ 1 đúng SLA', 'target_value' => '≥ 95%', 'unit' => '%', 'weight' => 20, 'bsc_pillar' => 'internal_process', 'priority_group' => 'B'],
            ['criteria_name' => 'Nâng cấp bảo mật dữ liệu khách hàng & thanh toán', 'target_value' => '100% đạt chuẩn', 'unit' => '%', 'weight' => 10, 'bsc_pillar' => 'learning_growth', 'priority_group' => 'C']
        ]
    ],
    [
        'department_id' => 2, // HR
        'target_title' => 'Đáp Ứng 100% Định Mức Nhân Lực Tuyến Đầu & Nâng Tầm Văn Hóa Masan',
        'target_description' => 'Đảm bảo đủ nhân sự ca làm việc cho toàn hệ thống siêu thị, giảm tỷ lệ nghỉ việc tháng đầu, tổ chức đào tạo hội nhập văn hóa WinCommerce.',
        'kpi_items' => [
            ['criteria_name' => 'Tỷ lệ lấp đầy định biên nhân sự cửa hàng/siêu thị', 'target_value' => '≥ 96%', 'unit' => '%', 'weight' => 40, 'bsc_pillar' => 'internal_process', 'priority_group' => 'A'],
            ['criteria_name' => 'Chi trả lương thưởng C&B đúng hạn và chính xác 100%', 'target_value' => '100%', 'unit' => '%', 'weight' => 30, 'bsc_pillar' => 'internal_process', 'priority_group' => 'A'],
            ['criteria_name' => 'Tỷ lệ giữ chân nhân sự thử việc sau 60 ngày', 'target_value' => '≥ 85%', 'unit' => '%', 'weight' => 20, 'bsc_pillar' => 'learning_growth', 'priority_group' => 'B'],
            ['criteria_name' => 'Tổ chức chương trình văn hóa "Người WinMart Tự Hào"', 'target_value' => '≥ 4 khóa', 'unit' => 'Khóa', 'weight' => 10, 'bsc_pillar' => 'learning_growth', 'priority_group' => 'C']
        ]
    ],
    [
        'department_id' => 7, // Marketing
        'target_title' => 'Tăng Trưởng 1.5 Triệu Hội Viên WiN Mới & Doanh Số Khuyến Mại',
        'target_description' => 'Mở rộng hệ sinh thái hội viên WiN, triển khai hiệu quả các tuần lễ khuyến mại hàng Việt và kết nối người tiêu dùng.',
        'kpi_items' => [
            ['criteria_name' => 'Số lượng hội viên WiN đăng ký mới trong quý', 'target_value' => '≥ 1.5 triệu', 'unit' => 'Hội viên', 'weight' => 40, 'bsc_pillar' => 'customer', 'priority_group' => 'A'],
            ['criteria_name' => 'Tỷ lệ giỏ hàng có áp dụng ưu đãi hội viên WiN', 'target_value' => '≥ 65%', 'unit' => '%', 'weight' => 30, 'bsc_pillar' => 'financial', 'priority_group' => 'A'],
            ['criteria_name' => 'Hiệu quả doanh số từ các chiến dịch tiếp thị (ROAS)', 'target_value' => '≥ 4.5x', 'unit' => 'lần', 'weight' => 20, 'bsc_pillar' => 'financial', 'priority_group' => 'B'],
            ['criteria_name' => 'Độ nhận diện thương hiệu chuỗi (Brand Awareness)', 'target_value' => '≥ 88%', 'unit' => '%', 'weight' => 10, 'bsc_pillar' => 'customer', 'priority_group' => 'C']
        ]
    ],
    [
        'department_id' => 6, // Finance
        'target_title' => 'Kiểm Soát Chi Phí Hoạt Động OPEX & Quản Trị Dòng Tiền Siêu Thị',
        'target_description' => 'Tối ưu hóa chi phí điện thoại, vận hành tại các điểm bán, đảm bảo đối soát công nợ nhà cung cấp chính xác tuyệt đối.',
        'kpi_items' => [
            ['criteria_name' => 'Kiểm soát tỷ lệ chi phí OPEX / Doanh thu thuần', 'target_value' => '≤ 18.2%', 'unit' => '%', 'weight' => 40, 'bsc_pillar' => 'financial', 'priority_group' => 'A'],
            ['criteria_name' => 'Độ chính xác và thời gian đối soát công nợ NCC', 'target_value' => '100% đúng hạn', 'unit' => '%', 'weight' => 30, 'bsc_pillar' => 'internal_process', 'priority_group' => 'B'],
            ['criteria_name' => 'Thời gian hoàn tất báo cáo tài chính quản trị tháng', 'target_value' => 'Trước ngày 5', 'unit' => 'ngày', 'weight' => 20, 'bsc_pillar' => 'internal_process', 'priority_group' => 'B'],
            ['criteria_name' => 'Sáng kiến số hóa chứng từ kế toán tự động', 'target_value' => '≥ 1 dự án', 'unit' => 'Dự án', 'weight' => 10, 'bsc_pillar' => 'learning_growth', 'priority_group' => 'C']
        ]
    ],
    [
        'department_id' => 8, // CX & Care
        'target_title' => 'Nâng Tầm Trải Nghiệm Khách Hàng Siêu Thị & Giảm Tỷ Lệ Khiếu Nại',
        'target_description' => 'Nâng cao chỉ số Net Promoter Score (NPS) chuỗi WinMart, rút ngắn thời gian giải quyết khiếu nại khách hàng dưới 2 giờ và đào tạo 100% nhân viên quầy văn hóa phục vụ từ tâm.',
        'kpi_items' => [
            ['criteria_name' => 'Chỉ số hài lòng khách hàng CSAT quầy & tổng đài', 'target_value' => '≥ 95%', 'unit' => '%', 'weight' => 35, 'bsc_pillar' => 'customer', 'priority_group' => 'A'],
            ['criteria_name' => 'Tỷ lệ giải quyết khiếu nại khách hàng dưới 2 giờ', 'target_value' => '≥ 98%', 'unit' => '%', 'weight' => 30, 'bsc_pillar' => 'internal_process', 'priority_group' => 'A'],
            ['criteria_name' => 'Điểm khảo sát chất lượng phục vụ Mystery Shopper', 'target_value' => '≥ 90', 'unit' => 'điểm', 'weight' => 20, 'bsc_pillar' => 'customer', 'priority_group' => 'B'],
            ['criteria_name' => 'Thu thập và phân tích phản hồi khách hàng qua App WiN', 'target_value' => '≥ 25.000', 'unit' => 'lượt', 'weight' => 15, 'bsc_pillar' => 'learning_growth', 'priority_group' => 'C']
        ]
    ],
    [
        'department_id' => 1, // Ban Giám Đốc
        'target_title' => 'Tăng Trưởng Doanh Thu Tập Đoàn & Mở Rộng Hệ Thống Bán Lẻ Hiện Đại',
        'target_description' => 'Lãnh đạo toàn diện chiến lược kinh doanh, tối ưu hóa biên lợi nhuận EBITDA chuỗi và tăng tốc mở mới cửa hàng WinMart/WiN toàn quốc.',
        'kpi_items' => [
            ['criteria_name' => 'Hoàn thành chỉ tiêu doanh thu & EBITDA tập đoàn', 'target_value' => '≥ 100%', 'unit' => '%', 'weight' => 45, 'bsc_pillar' => 'financial', 'priority_group' => 'A'],
            ['criteria_name' => 'Số lượng cửa hàng bán lẻ tiện ích WiN mở mới', 'target_value' => '≥ 250', 'unit' => 'Cửa hàng', 'weight' => 30, 'bsc_pillar' => 'internal_process', 'priority_group' => 'A'],
            ['criteria_name' => 'Hiệu quả sử dụng vốn lưu động và dòng tiền', 'target_value' => '100% đạt chuẩn', 'unit' => '%', 'weight' => 15, 'bsc_pillar' => 'financial', 'priority_group' => 'B'],
            ['criteria_name' => 'Phát triển năng lực đội ngũ kế thừa cấp quản lý', 'target_value' => '≥ 90%', 'unit' => '%', 'weight' => 10, 'bsc_pillar' => 'learning_growth', 'priority_group' => 'C']
        ]
    ],
    [
        'department_id' => 9, // Pháp chế & Tuân thủ
        'target_title' => '100% Tuân Thủ An Toàn Vệ Sinh Thực Phẩm & Kiểm Soát Rủi Ro Pháp Lý',
        'target_description' => 'Đảm bảo tuân thủ tuyệt đối quy định ATVSTP tại 100% siêu thị, kiểm tra định kỳ chuỗi cung ứng và hoàn thiện pháp lý mở rộng điểm bán.',
        'kpi_items' => [
            ['criteria_name' => 'Không xảy ra sự cố vi phạm quy định ATVSTP', 'target_value' => '0 sự cố', 'unit' => 'sự cố', 'weight' => 40, 'bsc_pillar' => 'internal_process', 'priority_group' => 'A'],
            ['criteria_name' => 'Tỷ lệ hoàn thành hồ sơ pháp lý cửa hàng mở mới', 'target_value' => '100% đúng hạn', 'unit' => '%', 'weight' => 30, 'bsc_pillar' => 'internal_process', 'priority_group' => 'A'],
            ['criteria_name' => 'Kiểm tra đột xuất quy chuẩn an toàn chuỗi bán lẻ', 'target_value' => '≥ 120 đợt', 'unit' => 'đợt', 'weight' => 20, 'bsc_pillar' => 'internal_process', 'priority_group' => 'B'],
            ['criteria_name' => 'Tập huấn quy định pháp luật và an toàn lao động', 'target_value' => '100% quản lý', 'unit' => '%', 'weight' => 10, 'bsc_pillar' => 'learning_growth', 'priority_group' => 'C']
        ]
    ]
];

$stmt = $pdo->prepare("
    INSERT INTO department_kpi_targets (
        period_id, department_id, target_title, target_description, kpi_items_json, assigned_by, status
    ) VALUES (?, ?, ?, ?, ?, 1, 'assigned')
    ON DUPLICATE KEY UPDATE
        target_title = VALUES(target_title),
        target_description = VALUES(target_description),
        kpi_items_json = VALUES(kpi_items_json),
        status = VALUES(status)
");

foreach ($targets as $t) {
    $stmt->execute([
        $period_id,
        $t['department_id'],
        $t['target_title'],
        $t['target_description'],
        json_encode($t['kpi_items'], JSON_UNESCAPED_UNICODE)
    ]);
    echo "  [OK] Đã giao mục tiêu đợt cho phòng ban ID {$t['department_id']}: {$t['target_title']}\n";
}

echo "=== HOÀN TẤT KHỞI TẠO DỮ LIỆU MỤC TIÊU PHÒNG BAN ===\n";
