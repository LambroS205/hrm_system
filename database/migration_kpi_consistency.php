<?php
// database/migration_kpi_consistency.php - Đồng bộ hóa dữ liệu KPI & Snapshot tiêu chí
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../config/kpi_rules.php';
require_once __DIR__ . '/../core/KpiCalculator.php';

echo "=== BẮT ĐẦU MIGRATION ĐỒNG BỘ HÓA KPI CONSISTENCY ===\n";

// 1. Nạp sẵn tất cả template items theo template_id
$stmt = $pdo->query("SELECT * FROM kpi_template_items ORDER BY template_id ASC, sort_order ASC");
$all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$items_by_template = [];
foreach ($all_items as $item) {
    $t_id = (int)$item['template_id'];
    $items_by_template[$t_id][] = [
        'id'             => (int)$item['id'],
        'criteria_name'  => $item['criteria_name'],
        'priority_group' => $item['priority_group'] ?? 'B',
        'bsc_pillar'     => $item['bsc_pillar'] ?? 'internal_process',
        'description'    => $item['description'] ?? '',
        'weight'         => (float)$item['weight'],
        'target_type'    => $item['target_type'] ?? 'percentage',
        'unit'           => $item['unit'] ?? '%',
        'target_value'   => $item['target_value'] ?? '100%',
        'calc_direction' => $item['calc_direction'] ?? 'higher_better',
        'data_source'    => $item['data_source'] ?? '',
        'min_threshold'  => (float)($item['min_threshold'] ?? 0),
        'max_cap'        => (float)($item['max_cap'] ?? 120),
        'sort_order'     => (int)$item['sort_order']
    ];
}

// 2. Cập nhật custom_items_json cho các evaluations còn NULL
$missing_stmt = $pdo->query("SELECT id, evaluation_code, template_id FROM evaluations WHERE custom_items_json IS NULL OR custom_items_json = ''");
$missing_evals = $missing_stmt->fetchAll(PDO::FETCH_ASSOC);

$json_backfilled = 0;
$update_json_stmt = $pdo->prepare("UPDATE evaluations SET custom_items_json = ? WHERE id = ?");

foreach ($missing_evals as $me) {
    $t_id = (int)$me['template_id'];
    $items = $items_by_template[$t_id] ?? [];
    if (!empty($items)) {
        $json_str = json_encode($items, JSON_UNESCAPED_UNICODE);
        $update_json_stmt->execute([$json_str, $me['id']]);
        $json_backfilled++;
    } else {
        echo "  [CẢNH BÁO] Không tìm thấy template items cho evaluation ID {$me['id']} ({$me['evaluation_code']}), template_id: {$t_id}\n";
    }
}
echo "[1] Đã backfill custom_items_json snapshot cho {$json_backfilled} / " . count($missing_evals) . " phiếu đánh giá.\n";

// 3. Đồng bộ hóa rating, rating_grade, bonus_multiplier cho tất cả evaluations đã hoàn tất (status='completed')
$completed_stmt = $pdo->query("SELECT id, evaluation_code, final_score, rating, rating_grade, bonus_multiplier FROM evaluations WHERE status = 'completed' AND final_score IS NOT NULL");
$completed_evals = $completed_stmt->fetchAll(PDO::FETCH_ASSOC);

$rating_synced = 0;
$update_rating_stmt = $pdo->prepare("
    UPDATE evaluations
    SET rating = ?, rating_grade = ?, bonus_multiplier = ?
    WHERE id = ?
");

foreach ($completed_evals as $ce) {
    $f_score = (float)$ce['final_score'];
    $classified = KpiCalculator::classifyRating($f_score);

    $needs_update = (
        $ce['rating'] !== $classified['rating'] ||
        $ce['rating_grade'] !== $classified['rating_grade'] ||
        abs((float)$ce['bonus_multiplier'] - (float)$classified['multiplier']) > 0.001
    );

    if ($needs_update) {
        $update_rating_stmt->execute([
            $classified['rating'],
            $classified['rating_grade'],
            $classified['multiplier'],
            $ce['id']
        ]);
        $rating_synced++;
    }
}

echo "[2] Đã đồng bộ chuẩn hóa xếp loại & hệ số thưởng cho {$rating_synced} phiếu đánh giá đã hoàn thành.\n";
echo "=== HOÀN TẤT MIGRATION THÀNH CÔNG ===\n";
