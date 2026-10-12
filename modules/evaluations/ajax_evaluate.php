<?php
// modules/evaluations/ajax_evaluate.php - Xử lý AJAX chấm điểm và tính toán KPI khoa học (BSC & SMART Link Power)
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../core/auth.php';

// Kiểm tra đăng nhập
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Phiên đăng nhập đã hết hạn.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Phương thức không được hỗ trợ.']);
    exit;
}

// Nhận dữ liệu JSON hoặc POST data
$input_raw = file_get_contents('php://input');
$data = json_decode($input_raw, true);
if (!$data) {
    $data = $_POST;
}

$evaluation_id = (int)($data['evaluation_id'] ?? 0);
$review_type = $data['review_type'] ?? ''; // 'self' hoặc 'manager'
$scores_input = $data['scores'] ?? [];
$notes = trim($data['notes'] ?? '');
$is_submit_final = !empty($data['submit_final']);
$promotion_rec = $data['promotion_recommendation'] ?? 'none';

if ($evaluation_id <= 0 || !in_array($review_type, ['self', 'manager'])) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu yêu cầu không hợp lệ.']);
    exit;
}

// Lấy thông tin phiếu đánh giá
$eval_stmt = $pdo->prepare("
    SELECT e.*, ep.status AS period_status,
           emp.user_id AS emp_user_id,
           rev.user_id AS rev_user_id
    FROM evaluations e
    JOIN evaluation_periods ep ON e.period_id = ep.id
    JOIN employees emp ON e.employee_id = emp.id
    JOIN employees rev ON e.reviewer_id = rev.id
    WHERE e.id = ?
");
$eval_stmt->execute([$evaluation_id]);
$eval = $eval_stmt->fetch();

if (!$eval) {
    echo json_encode(['success' => false, 'message' => 'Không tìm thấy phiếu đánh giá.']);
    exit;
}

$user = current_user();

// Kiểm tra quyền chấm điểm:
if ($review_type === 'self') {
    $can_self = is_superadmin() || 
                (has_permission('evaluations', 'self_review') && ($user['id'] == $eval['emp_user_id'] || $user['id'] == $eval['employee_id']));
    
    if (!$can_self && !has_permission('evaluations', 'edit')) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thực hiện tự đánh giá trên hồ sơ này.']);
        exit;
    }
} else if ($review_type === 'manager') {
    $can_mgr = is_superadmin() || 
               has_permission('evaluations', 'approve') || 
               ($user['id'] == $eval['rev_user_id'] || $user['id'] == $eval['reviewer_id']) ||
               has_permission('evaluations', 'manager_review');

    if (!$can_mgr) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có thẩm quyền quản lý để chấm điểm phiếu này.']);
        exit;
    }
}

// Lấy danh sách tiêu chí (ưu tiên custom_items_json được thiết lập riêng theo vị trí & mục tiêu)
if (!empty($eval['custom_items_json'])) {
    $parsed_custom = json_decode($eval['custom_items_json'], true);
    $template_items = is_array($parsed_custom) && !empty($parsed_custom) ? $parsed_custom : [];
} else {
    $items_stmt = $pdo->prepare("SELECT * FROM kpi_template_items WHERE template_id = ? ORDER BY sort_order ASC");
    $items_stmt->execute([$eval['template_id']]);
    $template_items = $items_stmt->fetchAll();
}

$items_map = [];
foreach ($template_items as $ti) {
    $items_map[$ti['id']] = $ti;
}

// Xử lý tính điểm theo trọng số và phân tích Nhóm ưu tiên Link Power (A, B, C)
$total_weighted_score = 0;
$total_weight_accounted = 0;
$scores_json_array = [];

$priority_weights = ['A' => 0, 'B' => 0, 'C' => 0];
$priority_scores = ['A' => 0, 'B' => 0, 'C' => 0];

$bsc_weights = ['financial' => 0, 'customer' => 0, 'internal_process' => 0, 'learning_growth' => 0];
$bsc_scores = ['financial' => 0, 'customer' => 0, 'internal_process' => 0, 'learning_growth' => 0];

