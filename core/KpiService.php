<?php
// core/KpiService.php - Dịch vụ bảo mật, phân quyền, trạng thái & workflow đánh giá KPI

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/kpi_rules.php';

final class KpiService {

    /**
     * Danh sách các hình thức đề xuất hợp lệ (Whitelist)
     */
    public static function allowedPromotionRecommendations(): array {
        return [
            'none'            => 'Không đề xuất',
            'bonus'           => 'Khen thưởng hiệu suất',
            'salary_increase' => 'Đề xuất tăng bậc lương',
            'promotion'       => 'Quy hoạch bổ nhiệm',
            'training'        => 'Đào tạo & Hướng dẫn lại',
            'warning'         => 'Nhắc nhở & Giám sát hiệu suất'
        ];
    }

    /**
     * Kiểm tra trạng thái khóa của phiếu đánh giá
     */
    public static function getLockStatus(array $eval): array {
        // 1. Kiểm tra kỳ đánh giá đã đóng chưa
        if (($eval['period_status'] ?? '') === 'closed') {
            return [
                'is_locked' => true,
                'reason'    => 'Kỳ đánh giá này đã được chốt và đóng lại. Hồ sơ chuyển sang chế độ lưu trữ chỉ xem.'
            ];
        }

        // 2. Kiểm tra phiếu đã hoàn tất hay chưa
        if (($eval['status'] ?? '') === 'completed') {
            return [
                'is_locked' => true,
                'reason'    => 'Phiếu đánh giá đã được quản lý phê duyệt và chốt kết quả xếp loại chính thức.'
            ];
        }

        return [
            'is_locked' => false,
            'reason'    => null
        ];
    }

    /**
     * Kiểm tra quyền thực hiện TỰ ĐÁNH GIÁ (Self Review)
     * Đảm bảo: Chỉ nhân viên sở hữu phiếu mới được tự chấm, khi kỳ đang mở và phiếu chưa bị chốt
     */
    public static function canSelfReview(array $eval, array $user): bool {
        // Kỳ phải đang active
        if (($eval['period_status'] ?? '') !== 'active') {
            return false;
        }

        // Phiếu phải đang ở trạng thái cho phép tự đánh giá
        if (!in_array($eval['status'] ?? '', ['pending', 'self_review'], true)) {
            return false;
        }

        // Super Admin luôn có quyền can thiệp
        if (is_superadmin()) {
            return true;
        }

        // Xác thực chính xác qua employee_user_id (User ID của nhân viên, không so nhầm với Employee ID)
        $empUserId = isset($eval['employee_user_id']) ? (int)$eval['employee_user_id'] : (int)($eval['emp_user_id'] ?? 0);
        $currUserId = (int)($user['id'] ?? 0);

        if ($currUserId > 0 && $currUserId === $empUserId && has_permission('evaluations', 'self_review')) {
            return true;
        }

        return false;
    }

    /**
     * Kiểm tra quyền thực hiện QUẢN LÝ CHẤM ĐIỂM (Manager Review)
     * Đảm bảo: Đúng người quản lý trực tiếp được chỉ định (hoặc cấp trên có quyền approve/Super Admin)
     */
    public static function canManagerReview(array $eval, array $user): bool {
        // Kỳ phải đang active
        if (($eval['period_status'] ?? '') !== 'active') {
            return false;
        }

        // Super Admin hoặc người có quyền Approve
        if (is_superadmin() || has_permission('evaluations', 'approve')) {
            return true;
        }

        // Nếu phiếu đã hoàn tất và không phải Super Admin/Approver thì không được sửa
        if (($eval['status'] ?? '') === 'completed') {
            return false;
        }

        // Xác thực đúng User ID của quản lý được phân công (Reviewer)
        $revUserId = isset($eval['reviewer_user_id']) ? (int)$eval['reviewer_user_id'] : (int)($eval['rev_user_id'] ?? 0);
        $currUserId = (int)($user['id'] ?? 0);

        if ($currUserId > 0 && $currUserId === $revUserId && has_permission('evaluations', 'manager_review')) {
            return true;
        }

        return false;
    }

