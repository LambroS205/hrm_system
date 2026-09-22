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

    // 3. GLOBAL CONFIRM MODAL (Thay thế confirm browser cổ điển)
    let pendingConfirmCallback = null;

    window.openConfirmModal = function(options = {}) {
        const title = options.title || 'Xác Nhận Thao Tác';
        const message = options.message || 'Bạn có chắc chắn muốn thực hiện hành động này không? Dữ liệu có thể không thể khôi phục.';
        const confirmText = options.confirmText || 'Đồng Ý';
        const cancelText = options.cancelText || 'Hủy Bỏ';
        const isDanger = options.isDanger !== false; // Mặc định là nút đỏ danger nếu không chỉ định

        const modal = document.getElementById('globalConfirmModal');
        if (!modal) {
            console.error('Confirm Modal element not found in DOM.');
            return;
        }

        document.getElementById('confirmModalTitle').textContent = title;
        document.getElementById('confirmModalMessage').textContent = message;
        
        const confirmBtn = document.getElementById('confirmModalSubmitBtn');
        confirmBtn.textContent = confirmText;
        if (isDanger) {
            confirmBtn.className = 'px-4 py-2.5 bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white font-semibold rounded-xl text-sm transition shadow-sm';
        } else {
            confirmBtn.className = 'px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold rounded-xl text-sm transition shadow-sm';
        }

        pendingConfirmCallback = options.onConfirm || null;
        modal.classList.remove('hidden');
    };

    window.closeConfirmModal = function() {
        const modal = document.getElementById('globalConfirmModal');
        if (modal) {
            modal.classList.add('hidden');
        }
        pendingConfirmCallback = null;
    };

    window.triggerConfirmModalAction = function() {
        if (typeof pendingConfirmCallback === 'function') {
            const cb = pendingConfirmCallback;
            closeConfirmModal();
            cb();
        } else {
            closeConfirmModal();
        }
    };

    // 4. INTERCEPT DATA-CONFIRM ATTRIBUTES ON FORMS & BUTTONS
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-confirm]');
        if (!btn) return;

        e.preventDefault();
        const message = btn.getAttribute('data-confirm') || 'Bạn có chắc chắn muốn tiếp tục?';
        const title = btn.getAttribute('data-confirm-title') || 'Xác Nhận';
        const isDanger = btn.getAttribute('data-confirm-danger') !== 'false';

        openConfirmModal({
            title: title,
            message: message,
            isDanger: isDanger,
            onConfirm: function() {
                if (btn.tagName === 'A') {
                    window.location.href = btn.href;
                } else if (btn.tagName === 'BUTTON' && btn.form) {
                    // Thêm hidden input nếu button có name & value
                    if (btn.name && btn.value) {
                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = btn.name;
                        hiddenInput.value = btn.value;
                        btn.form.appendChild(hiddenInput);
                    }
                    btn.form.submit();
                } else if (btn.tagName === 'BUTTON') {
                    btn.click();
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

})();
