<?php
// core/KpiCalculator.php - Động cơ tính toán điểm KPI, chuẩn hóa trọng số & phân loại Link Power

require_once __DIR__ . '/../config/kpi_rules.php';

final class KpiCalculator {

    /**
     * Trích xuất số mục tiêu từ chuỗi mô tả (VD: "≥ 95%" -> 95, "< 0,5%" -> 0.5, "100 Triệu" -> 100)
     */
    public static function parseTarget(?string $raw): ?float {
        if ($raw === null) return null;
        $str = trim($raw);
        if ($str === '') return null;

        // Chuẩn hóa dấu phẩy thập phân kiểu Việt Nam "0,5" -> "0.5"
        $str = str_replace(',', '.', $str);

        if (preg_match('/(\d+(?:\.\d+)?)/', $str, $matches)) {
            return (float)$matches[1];
        }
        return null;
    }

    /**
     * Tính toán % hoàn thành của 1 tiêu chí dựa trên số thực tế (Actual) so với kế hoạch (Target)
     */
    public static function computeItemCompletion(array $item, $actualInput, ?float $manualScore = null): array {
        $direction   = $item['calc_direction'] ?? 'higher_better';
        $targetValue = $item['target_value'] ?? '';
        $minThresh   = isset($item['min_threshold']) ? (float)$item['min_threshold'] : 0.0;
        $maxCap      = isset($item['max_cap']) && (float)$item['max_cap'] > 0 ? (float)$item['max_cap'] : 120.0;

        $actualStr = trim((string)($actualInput ?? ''));
        $targetNum = self::parseTarget($targetValue);

        $completionRate = 0.0;
        $actualNum = self::parseTarget($actualStr);

        // 1. Trường hợp tiêu chí Boolean (Đạt / Không đạt)
        if ($direction === 'boolean' || ($item['target_type'] ?? '') === 'boolean') {
            $valLower = mb_strtolower($actualStr, 'UTF-8');
            $truthy = in_array($valLower, ['1', 'true', 'đạt', 'dat', 'yes', 'có', 'co', 'pass', 'hoàn thành'], true);
            $completionRate = $truthy ? 100.0 : 0.0;
            $isAutoComputed = true;
        }
        // 2. Trường hợp có số thực tế hợp lệ và có số mục tiêu
        elseif ($actualStr !== '' && $actualNum !== null && $targetNum !== null) {
            $numActual = $actualNum;

            if ($targetNum > 0) {
                if ($direction === 'higher_better') {
                    $completionRate = ($numActual / $targetNum) * 100.0;
                } elseif ($direction === 'lower_better') {
                    // Càng thấp càng tốt (VD: tỷ lệ hao hụt target < 0.5%, actual 0.4% -> 120%)
                    $completionRate = (1.0 + ($targetNum - $numActual) / $targetNum) * 100.0;
                }
                $isAutoComputed = true;
            } elseif ($targetNum == 0.0 && $direction === 'lower_better') {
                // Mục tiêu là 0 (VD: "0 lỗi nghiêm trọng", "0 sự cố")
                $penaltyPerUnit = (float)(kpi_rules()['zero_target_penalty_per_unit'] ?? 20.0);
                if ($numActual <= 0) {
                    $completionRate = 100.0;
                } else {
                    $completionRate = max(0.0, 100.0 - ($numActual * $penaltyPerUnit));
                }
                $isAutoComputed = true;
            }
        }

        // 3. Nếu không tự động tính được, dùng điểm nhập tay (manual score) nếu có
        if (!$isAutoComputed) {
            if ($manualScore !== null && $manualScore !== '') {
                $completionRate = (float)$manualScore;
            } elseif ($actualStr !== '' && is_numeric(str_replace(',', '.', $actualStr))) {
                $completionRate = (float)str_replace(',', '.', $actualStr);
            } else {
                $completionRate = 0.0;
            }
        }

        // 4. ÁP DỤNG QUY TẮC NGƯỠNG LIỆT (FAIL THRESHOLD)
        // Nếu mức hoàn thành < min_threshold thì tiêu chí bị 0 điểm (thay vì nâng lên min_threshold)
        $rawCompletion = round($completionRate, 2);
        if ($minThresh > 0 && $rawCompletion < $minThresh) {
            $effectiveScore = 0.0;
            $thresholdFailed = true;
        } else {
            // Khống chế trần tối đa theo max_cap của tiêu chí
            $effectiveScore = min($maxCap, max(0.0, $rawCompletion));
            $thresholdFailed = false;
        }

        return [
            'raw_completion'   => $rawCompletion,
            'effective_score'  => round($effectiveScore, 2),
            'threshold_failed' => $thresholdFailed,
            'is_auto'          => $isAutoComputed,
            'max_cap'          => $maxCap,
            'min_threshold'    => $minThresh
        ];
    }

