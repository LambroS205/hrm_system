<?php
// config/kpi_rules.php - Quy tắc đánh giá hiệu suất, xếp loại & Total Rewards (Single Source of Truth)

if (!defined('KPI_RULES_LOADED')) {
    define('KPI_RULES_LOADED', true);

    define('KPI_RULES', [
        // Trọng số đánh giá chuẩn
        'self_weight'    => 0.30, // 30% Điểm nhân viên tự chấm
        'manager_weight' => 0.70, // 70% Điểm quản lý trực tiếp chấm
        'default_score_cap' => 120.0, // Điểm trần tối đa cho 1 tiêu chí (%)
        'zero_target_penalty_per_unit' => 20.0, // Tiêu chí mục tiêu 0 lỗi: mỗi đơn vị vi phạm trừ 20đ

        // Điều kiện khống chế nhóm ưu tiên Link Power (Group A Gate)
        // Nếu mức hoàn thành trung bình của các tiêu chí cốt lõi (Nhóm A) < 60%, xếp loại tối đa không vượt quá 'average' (C)
        'group_a_gate'   => [
            'enabled'        => true,
            'min_completion' => 60.0,
            'max_rating'     => 'average',
            'max_grade'      => 'C',
            'reason_msg'     => 'Không đạt ngưỡng 60% KPI cốt lõi nhóm A (Điểm liệt chiến lược)'
        ],

        // Bảng quy đổi điểm tổng kết sang Xếp loại & Hệ số thưởng Total Rewards
        // Xếp theo thứ tự từ cao xuống thấp
        'ratings' => [
            [
                'key'         => 'outstanding',
                'min_score'   => 95.0,
                'grade'       => 'A+',
                'label'       => 'Xuất Sắc (A+)',
                'multiplier'  => 1.50, // Thưởng 150% quỹ KPI
                'default_rec' => 'bonus',
                'badge_class' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300 dark:border-amber-700',
                'reward_desc' => 'Thưởng 150% quỹ KPI & Đề xuất thăng chức / tăng lương trước hạn'
            ],
            [
                'key'         => 'excellent',
                'min_score'   => 85.0,
                'grade'       => 'A',
                'label'       => 'Tốt (A)',
                'multiplier'  => 1.20, // Thưởng 120% quỹ KPI
                'default_rec' => 'salary_increase',
                'badge_class' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700',
                'reward_desc' => 'Thưởng 120% quỹ KPI & Xét nâng bậc lương định kỳ'
            ],
            [
                'key'         => 'good',
                'min_score'   => 70.0,
                'grade'       => 'B',
                'label'       => 'Khá (B)',
                'multiplier'  => 1.00, // Thưởng 100% quỹ KPI
                'default_rec' => 'none',
                'badge_class' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-950/60 dark:text-indigo-300 border-indigo-300 dark:border-indigo-700',
                'reward_desc' => 'Thưởng 100% quỹ KPI tiêu chuẩn (Đạt định mức)'
            ],
            [
                'key'         => 'average',
                'min_score'   => 55.0,
                'grade'       => 'C',
                'label'       => 'Trung Bình (C)',
                'multiplier'  => 0.60, // Thưởng 60% quỹ KPI
                'default_rec' => 'training',
                'badge_class' => 'bg-slate-100 text-slate-800 dark:bg-slate-700 dark:text-slate-200 border-slate-300 dark:border-slate-600',
                'reward_desc' => 'Thưởng 60% quỹ KPI & Lập kế hoạch kèm cặp cải thiện PIP 60 ngày'
            ],
            [
                'key'         => 'below_average',
                'min_score'   => 40.0,
                'grade'       => 'D',
                'label'       => 'Yếu (D)',
                'multiplier'  => 0.00, // 0%
                'default_rec' => 'warning',
                'badge_class' => 'bg-orange-100 text-orange-800 dark:bg-orange-950/60 dark:text-orange-300 border-orange-300 dark:border-orange-700',
                'reward_desc' => '0% thưởng hiệu suất, cảnh cáo và tái đào tạo chuyên môn'
            ],
            [
                'key'         => 'poor',
                'min_score'   => 0.0,
                'grade'       => 'E',
                'label'       => 'Kém (E)',
                'multiplier'  => 0.00, // 0%
                'default_rec' => 'warning',
                'badge_class' => 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-300 dark:border-rose-700',
                'reward_desc' => '0% thưởng hiệu suất, xem xét chuyển đổi vị trí hoặc chấm dứt HĐ'
            ]
        ]
    ]);

    function kpi_rules(): array {
        return KPI_RULES;
    }
}
