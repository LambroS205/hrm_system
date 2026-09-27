<?php
// modules/search/api.php - API Tìm kiếm toàn cục hiệu suất cao (Global Search API)
// Hỗ trợ: Debounce, Prepared Statements, Phân quyền, Pagination

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../../core/auth.php';

// Chỉ cho phép người đã đăng nhập
if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$keyword = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? 'all'); // all, employees, departments, branches, rewards, disciplines, transfers, recruitment
$branch_filter = (int)($_GET['branch_id'] ?? 0);
$dept_filter = (int)($_GET['department_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = min(50, max(5, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;
$mode = trim($_GET['mode'] ?? 'full'); // 'quick' cho dropdown, 'full' cho trang tìm kiếm

$results = [];
$total = 0;
$search_param = "%{$keyword}%";

try {
    // ====================================
    // 1. TÌM KIẾM NHÂN VIÊN
    // ====================================
    if (($category === 'all' || $category === 'employees') && has_permission('employees', 'view')) {
        $emp_wheres = ['1=1'];
        $emp_params = [];

        if (!empty($keyword)) {
            $emp_wheres[] = "(e.fullname LIKE ? OR e.employee_code LIKE ? OR e.email LIKE ? OR e.phone LIKE ? OR e.identity_card LIKE ?)";
            $emp_params = array_merge($emp_params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
        }
        if ($branch_filter > 0) {
            $emp_wheres[] = "e.branch_id = ?";
            $emp_params[] = $branch_filter;
        }
        if ($dept_filter > 0) {
            $emp_wheres[] = "e.department_id = ?";
            $emp_params[] = $dept_filter;
        }
        if (!empty($status_filter)) {
            $emp_wheres[] = "e.employment_status = ?";
            $emp_params[] = $status_filter;
        }
        if (!empty($date_from)) {
            $emp_wheres[] = "e.hire_date >= ?";
            $emp_params[] = $date_from;
        }
        if (!empty($date_to)) {
            $emp_wheres[] = "e.hire_date <= ?";
            $emp_params[] = $date_to;
        }

        $emp_where_sql = implode(' AND ', $emp_wheres);

        // Đếm tổng
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM employees e WHERE {$emp_where_sql}");
        $countStmt->execute($emp_params);
        $emp_total = (int)$countStmt->fetchColumn();

        // Lấy dữ liệu
        $emp_limit = ($mode === 'quick') ? 5 : $limit;
        $emp_offset = ($mode === 'quick') ? 0 : $offset;

        $empStmt = $pdo->prepare("
            SELECT e.id, e.employee_code, e.fullname, e.email, e.phone, e.avatar, 
                   e.employment_status, e.hire_date, e.gender,
                   b.name AS branch_name, b.code AS branch_code,
                   d.name AS department_name, d.code AS department_code,
                   p.name AS position_name
            FROM employees e
            LEFT JOIN branches b ON e.branch_id = b.id
            LEFT JOIN departments d ON e.department_id = d.id
            LEFT JOIN positions p ON e.position_id = p.id
            WHERE {$emp_where_sql}
            ORDER BY 
                CASE WHEN e.fullname LIKE ? THEN 0 
                     WHEN e.employee_code LIKE ? THEN 1 
                     ELSE 2 END,
                e.fullname ASC
            LIMIT {$emp_limit} OFFSET {$emp_offset}
        ");
        $relevance_params = array_merge($emp_params, [$search_param, $search_param]);
        $empStmt->execute($relevance_params);
        $emp_rows = $empStmt->fetchAll();

        $results['employees'] = [
            'items' => $emp_rows,
            'total' => $emp_total,
            'label' => 'Nhân Viên',
            'icon' => 'fa-solid fa-users',
            'color' => 'indigo'
        ];
        $total += $emp_total;
    }

    // ====================================
    // 2. TÌM KIẾM PHÒNG BAN
    // ====================================
    if (($category === 'all' || $category === 'departments') && has_permission('departments', 'view')) {
        $dept_wheres = ['1=1'];
        $dept_params = [];

        if (!empty($keyword)) {
            $dept_wheres[] = "(d.name LIKE ? OR d.code LIKE ? OR d.description LIKE ?)";
            $dept_params = array_merge($dept_params, [$search_param, $search_param, $search_param]);
        }
        if ($branch_filter > 0) {
            $dept_wheres[] = "d.branch_id = ?";
            $dept_params[] = $branch_filter;
        }

        $dept_where_sql = implode(' AND ', $dept_wheres);
        $dept_limit = ($mode === 'quick') ? 3 : $limit;
        $dept_offset = ($mode === 'quick') ? 0 : $offset;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM departments d WHERE {$dept_where_sql}");
        $countStmt->execute($dept_params);
        $dept_total = (int)$countStmt->fetchColumn();

        $deptStmt = $pdo->prepare("
            SELECT d.id, d.name, d.code, d.description,
                   b.name AS branch_name,
                   (SELECT COUNT(*) FROM employees e WHERE e.department_id = d.id AND e.employment_status != 'resigned') AS employee_count,
                   mgr.fullname AS manager_name
            FROM departments d
            LEFT JOIN branches b ON d.branch_id = b.id
            LEFT JOIN employees mgr ON d.manager_id = mgr.id
            WHERE {$dept_where_sql}
            ORDER BY d.name ASC
            LIMIT {$dept_limit} OFFSET {$dept_offset}
        ");
        $deptStmt->execute($dept_params);
        $dept_rows = $deptStmt->fetchAll();

        $results['departments'] = [
            'items' => $dept_rows,
            'total' => $dept_total,
            'label' => 'Phòng Ban',
            'icon' => 'fa-solid fa-building-user',
            'color' => 'sky'
        ];
        $total += $dept_total;
    }

    // ====================================
    // 3. TÌM KIẾM CHI NHÁNH
    // ====================================
    if (($category === 'all' || $category === 'branches') && has_permission('branches', 'view')) {
        $br_wheres = ['1=1'];
        $br_params = [];

        if (!empty($keyword)) {
            $br_wheres[] = "(b.name LIKE ? OR b.code LIKE ? OR b.address LIKE ? OR b.phone LIKE ?)";
            $br_params = array_merge($br_params, [$search_param, $search_param, $search_param, $search_param]);
        }

        $br_where_sql = implode(' AND ', $br_wheres);
        $br_limit = ($mode === 'quick') ? 3 : $limit;
        $br_offset = ($mode === 'quick') ? 0 : $offset;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM branches b WHERE {$br_where_sql}");
        $countStmt->execute($br_params);
        $br_total = (int)$countStmt->fetchColumn();

        $brStmt = $pdo->prepare("
            SELECT b.id, b.name, b.code, b.address, b.phone, b.email, b.is_headquarter, b.status,
                   (SELECT COUNT(*) FROM employees e WHERE e.branch_id = b.id AND e.employment_status != 'resigned') AS employee_count
            FROM branches b
            WHERE {$br_where_sql}
            ORDER BY b.is_headquarter DESC, b.name ASC
            LIMIT {$br_limit} OFFSET {$br_offset}
        ");
        $brStmt->execute($br_params);
        $br_rows = $brStmt->fetchAll();

        $results['branches'] = [
            'items' => $br_rows,
            'total' => $br_total,
            'label' => 'Chi Nhánh',
            'icon' => 'fa-solid fa-building-flag',
            'color' => 'violet'
        ];
        $total += $br_total;
    }

    // ====================================
    // 4. TÌM KIẾM KHEN THƯỞNG
    // ====================================
    if (($category === 'all' || $category === 'rewards') && has_permission('rewards', 'view')) {
        $rw_wheres = ['1=1'];
        $rw_params = [];

        if (!empty($keyword)) {
            $rw_wheres[] = "(r.title LIKE ? OR r.decision_number LIKE ? OR e.fullname LIKE ?)";
            $rw_params = array_merge($rw_params, [$search_param, $search_param, $search_param]);
        }
        if (!empty($date_from)) {
            $rw_wheres[] = "r.reward_date >= ?";
            $rw_params[] = $date_from;
        }
        if (!empty($date_to)) {
            $rw_wheres[] = "r.reward_date <= ?";
            $rw_params[] = $date_to;
        }

        $rw_where_sql = implode(' AND ', $rw_wheres);
        $rw_limit = ($mode === 'quick') ? 3 : $limit;
        $rw_offset = ($mode === 'quick') ? 0 : $offset;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM rewards r LEFT JOIN employees e ON r.employee_id = e.id WHERE {$rw_where_sql}");
        $countStmt->execute($rw_params);
        $rw_total = (int)$countStmt->fetchColumn();

        $rwStmt = $pdo->prepare("
            SELECT r.id, r.title, r.decision_number, r.reward_date AS decision_date, r.reward_type, r.amount, r.status,
                   e.fullname AS employee_name, e.employee_code
            FROM rewards r
            LEFT JOIN employees e ON r.employee_id = e.id
            WHERE {$rw_where_sql}
            ORDER BY r.reward_date DESC
            LIMIT {$rw_limit} OFFSET {$rw_offset}
        ");
        $rwStmt->execute($rw_params);
        $rw_rows = $rwStmt->fetchAll();

        $results['rewards'] = [
            'items' => $rw_rows,
            'total' => $rw_total,
            'label' => 'Khen Thưởng',
            'icon' => 'fa-solid fa-award',
            'color' => 'emerald'
        ];
        $total += $rw_total;
    }

    // ====================================
    // 5. TÌM KIẾM KỶ LUẬT
    // ====================================
    if (($category === 'all' || $category === 'disciplines') && has_permission('disciplines', 'view')) {
        $dc_wheres = ['1=1'];
        $dc_params = [];

        if (!empty($keyword)) {
            $dc_wheres[] = "(dc.title LIKE ? OR dc.decision_number LIKE ? OR e.fullname LIKE ?)";
            $dc_params = array_merge($dc_params, [$search_param, $search_param, $search_param]);
        }
        if (!empty($date_from)) {
            $dc_wheres[] = "dc.discipline_date >= ?";
            $dc_params[] = $date_from;
        }
        if (!empty($date_to)) {
            $dc_wheres[] = "dc.discipline_date <= ?";
            $dc_params[] = $date_to;
        }

        $dc_where_sql = implode(' AND ', $dc_wheres);
        $dc_limit = ($mode === 'quick') ? 3 : $limit;
        $dc_offset = ($mode === 'quick') ? 0 : $offset;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM disciplines dc LEFT JOIN employees e ON dc.employee_id = e.id WHERE {$dc_where_sql}");
        $countStmt->execute($dc_params);
        $dc_total = (int)$countStmt->fetchColumn();

        $dcStmt = $pdo->prepare("
            SELECT dc.id, dc.title, dc.decision_number, dc.discipline_date AS decision_date, dc.discipline_type, dc.status,
                   e.fullname AS employee_name, e.employee_code
            FROM disciplines dc
            LEFT JOIN employees e ON dc.employee_id = e.id
            WHERE {$dc_where_sql}
            ORDER BY dc.discipline_date DESC
            LIMIT {$dc_limit} OFFSET {$dc_offset}
        ");
        $dcStmt->execute($dc_params);
        $dc_rows = $dcStmt->fetchAll();

        $results['disciplines'] = [
            'items' => $dc_rows,
            'total' => $dc_total,
            'label' => 'Kỷ Luật',
            'icon' => 'fa-solid fa-scale-unbalanced',
            'color' => 'rose'
        ];
        $total += $dc_total;
    }

    // ====================================
    // 6. TÌM KIẾM THUYÊN CHUYỂN
    // ====================================
    if (($category === 'all' || $category === 'transfers') && has_permission('transfers', 'view')) {
        $tr_wheres = ['1=1'];
        $tr_params = [];

        if (!empty($keyword)) {
            $tr_wheres[] = "(t.reason LIKE ? OR t.decision_number LIKE ? OR e.fullname LIKE ?)";
            $tr_params = array_merge($tr_params, [$search_param, $search_param, $search_param]);
        }

        $tr_where_sql = implode(' AND ', $tr_wheres);
        $tr_limit = ($mode === 'quick') ? 3 : $limit;
        $tr_offset = ($mode === 'quick') ? 0 : $offset;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM transfers t LEFT JOIN employees e ON t.employee_id = e.id WHERE {$tr_where_sql}");
        $countStmt->execute($tr_params);
        $tr_total = (int)$countStmt->fetchColumn();

        $trStmt = $pdo->prepare("
            SELECT t.id, t.decision_number, t.reason, t.effective_date, t.status,
                   e.fullname AS employee_name, e.employee_code,
                   from_dept.name AS from_department, to_dept.name AS to_department,
                   from_br.name AS from_branch, to_br.name AS to_branch
            FROM transfers t
            LEFT JOIN employees e ON t.employee_id = e.id
            LEFT JOIN departments from_dept ON t.from_department_id = from_dept.id
            LEFT JOIN departments to_dept ON t.to_department_id = to_dept.id
            LEFT JOIN branches from_br ON t.from_branch_id = from_br.id
            LEFT JOIN branches to_br ON t.to_branch_id = to_br.id
            WHERE {$tr_where_sql}
            ORDER BY t.effective_date DESC
            LIMIT {$tr_limit} OFFSET {$tr_offset}
        ");
        $trStmt->execute($tr_params);
        $tr_rows = $trStmt->fetchAll();

        $results['transfers'] = [
            'items' => $tr_rows,
            'total' => $tr_total,
            'label' => 'Thuyên Chuyển',
            'icon' => 'fa-solid fa-people-arrows',
            'color' => 'amber'
        ];
        $total += $tr_total;
    }

    // ====================================
    // 7. TÌM KIẾM ỨNG VIÊN TUYỂN DỤNG
    // ====================================
    if (($category === 'all' || $category === 'recruitment') && has_permission('recruitment', 'view')) {
        $cd_wheres = ['1=1'];
        $cd_params = [];

        if (!empty($keyword)) {
            $cd_wheres[] = "(c.fullname LIKE ? OR c.candidate_code LIKE ? OR c.email LIKE ? OR c.phone LIKE ?)";
            $cd_params = array_merge($cd_params, [$search_param, $search_param, $search_param, $search_param]);
        }

        $cd_where_sql = implode(' AND ', $cd_wheres);
        $cd_limit = ($mode === 'quick') ? 3 : $limit;
        $cd_offset = ($mode === 'quick') ? 0 : $offset;

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM candidates c WHERE {$cd_where_sql}");
        $countStmt->execute($cd_params);
        $cd_total = (int)$countStmt->fetchColumn();

        $cdStmt = $pdo->prepare("
            SELECT c.id, c.candidate_code, c.fullname, c.email, c.phone, c.stage, c.created_at AS applied_date,
                   jp.title AS job_title
            FROM candidates c
            LEFT JOIN job_positions jp ON c.job_position_id = jp.id
            WHERE {$cd_where_sql}
            ORDER BY c.created_at DESC
            LIMIT {$cd_limit} OFFSET {$cd_offset}
        ");
        $cdStmt->execute($cd_params);
        $cd_rows = $cdStmt->fetchAll();

        $results['recruitment'] = [
            'items' => $cd_rows,
            'total' => $cd_total,
            'label' => 'Ứng Viên',
            'icon' => 'fa-solid fa-people-roof',
            'color' => 'pink'
        ];
        $total += $cd_total;
    }

    echo json_encode([
        'success' => true,
        'keyword' => $keyword,
        'category' => $category,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'results' => $results
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    log_system_error("Search API Error", $e);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Lỗi truy vấn cơ sở dữ liệu. Vui lòng thử lại.',
        'detail' => APP_DEBUG ? $e->getMessage() : null
    ], JSON_UNESCAPED_UNICODE);
}
