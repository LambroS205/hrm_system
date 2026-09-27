<?php
// includes/footer.php - Đóng khung giao diện, Modal xác nhận toàn cục và nạp Javascript
?>
    </main>
</div>

<!-- ==================================================== -->
<!-- GLOBAL CONFIRM MODAL (Thay thế toàn bộ confirm browser) -->
<!-- ==================================================== -->
<div id="globalConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden animate-fade-in">
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-md w-full p-6 border-2 border-slate-200 dark:border-slate-700 animate-scale-up">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0 text-xl shadow-xs">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="flex-1">
                <h3 id="confirmModalTitle" class="text-base font-extrabold text-slate-900 dark:text-white">
                    Xác Nhận Thao Tác
                </h3>
                <p id="confirmModalMessage" class="text-sm font-medium text-slate-600 dark:text-slate-300 mt-1.5 leading-relaxed">
                    Bạn có chắc chắn muốn thực hiện hành động này không?
                </p>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t-2 border-slate-200 dark:border-slate-700">
            <button type="button" onclick="closeConfirmModal()" class="px-4 py-2.5 rounded-xl border-2 border-slate-300 dark:border-slate-600 text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm font-bold transition">
                Hủy Bỏ
            </button>
            <button type="button" id="confirmModalSubmitBtn" onclick="triggerConfirmModalAction()" class="px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white text-sm font-bold transition shadow-sm">
                Đồng Ý
            </button>
        </div>
    </div>
</div>