    /**
     * Tính toán toàn bộ bảng điểm phiếu đánh giá
     * Đảm bảo: KHÔNG bao giờ bị lạm phát điểm khi bỏ trống tiêu chí
     */
    public static function calculateScoreSheet(array $templateItems, array $scoresInput, bool $isSubmitFinal = false, ?array $rules = null): array {
        $rules = $rules ?? kpi_rules();

        // Lập map template items
        $itemsMap = [];
        $totalTemplateWeight = 0.0;
        foreach ($templateItems as $idx => $ti) {
            $itId = (int)($ti['id'] ?? ($idx + 1));
            $w = (float)($ti['weight'] ?? 0);
            $totalTemplateWeight += $w;
            $itemsMap[$itId] = $ti;
        }
        if ($totalTemplateWeight <= 0) {
            $totalTemplateWeight = 100.0;
        }

        // Chuẩn bị mảng nhóm Link Power & Trụ cột BSC
        $priorityWeights = ['A' => 0.0, 'B' => 0.0, 'C' => 0.0];
        $priorityScores  = ['A' => 0.0, 'B' => 0.0, 'C' => 0.0];

        $bscWeights = ['financial' => 0.0, 'customer' => 0.0, 'internal_process' => 0.0, 'learning_growth' => 0.0];
        $bscScores  = ['financial' => 0.0, 'customer' => 0.0, 'internal_process' => 0.0, 'learning_growth' => 0.0];

        // Map input từ client
        $inputMap = [];
        foreach ($scoresInput as $inp) {
            $itId = (int)($inp['item_id'] ?? 0);
            if ($itId > 0) {
                $inputMap[$itId] = $inp;
            }
        }

        $scoresJsonArray = [];
        $totalWeightedPoints = 0.0;
        $missingItems = [];

        foreach ($itemsMap as $itId => $item) {
            $weight  = (float)($item['weight'] ?? 0);
            $pGroup  = in_array($item['priority_group'] ?? '', ['A', 'B', 'C'], true) ? $item['priority_group'] : 'B';
            $pillar  = in_array($item['bsc_pillar'] ?? '', ['financial', 'customer', 'internal_process', 'learning_growth'], true) ? $item['bsc_pillar'] : 'internal_process';

            if (isset($inputMap[$itId])) {
                $userInp    = $inputMap[$itId];
                $actualVal  = trim((string)($userInp['actual'] ?? ($userInp['actual_value'] ?? '')));
                $manualSc   = isset($userInp['score']) ? (float)$userInp['score'] : null;
                $commentVal = trim((string)($userInp['comment'] ?? ''));

                $comp = self::computeItemCompletion($item, $actualVal, $manualSc);
                $itemScore = $comp['effective_score'];
                $completionRate = $comp['raw_completion'];
            } else {
                // Tiêu chí bị bỏ sót
                $actualVal = '';
                $commentVal = '';
                $itemScore = 0.0;
                $completionRate = 0.0;
                $missingItems[] = $item['criteria_name'] ?? ('Tiêu chí #' . $itId);
            }

            // Cộng điểm theo trọng số chuẩn
            $totalWeightedPoints += ($itemScore * $weight / 100.0);

            // Tích lũy theo Nhóm ưu tiên
            $priorityWeights[$pGroup] += $weight;
            $priorityScores[$pGroup]  += ($itemScore * $weight / 100.0);

            // Tích lũy theo Trụ cột BSC
            $bscWeights[$pillar] += $weight;
            $bscScores[$pillar]  += ($itemScore * $weight / 100.0);

            $scoresJsonArray[] = [
                'item_id'         => $itId,
                'criteria'        => $item['criteria_name'] ?? 'Tiêu chí',
                'priority_group'  => $pGroup,
                'bsc_pillar'      => $pillar,
                'weight'          => $weight,
                'target'          => $item['target_value'] ?? '',
                'unit'            => $item['unit'] ?? '%',
                'actual'          => $actualVal,
                'completion_rate' => $completionRate,
                'score'           => $itemScore,
                'comment'         => $commentVal
            ];
        }

        // Điểm quy chuẩn thang 100 tính trên TỔNG TRỌNG SỐ THẬT CỦA BỘ TIÊU CHÍ
        $finalCalculatedScore = round(($totalWeightedPoints / $totalTemplateWeight) * 100.0, 2);

        // % hoàn thành trung bình theo Nhóm A, B, C
        $priorityAvg = [];
        foreach (['A', 'B', 'C'] as $pg) {
            $priorityAvg[$pg] = $priorityWeights[$pg] > 0
                ? round(($priorityScores[$pg] / $priorityWeights[$pg]) * 100.0, 1)
                : 0.0;
        }

        // % hoàn thành trung bình theo 4 Trụ cột BSC
        $bscAvg = [];
        foreach (['financial', 'customer', 'internal_process', 'learning_growth'] as $bp) {
            $bscAvg[$bp] = $bscWeights[$bp] > 0
                ? round(($bscScores[$bp] / $bscWeights[$bp]) * 100.0, 1)
                : 0.0;
        }

        return [
            'score'             => $finalCalculatedScore,
            'scores_json'       => $scoresJsonArray,
            'priority_avg'      => $priorityAvg,
            'bsc_avg'           => $bscAvg,
            'missing_items'     => $missingItems,
            'total_template_w'  => $totalTemplateWeight
        ];
    }

