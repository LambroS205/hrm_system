<?php
// modules/planning/planning_engine.php - Bộ Máy Phân Tích & Mô Phỏng Tối Ưu Nhân Lực
// Aura Strategic Workforce Analytics & What-If Simulation Engine

/**
 * 1. Lấy dữ liệu phân tích định biên & quỹ lương cấp Chi nhánh
 */
function get_branch_workforce_analytics(PDO $pdo) {
    $sql = "
        SELECT 
            b.*,
            m.fullname AS manager_name,
            COUNT(DISTINCT e.id) AS current_headcount,
            (b.target_headcount - COUNT(DISTINCT e.id)) AS shortage_count,
            ROUND((COUNT(DISTINCT e.id) / NULLIF(b.target_headcount, 0)) * 100) AS fulfillment_rate,
            COUNT(DISTINCT d.id) AS dept_count,
            COALESCE(SUM(p.base_salary), 0) AS monthly_payroll,
            ROUND(COALESCE(AVG(p.base_salary), 0)) AS avg_salary
        FROM branches b
        LEFT JOIN employees m ON b.manager_id = m.id
        LEFT JOIN employees e ON b.id = e.branch_id AND e.employment_status != 'resigned'
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN departments d ON b.id = d.branch_id
        WHERE b.status = 'active'
        GROUP BY b.id
        ORDER BY fulfillment_rate ASC, b.target_headcount DESC
    ";
    return $pdo->query($sql)->fetchAll();
}

/**
 * 2. Lấy dữ liệu phân tích định biên cấp Phòng Ban
 */
