// assets/js/app.js - HRMS Core UI Engine & Component Controller

(function() {
    'use strict';

    // 1. THEME CONTROLLER (Dark / Light Mode)
    window.toggleTheme = function() {
        const isDark = document.documentElement.classList.contains('dark');
        if (isDark) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('hrms_theme', 'light');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('hrms_theme', 'dark');
        }
        updateThemeIcons(!isDark);
    };

    function updateThemeIcons(isDark) {
        document.querySelectorAll('.theme-icon-sun').forEach(el => {
            el.classList.toggle('hidden', !isDark);
        });
        document.querySelectorAll('.theme-icon-moon').forEach(el => {
            el.classList.toggle('hidden', isDark);
        });
    }

    // 2. TOAST NOTIFICATIONS ENGINE
    window.showToast = function(type = 'info', message = '', duration = 4000) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            document.body.appendChild(container);
        }

        const icons = {
            success: 'fa-circle-check text-emerald-500',
            danger: 'fa-circle-xmark text-rose-500',
            error: 'fa-circle-xmark text-rose-500',
            warning: 'fa-triangle-exclamation text-amber-500',
            info: 'fa-circle-info text-indigo-500'
        };

        const bgStyles = {
            success: 'bg-white dark:bg-slate-800 border-emerald-200 dark:border-emerald-800 text-slate-800 dark:text-slate-100',
            danger: 'bg-white dark:bg-slate-800 border-rose-200 dark:border-rose-800 text-slate-800 dark:text-slate-100',
            error: 'bg-white dark:bg-slate-800 border-rose-200 dark:border-rose-800 text-slate-800 dark:text-slate-100',
            warning: 'bg-white dark:bg-slate-800 border-amber-200 dark:border-amber-800 text-slate-800 dark:text-slate-100',
            info: 'bg-white dark:bg-slate-800 border-indigo-200 dark:border-indigo-800 text-slate-800 dark:text-slate-100'
        };

        const toast = document.createElement('div');
        toast.className = `toast-item border shadow-lg ${bgStyles[type] || bgStyles.info}`;
        toast.innerHTML = `
            <i class="fa-solid ${icons[type] || icons.info} text-xl flex-shrink-0"></i>
            <div class="text-sm font-medium flex-1">${escapeHtml(message)}</div>
            <button type="button" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 ml-2" onclick="this.parentElement.remove()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            setTimeout(() => toast.remove(), 250);
        }, duration);
    };

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Ghi đè window.alert mặc định của trình duyệt để loại bỏ vĩnh viễn popup "localhost says"
    window.alert = function(message) {
        if (typeof window.showToast === 'function') {
            window.showToast('info', message);
        } else {
            console.info('[App Alert]', message);
        }
    };

    // 3. GLOBAL CONFIRM MODAL (Thay thế toàn bộ confirm browser cổ điển)
    let pendingConfirmCallback = null;
    let pendingCancelCallback = null;

    function ensureConfirmModalDOM() {
        let modal = document.getElementById('globalConfirmModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'globalConfirmModal';
            modal.className = 'fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden animate-fade-in';
            modal.innerHTML = `
                <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl max-w-md w-full p-6 border-2 border-slate-200 dark:border-slate-700 animate-scale-up">
                    <div class="flex items-start gap-4">
                        <div id="confirmModalIconBox" class="w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0 text-xl shadow-xs">
                            <i id="confirmModalIcon" class="fa-solid fa-triangle-exclamation"></i>
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
            `;
            document.body.appendChild(modal);
        }
        return modal;
    }

    window.openConfirmModal = function(options = {}) {
        const title = options.title || 'Xác Nhận Thao Tác';
        const message = options.message || 'Bạn có chắc chắn muốn thực hiện hành động này không? Dữ liệu có thể không thể khôi phục.';
        const confirmText = options.confirmText || 'Đồng Ý';
        const isDanger = options.isDanger !== false; // Mặc định là nút đỏ danger nếu không chỉ định

        const modal = ensureConfirmModalDOM();

        const titleEl = document.getElementById('confirmModalTitle');
        const messageEl = document.getElementById('confirmModalMessage');
        const iconBox = document.getElementById('confirmModalIconBox');
        const iconEl = document.getElementById('confirmModalIcon');
        const confirmBtn = document.getElementById('confirmModalSubmitBtn');

        if (titleEl) titleEl.textContent = title;
        if (messageEl) messageEl.textContent = message;
        
        if (confirmBtn) {
            confirmBtn.textContent = confirmText;
            if (isDanger) {
                confirmBtn.className = 'px-4 py-2.5 bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white font-semibold rounded-xl text-sm transition shadow-sm';
            } else {
                confirmBtn.className = 'px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold rounded-xl text-sm transition shadow-sm';
            }
        }

        if (iconBox && iconEl) {
            if (isDanger) {
                iconBox.className = 'w-12 h-12 rounded-2xl bg-rose-100 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center flex-shrink-0 text-xl shadow-xs';
                iconEl.className = 'fa-solid fa-triangle-exclamation';
            } else {
                iconBox.className = 'w-12 h-12 rounded-2xl bg-indigo-100 dark:bg-indigo-950/60 text-indigo-600 dark:text-indigo-400 flex items-center justify-center flex-shrink-0 text-xl shadow-xs';
                iconEl.className = 'fa-solid fa-circle-question';
            }
        }

        pendingConfirmCallback = options.onConfirm || null;
        pendingCancelCallback = options.onCancel || null;
        modal.classList.remove('hidden');
    };

    window.closeConfirmModal = function() {
        const modal = document.getElementById('globalConfirmModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        if (typeof pendingCancelCallback === 'function') {
            const cancelCb = pendingCancelCallback;
            pendingCancelCallback = null;
            pendingConfirmCallback = null;
            cancelCb();
        } else {
            pendingConfirmCallback = null;
            pendingCancelCallback = null;
        }
    };

    window.triggerConfirmModalAction = function() {
        const confirmCb = pendingConfirmCallback;
        pendingConfirmCallback = null;
        pendingCancelCallback = null;
        
        const modal = document.getElementById('globalConfirmModal');
        if (modal) {
            modal.classList.add('hidden');
        }

        if (typeof confirmCb === 'function') {
            confirmCb();
        }
    };

    // Hỗ trợ Promise-based modal xác nhận bất đồng bộ (thay thế async confirm)
    window.confirmAsync = function(options = {}) {
        return new Promise((resolve) => {
            const opts = typeof options === 'string' ? { message: options } : options;
            window.openConfirmModal({
                ...opts,
                onConfirm: () => resolve(true),
                onCancel: () => resolve(false)
            });
        });
    };

    // 4. INTERCEPT DATA-CONFIRM ATTRIBUTES ON BUTTONS, LINKS & FORMS
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-confirm]');
        if (!btn) return;

        // Nếu phần tử đã được đánh dấu confirmed thì để sự kiện diễn ra tự nhiên
        if (btn.dataset.isConfirmed === 'true') {
            return;
        }

        e.preventDefault();
        const message = btn.getAttribute('data-confirm') || 'Bạn có chắc chắn muốn tiếp tục?';
        const title = btn.getAttribute('data-confirm-title') || 'Xác Nhận Thao Tác';
        const confirmText = btn.getAttribute('data-confirm-btn') || 'Đồng Ý';
        const isDanger = btn.getAttribute('data-confirm-danger') !== 'false';

        openConfirmModal({
            title: title,
            message: message,
            confirmText: confirmText,
            isDanger: isDanger,
            onConfirm: function() {
                if (btn.tagName === 'A') {
                    window.location.href = btn.href;
                } else if (btn.tagName === 'BUTTON' && btn.form) {
                    if (btn.name && btn.value) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = btn.name;
                        hiddenInput.value = btn.value;
                        btn.form.appendChild(hiddenInput);
                    }
                    btn.form.dataset.isConfirmed = 'true';
                    btn.form.submit();
                } else {
                    btn.dataset.isConfirmed = 'true';
                    btn.click();
                    setTimeout(() => { delete btn.dataset.isConfirmed; }, 500);
                }
            }
        });
    });

    // Bắt sự kiện submit form nếu form có thuộc tính data-confirm
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.hasAttribute('data-confirm') && form.dataset.isConfirmed !== 'true') {
            e.preventDefault();
            const message = form.getAttribute('data-confirm') || 'Bạn có chắc chắn muốn gửi biểu mẫu này?';
            const title = form.getAttribute('data-confirm-title') || 'Xác Nhận Biểu Mẫu';
            const confirmText = form.getAttribute('data-confirm-btn') || 'Đồng Ý';
            const isDanger = form.getAttribute('data-confirm-danger') !== 'false';

            openConfirmModal({
                title: title,
                message: message,
                confirmText: confirmText,
                isDanger: isDanger,
                onConfirm: function() {
                    form.dataset.isConfirmed = 'true';
                    form.submit();
                }
            });
        }
    }, true);

    // Tự động chuyển đổi các phần tử HTML cũ có onsubmit / onclick chứa confirm(...)
    // sang hệ thống data-confirm để triệt tiêu hoàn toàn pop-up "localhost says"
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('form, button, a').forEach(el => {
            const onsubmitAttr = el.getAttribute('onsubmit');
            const onclickAttr = el.getAttribute('onclick');

            if (onsubmitAttr && onsubmitAttr.includes('confirm(')) {
                const match = onsubmitAttr.match(/confirm\s*\(\s*(['"`])(.*?)\1\s*\)/);
                if (match && match[2]) {
                    el.removeAttribute('onsubmit');
                    el.setAttribute('data-confirm', match[2]);
                    const lower = onsubmitAttr.toLowerCase();
                    if (lower.includes('chốt') || lower.includes('phê duyệt')) {
                        el.setAttribute('data-confirm-danger', 'false');
                    } else if (lower.includes('xóa') || lower.includes('hủy')) {
                        el.setAttribute('data-confirm-danger', 'true');
                    }
                }
            }

            if (onclickAttr && onclickAttr.includes('confirm(')) {
                const match = onclickAttr.match(/confirm\s*\(\s*(['"`])(.*?)\1\s*\)/);
                if (match && match[2]) {
                    el.removeAttribute('onclick');
                    el.setAttribute('data-confirm', match[2]);
                    const lower = onclickAttr.toLowerCase();
                    if (lower.includes('phê duyệt') || lower.includes('đồng ý')) {
                        el.setAttribute('data-confirm-danger', 'false');
                    } else if (lower.includes('xóa') || lower.includes('hủy')) {
                        el.setAttribute('data-confirm-danger', 'true');
                    }
                }
            }
        });
    });

    // 5. PREVENT DOUBLE SUBMIT & ADD LOADING SPINNER
    document.addEventListener('submit', function(e) {
        const form = e.target;
        if (form.classList.contains('no-submit-spinner')) return;

        const submitBtn = form.querySelector('button[type="submit"]:not(.no-spin)');
        if (submitBtn && !submitBtn.disabled) {
            // Không disable ngay lập tức nếu form dùng HTML5 validation bị invalid
            if (form.checkValidity && !form.checkValidity()) {
                return;
            }

            setTimeout(() => {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-75', 'cursor-not-allowed');
                const origHtml = submitBtn.innerHTML;
                submitBtn.setAttribute('data-orig-html', origHtml);
                submitBtn.innerHTML = `<i class="fa-solid fa-circle-notch fa-spin mr-2"></i><span>Đang xử lý...</span>`;
            }, 10);
        }
    });

    // 6. AUTO-DISMISS FLASH ALERTS
    document.addEventListener('DOMContentLoaded', function() {
        const flashAlerts = document.querySelectorAll('.flash-alert-auto');
        flashAlerts.forEach(alert => {
            setTimeout(() => {
                alert.style.transition = 'all 0.5s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 500);
            }, 5000);
        });
    });
    // 7. COMMAND PALETTE (Ctrl+K / Cmd+K Quick Search)
    document.addEventListener('DOMContentLoaded', function() {
        const palette = document.getElementById('searchCommandPalette');
        const paletteInput = document.getElementById('cmdPaletteInput');
        const paletteResults = document.getElementById('cmdPaletteResults');
        const paletteLoading = document.getElementById('cmdPaletteLoading');
        const quickLinks = document.getElementById('cmdQuickLinks');
        const openBtn = document.getElementById('openSearchModalBtn');
        const openBtnMobile = document.getElementById('openSearchModalBtnMobile');

        if (!palette || !paletteInput) return;

        let cmdDebounce = null;
        let cmdAbort = null;
        let cmdSelectedIndex = -1;
        let cmdResultLinks = [];

        // Xác định base URL từ script tag hoặc meta
        const scriptTag = document.querySelector('script[src*="app.js"]');
        let searchApiUrl = '';
        if (scriptTag) {
            const src = scriptTag.getAttribute('src');
            const assetsIndex = src.indexOf('assets/');
            if (assetsIndex !== -1) {
                searchApiUrl = src.substring(0, assetsIndex) + 'modules/search/api.php';
            }
        }
        // Fallback: try to build from current URL
        if (!searchApiUrl) {
            const path = window.location.pathname;
            const hrmIndex = path.indexOf('/hrm_system/');
            if (hrmIndex !== -1) {
                searchApiUrl = path.substring(0, hrmIndex) + '/hrm_system/modules/search/api.php';
            } else {
                searchApiUrl = '/hrm_system/modules/search/api.php';
            }
        }

        function openPalette() {
            palette.classList.remove('hidden');
            palette.style.animation = 'fadeIn 0.15s ease forwards';
            paletteInput.value = '';
            paletteInput.focus();
            showQuickLinks();
            document.body.style.overflow = 'hidden';
        }

        function closePalette() {
            palette.classList.add('hidden');
            paletteInput.value = '';
            cmdSelectedIndex = -1;
            document.body.style.overflow = '';
        }

        function showQuickLinks() {
            if (quickLinks) {
                paletteResults.innerHTML = '';
                paletteResults.appendChild(quickLinks);
                quickLinks.style.display = '';
            }
            paletteLoading.classList.add('hidden');
            updateResultLinks();
        }

        // Open handlers
        if (openBtn) openBtn.addEventListener('click', openPalette);
        if (openBtnMobile) openBtnMobile.addEventListener('click', openPalette);

        // Ctrl+K / Cmd+K
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                if (palette.classList.contains('hidden')) {
                    openPalette();
                } else {
                    closePalette();
                }
            }
        });

        // Close on backdrop click
        palette.addEventListener('click', function(e) {
            if (e.target === palette) closePalette();
        });

        // Input events
        paletteInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closePalette();
                return;
            }
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                cmdSelectedIndex = Math.min(cmdSelectedIndex + 1, cmdResultLinks.length - 1);
                highlightResult();
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                cmdSelectedIndex = Math.max(cmdSelectedIndex - 1, 0);
                highlightResult();
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                if (cmdResultLinks[cmdSelectedIndex]) {
                    cmdResultLinks[cmdSelectedIndex].click();
                } else if (paletteInput.value.trim()) {
                    // Go to full search page
                    window.location.href = searchApiUrl.replace('api.php', 'index.php') + '?q=' + encodeURIComponent(paletteInput.value.trim());
                }
                return;
            }
        });

        paletteInput.addEventListener('input', function() {
            clearTimeout(cmdDebounce);
            const kw = this.value.trim();
            
            if (!kw) {
                showQuickLinks();
                return;
            }

            cmdDebounce = setTimeout(() => {
                performQuickSearch(kw);
            }, 250);
        });

        async function performQuickSearch(keyword) {
            if (cmdAbort) cmdAbort.abort();
            cmdAbort = new AbortController();

            paletteLoading.classList.remove('hidden');
            if (quickLinks) quickLinks.style.display = 'none';

            try {
                const resp = await fetch(`${searchApiUrl}?q=${encodeURIComponent(keyword)}&mode=quick&category=all&limit=5`, {
                    signal: cmdAbort.signal
                });
                const data = await resp.json();
                paletteLoading.classList.add('hidden');

                if (data.success && data.total > 0) {
                    renderQuickResults(data, keyword);
                } else {
                    paletteResults.innerHTML = `
                        <div class="px-5 py-8 text-center">
                            <i class="fa-regular fa-folder-open text-2xl text-slate-300 dark:text-slate-600 mb-2 block"></i>
                            <div class="text-sm text-slate-400 dark:text-slate-500">Không tìm thấy kết quả cho "<strong>${escapeHtml(keyword)}</strong>"</div>
                        </div>
                    `;
                }
            } catch (err) {
                if (err.name !== 'AbortError') {
                    paletteLoading.classList.add('hidden');
                }
            }

            cmdSelectedIndex = -1;
            updateResultLinks();
        }

        function renderQuickResults(data, keyword) {
            let html = '';

            for (const [catKey, catData] of Object.entries(data.results)) {
                if (!catData.items || catData.items.length === 0) continue;

                const iconColors = {
                    indigo: 'bg-indigo-50 dark:bg-indigo-950/60 text-indigo-500',
                    sky: 'bg-sky-50 dark:bg-sky-950/60 text-sky-500',
                    violet: 'bg-violet-50 dark:bg-violet-950/60 text-violet-500',
                    emerald: 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-500',
                    rose: 'bg-rose-50 dark:bg-rose-950/60 text-rose-500',
                    amber: 'bg-amber-50 dark:bg-amber-950/60 text-amber-500',
                    pink: 'bg-pink-50 dark:bg-pink-950/60 text-pink-500'
                };
                const ic = iconColors[catData.color] || iconColors.indigo;

                html += `<div class="px-5 py-1.5 text-[11px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider flex items-center justify-between">
                    <span>${escapeHtml(catData.label)}</span>
                    <span class="text-[10px] font-mono">${catData.total}</span>
                </div>`;

                catData.items.forEach(item => {
                    const itemUrl = getItemUrl(catKey, item);
                    const title = getItemTitle(catKey, item);
                    const subtitle = getItemSubtitle(catKey, item);

                    html += `<a href="${itemUrl}" class="cmd-result flex items-center gap-3 px-5 py-2.5 hover:bg-indigo-50 dark:hover:bg-indigo-950/40 transition text-sm rounded-lg mx-2">
                        <div class="w-8 h-8 rounded-lg ${ic} flex items-center justify-center text-xs flex-shrink-0"><i class="${catData.icon}"></i></div>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-slate-700 dark:text-slate-200 truncate text-[13px]">${highlightText(title, keyword)}</div>
                            ${subtitle ? `<div class="text-[11px] text-slate-400 dark:text-slate-500 truncate">${escapeHtml(subtitle)}</div>` : ''}
                        </div>
                        <i class="fa-solid fa-chevron-right text-[10px] text-slate-300 dark:text-slate-600 flex-shrink-0"></i>
                    </a>`;
                });
            }

            // View all link
            const searchPageUrl = searchApiUrl.replace('api.php', 'index.php');
            html += `<div class="px-5 py-3 border-t border-slate-100 dark:border-slate-700 mt-2">
                <a href="${searchPageUrl}?q=${encodeURIComponent(keyword)}" class="cmd-result flex items-center justify-center gap-2 text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 transition py-2">
                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                    Xem tất cả ${data.total} kết quả trong Tìm Kiếm Nâng Cao
                    <i class="fa-solid fa-arrow-right text-[10px]"></i>
                </a>
            </div>`;

            paletteResults.innerHTML = html;
            updateResultLinks();
        }

        function getItemUrl(catKey, item) {
            const base = searchApiUrl.replace('modules/search/api.php', '');
            switch (catKey) {
                case 'employees': return base + 'modules/employees/view.php?id=' + item.id;
                case 'departments': return base + 'modules/departments/index.php';
                case 'branches': return base + 'modules/branches/index.php';
                case 'rewards': return base + 'modules/rewards/index.php';
                case 'disciplines': return base + 'modules/disciplines/index.php';
                case 'transfers': return base + 'modules/transfers/index.php';
                case 'recruitment': return base + 'modules/recruitment/candidate_view.php?id=' + item.id;
                default: return '#';
            }
        }

        function getItemTitle(catKey, item) {
            switch (catKey) {
                case 'employees': return item.fullname || '';
                case 'departments': return item.name || '';
                case 'branches': return item.name || '';
                case 'rewards': return item.title || '';
                case 'disciplines': return item.title || '';
                case 'transfers': return item.employee_name || 'Thuyên chuyển';
                case 'recruitment': return item.fullname || '';
                default: return '';
            }
        }

        function getItemSubtitle(catKey, item) {
            switch (catKey) {
                case 'employees': return [item.employee_code, item.department_name, item.position_name].filter(Boolean).join(' • ');
                case 'departments': return [item.code, item.branch_name, (item.employee_count || 0) + ' NV'].filter(Boolean).join(' • ');
                case 'branches': return [item.code, (item.employee_count || 0) + ' NV', item.address].filter(Boolean).join(' • ');
                case 'rewards': return [item.employee_name, item.decision_number].filter(Boolean).join(' • ');
                case 'disciplines': return [item.employee_name, item.decision_number].filter(Boolean).join(' • ');
                case 'transfers': return [item.from_department, '→', item.to_department].filter(Boolean).join(' ');
                case 'recruitment': return [item.job_title, item.email, item.stage].filter(Boolean).join(' • ');
                default: return '';
            }
        }

        function highlightText(text, keyword) {
            if (!text || !keyword) return escapeHtml(text || '');
            const escaped = escapeHtml(text);
            const kwEscaped = escapeHtml(keyword);
            const regex = new RegExp(`(${kwEscaped.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
            return escaped.replace(regex, '<mark class="bg-amber-200/70 dark:bg-amber-600/40 text-inherit rounded px-0.5">$1</mark>');
        }

        function updateResultLinks() {
            cmdResultLinks = Array.from(paletteResults.querySelectorAll('a.cmd-result'));
        }

        function highlightResult() {
            cmdResultLinks.forEach((el, i) => {
                if (i === cmdSelectedIndex) {
                    el.classList.add('bg-indigo-50', 'dark:bg-indigo-950/40');
                    el.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                } else {
                    el.classList.remove('bg-indigo-50', 'dark:bg-indigo-950/40');
                }
            });
        }
    });

    // 7. SIDEBAR SCROLL POSITION PERSISTENCE & SMART VISIBILITY
    (function initSidebarScrollPersistence() {
        const sidebarNav = document.getElementById('sidebarNav');
        if (!sidebarNav) return;

        // Lưu vị trí cuộn khi người dùng cuộn sidebar (throttled)
        let scrollTimer = null;
        sidebarNav.addEventListener('scroll', function() {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function() {
                const pos = sidebarNav.scrollTop;
                try {
                    sessionStorage.setItem('hrms_sidebar_scroll', pos);
                    localStorage.setItem('hrms_sidebar_scroll', pos);
                } catch (e) {}
            }, 60);
        }, { passive: true });

        // Lưu ngay lập tức khi click bất kỳ liên kết nào trong sidebar
        sidebarNav.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (link) {
                const pos = sidebarNav.scrollTop;
                try {
                    sessionStorage.setItem('hrms_sidebar_scroll', pos);
                    localStorage.setItem('hrms_sidebar_scroll', pos);
                } catch (e) {}
            }
        });

        // Lưu vị trí khi chuẩn bị rời trang (beforeunload & pagehide)
        function saveCurrentPos() {
            if (sidebarNav) {
                const pos = sidebarNav.scrollTop;
                try {
                    sessionStorage.setItem('hrms_sidebar_scroll', pos);
                    localStorage.setItem('hrms_sidebar_scroll', pos);
                } catch (e) {}
            }
        }
        window.addEventListener('beforeunload', saveCurrentPos);
        window.addEventListener('pagehide', saveCurrentPos);

        // Khôi phục vị trí khi chuyển sang tab trình duyệt khác rồi quay lại
        document.addEventListener('visibilitychange', function() {
            if (document.visibilityState === 'visible' && sidebarNav) {
                try {
                    const pos = sessionStorage.getItem('hrms_sidebar_scroll') ?? localStorage.getItem('hrms_sidebar_scroll');
                    if (pos !== null) {
                        sidebarNav.scrollTop = parseInt(pos, 10);
                    }
                } catch (e) {}
            }
        });

        // Kiểm tra và bảo đảm mục đang active hiển thị trọn vẹn trong khung nhìn
        function ensureActiveItemVisible() {
            try {
                const savedPos = sessionStorage.getItem('hrms_sidebar_scroll') ?? localStorage.getItem('hrms_sidebar_scroll');
                if (savedPos !== null) {
                    sidebarNav.scrollTop = parseInt(savedPos, 10);
                }
            } catch (e) {}

            const activeItem = sidebarNav.querySelector('.sidebar-active-item') || 
                               sidebarNav.querySelector('a.bg-indigo-600');
            if (!activeItem) return;

            const navRect = sidebarNav.getBoundingClientRect();
            const itemRect = activeItem.getBoundingClientRect();

            // Nếu mục active bị khuất phía trên hoặc phía dưới của khung cuộn
            if (itemRect.top < navRect.top + 8 || itemRect.bottom > navRect.bottom - 8) {
                activeItem.scrollIntoView({ block: 'nearest', behavior: 'auto' });
                try {
                    sessionStorage.setItem('hrms_sidebar_scroll', sidebarNav.scrollTop);
                    localStorage.setItem('hrms_sidebar_scroll', sidebarNav.scrollTop);
                } catch (e) {}
            }
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', ensureActiveItemVisible);
        } else {
            ensureActiveItemVisible();
        }
    })();

    // 8. ENTERPRISE WORKFLOW BANNER CONTROLLER
    window.toggleWorkflowBanner = function(bannerId) {
        const el = document.getElementById(bannerId);
        const textEl = document.getElementById(bannerId + '-toggleText');
        const iconEl = document.getElementById(bannerId + '-toggleIcon');
        if (!el) return;
        const isHidden = el.classList.toggle('hidden');
        if (textEl) textEl.textContent = isHidden ? 'Mở rộng' : 'Thu gọn';
        if (iconEl) {
            iconEl.classList.toggle('fa-chevron-up', !isHidden);
            iconEl.classList.toggle('fa-chevron-down', isHidden);
        }
        try {
            localStorage.setItem(bannerId + '_collapsed', isHidden ? 'true' : 'false');
        } catch (e) {}
    };

    // Tự động khôi phục trạng thái thu gọn của các workflow banner
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[id$="WorkflowGuide"]').forEach(el => {
            const id = el.id;
            try {
                if (localStorage.getItem(id + '_collapsed') === 'true') {
                    el.classList.add('hidden');
                    const textEl = document.getElementById(id + '-toggleText');
                    const iconEl = document.getElementById(id + '-toggleIcon');
                    if (textEl) textEl.textContent = 'Mở rộng';
                    if (iconEl) {
                        iconEl.classList.remove('fa-chevron-up');
                        iconEl.classList.add('fa-chevron-down');
                    }
                }
            } catch (e) {}
        });
    });

})();