<!-- ==================================================== -->
<!-- COMMAND PALETTE: Tìm Kiếm Toàn Cục Nhanh (Ctrl+K)  -->
<!-- ==================================================== -->
<div id="searchCommandPalette" class="fixed inset-0 z-[60] flex items-start justify-center p-4 pt-[12vh] bg-slate-900/60 backdrop-blur-sm hidden" style="animation: none;">
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-2xl w-full border-2 border-slate-300 dark:border-slate-700 animate-scale-up overflow-hidden" style="max-height: 72vh;">
        <!-- Input Tìm kiếm -->
        <div class="flex items-center gap-3 px-5 py-4 border-b-2 border-slate-200 dark:border-slate-700">
            <i class="fa-solid fa-magnifying-glass text-indigo-600 dark:text-indigo-400 text-lg flex-shrink-0"></i>
            <input type="text" id="cmdPaletteInput" 
                   placeholder="Tìm nhân viên, phòng ban, chi nhánh..."
                   autocomplete="off"
                   class="w-full bg-transparent text-sm font-bold text-slate-900 dark:text-white placeholder-slate-500 dark:placeholder-slate-400 focus:outline-none">
            <kbd class="px-2 py-0.5 bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-200 rounded text-[10px] font-extrabold flex-shrink-0 border border-slate-300 dark:border-slate-500 shadow-2xs">ESC</kbd>
        </div>

        <!-- Loading -->
        <div id="cmdPaletteLoading" class="hidden px-5 py-6">
            <div class="flex items-center gap-3 text-sm font-semibold text-slate-600 dark:text-slate-300">
                <i class="fa-solid fa-circle-notch fa-spin text-indigo-600 dark:text-indigo-400 text-base"></i>
                <span>Đang tìm kiếm...</span>
            </div>
        </div>

        <!-- Kết quả -->
        <div id="cmdPaletteResults" class="overflow-y-auto" style="max-height: calc(72vh - 64px);">
            <!-- Quick Links mặc định khi chưa nhập gì -->
            <div id="cmdQuickLinks" class="py-3">
                <div class="px-5 py-1.5 text-[11px] font-extrabold text-slate-500 dark:text-slate-400 uppercase tracking-widest">Truy Cập Nhanh</div>
                <a href="<?= base_url('modules/search/index.php') ?>" class="cmd-result flex items-center gap-3 px-5 py-2.5 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition text-sm">
                    <div class="w-8 h-8 rounded-lg bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 flex items-center justify-center text-xs shadow-xs"><i class="fa-solid fa-magnifying-glass-chart"></i></div>
                    <div class="flex-1"><span class="font-bold text-slate-900 dark:text-white">Tìm Kiếm Nâng Cao</span><span class="text-slate-500 dark:text-slate-400 text-xs ml-2 font-medium">Trang tìm kiếm đầy đủ với bộ lọc</span></div>
                    <i class="fa-solid fa-arrow-right text-[11px] text-slate-400 dark:text-slate-500"></i>
                </a>
                <a href="<?= base_url('modules/employees/index.php') ?>" class="cmd-result flex items-center gap-3 px-5 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition text-sm">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center text-xs shadow-xs"><i class="fa-solid fa-users"></i></div>
                    <span class="font-bold text-slate-800 dark:text-slate-200">Danh sách Nhân Viên</span>
                    <i class="fa-solid fa-arrow-right text-[11px] text-slate-400 dark:text-slate-500 ml-auto"></i>
                </a>
                <a href="<?= base_url('modules/departments/index.php') ?>" class="cmd-result flex items-center gap-3 px-5 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition text-sm">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center text-xs shadow-xs"><i class="fa-solid fa-building-user"></i></div>
                    <span class="font-bold text-slate-800 dark:text-slate-200">Phòng Ban & Chức Vụ</span>
                    <i class="fa-solid fa-arrow-right text-[11px] text-slate-400 dark:text-slate-500 ml-auto"></i>
                </a>
                <a href="<?= base_url('modules/attendance/index.php') ?>" class="cmd-result flex items-center gap-3 px-5 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition text-sm">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center text-xs shadow-xs"><i class="fa-solid fa-calendar-check"></i></div>
                    <span class="font-bold text-slate-800 dark:text-slate-200">Bảng Chấm Công</span>
                    <i class="fa-solid fa-arrow-right text-[11px] text-slate-400 dark:text-slate-500 ml-auto"></i>
                </a>
                <a href="<?= base_url('modules/payroll/index.php') ?>" class="cmd-result flex items-center gap-3 px-5 py-2.5 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition text-sm">
                    <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 flex items-center justify-center text-xs shadow-xs"><i class="fa-solid fa-money-bill-wave"></i></div>
                    <span class="font-bold text-slate-800 dark:text-slate-200">Bảng Lương</span>
                    <i class="fa-solid fa-arrow-right text-[11px] text-slate-400 dark:text-slate-500 ml-auto"></i>
                </a>
            </div>
        </div>

        <!-- Footer của modal -->
        <div class="px-5 py-3 border-t-2 border-slate-200 dark:border-slate-700 flex items-center justify-between text-[11px] text-slate-600 dark:text-slate-300 font-semibold bg-slate-50 dark:bg-slate-800/80">
            <div class="flex items-center gap-3">
                <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 rounded text-[10px] font-extrabold border border-slate-300 dark:border-slate-500">↑↓</kbd> Điều hướng</span>
                <span class="flex items-center gap-1"><kbd class="px-1.5 py-0.5 bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200 rounded text-[10px] font-extrabold border border-slate-300 dark:border-slate-500">↵</kbd> Mở</span>
            </div>
            <a href="<?= base_url('modules/search/index.php') ?>" class="font-bold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition flex items-center gap-1">
                <span>Tìm kiếm nâng cao</span>
                <i class="fa-solid fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    </div>
</div>

<!-- ==================================================== -->
<!-- SCRIPTS & CONTROLLERS -->
<!-- ==================================================== -->
<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/drag_drop.js') ?>"></script>

<script>
// Xử lý bật tắt Sidebar trên thiết bị di động
const mobileMenuBtn = document.getElementById('mobileMenuBtn');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

if (mobileMenuBtn && sidebar && sidebarOverlay) {
    mobileMenuBtn.addEventListener('click', () => {
        sidebar.classList.toggle('-translate-x-full');
        sidebarOverlay.classList.toggle('hidden');
    });

    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.add('-translate-x-full');
        sidebarOverlay.classList.add('hidden');
    });
}

// Xử lý bật tắt Dropdown Menu của User Profile trên Topbar
const userMenuBtn = document.getElementById('userMenuBtn');
const userMenuDropdown = document.getElementById('userMenuDropdown');

if (userMenuBtn && userMenuDropdown) {
    userMenuBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        userMenuDropdown.classList.toggle('hidden');
    });

    document.addEventListener('click', (e) => {
        if (!userMenuDropdown.contains(e.target) && !userMenuBtn.contains(e.target)) {
            userMenuDropdown.classList.add('hidden');
        }
    });
}
</script>
</body>
</html>