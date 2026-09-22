<?php
// includes/footer.php - Đóng khung giao diện, Modal xác nhận toàn cục và nạp Javascript
?>
    </main>
</div>

<!-- ==================================================== -->
<!-- GLOBAL CONFIRM MODAL (Thay thế toàn bộ confirm browser) -->
<!-- ==================================================== -->
<div id="globalConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-sm hidden animate-fade-in">
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-md w-full p-6 border border-slate-100 dark:border-slate-700 animate-scale-up">
        <div class="flex items-start gap-4">
            <div class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0 text-xl shadow-sm">
                <i class="fa-solid fa-triangle-exclamation"></i>
            </div>
            <div class="flex-1">
                <h3 id="confirmModalTitle" class="text-base font-bold text-slate-800 dark:text-white">
                    Xác Nhận Thao Tác
                </h3>
                <p id="confirmModalMessage" class="text-sm text-slate-600 dark:text-slate-300 mt-1.5 leading-relaxed">
                    Bạn có chắc chắn muốn thực hiện hành động này không?
                </p>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-700">
            <button type="button" onclick="closeConfirmModal()" class="px-4 py-2.5 rounded-xl border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 text-sm font-semibold transition">
                Hủy Bỏ
            </button>
            <button type="button" id="confirmModalSubmitBtn" onclick="triggerConfirmModalAction()" class="px-4 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white text-sm font-semibold transition shadow-sm">
                Đồng Ý
            </button>
        </div>
    </div>
</div>

<!-- ==================================================== -->
<!-- SCRIPTS & CONTROLLERS -->
<!-- ==================================================== -->
<script src="<?= asset('js/app.js') ?>"></script>

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