foreach ($scores_input as $sc) {
    $item_id = (int)($sc['item_id'] ?? 0);
    if (!isset($items_map[$item_id])) continue;

    $item = $items_map[$item_id];
    $weight = (float)$item['weight'];
    $comment_val = trim($sc['comment'] ?? '');
    $actual_val = trim($sc['actual'] ?? ($sc['actual_value'] ?? ''));
    
    // Tính % hoàn thành và điểm quy đổi khoa học
    $score_val = max(0, min(120, (float)($sc['score'] ?? 0)));
    $completion_rate = isset($sc['completion_rate']) ? (float)$sc['completion_rate'] : $score_val;

    // Nếu người dùng nhập số thực tế và chưa nhập score thủ công, tính tự động
    if ($actual_val !== '' && is_numeric($actual_val)) {
        $num_act = (float)$actual_val;
        // Trích xuất số từ target nếu có (ví dụ '>= 95%' -> 95)
        if (preg_match('/(\d+(?:\.\d+)?)/', $item['target_value'] ?? '', $matches)) {
            $num_tgt = (float)$matches[1];
            if ($num_tgt > 0) {
                if ($item['calc_direction'] === 'higher_better') {
                    $completion_rate = ($num_act / $num_tgt) * 100.0;
                } elseif ($item['calc_direction'] === 'lower_better') {
                    $completion_rate = (1.0 + ($num_tgt - $num_act) / $num_tgt) * 100.0;
                }
                // Khống chế trần sàn
                $max_cap = (float)($item['max_cap'] ?? 120.0);
                $min_thresh = (float)($item['min_threshold'] ?? 0.0);
                $completion_rate = max($min_thresh, min($max_cap, $completion_rate));
                $score_val = round($completion_rate, 2);
            }
        }
    }

    $total_weighted_score += ($score_val * $weight / 100);
    $total_weight_accounted += $weight;

    // Phân tích theo nhóm ưu tiên Link Power
    $p_group = $item['priority_group'] ?? 'B';
    if (isset($priority_weights[$p_group])) {
        $priority_weights[$p_group] += $weight;
        $priority_scores[$p_group] += ($score_val * $weight / 100);
    }

    // Phân tích theo trụ cột BSC
    $pillar = $item['bsc_pillar'] ?? 'internal_process';
    if (isset($bsc_weights[$pillar])) {
        $bsc_weights[$pillar] += $weight;
        $bsc_scores[$pillar] += ($score_val * $weight / 100);
    }

    $scores_json_array[] = [
        'item_id'         => $item_id,
        'criteria'        => $item['criteria_name'],
        'priority_group'  => $p_group,
        'bsc_pillar'      => $pillar,
        'weight'          => $weight,
        'target'          => $item['target_value'] ?? '',
        'unit'            => $item['unit'] ?? '%',
        'actual'          => $actual_val,
        'completion_rate' => round($completion_rate, 1),
        'score'           => $score_val,
        'comment'         => $comment_val
    ];
}

// Chuẩn hóa điểm thang 100 theo tổng trọng số đã chấm
$calculated_score = $total_weight_accounted > 0 ? round(($total_weighted_score / $total_weight_accounted) * 100, 2) : 0;
$scores_json_encoded = json_encode($scores_json_array, JSON_UNESCAPED_UNICODE);

// Tính % hoàn thành trung bình theo nhóm A, B, C
$priority_avg = [];
foreach (['A', 'B', 'C'] as $pg) {
    $priority_avg[$pg] = $priority_weights[$pg] > 0 ? round(($priority_scores[$pg] / $priority_weights[$pg]) * 100, 1) : 0;
}

// Tính điểm theo 4 trụ cột BSC
$bsc_avg = [];
foreach (['financial', 'customer', 'internal_process', 'learning_growth'] as $b) {
    $bsc_avg[$b] = $bsc_weights[$b] > 0 ? round(($bsc_scores[$b] / $bsc_weights[$b]) * 100, 1) : 0;
}