    /**
     * Kiểm tra quyền xóa phiếu đánh giá
     */
    public static function canDelete(array $eval, array $user): bool {
        if (!has_permission('evaluations', 'delete')) {
            return false;
        }
        // Không cho phép xóa phiếu đã hoàn tất hoặc kỳ đã đóng trừ khi là Super Admin
        if (($eval['status'] ?? '') === 'completed' || ($eval['period_status'] ?? '') === 'closed') {
            return is_superadmin();
        }
        return true;
    }

    /**
     * Lấy danh sách mục tiêu cấp phòng ban theo kỳ đánh giá
     * @return array [department_id => target_data]
     */
    public static function getDepartmentTargets(PDO $pdo, int $periodId): array {
        if ($periodId <= 0) return [];

        $stmt = $pdo->prepare("
            SELECT dkt.*, d.name AS department_name, d.code AS department_code,
                   mgr.id AS manager_id, mgr.fullname AS manager_name, mgr.avatar AS manager_avatar,
                   creator.fullname AS assigned_by_name
            FROM department_kpi_targets dkt
            JOIN departments d ON dkt.department_id = d.id
            LEFT JOIN employees mgr ON d.manager_id = mgr.id
            LEFT JOIN users creator ON dkt.assigned_by = creator.id
            WHERE dkt.period_id = ?
        ");
        $stmt->execute([$periodId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $r) {
            $r['kpi_items'] = !empty($r['kpi_items_json']) ? json_decode($r['kpi_items_json'], true) : [];
            $result[(int)$r['department_id']] = $r;
        }
        return $result;
    }

    /**
     * Lấy mục tiêu của một phòng ban cụ thể trong kỳ
     */
    public static function getDepartmentTarget(PDO $pdo, int $periodId, int $departmentId): ?array {
        if ($periodId <= 0 || $departmentId <= 0) return null;

        $stmt = $pdo->prepare("
            SELECT dkt.*, d.name AS department_name, d.code AS department_code,
                   mgr.id AS manager_id, mgr.fullname AS manager_name
            FROM department_kpi_targets dkt
            JOIN departments d ON dkt.department_id = d.id
            LEFT JOIN employees mgr ON d.manager_id = mgr.id
            WHERE dkt.period_id = ? AND dkt.department_id = ?
        ");
        $stmt->execute([$periodId, $departmentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $row['kpi_items'] = !empty($row['kpi_items_json']) ? json_decode($row['kpi_items_json'], true) : [];
            return $row;
        }
        return null;
    }

    /**
     * Lấy ma trận tiến độ toàn diện của các phòng ban trong một kỳ đánh giá
     */
    public static function getDepartmentProgressMatrix(PDO $pdo, int $periodId): array {
        if ($periodId <= 0) return [];

        // 1. Lấy tất cả phòng ban kèm thông tin Trưởng phòng & số lượng nhân viên thực tế
        $deptStmt = $pdo->query("
            SELECT d.id, d.name, d.code, d.description, d.manager_id,
                   mgr.fullname AS manager_name, mgr.avatar AS manager_avatar, mgr.employee_code AS manager_code,
                   (SELECT COUNT(*) FROM employees WHERE department_id = d.id AND employment_status != 'resigned') AS total_employees
            FROM departments d
            LEFT JOIN employees mgr ON d.manager_id = mgr.id
            ORDER BY d.id ASC
        ");
        $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Lấy dữ liệu mục tiêu phòng ban đã giao
        $targets = self::getDepartmentTargets($pdo, $periodId);

        // 3. Thống kê phiếu đánh giá theo phòng ban trong kỳ
        $statStmt = $pdo->prepare("
            SELECT emp.department_id,
                   COUNT(e.id) AS total_evals,
                   COUNT(CASE WHEN e.status = 'completed' THEN 1 END) AS completed_count,
                   COUNT(CASE WHEN e.status = 'manager_review' THEN 1 END) AS mgr_review_count,
                   COUNT(CASE WHEN e.status = 'self_review' THEN 1 END) AS self_review_count,
                   COUNT(CASE WHEN e.status = 'pending' THEN 1 END) AS pending_count,
                   AVG(CASE WHEN e.status = 'completed' THEN e.final_score END) AS avg_score
            FROM evaluations e
            JOIN employees emp ON e.employee_id = emp.id
            WHERE e.period_id = ?
            GROUP BY emp.department_id
        ");
        $statStmt->execute([$periodId]);
        $statsByDept = [];
        foreach ($statStmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
            $statsByDept[(int)$s['department_id']] = $s;
        }

        $matrix = [];
        foreach ($departments as $d) {
            $dId = (int)$d['id'];
            $target = $targets[$dId] ?? null;
            $stat = $statsByDept[$dId] ?? [
                'total_evals' => 0,
                'completed_count' => 0,
                'mgr_review_count' => 0,
                'self_review_count' => 0,
                'pending_count' => 0,
                'avg_score' => null
            ];

            $totalEmployees = (int)$d['total_employees'];
            $totalEvals = (int)$stat['total_evals'];
            $completedCount = (int)$stat['completed_count'];

            // Tỷ lệ hoàn thành so với nhân sự thực tế
            $completionRate = $totalEmployees > 0 
                ? min(100, round(($completedCount / $totalEmployees) * 100))
                : ($totalEvals > 0 ? round(($completedCount / $totalEvals) * 100) : 0);

            $matrix[] = [
                'department_id'     => $dId,
                'department_name'   => $d['name'],
                'department_code'   => $d['code'],
                'manager_id'        => $d['manager_id'],
                'manager_name'      => $d['manager_name'] ?: 'Chưa chỉ định trưởng phòng',
                'manager_avatar'    => $d['manager_avatar'],
                'manager_code'      => $d['manager_code'],
                'total_employees'   => $totalEmployees,
                'has_target'        => ($target !== null),
                'target'            => $target,
                'total_evals'       => $totalEvals,
                'completed_count'   => $completedCount,
                'mgr_review_count'  => (int)$stat['mgr_review_count'],
                'self_review_count' => (int)$stat['self_review_count'],
                'pending_count'     => (int)$stat['pending_count'],
                'completion_rate'   => $completionRate,
                'avg_score'         => $stat['avg_score'] !== null ? round((float)$stat['avg_score'], 1) : null
            ];
        }

        return $matrix;
    }

    /**
     * Lưu hoặc cập nhật mục tiêu phòng ban theo đợt & Tự động phân bổ phiếu đánh giá
     */
    public static function saveDepartmentTarget(PDO $pdo, int $periodId, int $departmentId, array $data, int $userId, bool $autoCascade = false): array {
        $title = trim($data['target_title'] ?? '');
        $description = trim($data['target_description'] ?? '');
        $kpiItems = $data['kpi_items'] ?? [];

        if (empty($title)) {
            throw new Exception('Vui lòng nhập tiêu đề mục tiêu phòng ban.');
        }

        $itemsJson = json_encode($kpiItems, JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare("
            INSERT INTO department_kpi_targets (
                period_id, department_id, target_title, target_description, kpi_items_json, assigned_by, status
            ) VALUES (?, ?, ?, ?, ?, ?, 'assigned')
            ON DUPLICATE KEY UPDATE
                target_title = VALUES(target_title),
                target_description = VALUES(target_description),
                kpi_items_json = VALUES(kpi_items_json),
                status = VALUES(status),
                updated_at = NOW()
        ");
        $stmt->execute([$periodId, $departmentId, $title, $description, $itemsJson, $userId]);

        $createdCount = 0;
        $skippedCount = 0;

        // Nếu bật tự động phân bổ phiếu đánh giá cho nhân viên phòng ban
        if ($autoCascade) {
            $cascadeRes = self::cascadeEvaluationsToDepartment($pdo, $periodId, $departmentId);
            $createdCount = $cascadeRes['created'];
            $skippedCount = $cascadeRes['skipped'];
        }

        return [
            'success' => true,
            'created_evaluations' => $createdCount,
            'skipped_evaluations' => $skippedCount
        ];
    }

    /**
     * Tự động tạo phiếu đánh giá cho toàn bộ nhân viên trong phòng ban
     */
    public static function cascadeEvaluationsToDepartment(PDO $pdo, int $periodId, int $departmentId): array {
        // Lấy thông tin đợt
        $pStmt = $pdo->prepare("SELECT year, quarter FROM evaluation_periods WHERE id = ?");
        $pStmt->execute([$periodId]);
        $period = $pStmt->fetch();
        if (!$period) {
            throw new Exception("Kỳ đánh giá không tồn tại.");
        }

        // Lấy thông tin phòng ban & Trưởng phòng
        $deptStmt = $pdo->prepare("SELECT manager_id FROM departments WHERE id = ?");
        $deptStmt->execute([$departmentId]);
        $dept = $deptStmt->fetch();
        $managerId = (int)($dept['manager_id'] ?? 1);

        // Lấy danh sách nhân viên của phòng ban
        $empStmt = $pdo->prepare("
            SELECT id, employee_code, fullname, position_id
            FROM employees
            WHERE department_id = ? AND employment_status != 'resigned'
        ");
        $empStmt->execute([$departmentId]);
        $employees = $empStmt->fetchAll();

        // Nạp danh sách templates sẵn
        $templates = $pdo->query("SELECT id, department_id, position_id FROM kpi_templates WHERE status = 'active'")->fetchAll();

        $prefix = "DG-{$period['year']}" . ($period['quarter'] ? "-Q{$period['quarter']}" : "");
        $lastCode = $pdo->query("SELECT evaluation_code FROM evaluations WHERE evaluation_code LIKE '{$prefix}-%' ORDER BY id DESC LIMIT 1")->fetchColumn();
        $seq = 0;
        if ($lastCode && preg_match('/-(\d+)$/', $lastCode, $m)) {
            $seq = (int)$m[1];
        }

        $checkExist = $pdo->prepare("SELECT id FROM evaluations WHERE period_id = ? AND employee_id = ?");
        $insertStmt = $pdo->prepare("
            INSERT INTO evaluations (evaluation_code, period_id, employee_id, template_id, reviewer_id, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $created = 0;
        $skipped = 0;

        foreach ($employees as $emp) {
            $checkExist->execute([$periodId, $emp['id']]);
            if ($checkExist->fetch()) {
                $skipped++;
                continue;
            }

            // Tìm template: Ưu tiên theo position_id -> theo department_id -> template đầu tiên
            $tplId = null;
            foreach ($templates as $t) {
                if (!empty($t['position_id']) && $t['position_id'] == $emp['position_id']) {
                    $tplId = $t['id'];
                    break;
                }
            }
            if (!$tplId) {
                foreach ($templates as $t) {
                    if (!empty($t['department_id']) && $t['department_id'] == $departmentId) {
                        $tplId = $t['id'];
                        break;
                    }
                }
            }
            if (!$tplId && !empty($templates)) {
                $tplId = $templates[0]['id'];
            }
            if (!$tplId) continue;

            // Reviewer: Trưởng phòng ban. Nếu chính nhân viên này là trưởng phòng -> Giao cho Tổng Giám đốc (ID 1)
            $revId = ($managerId > 0 && $managerId != $emp['id']) ? $managerId : 1;
            if ($revId == $emp['id']) {
                $revId = 2; // fallback tránh trùng
            }

            $seq++;
            $evalCode = sprintf('%s-%03d', $prefix, $seq);

            $insertStmt->execute([
                $evalCode,
                $periodId,
                $emp['id'],
                $tplId,
                $revId
            ]);
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
