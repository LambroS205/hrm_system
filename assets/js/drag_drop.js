/**
 * assets/js/drag_drop.js - HRMS Drag & Drop Core Engine
 * 1. Kanban Pipeline Drag & Drop (HTML5 Native Drag & Drop with AJAX auto-save)
 * 2. File Upload Dropzone (Drag files from desktop, live preview & validation)
 */

(function() {
    'use strict';

    window.HRMSDragDrop = {
        
        /**
         * Khởi tạo tính năng Kéo - Thả cho Bảng Kanban
         * @param {Object} options 
         */
        initKanban: function(options) {
            const board = document.querySelector(options.boardSelector || '.kanban-board');
            if (!board) return;

            const columns = board.querySelectorAll(options.columnSelector || '.kanban-column');
            let draggedCard = null;
            let sourceColumn = null;
            let sourceStatus = null;

            // Đăng ký sự kiện cho tất cả thẻ card draggable
            function setupCards() {
                const cards = board.querySelectorAll(options.cardSelector || '.kanban-card');
                cards.forEach(card => {
                    card.setAttribute('draggable', 'true');
                    card.classList.add('cursor-grab', 'active:cursor-grabbing');

                    card.ondragstart = function(e) {
                        draggedCard = this;
                        sourceColumn = this.closest(options.columnSelector || '.kanban-column');
                        sourceStatus = sourceColumn ? sourceColumn.getAttribute('data-status') : null;

                        e.dataTransfer.effectAllowed = 'move';
                        e.dataTransfer.setData('text/plain', this.getAttribute('data-id'));

                        setTimeout(() => {
                            this.classList.add('opacity-40', 'scale-95', 'rotate-1');
                        }, 0);
                    };

                    card.ondragend = function() {
                        this.classList.remove('opacity-40', 'scale-95', 'rotate-1');
                        columns.forEach(col => {
                            col.classList.remove('kanban-column-over', 'bg-indigo-50/40', 'dark:bg-indigo-950/30', 'border-indigo-400');
                        });
                        draggedCard = null;
                        sourceColumn = null;
                        sourceStatus = null;
                    };
                });
            }

            // Đăng ký sự kiện cho các cột thả
            columns.forEach(col => {
                const dropArea = col.querySelector('.kanban-cards-container') || col;

                dropArea.ondragover = function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    col.classList.add('kanban-column-over', 'bg-indigo-50/40', 'dark:bg-indigo-950/30', 'border-indigo-400');
                };

                dropArea.ondragleave = function(e) {
                    if (!col.contains(e.relatedTarget)) {
                        col.classList.remove('kanban-column-over', 'bg-indigo-50/40', 'dark:bg-indigo-950/30', 'border-indigo-400');
                    }
                };

                dropArea.ondrop = function(e) {
                    e.preventDefault();
                    col.classList.remove('kanban-column-over', 'bg-indigo-50/40', 'dark:bg-indigo-950/30', 'border-indigo-400');

                    if (!draggedCard) return;

                    const targetStatus = col.getAttribute('data-status');
                    if (!targetStatus || targetStatus === sourceStatus) {
                        return; // Cùng cột, không đổi trạng thái
                    }

                    const cardId = draggedCard.getAttribute('data-id');
                    const originalParent = draggedCard.parentNode;
                    const originalNextSibling = draggedCard.nextSibling;

                    // Di chuyển DOM sang cột mới
                    dropArea.appendChild(draggedCard);

                    // Cập nhật số đếm badge ở tiêu đề cột
                    updateColumnBadges();

                    // Gọi callback xử lý AJAX
                    if (typeof options.onStatusChange === 'function') {
                        options.onStatusChange({
                            cardId: cardId,
                            cardElement: draggedCard,
                            oldStatus: sourceStatus,
                            newStatus: targetStatus,
                            revert: function() {
                                // Phục hồi lại vị trí cũ nếu AJAX thất bại
                                if (originalNextSibling) {
                                    originalParent.insertBefore(draggedCard, originalNextSibling);
                                } else {
                                    originalParent.appendChild(draggedCard);
                                }
                                updateColumnBadges();
                            }
                        });
                    }
                };
            });

            function updateColumnBadges() {
                columns.forEach(col => {
                    const countBadge = col.querySelector('.kanban-count-badge');
                    const cardsContainer = col.querySelector('.kanban-cards-container') || col;
                    const count = cardsContainer.querySelectorAll(options.cardSelector || '.kanban-card').length;
                    if (countBadge) {
                        countBadge.textContent = count;
                    }
                });
            }

            setupCards();
            updateColumnBadges();

            return {
                refresh: setupCards,
                updateBadges: updateColumnBadges
            };
        },

        /**
         * Khởi tạo Khu vực kéo thả tệp đính kèm (Dropzone)
         * @param {Object} options 
         */
        initDropzone: function(options) {
            const dropzone = document.querySelector(options.dropzoneSelector || '.drag-drop-zone');
            const fileInput = document.querySelector(options.inputSelector || '.drag-drop-input');
            const previewArea = document.querySelector(options.previewSelector || '.drag-drop-preview');
            const idleArea = dropzone ? dropzone.querySelector('.drag-drop-idle') : null;

            if (!dropzone || !fileInput) return;

            const allowedExts = options.allowedExts || ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'];
            const maxSizeMB = options.maxSizeMB || 10;

            // Highlight khi kéo file vào
            ['dragenter', 'dragover'].forEach(eventName => {
                dropzone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.add('border-indigo-500', 'bg-indigo-50/50', 'dark:bg-indigo-950/40', 'scale-[1.01]');
                });
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropzone.addEventListener(eventName, function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    dropzone.classList.remove('border-indigo-500', 'bg-indigo-50/50', 'dark:bg-indigo-950/40', 'scale-[1.01]');
                });
            });

            // Xử lý khi thả file (Drop)
            dropzone.addEventListener('drop', function(e) {
                const files = e.dataTransfer.files;
                if (files && files.length > 0) {
                    handleSelectedFile(files[0]);
                    // Gán vào input thông qua DataTransfer
                    const dt = new DataTransfer();
                    dt.items.add(files[0]);
                    fileInput.files = dt.files;
                }
            });

            // Xử lý khi chọn file qua click thông thường
            fileInput.addEventListener('change', function() {
                if (this.files && this.files.length > 0) {
                    handleSelectedFile(this.files[0]);
                }
            });

            function handleSelectedFile(file) {
                const ext = file.name.split('.').pop().toLowerCase();
                const sizeMB = file.size / (1024 * 1024);

                if (!allowedExts.includes(ext)) {
                    if (window.showToast) {
                        window.showToast('danger', `Định dạng .${ext} không được hỗ trợ. Vui lòng chọn: ${allowedExts.join(', ')}`);
                    } else {
                        alert(`Định dạng .${ext} không được hỗ trợ.`);
                    }
                    resetFile();
                    return;
                }

                if (sizeMB > maxSizeMB) {
                    if (window.showToast) {
                        window.showToast('danger', `Dung lượng tệp (${sizeMB.toFixed(1)}MB) vượt quá giới hạn tối đa ${maxSizeMB}MB.`);
                    } else {
                        alert(`Dung lượng tệp vượt quá ${maxSizeMB}MB.`);
                    }
                    resetFile();
                    return;
                }

                renderPreview(file);
            }

            function renderPreview(file) {
                if (!previewArea) return;
                const ext = file.name.split('.').pop().toLowerCase();
                const formattedSize = file.size > 1024 * 1024 
                    ? (file.size / (1024 * 1024)).toFixed(2) + ' MB'
                    : (file.size / 1024).toFixed(1) + ' KB';

                let iconClass = 'fa-file text-slate-500';
                let iconColor = 'text-indigo-600 bg-indigo-50 dark:bg-indigo-950/50';

                if (ext === 'pdf') {
                    iconClass = 'fa-file-pdf';
                    iconColor = 'text-rose-600 bg-rose-50 dark:bg-rose-950/50';
                } else if (['doc', 'docx'].includes(ext)) {
                    iconClass = 'fa-file-word';
                    iconColor = 'text-blue-600 bg-blue-50 dark:bg-blue-950/50';
                } else if (['jpg', 'jpeg', 'png', 'webp'].includes(ext)) {
                    iconClass = 'fa-file-image';
                    iconColor = 'text-emerald-600 bg-emerald-50 dark:bg-emerald-950/50';
                }

                previewArea.innerHTML = `
                    <div class="flex items-center justify-between p-3.5 bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm animate-scale-up">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded-xl ${iconColor} flex items-center justify-center text-lg flex-shrink-0">
                                <i class="fa-solid ${iconClass}"></i>
                            </div>
                            <div class="truncate">
                                <p class="text-xs font-bold text-slate-800 dark:text-white truncate">${escapeHtml(file.name)}</p>
                                <p class="text-[11px] text-slate-400 mt-0.5">${formattedSize} • Sẵn sàng tải lên</p>
                            </div>
                        </div>
                        <button type="button" class="remove-file-btn p-2 text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-xl transition" title="Gỡ tệp này">
                            <i class="fa-solid fa-trash-can text-sm"></i>
                        </button>
                    </div>
                `;

                if (idleArea) idleArea.classList.add('hidden');
                previewArea.classList.remove('hidden');

                const removeBtn = previewArea.querySelector('.remove-file-btn');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        resetFile();
                    });
                }
            }

            function resetFile() {
                fileInput.value = '';
                if (previewArea) {
                    previewArea.innerHTML = '';
                    previewArea.classList.add('hidden');
                }
                if (idleArea) {
                    idleArea.classList.remove('hidden');
                }
            }

            function escapeHtml(str) {
                return String(str || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;');
            }

            return {
                reset: resetFile
            };
        }
    };

})();