try {
    $pdo->beginTransaction();

    if ($review_type === 'self') {
        $new_status = $eval['status'];
        if ($is_submit_final) {
            $new_status = 'manager_review';
        } else if ($eval['status'] === 'pending') {
            $new_status = 'self_review';
        }

        $stmt = $pdo->prepare("
            UPDATE evaluations
            SET self_score = ?,
                self_scores_json = ?,
                self_notes = ?,
                self_submitted_at = NOW(),
                status = ?
            WHERE id = ?
        ");
        $stmt->execute([$calculated_score, $scores_json_encoded, $notes, $new_status, $evaluation_id]);

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => $is_submit_final ? 'Đã hoàn tất tự đánh giá và chuyển phiếu sang Quản lý duyệt!' : 'Đã lưu tạm kết quả tự đánh giá thành công.',
            'data' => [
                'self_score' => $calculated_score,
                'status' => $new_status,
                'is_submitted' => $is_submit_final,
                'priority_avg' => $priority_avg,
                'bsc_avg' => $bsc_avg
            ]
        ]);
        exit;
    } 
    
    if ($review_type === 'manager') {
        // Lấy lại self_score hiện tại
        $curr_self_score = $eval['self_score'];
        $mgr_score = $calculated_score;

        // Tính điểm tổng kết: 30% nhân viên + 70% quản lý (hoặc 100% quản lý nếu NV chưa chấm)
        if ($curr_self_score !== null) {
            $final_score = round((0.30 * (float)$curr_self_score) + (0.70 * $mgr_score), 2);
        } else {
            $final_score = $mgr_score;
        }

        // Tự động xếp loại & tính Hệ số thưởng Total Rewards theo Link Power
        $rating = 'average';
        $bonus_multiplier = 0.60;

        if ($final_score >= 95.0) {
            $rating = 'outstanding'; // Xuất sắc (A+)
            $bonus_multiplier = 1.50; // Thưởng 150%
            if ($promotion_rec === 'none') $promotion_rec = 'bonus';
        } elseif ($final_score >= 85.0) {
            $rating = 'excellent';   // Tốt (A)
            $bonus_multiplier = 1.20; // Thưởng 120%
            if ($promotion_rec === 'none') $promotion_rec = 'salary_increase';
        } elseif ($final_score >= 70.0) {
            $rating = 'good';        // Khá (B)
            $bonus_multiplier = 1.00; // Thưởng 100%
        } elseif ($final_score >= 55.0) {
            $rating = 'average';     // Trung bình (C)
            $bonus_multiplier = 0.60; // Thưởng 60%
            if ($promotion_rec === 'none') $promotion_rec = 'training';
        } elseif ($final_score >= 40.0) {
            $rating = 'below_average';// Yếu (D)
            $bonus_multiplier = 0.00; // Không thưởng
            if ($promotion_rec === 'none') $promotion_rec = 'warning';
        } else {
            $rating = 'poor';        // Kém (E)
            $bonus_multiplier = 0.00; // Không thưởng
            if ($promotion_rec === 'none') $promotion_rec = 'warning';
        }

        $new_status = $is_submit_final ? 'completed' : 'manager_review';

        $stmt = $pdo->prepare("
            UPDATE evaluations
            SET manager_score = ?,
                manager_scores_json = ?,
                manager_notes = ?,
                manager_submitted_at = NOW(),
                final_score = ?,
                rating = ?,
                bonus_multiplier = ?,
                promotion_recommendation = ?,
                status = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $mgr_score,
            $scores_json_encoded,
            $notes,
            $final_score,
            $rating,
            $bonus_multiplier,
            $promotion_rec,
            $new_status,
            $evaluation_id
        ]);

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => $is_submit_final ? 'Đã hoàn tất đánh giá quản lý và chốt kết quả xếp loại!' : 'Đã lưu tạm bảng chấm điểm quản lý thành công.',
            'data' => [
                'manager_score'    => $mgr_score,
                'final_score'      => $final_score,
                'rating'           => $rating,
                'bonus_multiplier' => $bonus_multiplier,
                'status'           => $new_status,
                'is_submitted'     => $is_submit_final,
                'priority_avg'     => $priority_avg,
                'bsc_avg'          => $bsc_avg
            ]
        ]);
        exit;
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Lỗi lưu đánh giá: ' . $e->getMessage()]);
    exit;
}
