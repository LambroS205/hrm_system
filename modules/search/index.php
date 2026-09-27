<?php
// modules/search/index.php - Trang tìm kiếm toàn cục nâng cao (Global Search Hub)
$page_title = 'Tìm Kiếm Toàn Cục';

require_once __DIR__ . '/../../core/auth.php';
require_login();

// Lấy dữ liệu cho dropdown bộ lọc
$branches = $pdo->query("SELECT id, name, code FROM branches WHERE status = 'active' ORDER BY is_headquarter DESC, name ASC")->fetchAll();
$departments = $pdo->query("SELECT id, name, code, branch_id FROM departments ORDER BY name ASC")->fetchAll();

// Đọc tham số từ URL
$keyword = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? 'all');
$branch_filter = (int)($_GET['branch_id'] ?? 0);
$dept_filter = (int)($_GET['department_id'] ?? 0);
$status_filter = trim($_GET['status'] ?? '');
$date_from = trim($_GET['date_from'] ?? '');
$date_to = trim($_GET['date_to'] ?? '');

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="space-y-6">

    <!-- Header Section với Gradient Banner -->
    <div class="bg-gradient-to-r from-slate-800 via-slate-900 to-indigo-950 rounded-3xl p-6 sm:p-8 text-white shadow-xl relative overflow-hidden">
        <!-- Background Pattern -->
        <div class="absolute inset-0 opacity-10">
            <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg width=\"60\" height=\"60\" viewBox=\"0 0 60 60\" xmlns=\"http://www.w3.org/2000/svg\"><g fill=\"none\" fill-rule=\"evenodd\"><g fill=\"%23ffffff\" fill-opacity=\"0.15\"><path d=\"M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z\"/></g></g></svg>');"></div>
        </div>
        
        <div class="relative z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-white/15 backdrop-blur-md mb-4 text-indigo-200">
                <i class="fa-solid fa-magnifying-glass-plus text-amber-300"></i> Trung Tâm Tìm Kiếm Thông Minh
            </div>
            <h2 class="text-2xl sm:text-3xl font-bold tracking-tight mb-2">Tìm Kiếm Toàn Cục</h2>
            <p class="text-slate-300 text-sm max-w-2xl leading-relaxed">
                Tìm kiếm nhanh chóng xuyên suốt toàn bộ dữ liệu nhân sự — nhân viên, phòng ban, chi nhánh, khen thưởng, kỷ luật, thuyên chuyển và ứng viên tuyển dụng.
            </p>
        </div>
    </div>

    <!-- Thanh Tìm Kiếm Chính -->
    <div class="bg-white dark:bg-slate-800 rounded-3xl border-2 border-slate-300 dark:border-slate-700 shadow-md p-6 space-y-5">
        
        <!-- Search Input Lớn -->
        <div class="relative">
            <i class="fa-solid fa-magnifying-glass absolute left-5 top-1/2 -translate-y-1/2 text-indigo-600 dark:text-indigo-400 text-lg"></i>
            <input type="text" 
                   id="globalSearchInput" 
                   value="<?= e($keyword) ?>" 
                   placeholder="Nhập tên nhân viên, mã NV, phòng ban, chi nhánh, số quyết định..."
                   autocomplete="off"
                   class="w-full pl-14 pr-36 py-4 bg-slate-50 dark:bg-slate-900 border-2 border-slate-300 dark:border-slate-600 rounded-2xl text-base font-bold text-slate-900 dark:text-white placeholder-slate-500 dark:placeholder-slate-400 focus:outline-none focus:border-indigo-600 focus:ring-4 focus:ring-indigo-100 dark:focus:ring-indigo-950 focus:bg-white dark:focus:bg-slate-900 transition-all duration-200">
            <div class="absolute right-3 top-1/2 -translate-y-1/2 flex items-center gap-2">
                <span id="searchResultCount" class="hidden text-xs font-bold text-indigo-800 dark:text-indigo-200 bg-indigo-100 dark:bg-indigo-900/60 border border-indigo-300 dark:border-indigo-700 px-2.5 py-1 rounded-lg"></span>
                <button type="button" id="btnClearSearch" class="hidden w-8 h-8 rounded-lg bg-slate-200 dark:bg-slate-700 hover:bg-slate-300 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 flex items-center justify-center transition border border-slate-300 dark:border-slate-600" title="Xóa từ khóa">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
                <button type="button" id="btnToggleFilters" class="px-3.5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm shadow-indigo-600/25">
                    <i class="fa-solid fa-sliders text-[11px]"></i>
                    <span>Bộ Lọc</span>
                    <i class="fa-solid fa-chevron-down text-[9px] ml-0.5 transition-transform duration-200" id="filterChevron"></i>
                </button>
            </div>
        </div>

        <!-- Tabs Phân Loại Nhanh -->
        <div class="flex items-center gap-2 flex-wrap">
            <button data-category="all" class="search-tab active px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200">
                <i class="fa-solid fa-layer-group mr-1"></i> Tất Cả
            </button>
            <?php if (has_permission('employees', 'view')): ?>
            <button data-category="employees" class="search-tab px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200">
                <i class="fa-solid fa-users mr-1"></i> Nhân Viên
            </button>
            <?php endif; ?>
            <?php if (has_permission('departments', 'view')): ?>
            <button data-category="departments" class="search-tab px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200">
                <i class="fa-solid fa-building-user mr-1"></i> Phòng Ban
            </button>
            <?php endif; ?>
            <?php if (has_permission('branches', 'view')): ?>
            <button data-category="branches" class="search-tab px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200">
                <i class="fa-solid fa-building-flag mr-1"></i> Chi Nhánh
            </button>
            <?php endif; ?>
            <?php if (has_permission('rewards', 'view')): ?>
            <button data-category="rewards" class="search-tab px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200">
                <i class="fa-solid fa-award mr-1"></i> Khen Thưởng
            </button>
            <?php endif; ?>
            <?php if (has_permission('disciplines', 'view')): ?>
            <button data-category="disciplines" class="search-tab px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200">
                <i class="fa-solid fa-scale-unbalanced mr-1"></i> Kỷ Luật
            </button>
            <?php endif; ?>
            <?php if (has_permission('transfers', 'view')): ?>
            <button data-category="transfers" class="search-tab px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200">
                <i class="fa-solid fa-people-arrows mr-1"></i> Thuyên Chuyển
            </button>
            <?php endif; ?>
            <?php if (has_permission('recruitment', 'view')): ?>
            <button data-category="recruitment" class="search-tab px-4 py-2 rounded-xl text-xs font-bold transition-all duration-200">
                <i class="fa-solid fa-people-roof mr-1"></i> Ứng Viên
            </button>
            <?php endif; ?>
        </div>

        <!-- Panel Bộ Lọc Nâng Cao (Ẩn ban đầu) -->
        <div id="advancedFilters" class="hidden border-t-2 border-slate-200 dark:border-slate-700 pt-5 animate-fade-in">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <div>
                    <label class="block text-[11px] font-extrabold text-slate-600 dark:text-slate-300 uppercase tracking-widest mb-1.5">Chi Nhánh</label>
                    <select id="filterBranch" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-slate-900 transition">
                        <option value="">-- Tất cả chi nhánh --</option>
                        <?php foreach ($branches as $br): ?>
                            <option value="<?= $br['id'] ?>" <?= ($branch_filter == $br['id']) ? 'selected' : '' ?>>
                                <?= e($br['name']) ?> (<?= e($br['code']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-extrabold text-slate-600 dark:text-slate-300 uppercase tracking-widest mb-1.5">Phòng Ban</label>
                    <select id="filterDepartment" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-slate-900 transition">
                        <option value="">-- Tất cả phòng ban --</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= $dept['id'] ?>" data-branch="<?= $dept['branch_id'] ?? '' ?>" <?= ($dept_filter == $dept['id']) ? 'selected' : '' ?>>
                                <?= e($dept['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-extrabold text-slate-600 dark:text-slate-300 uppercase tracking-widest mb-1.5">Trạng Thái</label>
                    <select id="filterStatus" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-slate-900 transition">
                        <option value="">-- Tất cả trạng thái --</option>
                        <option value="official" <?= ($status_filter === 'official') ? 'selected' : '' ?>>Chính thức</option>
                        <option value="probation" <?= ($status_filter === 'probation') ? 'selected' : '' ?>>Thử việc</option>
                        <option value="resigned" <?= ($status_filter === 'resigned') ? 'selected' : '' ?>>Đã nghỉ việc</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-extrabold text-slate-600 dark:text-slate-300 uppercase tracking-widest mb-1.5">Từ Ngày</label>
                    <input type="date" id="filterDateFrom" value="<?= e($date_from) ?>"
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-slate-900 transition">
                </div>

                <div>
                    <label class="block text-[11px] font-extrabold text-slate-600 dark:text-slate-300 uppercase tracking-widest mb-1.5">Đến Ngày</label>
                    <input type="date" id="filterDateTo" value="<?= e($date_to) ?>"
                           class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:bg-white dark:focus:bg-slate-900 transition">
                </div>
            </div>
            <div class="flex items-center justify-end gap-2 mt-4">
                <button type="button" id="btnResetFilters" class="px-4 py-2 bg-slate-100 dark:bg-slate-700 hover:bg-slate-200 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-300 dark:border-slate-600 transition">
                    <i class="fa-solid fa-rotate-left mr-1"></i> Đặt Lại
                </button>
                <button type="button" id="btnApplyFilters" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white text-xs font-bold rounded-xl shadow-xs transition">
                    <i class="fa-solid fa-filter mr-1"></i> Áp Dụng Bộ Lọc
                </button>
            </div>
        </div>
    </div>

    <!-- Loading Skeleton -->
    <div id="searchLoading" class="hidden space-y-4">
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 p-6">
            <div class="animate-pulse space-y-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-slate-200 dark:bg-slate-700 rounded-xl"></div>
                    <div class="flex-1 space-y-2">
                        <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/3"></div>
                        <div class="h-3 bg-slate-100 dark:bg-slate-700/60 rounded w-1/4"></div>
                    </div>
                </div>
                <div class="space-y-3">
                    <div class="h-14 bg-slate-100 dark:bg-slate-700/50 rounded-xl"></div>
                    <div class="h-14 bg-slate-100 dark:bg-slate-700/50 rounded-xl"></div>
                    <div class="h-14 bg-slate-100 dark:bg-slate-700/50 rounded-xl"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Khu Vực Kết Quả -->
    <div id="searchResults" class="space-y-6">
        <!-- Placeholder ban đầu -->
        <div id="searchPlaceholder" class="bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700 p-12 text-center">
            <div class="w-20 h-20 rounded-3xl bg-gradient-to-tr from-indigo-100 to-violet-100 dark:from-indigo-950/60 dark:to-violet-950/60 text-indigo-500 dark:text-indigo-400 flex items-center justify-center mx-auto mb-5 text-3xl">
                <i class="fa-solid fa-magnifying-glass-chart"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-2">Bắt Đầu Tìm Kiếm</h3>
            <p class="text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
                Nhập từ khóa vào ô tìm kiếm phía trên để tra cứu nhanh xuyên suốt toàn bộ dữ liệu nhân sự trong hệ thống.
            </p>
            <div class="mt-6 flex items-center justify-center gap-3 flex-wrap text-[11px] text-slate-400 dark:text-slate-500 font-medium">
                <span class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-700/50 px-3 py-1.5 rounded-lg">
                    <kbd class="px-1.5 py-0.5 bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300 rounded text-[10px] font-bold">Ctrl</kbd>
                    <span>+</span>
                    <kbd class="px-1.5 py-0.5 bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300 rounded text-[10px] font-bold">K</kbd>
                    <span>Mở nhanh từ bất kỳ đâu</span>
                </span>
                <span class="flex items-center gap-1.5 bg-slate-50 dark:bg-slate-700/50 px-3 py-1.5 rounded-lg">
                    <kbd class="px-1.5 py-0.5 bg-slate-200 dark:bg-slate-600 text-slate-600 dark:text-slate-300 rounded text-[10px] font-bold">Enter</kbd>
                    <span>để tìm kiếm</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Empty State -->
    <div id="searchEmpty" class="hidden bg-white dark:bg-slate-800 rounded-3xl border border-slate-200/80 dark:border-slate-700 p-12 text-center">
        <div class="w-20 h-20 rounded-3xl bg-amber-50 dark:bg-amber-950/40 text-amber-500 dark:text-amber-400 flex items-center justify-center mx-auto mb-5 text-3xl">
            <i class="fa-regular fa-folder-open"></i>
        </div>
        <h3 class="text-lg font-bold text-slate-800 dark:text-white mb-2">Không Tìm Thấy Kết Quả</h3>
        <p id="emptyMessage" class="text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto leading-relaxed">
            Không có kết quả phù hợp với từ khóa tìm kiếm. Thử thay đổi từ khóa hoặc điều chỉnh bộ lọc.
        </p>
    </div>

    <!-- Pagination -->
    <div id="searchPagination" class="hidden flex items-center justify-between bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 p-4">
        <div id="paginationInfo" class="text-xs text-slate-500 dark:text-slate-400 font-medium"></div>
        <div id="paginationButtons" class="flex items-center gap-1"></div>
    </div>
</div>

<script>
(function() {
    'use strict';

    const BASE_URL = '<?= base_url("") ?>';
    const API_URL = '<?= base_url("modules/search/api.php") ?>';

    // DOM Elements
    const searchInput = document.getElementById('globalSearchInput');
    const searchResults = document.getElementById('searchResults');
    const searchPlaceholder = document.getElementById('searchPlaceholder');
    const searchEmpty = document.getElementById('searchEmpty');
    const searchLoading = document.getElementById('searchLoading');
    const searchPagination = document.getElementById('searchPagination');
    const resultCountBadge = document.getElementById('searchResultCount');
    const btnClear = document.getElementById('btnClearSearch');
    const btnToggleFilters = document.getElementById('btnToggleFilters');
    const filterPanel = document.getElementById('advancedFilters');
    const filterChevron = document.getElementById('filterChevron');

    // State
    let currentCategory = '<?= e($category) ?>';
    let currentPage = 1;
    let debounceTimer = null;
    let abortController = null;

    // ====================================
    // SEARCH TABS
    // ====================================
    document.querySelectorAll('.search-tab').forEach(tab => {
        tab.addEventListener('click', function() {
            document.querySelectorAll('.search-tab').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            currentCategory = this.dataset.category;
            currentPage = 1;
            performSearch();
        });
        // Set active from URL
        if (tab.dataset.category === currentCategory) {
            document.querySelectorAll('.search-tab').forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
        }
    });

    // ====================================
    // FILTER TOGGLE
    // ====================================
    btnToggleFilters.addEventListener('click', () => {
        filterPanel.classList.toggle('hidden');
        filterChevron.classList.toggle('rotate-180');
    });

    // Department cascade filter by branch
    document.getElementById('filterBranch').addEventListener('change', function() {
        const branchId = this.value;
        const deptSelect = document.getElementById('filterDepartment');
        Array.from(deptSelect.options).forEach(opt => {
            if (!opt.value) return;
            const optBranch = opt.dataset.branch;
            opt.style.display = (!branchId || optBranch === branchId) ? '' : 'none';
        });
        if (branchId && deptSelect.selectedOptions[0]?.dataset.branch !== branchId) {
            deptSelect.value = '';
        }
    });

    document.getElementById('btnResetFilters').addEventListener('click', () => {
        document.getElementById('filterBranch').value = '';
        document.getElementById('filterDepartment').value = '';
        document.getElementById('filterStatus').value = '';
        document.getElementById('filterDateFrom').value = '';
        document.getElementById('filterDateTo').value = '';
        Array.from(document.getElementById('filterDepartment').options).forEach(opt => opt.style.display = '');
        currentPage = 1;
        performSearch();
    });

    document.getElementById('btnApplyFilters').addEventListener('click', () => {
        currentPage = 1;
        performSearch();
    });

    // ====================================
    // SEARCH INPUT + DEBOUNCE
    // ====================================
    searchInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const val = this.value.trim();
        btnClear.classList.toggle('hidden', !val);
        debounceTimer = setTimeout(() => {
            currentPage = 1;
            performSearch();
        }, 350); // 350ms debounce for high performance
    });

    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            clearTimeout(debounceTimer);
            currentPage = 1;
            performSearch();
        }
        if (e.key === 'Escape') {
            this.blur();
        }
    });

    btnClear.addEventListener('click', () => {
        searchInput.value = '';
        btnClear.classList.add('hidden');
        resultCountBadge.classList.add('hidden');
        showPlaceholder();
    });

    // ====================================
    // CORE SEARCH FUNCTION
    // ====================================
    async function performSearch() {
        const keyword = searchInput.value.trim();
        
        if (!keyword && !getActiveFilters()) {
            showPlaceholder();
            return;
        }

        // Cancel previous request
        if (abortController) abortController.abort();
        abortController = new AbortController();

        showLoading();

        const params = new URLSearchParams({
            q: keyword,
            category: currentCategory,
            mode: 'full',
            page: currentPage,
            limit: 10,
            branch_id: document.getElementById('filterBranch').value,
            department_id: document.getElementById('filterDepartment').value,
            status: document.getElementById('filterStatus').value,
            date_from: document.getElementById('filterDateFrom').value,
            date_to: document.getElementById('filterDateTo').value
        });

        try {
            const response = await fetch(`${API_URL}?${params}`, {
                signal: abortController.signal
            });
            const data = await response.json();

            if (data.success) {
                renderResults(data);
            } else {
                showEmpty(data.error || 'Có lỗi xảy ra khi tìm kiếm.');
            }
        } catch (err) {
            if (err.name !== 'AbortError') {
                showEmpty('Lỗi kết nối. Vui lòng thử lại.');
            }
        }
    }

    function getActiveFilters() {
        return document.getElementById('filterBranch').value ||
               document.getElementById('filterDepartment').value ||
               document.getElementById('filterStatus').value ||
               document.getElementById('filterDateFrom').value ||
               document.getElementById('filterDateTo').value;
    }

    // ====================================
    // RENDER RESULTS
    // ====================================
    function renderResults(data) {
        searchLoading.classList.add('hidden');
        searchPlaceholder.classList.add('hidden');
        
        if (data.total === 0) {
            showEmpty(`Không tìm thấy kết quả cho "${escapeHtml(data.keyword)}". Thử thay đổi từ khóa hoặc bộ lọc.`);
            return;
        }

        searchEmpty.classList.add('hidden');
        
        // Update counter
        resultCountBadge.textContent = `${data.total} kết quả`;
        resultCountBadge.classList.remove('hidden');

        let html = '';
        const keyword = data.keyword;
        
        for (const [catKey, catData] of Object.entries(data.results)) {
            if (!catData.items || catData.items.length === 0) continue;

            const colorMap = {
                indigo: { bg: 'bg-indigo-50 dark:bg-indigo-950/60', text: 'text-indigo-600 dark:text-indigo-400', border: 'border-indigo-200 dark:border-indigo-800' },
                sky: { bg: 'bg-sky-50 dark:bg-sky-950/60', text: 'text-sky-600 dark:text-sky-400', border: 'border-sky-200 dark:border-sky-800' },
                violet: { bg: 'bg-violet-50 dark:bg-violet-950/60', text: 'text-violet-600 dark:text-violet-400', border: 'border-violet-200 dark:border-violet-800' },
                emerald: { bg: 'bg-emerald-50 dark:bg-emerald-950/60', text: 'text-emerald-600 dark:text-emerald-400', border: 'border-emerald-200 dark:border-emerald-800' },
                rose: { bg: 'bg-rose-50 dark:bg-rose-950/60', text: 'text-rose-600 dark:text-rose-400', border: 'border-rose-200 dark:border-rose-800' },
                amber: { bg: 'bg-amber-50 dark:bg-amber-950/60', text: 'text-amber-600 dark:text-amber-400', border: 'border-amber-200 dark:border-amber-800' },
                pink: { bg: 'bg-pink-50 dark:bg-pink-950/60', text: 'text-pink-600 dark:text-pink-400', border: 'border-pink-200 dark:border-pink-800' }
            };
            const colors = colorMap[catData.color] || colorMap.indigo;

            html += `
                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200/80 dark:border-slate-700 shadow-sm overflow-hidden animate-fade-in">
                    <div class="px-6 py-4 border-b border-slate-100 dark:border-slate-700 flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-xl ${colors.bg} ${colors.text} flex items-center justify-center text-sm">
                                <i class="${catData.icon}"></i>
                            </div>
                            <div>
                                <h3 class="font-bold text-slate-800 dark:text-white text-sm">${escapeHtml(catData.label)}</h3>
                                <p class="text-[11px] text-slate-400 dark:text-slate-500">${catData.total} kết quả tìm thấy</p>
                            </div>
                        </div>
                        ${catData.total > catData.items.length ? `
                            <button onclick="filterByCategory('${catKey}')" class="text-xs font-semibold ${colors.text} hover:underline flex items-center gap-1 transition">
                                Xem tất cả ${catData.total}
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </button>
                        ` : ''}
                    </div>
                    <div class="divide-y divide-slate-50 dark:divide-slate-700/60">
            `;

            catData.items.forEach(item => {
                html += renderItem(catKey, item, keyword, colors);
            });

            html += `</div></div>`;
        }

        searchResults.innerHTML = html;

        // Pagination
        if (currentCategory !== 'all') {
            const totalForCategory = data.results[currentCategory]?.total || 0;
            renderPagination(totalForCategory, data.page, data.limit);
        } else {
            searchPagination.classList.add('hidden');
        }
    }

    // ====================================
    // RENDER INDIVIDUAL ITEMS
    // ====================================
    function renderItem(catKey, item, keyword, colors) {
        switch (catKey) {
            case 'employees': return renderEmployee(item, keyword);
            case 'departments': return renderDepartment(item, keyword);
            case 'branches': return renderBranch(item, keyword);
            case 'rewards': return renderReward(item, keyword);
            case 'disciplines': return renderDiscipline(item, keyword);
            case 'transfers': return renderTransfer(item, keyword);
            case 'recruitment': return renderCandidate(item, keyword);
            default: return '';
        }
    }

    function renderEmployee(emp, kw) {
        const statusMap = {
            'official': { label: 'Chính thức', cls: 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800' },
            'probation': { label: 'Thử việc', cls: 'bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800' },
            'resigned': { label: 'Đã nghỉ', cls: 'bg-rose-50 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border-rose-200 dark:border-rose-800' }
        };
        const st = statusMap[emp.employment_status] || statusMap.official;
        const initial = (emp.fullname || 'U').charAt(0).toUpperCase();

        return `
            <a href="${BASE_URL}modules/employees/view.php?id=${emp.id}" class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition group">
                ${emp.avatar ? 
                    `<img src="${BASE_URL}assets/uploads/${escapeHtml(emp.avatar)}" class="w-11 h-11 rounded-xl object-cover border border-slate-200 dark:border-slate-700 shadow-xs flex-shrink-0" alt="">` :
                    `<div class="w-11 h-11 rounded-xl bg-gradient-to-tr from-indigo-600 to-violet-500 text-white flex items-center justify-center font-bold text-sm shadow-xs flex-shrink-0">${initial}</div>`
                }
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm text-slate-800 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition truncate">${highlight(emp.fullname, kw)}</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold border ${st.cls}">${st.label}</span>
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-3 flex-wrap">
                        <span class="font-mono"><i class="fa-solid fa-id-badge mr-1 text-indigo-400"></i>${highlight(emp.employee_code, kw)}</span>
                        ${emp.department_name ? `<span><i class="fa-solid fa-building-user mr-1 text-sky-400"></i>${escapeHtml(emp.department_name)}</span>` : ''}
                        ${emp.position_name ? `<span><i class="fa-solid fa-tag mr-1 text-violet-400"></i>${escapeHtml(emp.position_name)}</span>` : ''}
                        ${emp.branch_name ? `<span><i class="fa-solid fa-building-flag mr-1 text-amber-400"></i>${escapeHtml(emp.branch_name)}</span>` : ''}
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-slate-300 dark:text-slate-600 group-hover:text-indigo-500 transition flex-shrink-0"></i>
            </a>
        `;
    }

    function renderDepartment(dept, kw) {
        return `
            <a href="${BASE_URL}modules/departments/index.php" class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition group">
                <div class="w-11 h-11 rounded-xl bg-sky-50 dark:bg-sky-950/60 text-sky-600 dark:text-sky-400 flex items-center justify-center text-base flex-shrink-0">
                    <i class="fa-solid fa-building-user"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-slate-800 dark:text-white group-hover:text-sky-600 dark:group-hover:text-sky-400 transition truncate">
                        ${highlight(dept.name, kw)}
                        <span class="ml-1.5 text-[10px] font-mono text-slate-400 dark:text-slate-500">${highlight(dept.code, kw)}</span>
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-3">
                        <span><i class="fa-solid fa-users mr-1 text-indigo-400"></i>${dept.employee_count || 0} nhân viên</span>
                        ${dept.branch_name ? `<span><i class="fa-solid fa-building-flag mr-1 text-amber-400"></i>${escapeHtml(dept.branch_name)}</span>` : ''}
                        ${dept.manager_name ? `<span><i class="fa-solid fa-user-tie mr-1 text-emerald-400"></i>${escapeHtml(dept.manager_name)}</span>` : ''}
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-slate-300 dark:text-slate-600 group-hover:text-sky-500 transition flex-shrink-0"></i>
            </a>
        `;
    }

    function renderBranch(br, kw) {
        return `
            <a href="${BASE_URL}modules/branches/index.php" class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition group">
                <div class="w-11 h-11 rounded-xl bg-violet-50 dark:bg-violet-950/60 text-violet-600 dark:text-violet-400 flex items-center justify-center text-base flex-shrink-0">
                    <i class="fa-solid fa-building-flag"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-bold text-sm text-slate-800 dark:text-white group-hover:text-violet-600 dark:group-hover:text-violet-400 transition truncate">${highlight(br.name, kw)}</span>
                        ${br.is_headquarter == 1 ? '<span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300">HQ</span>' : ''}
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-3 flex-wrap">
                        <span class="font-mono">${highlight(br.code, kw)}</span>
                        <span><i class="fa-solid fa-users mr-1 text-indigo-400"></i>${br.employee_count || 0} nhân viên</span>
                        ${br.phone ? `<span><i class="fa-solid fa-phone mr-1 text-emerald-400"></i>${escapeHtml(br.phone)}</span>` : ''}
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-slate-300 dark:text-slate-600 group-hover:text-violet-500 transition flex-shrink-0"></i>
            </a>
        `;
    }

    function renderReward(rw, kw) {
        const stMap = { 'approved': 'text-emerald-600', 'pending': 'text-amber-600', 'rejected': 'text-rose-600' };
        return `
            <a href="${BASE_URL}modules/rewards/index.php" class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition group">
                <div class="w-11 h-11 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-base flex-shrink-0">
                    <i class="fa-solid fa-award"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-slate-800 dark:text-white group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition truncate">${highlight(rw.title || '', kw)}</div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-3 flex-wrap">
                        ${rw.employee_name ? `<span><i class="fa-solid fa-user mr-1 text-indigo-400"></i>${highlight(rw.employee_name, kw)}</span>` : ''}
                        ${rw.decision_number ? `<span class="font-mono">${highlight(rw.decision_number, kw)}</span>` : ''}
                        ${rw.decision_date ? `<span><i class="fa-regular fa-calendar mr-1"></i>${rw.decision_date}</span>` : ''}
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-slate-300 dark:text-slate-600 group-hover:text-emerald-500 transition flex-shrink-0"></i>
            </a>
        `;
    }

    function renderDiscipline(dc, kw) {
        return `
            <a href="${BASE_URL}modules/disciplines/index.php" class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition group">
                <div class="w-11 h-11 rounded-xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center text-base flex-shrink-0">
                    <i class="fa-solid fa-scale-unbalanced"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-slate-800 dark:text-white group-hover:text-rose-600 dark:group-hover:text-rose-400 transition truncate">${highlight(dc.title || '', kw)}</div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-3 flex-wrap">
                        ${dc.employee_name ? `<span><i class="fa-solid fa-user mr-1 text-indigo-400"></i>${highlight(dc.employee_name, kw)}</span>` : ''}
                        ${dc.decision_number ? `<span class="font-mono">${highlight(dc.decision_number, kw)}</span>` : ''}
                        ${dc.decision_date ? `<span><i class="fa-regular fa-calendar mr-1"></i>${dc.decision_date}</span>` : ''}
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-slate-300 dark:text-slate-600 group-hover:text-rose-500 transition flex-shrink-0"></i>
            </a>
        `;
    }

    function renderTransfer(tr, kw) {
        return `
            <a href="${BASE_URL}modules/transfers/index.php" class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition group">
                <div class="w-11 h-11 rounded-xl bg-amber-50 dark:bg-amber-950/60 text-amber-600 dark:text-amber-400 flex items-center justify-center text-base flex-shrink-0">
                    <i class="fa-solid fa-people-arrows"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-slate-800 dark:text-white group-hover:text-amber-600 dark:group-hover:text-amber-400 transition truncate">
                        ${tr.employee_name ? highlight(tr.employee_name, kw) : 'Thuyên chuyển'}
                    </div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-3 flex-wrap">
                        ${tr.from_department ? `<span>${escapeHtml(tr.from_department)}</span>` : ''}
                        ${tr.from_department && tr.to_department ? '<i class="fa-solid fa-arrow-right text-[9px] text-slate-300"></i>' : ''}
                        ${tr.to_department ? `<span class="font-semibold text-slate-600 dark:text-slate-300">${escapeHtml(tr.to_department)}</span>` : ''}
                        ${tr.effective_date ? `<span><i class="fa-regular fa-calendar mr-1"></i>${tr.effective_date}</span>` : ''}
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-slate-300 dark:text-slate-600 group-hover:text-amber-500 transition flex-shrink-0"></i>
            </a>
        `;
    }

    function renderCandidate(cd, kw) {
        return `
            <a href="${BASE_URL}modules/recruitment/candidate_view.php?id=${cd.id}" class="flex items-center gap-4 px-6 py-4 hover:bg-slate-50/70 dark:hover:bg-slate-700/30 transition group">
                <div class="w-11 h-11 rounded-xl bg-pink-50 dark:bg-pink-950/60 text-pink-600 dark:text-pink-400 flex items-center justify-center text-base flex-shrink-0">
                    <i class="fa-solid fa-people-roof"></i>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm text-slate-800 dark:text-white group-hover:text-pink-600 dark:group-hover:text-pink-400 transition truncate">${highlight(cd.fullname || '', kw)}</div>
                    <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex items-center gap-3 flex-wrap">
                        ${cd.job_title ? `<span><i class="fa-solid fa-briefcase mr-1 text-violet-400"></i>${escapeHtml(cd.job_title)}</span>` : ''}
                        ${cd.email ? `<span><i class="fa-solid fa-envelope mr-1 text-sky-400"></i>${highlight(cd.email, kw)}</span>` : ''}
                        ${cd.stage ? `<span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300">${escapeHtml(cd.stage)}</span>` : ''}
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-xs text-slate-300 dark:text-slate-600 group-hover:text-pink-500 transition flex-shrink-0"></i>
            </a>
        `;
    }

    // ====================================
    // PAGINATION
    // ====================================
    function renderPagination(total, page, limit) {
        const totalPages = Math.ceil(total / limit);
        if (totalPages <= 1) {
            searchPagination.classList.add('hidden');
            return;
        }

        searchPagination.classList.remove('hidden');
        document.getElementById('paginationInfo').textContent = `Trang ${page}/${totalPages} • ${total} kết quả`;

        let btns = '';
        for (let i = 1; i <= Math.min(totalPages, 10); i++) {
            const active = i === page;
            btns += `<button onclick="goToPage(${i})" class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-semibold transition ${active ? 'bg-indigo-600 text-white shadow-xs' : 'bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-600'}">${i}</button>`;
        }
        document.getElementById('paginationButtons').innerHTML = btns;
    }

    window.goToPage = function(p) {
        currentPage = p;
        performSearch();
        searchResults.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.filterByCategory = function(cat) {
        currentCategory = cat;
        currentPage = 1;
        document.querySelectorAll('.search-tab').forEach(t => {
            t.classList.toggle('active', t.dataset.category === cat);
        });
        performSearch();
    };

    // ====================================
    // UTILITY FUNCTIONS
    // ====================================
    function highlight(text, keyword) {
        if (!text || !keyword) return escapeHtml(text || '');
        const escaped = escapeHtml(text);
        const kwEscaped = escapeHtml(keyword);
        const regex = new RegExp(`(${kwEscaped.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
        return escaped.replace(regex, '<mark class="bg-amber-200/70 dark:bg-amber-600/40 text-inherit rounded px-0.5">$1</mark>');
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function showPlaceholder() {
        searchLoading.classList.add('hidden');
        searchEmpty.classList.add('hidden');
        searchPagination.classList.add('hidden');
        resultCountBadge.classList.add('hidden');
        searchResults.innerHTML = '';
        searchResults.appendChild(searchPlaceholder);
        searchPlaceholder.classList.remove('hidden');
    }

    function showLoading() {
        searchPlaceholder.classList.add('hidden');
        searchEmpty.classList.add('hidden');
        searchPagination.classList.add('hidden');
        searchResults.innerHTML = '';
        searchLoading.classList.remove('hidden');
    }

    function showEmpty(msg) {
        searchLoading.classList.add('hidden');
        searchPlaceholder.classList.add('hidden');
        searchPagination.classList.add('hidden');
        searchResults.innerHTML = '';
        resultCountBadge.textContent = '0 kết quả';
        resultCountBadge.classList.remove('hidden');
        document.getElementById('emptyMessage').textContent = msg || 'Không tìm thấy kết quả phù hợp.';
        searchEmpty.classList.remove('hidden');
    }

    // ====================================
    // AUTO-SEARCH ON LOAD (if keyword from URL)
    // ====================================
    if (searchInput.value.trim()) {
        btnClear.classList.remove('hidden');
        performSearch();
    }

    // Focus search input
    searchInput.focus();
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
