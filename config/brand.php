<?php
// config/brand.php - Cấu hình nhận diện thương hiệu doanh nghiệp (Single Source of Truth)

if (!defined('BRAND_LOADED')) {
    define('BRAND_LOADED', true);

    define('BRAND_CONFIG', [
        'product_name'    => 'WinCommerce People',
        'product_short'   => 'WCM People',
        'company_short'   => 'WinCommerce',
        'company_legal'   => 'Công ty Cổ phần Dịch vụ Thương mại Tổng hợp WinCommerce',
        'company_en'      => 'WinCommerce General Commercial Services JSC',
        'parent_group'    => 'Tập đoàn Masan',
        'retail_brands'   => 'WinMart · WinMart+ · WiN',
        'hq_address'      => 'Số 23 Lê Duẩn, Phường Bến Nghé, Quận 1, TP. Hồ Chí Minh',
        'hn_office'       => 'Tòa nhà International Centre, 17 Ngô Quyền, Q. Hoàn Kiếm, Hà Nội',
        'email_domain'    => 'wincommerce.vn',
        'emp_code_prefix' => 'WCM',
        'decision_prefix' => 'WCM',
        'tagline'         => 'Phục vụ hàng triệu gia đình Việt mỗi ngày',
        'version_label'   => 'Enterprise v4.0',
        'demo_disclaimer' => 'Bản demo học thuật – Mô phỏng hệ thống quản trị nhân sự chuỗi bán lẻ WinCommerce (Masan Group).',
    ]);

    /**
     * Lấy giá trị cấu hình thương hiệu
     */
    function brand(string $key): string {
        return BRAND_CONFIG[$key] ?? '';
    }
}