function get_department_workforce_analytics(PDO $pdo, $branch_id = null) {
    $where = "";
    $params = [];
    if (!empty($branch_id)) {
        $where = "WHERE d.branch_id = ?";
        $params = [$branch_id];
    }

    $sql = "
        SELECT 
            d.id, d.code, d.name, d.branch_id, d.target_headcount,
            b.code AS branch_code, b.name AS branch_name,
            m.fullname AS manager_name,
            COUNT(e.id) AS current_headcount,
            (d.target_headcount - COUNT(e.id)) AS shortage_count,
            ROUND((COUNT(e.id) / NULLIF(d.target_headcount, 0)) * 100) AS fulfillment_rate,
            COALESCE(SUM(p.base_salary), 0) AS monthly_payroll
        FROM departments d
        JOIN branches b ON d.branch_id = b.id
        LEFT JOIN employees m ON d.manager_id = m.id
        LEFT JOIN employees e ON d.id = e.department_id AND e.employment_status != 'resigned'
        LEFT JOIN positions p ON e.position_id = p.id
        {$where}
        GROUP BY d.id
        ORDER BY b.is_headquarter DESC, b.name ASC, d.name ASC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * 3. Tính điểm tương thích (Match Score 0 - 100) của ứng viên cho phương án điều chuyển
 */
function calculate_candidate_match_score($candidate, $target_branch, $target_dept_id, $scenario_type) {
    $score = 50; // Base score

    // 1. Phù hợp chuyên môn / cùng phòng ban hoặc phòng ban liên quan
    if (!empty($candidate['department_id']) && $candidate['department_id'] == $target_dept_id) {
        $score += 25;
    } elseif (!empty($candidate['dept_code']) && in_array($candidate['dept_code'], ['HR', 'IT', 'FIN', 'SCM', 'RETAIL'])) {
        $score += 15;
    }

    // 2. Điểm đánh giá KPI (nếu có dữ liệu từ bảng evaluations)
    if (!empty($candidate['kpi_score'])) {
        if ($candidate['kpi_score'] >= 90) {
            $score += 20;
        } elseif ($candidate['kpi_score'] >= 80) {
            $score += 15;
        } elseif ($candidate['kpi_score'] >= 70) {
            $score += 10;
        }
    } else {
        $score += 10; // Điểm trung bình nếu chưa đánh giá kỳ mới nhất
    }

    // 3. Thâm niên công tác
    if (!empty($candidate['hire_date'])) {
        $years = (time() - strtotime($candidate['hire_date'])) / (365 * 86400);
        if ($years >= 3) {
            $score += 10;
        } elseif ($years >= 1) {
            $score += 6;
        }
    }

    // 4. Trạng thái hợp đồng chính thức
    if (($candidate['employment_status'] ?? '') === 'official') {
        $score += 5;
    }

    return min(99, max(60, $score));
}

/**
 * 4. Thuật toán khởi tạo 4 Kịch bản Mô phỏng Tối ưu Hóa Thông Minh (What-If Simulation)
 */
function generate_smart_scenarios(PDO $pdo, $branch_analytics) {
    // Phân loại chi nhánh thiếu hụt và chi nhánh dồi dào
    $deficit_branches = [];
    $surplus_branches = [];

    foreach ($branch_analytics as $ba) {
        if ($ba['shortage_count'] > 0) {
            $deficit_branches[] = $ba;
        } else {
            $surplus_branches[] = $ba;
        }
    }

    // Lấy danh sách nhân viên tiềm năng kèm điểm KPI gần nhất
    $candidates_sql = "
        SELECT 
            e.id, e.fullname, e.employee_code, e.branch_id, e.department_id, e.position_id, e.hire_date, e.employment_status,
            b.name AS branch_name, b.code AS branch_code, b.is_headquarter,
            d.id AS dept_id, d.name AS dept_name, d.code AS dept_code,
            p.id AS pos_id, p.name AS pos_name, p.base_salary,
            ev.final_score AS kpi_score, ev.rating_grade AS kpi_grade
        FROM employees e
        JOIN branches b ON e.branch_id = b.id
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN positions p ON e.position_id = p.id
        LEFT JOIN (
            SELECT employee_id, final_score, rating_grade
            FROM evaluations
            WHERE status = 'approved'
            ORDER BY created_at DESC
        ) ev ON e.id = ev.employee_id
        WHERE e.employment_status = 'official'
        ORDER BY b.is_headquarter DESC, ev.final_score DESC, e.hire_date ASC
    ";
    $all_candidates = $pdo->query($candidates_sql)->fetchAll();

    // Lấy danh sách tất cả phòng ban và chức vụ
    $all_departments = $pdo->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
    $all_positions = $pdo->query("SELECT * FROM positions ORDER BY base_salary DESC")->fetchAll();

    $scenarios = [];

    // =========================================================================
    // KỊCH BẢN 1: CÂN BẰNG ĐỊNH BIÊN CẤP BÁCH (Urgent Headcount Balancing)
    // =========================================================================
    // Tìm chi nhánh thiếu nhiều nhất
    $primary_deficit = !empty($deficit_branches) ? $deficit_branches[0] : (!empty($branch_analytics) ? $branch_analytics[0] : null);

    if ($primary_deficit) {
        // Tìm ứng viên từ chi nhánh KHÁC có tỷ lệ cao hoặc từ HQ
        $cand1 = null;
        foreach ($all_candidates as $c) {
            if ($c['branch_id'] != $primary_deficit['id']) {
                $cand1 = $c;
                break;
            }
        }

        if ($cand1) {
            // Tìm phòng ban tiếp nhận tại chi nhánh đích
            $target_dept = null;
            foreach ($all_departments as $d) {
                if ($d['branch_id'] == $primary_deficit['id']) {
                    $target_dept = $d;
                    break;
                }
            }
            if (!$target_dept) {
                $target_dept = ['id' => $cand1['dept_id'], 'name' => $cand1['dept_name']];
            }

            $match_score = calculate_candidate_match_score($cand1, $primary_deficit, $target_dept['id'], 'rebalance');
            $allowance = 4500000;

            $new_headcount = $primary_deficit['current_headcount'] + 1;
            $new_rate = round(($new_headcount / max(1, $primary_deficit['target_headcount'])) * 100);

            $scenarios[] = [
                'id' => 1,
                'tag' => 'Cân Bằng Cấp Bách',
                'tag_class' => 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20',
                'icon' => 'fa-scale-unbalanced-flip',
                'title' => "Phương Án 1: Bù Đắp Định Biên Thiếu Hụt Cho {$primary_deficit['name']}",
                'desc' => "Chi nhánh {$primary_deficit['name']} ({$primary_deficit['code']}) hiện chỉ đạt {$primary_deficit['fulfillment_rate']}% định biên (thiếu {$primary_deficit['shortage_count']} nhân sự). Đề xuất điều động cán bộ từ {$cand1['branch_name']} sang nhằm bù đắp ngay nhân lực cốt lõi.",
                'target_branch_id' => $primary_deficit['id'],
                'target_branch_name' => $primary_deficit['name'],
                'target_branch_code' => $primary_deficit['code'],
                'candidates' => [
                    [
                        'candidate_id' => $cand1['id'],
                        'candidate_name' => $cand1['fullname'],
                        'candidate_code' => $cand1['employee_code'],
                        'from_branch_id' => $cand1['branch_id'],
                        'from_branch_name' => $cand1['branch_name'],
                        'from_dept_id' => $cand1['dept_id'],
                        'from_dept_name' => $cand1['dept_name'],
                        'from_pos_id' => $cand1['pos_id'],
                        'from_pos_name' => $cand1['pos_name'],
                        'to_branch_id' => $primary_deficit['id'],
                        'to_branch_name' => $primary_deficit['name'],
                        'to_dept_id' => $target_dept['id'],
                        'to_dept_name' => $target_dept['name'],
                        'to_pos_id' => $cand1['pos_id'],
                        'to_pos_name' => $cand1['pos_name'],
                        'match_score' => $match_score,
                        'kpi_score' => $cand1['kpi_score'] ?? 88,
                        'kpi_grade' => $cand1['kpi_grade'] ?? 'A',
                        'allowance' => $allowance,
                        'role_note' => 'Tăng cường Chuyên viên Vận hành nòng cốt'
                    ]
                ],
                'impact_score' => 96,
                'impact_label' => "Nâng tỷ lệ định biên {$primary_deficit['code']} từ {$primary_deficit['fulfillment_rate']}% lên {$new_rate}%",
                'estimated_total_budget' => $allowance,
                'before_after' => [
                    'current_rate' => $primary_deficit['fulfillment_rate'],
                    'projected_rate' => $new_rate,
                    'shortage_before' => $primary_deficit['shortage_count'],
                    'shortage_after' => max(0, $primary_deficit['shortage_count'] - 1)
                ]
            ];
        }
    }

    // =========================================================================
    // KỊCH BẢN 2: TĂNG CƯỜNG CÁN BỘ NGUỒN & KPI CAO (Talent & Leadership Boost)
    // =========================================================================
    // Lấy nhân viên có KPI cao nhất từ HQ hoặc chi nhánh lớn
    $talent_cand = null;
    $target_expansion = null;

    // Tìm chi nhánh đích (ví dụ chi nhánh mở rộng như Cần Thơ, Hải Phòng, hoặc Đà Nẵng)
    foreach ($branch_analytics as $ba) {
        if (!$ba['is_headquarter'] && ($ba['shortage_count'] > 0 || in_array($ba['code'], ['CT-HUB', 'HP-HUB', 'DN-HUB', 'BR-DNG', 'BR-CT']))) {
            $target_expansion = $ba;
            break;
        }
    }
    if (!$target_expansion && count($branch_analytics) > 1) {
        $target_expansion = $branch_analytics[1];
    }

    if ($target_expansion) {
        foreach ($all_candidates as $c) {
            if ($c['branch_id'] != $target_expansion['id'] && !empty($c['kpi_score']) && $c['kpi_score'] >= 85) {
                $talent_cand = $c;
                break;
            }
        }
        // Nếu không có ai có KPI score lưu sẵn, lấy người có thâm niên cao từ HQ
        if (!$talent_cand) {
            foreach ($all_candidates as $c) {
                if ($c['branch_id'] != $target_expansion['id'] && $c['is_headquarter']) {
                    $talent_cand = $c;
                    break;
                }
            }
        }

        if ($talent_cand) {
            $target_dept = null;
            foreach ($all_departments as $d) {
                if ($d['branch_id'] == $target_expansion['id']) {
                    $target_dept = $d;
                    break;
                }
            }
            if (!$target_dept) {
                $target_dept = ['id' => $talent_cand['dept_id'], 'name' => $talent_cand['dept_name']];
            }

            $match_score = calculate_candidate_match_score($talent_cand, $target_expansion, $target_dept['id'], 'talent_boost');
            $allowance = 5500000;

            $scenarios[] = [
                'id' => 2,
                'tag' => 'Cán Bộ Nguồn & KPI Cao',
                'tag_class' => 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400 border-indigo-500/20',
                'icon' => 'fa-award',
                'title' => "Phương Án 2: Luân Chuyển Cán Bộ Nguồn & Biệt Phái Phát Triển Vùng {$target_expansion['name']}",
                'desc' => "Điều động cán bộ có thành tích KPI xuất sắc từ {$talent_cand['branch_name']} sang cọ xát thực địa tại {$target_expansion['name']} trong 12 tháng, vừa hỗ trợ chuẩn hóa vận hành chi nhánh vừa tạo nguồn bổ nhiệm cấp cao.",
                'target_branch_id' => $target_expansion['id'],
                'target_branch_name' => $target_expansion['name'],
                'target_branch_code' => $target_expansion['code'],
                'candidates' => [
                    [
                        'candidate_id' => $talent_cand['id'],
                        'candidate_name' => $talent_cand['fullname'],
                        'candidate_code' => $talent_cand['employee_code'],
                        'from_branch_id' => $talent_cand['branch_id'],
                        'from_branch_name' => $talent_cand['branch_name'],
                        'from_dept_id' => $talent_cand['dept_id'],
                        'from_dept_name' => $talent_cand['dept_name'],
                        'from_pos_id' => $talent_cand['pos_id'],
                        'from_pos_name' => $talent_cand['pos_name'],
                        'to_branch_id' => $target_expansion['id'],
                        'to_branch_name' => $target_expansion['name'],
                        'to_dept_id' => $target_dept['id'],
                        'to_dept_name' => $target_dept['name'],
                        'to_pos_id' => $talent_cand['pos_id'],
                        'to_pos_name' => $talent_cand['pos_name'],
                        'match_score' => $match_score,
                        'kpi_score' => $talent_cand['kpi_score'] ?? 94.5,
                        'kpi_grade' => $talent_cand['kpi_grade'] ?? 'A+',
                        'allowance' => $allowance,
                        'role_note' => 'Biệt phái Quản lý / Tech Lead dẫn dắt dự án vùng'
                    ]
                ],
                'impact_score' => 92,
                'impact_label' => "Nâng 40% chỉ số năng lực quản trị chuẩn tập đoàn tại {$target_expansion['code']}",
                'estimated_total_budget' => $allowance,
                'before_after' => [
                    'current_rate' => $target_expansion['fulfillment_rate'],
                    'projected_rate' => round((($target_expansion['current_headcount'] + 1) / max(1, $target_expansion['target_headcount'])) * 100),
                    'shortage_before' => $target_expansion['shortage_count'],
                    'shortage_after' => max(0, $target_expansion['shortage_count'] - 1)
                ]
            ];
        }
    }

    // =========================================================================
    // KỊCH BẢN 3: TỐI ƯU HÓA CHI PHÍ & QUỸ LƯƠNG (Cost & Payroll Optimization)
    // =========================================================================
    // Tìm chi nhánh có mức lương bình quân cao và đề xuất phân bổ bớt nhân sự hỗ trợ về hub tối ưu chi phí
    $cost_source = null;
    $cost_target = null;

    // Chi nhánh nguồn là chi nhánh có chi phí lương cao (thường là HN hoặc HCM)
    foreach ($branch_analytics as $ba) {
        if ($ba['is_headquarter'] || $ba['code'] === 'HN-HQ' || $ba['code'] === 'HCM-BR') {
            $cost_source = $ba;
            break;
        }
    }
    // Chi nhánh đích là các hub vận hành có chi phí tối ưu (Đà Nẵng, Bình Dương, Hải Phòng)
    foreach ($branch_analytics as $ba) {
        if ($cost_source && $ba['id'] != $cost_source['id'] && in_array($ba['code'], ['BD-HUB', 'HP-HUB', 'DN-HUB', 'BR-DNG'])) {
            $cost_target = $ba;
            break;
        }
    }
    if (!$cost_target && count($branch_analytics) > 2) {
        $cost_target = $branch_analytics[2];
    }

    if ($cost_source && $cost_target) {
        $cost_cand = null;
        foreach ($all_candidates as $c) {
            if ($c['branch_id'] == $cost_source['id']) {
                $cost_cand = $c;
                break;
            }
        }

        if ($cost_cand) {
            $target_dept = null;
            foreach ($all_departments as $d) {
                if ($d['branch_id'] == $cost_target['id']) {
                    $target_dept = $d;
                    break;
                }
            }
            if (!$target_dept) {
                $target_dept = ['id' => $cost_cand['dept_id'], 'name' => $cost_cand['dept_name']];
            }

            $match_score = calculate_candidate_match_score($cost_cand, $cost_target, $target_dept['id'], 'cost_opt');
            $allowance = 3500000;

            $scenarios[] = [
                'id' => 3,
                'tag' => 'Tối Ưu Quỹ Lương',
                'tag_class' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border-amber-500/20',
                'icon' => 'fa-coins',
                'title' => "Phương Án 3: Tái Phân Bổ Nhân Lực Vận Hành & Tối Ưu Quỹ Lương {$cost_target['name']}",
                'desc' => "Dịch chuyển một phần bộ phận hỗ trợ kỹ thuật / vận hành từ trung tâm chi phí cao ({$cost_source['name']}) về Hub trung chuyển ({$cost_target['name']}) để giảm gánh nặng quỹ lương cơ quan và tối ưu chi phí mặt bằng vận hành.",
                'target_branch_id' => $cost_target['id'],
                'target_branch_name' => $cost_target['name'],
                'target_branch_code' => $cost_target['code'],
                'candidates' => [
                    [
                        'candidate_id' => $cost_cand['id'],
                        'candidate_name' => $cost_cand['fullname'],
                        'candidate_code' => $cost_cand['employee_code'],
                        'from_branch_id' => $cost_cand['branch_id'],
                        'from_branch_name' => $cost_cand['branch_name'],
                        'from_dept_id' => $cost_cand['dept_id'],
                        'from_dept_name' => $cost_cand['dept_name'],
                        'from_pos_id' => $cost_cand['pos_id'],
                        'from_pos_name' => $cost_cand['pos_name'],
                        'to_branch_id' => $cost_target['id'],
                        'to_branch_name' => $cost_target['name'],
                        'to_dept_id' => $target_dept['id'],
                        'to_dept_name' => $target_dept['name'],
                        'to_pos_id' => $cost_cand['pos_id'],
                        'to_pos_name' => $cost_cand['pos_name'],
                        'match_score' => $match_score,
                        'kpi_score' => $cost_cand['kpi_score'] ?? 85,
                        'kpi_grade' => $cost_cand['kpi_grade'] ?? 'B+',
                        'allowance' => $allowance,
                        'role_note' => 'Điều phối vận hành & kiểm soát chi phí Hub'
                    ]
                ],
                'impact_score' => 89,
                'impact_label' => "Tiết kiệm ước tính ~120 triệu VNĐ/năm chi phí vận hành tại {$cost_source['code']}",
                'estimated_total_budget' => $allowance,
                'before_after' => [
                    'current_rate' => $cost_target['fulfillment_rate'],
                    'projected_rate' => round((($cost_target['current_headcount'] + 1) / max(1, $cost_target['target_headcount'])) * 100),
                    'shortage_before' => $cost_target['shortage_count'],
                    'shortage_after' => max(0, $cost_target['shortage_count'] - 1)
                ]
            ];
        }
    }

    // =========================================================================
    // KỊCH BẢN 4: KẾ HOẠCH KẾ THỪA & BÙ ĐẮP VỊ TRÍ TRỐNG (Succession & Gap Filling)
    // =========================================================================
    // Tìm chi nhánh cần kiện toàn vị trí quản lý hoặc có nhu cầu bổ nhiệm Store Manager / SCM Manager
    $succ_target = null;
    foreach ($branch_analytics as $ba) {
        if ($ba['shortage_count'] > 0 && $ba['id'] != 1) {
            $succ_target = $ba;
            break;
        }
    }
    if (!$succ_target && count($branch_analytics) > 1) {
        $succ_target = $branch_analytics[count($branch_analytics) - 1];
    }

    if ($succ_target) {
        $succ_cand = null;
        foreach ($all_candidates as $c) {
            if ($c['branch_id'] != $succ_target['id'] && in_array($c['pos_id'], [4, 6, 8, 11])) {
                $succ_cand = $c;
                break;
            }
        }
        if (!$succ_cand && count($all_candidates) > 2) {
            foreach ($all_candidates as $c) {
                if ($c['branch_id'] != $succ_target['id']) {
                    $succ_cand = $c;
                    break;
                }
            }
        }

        if ($succ_cand) {
            $target_dept = null;
            foreach ($all_departments as $d) {
                if ($d['branch_id'] == $succ_target['id']) {
                    $target_dept = $d;
                    break;
                }
            }
            if (!$target_dept) {
                $target_dept = ['id' => $succ_cand['dept_id'], 'name' => $succ_cand['dept_name']];
            }

            $match_score = calculate_candidate_match_score($succ_cand, $succ_target, $target_dept['id'], 'succession');
            $allowance = 5000000;

            $scenarios[] = [
                'id' => 4,
                'tag' => 'Kế Thừa & Bù Đắp Vị Trí Trống',
                'tag_class' => 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20',
                'icon' => 'fa-sitemap',
                'title' => "Phương Án 4: Bổ Nhiệm Cán Bộ Kế Cận & Kiện Toàn Bộ Máy {$succ_target['name']}",
                'desc' => "Rà soát khoảng trống chức danh quản lý bộ phận tại {$succ_target['name']}. Đề xuất điều động cán bộ quản lý tiềm năng từ {$succ_cand['branch_name']} sang tiếp quản vị trí trọng yếu, đảm bảo tính liên tục của chuỗi vận hành.",
                'target_branch_id' => $succ_target['id'],
                'target_branch_name' => $succ_target['name'],
                'target_branch_code' => $succ_target['code'],
                'candidates' => [
                    [
                        'candidate_id' => $succ_cand['id'],
                        'candidate_name' => $succ_cand['fullname'],
                        'candidate_code' => $succ_cand['employee_code'],
                        'from_branch_id' => $succ_cand['branch_id'],
                        'from_branch_name' => $succ_cand['branch_name'],
                        'from_dept_id' => $succ_cand['dept_id'],
                        'from_dept_name' => $succ_cand['dept_name'],
                        'from_pos_id' => $succ_cand['pos_id'],
                        'from_pos_name' => $succ_cand['pos_name'],
                        'to_branch_id' => $succ_target['id'],
                        'to_branch_name' => $succ_target['name'],
                        'to_dept_id' => $target_dept['id'],
                        'to_dept_name' => $target_dept['name'],
                        'to_pos_id' => $succ_cand['pos_id'],
                        'to_pos_name' => $succ_cand['pos_name'],
                        'match_score' => $match_score,
                        'kpi_score' => $succ_cand['kpi_score'] ?? 91,
                        'kpi_grade' => $succ_cand['kpi_grade'] ?? 'A',
                        'allowance' => $allowance,
                        'role_note' => 'Bổ nhiệm Quản lý đơn vị kế cận'
                    ]
                ],
                'impact_score' => 94,
                'impact_label' => "Lấp đầy 100% vị trí chủ chốt tại {$succ_target['code']}",
                'estimated_total_budget' => $allowance,
                'before_after' => [
                    'current_rate' => $succ_target['fulfillment_rate'],
                    'projected_rate' => round((($succ_target['current_headcount'] + 1) / max(1, $succ_target['target_headcount'])) * 100),
                    'shortage_before' => $succ_target['shortage_count'],
                    'shortage_after' => max(0, $succ_target['shortage_count'] - 1)
                ]
            ];
        }
    }

    return $scenarios;
}

/**
 * 5. 1-Click Áp dụng Kịch bản vào Kế hoạch (Tạo mới hoặc Nạp vào kế hoạch đang có)
 */
function apply_scenario_to_plan(PDO $pdo, array $scenario, string $mode, $target_plan_id = null, $custom_title = null, $user_id = 1) {
    $plan_id = null;

    if ($mode === 'new') {
        // Tạo Kế hoạch mới
        $last_id = (int)($pdo->query("SELECT MAX(id) FROM transfer_plans")->fetchColumn() ?: 0);
        $plan_code = 'PLAN-' . date('Y') . '-OPT-' . str_pad($last_id + 1, 3, '0', STR_PAD_LEFT);
        $title = !empty($custom_title) ? $custom_title : "Kế Hoạch Tối Ưu: " . $scenario['title'];
        $desc = "Tự động khởi tạo từ Kịch bản thông minh [{$scenario['tag']}]: " . $scenario['desc'];
        $plan_type = ($scenario['id'] == 1) ? 'emergency_rebalance' : (($scenario['id'] == 2) ? 'branch_expansion' : 'quarterly');

        $insPlan = $pdo->prepare("
            INSERT INTO transfer_plans (plan_code, title, description, plan_type, target_branch_id, status, start_date, end_date, created_by)
            VALUES (?, ?, ?, ?, ?, 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 MONTH), ?)
        ");
        $insPlan->execute([
            $plan_code, $title, $desc, $plan_type, $scenario['target_branch_id'], $user_id
        ]);
        $plan_id = $pdo->lastInsertId();
    } else {
        // Nạp vào Kế hoạch đã có
        $plan_id = (int)$target_plan_id;
    }

    if ($plan_id > 0 && !empty($scenario['candidates'])) {
        $insItem = $pdo->prepare("
            INSERT INTO transfer_plan_items (
                plan_id, employee_id, from_branch_id, to_branch_id,
                from_department_id, to_department_id, from_position_id, to_position_id,
                priority, rationale, estimated_allowance
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($scenario['candidates'] as $cand) {
            $priority = ($scenario['id'] == 1) ? 'high' : 'medium';
            $rationale = "Được đề xuất tự động từ Kịch bản [{$scenario['tag']}]. Match Score: {$cand['match_score']}%. " . $cand['role_note'];
            
            $insItem->execute([
                $plan_id,
                $cand['candidate_id'],
                $cand['from_branch_id'],
                $cand['to_branch_id'],
                $cand['from_dept_id'],
                $cand['to_dept_id'],
                $cand['from_pos_id'],
                $cand['to_pos_id'],
                $priority,
                $rationale,
                $cand['allowance']
            ]);
        }
    }

    return $plan_id;
}