    /**
     * Tính điểm tổng kết: 30% Nhân viên + 70% Quản lý (hoặc cấu hình)
     */
    public static function calculateFinalScore(?float $selfScore, float $managerScore, ?array $rules = null): float {
        $rules = $rules ?? kpi_rules();
        $selfWeight = (float)($rules['self_weight'] ?? 0.30);
        $mgrWeight  = (float)($rules['manager_weight'] ?? 0.70);

        if ($selfScore !== null && $selfScore >= 0) {
            $final = ($selfWeight * $selfScore) + ($mgrWeight * $managerScore);
        } else {
            $final = $managerScore;
        }
        return round($final, 2);
    }

    /**
     * Xếp loại, gắn Rating Grade và Hệ số thưởng Total Rewards (Kèm Gate điểm liệt Nhóm A)
     */
    public static function classifyRating(float $finalScore, array $priorityAvg = [], ?array $rules = null): array {
        $rules = $rules ?? kpi_rules();
        $ratings = $rules['ratings'] ?? [];

        // 1. Tìm xếp loại thô theo điểm
        $selected = $ratings[count($ratings) - 1]; // Fallback là mức kém nhất
        foreach ($ratings as $r) {
            if ($finalScore >= (float)$r['min_score']) {
                $selected = $r;
                break;
            }
        }

        // 2. Kiểm tra điều kiện khống chế Nhóm A (Link Power Group A Gate)
        $gateApplied = false;
        $gateReason  = null;
        $groupAGate  = $rules['group_a_gate'] ?? null;

        if (!empty($groupAGate['enabled']) && isset($priorityAvg['A'])) {
            $minA = (float)$groupAGate['min_completion'];
            if ($priorityAvg['A'] < $minA) {
                // Nhóm A không đạt chuẩn -> Bị khống chế trần xếp loại
                $capKey = $groupAGate['max_rating'] ?? 'average';
                // Kiểm tra xem hạng hiện tại có cao hơn trần không
                $orderMap = ['outstanding' => 5, 'excellent' => 4, 'good' => 3, 'average' => 2, 'below_average' => 1, 'poor' => 0];
                $currOrder = $orderMap[$selected['key']] ?? 0;
                $capOrder  = $orderMap[$capKey] ?? 2;

                if ($currOrder > $capOrder) {
                    foreach ($ratings as $r) {
                        if ($r['key'] === $capKey) {
                            $selected = $r;
                            $gateApplied = true;
                            $gateReason = sprintf(
                                '%s (Đạt %.1f%% / yêu cầu ≥ %.0f%%)',
                                $groupAGate['reason_msg'] ?? 'Không đạt chuẩn KPI cốt lõi nhóm A',
                                $priorityAvg['A'],
                                $minA
                            );
                            break;
                        }
                    }
                }
            }
        }

        return [
            'rating'           => $selected['key'],
            'rating_grade'     => $selected['grade'],
            'label'            => $selected['label'],
            'multiplier'       => (float)$selected['multiplier'],
            'default_rec'      => $selected['default_rec'],
            'badge_class'      => $selected['badge_class'],
            'reward_desc'      => $selected['reward_desc'],
            'gate_applied'     => $gateApplied,
            'gate_reason'      => $gateReason
        ];
    }
}
