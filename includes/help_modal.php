<?php
// includes/help_modal.php - Trung tâm trợ giúp, cẩm nang phím tắt & sơ đồ đào tạo nhân viên chuẩn Enterprise
?>
<div id="helpTrainingModal" class="fixed inset-0 z-50 hidden flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in" role="dialog" aria-modal="true" aria-labelledby="helpModalTitle">
    <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl border-2 border-slate-200 dark:border-slate-700 w-full max-w-4xl max-h-[90vh] flex flex-col overflow-hidden animate-scale-up">
        
        <!-- Header Modal -->
        <div class="px-6 py-5 border-b-2 border-slate-200 dark:border-slate-700 flex items-center justify-between bg-slate-50/80 dark:bg-slate-900/40 flex-shrink-0">
            <div class="flex items-center gap-3.5">
                <div class="w-11 h-11 rounded-2xl bg-indigo-600 text-white flex items-center justify-center text-lg shadow-md shadow-indigo-300 dark:shadow-none flex-shrink-0">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>
                <div>
                    <h3 id="helpModalTitle" class="text-lg font-extrabold text-slate-900 dark:text-white leading-tight">
                        Trung Tâm Hướng Dẫn & Đào Tạo Vận Hành AURA HRM
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                        Cẩm nang trực quan giúp nhân sự & quản lý mới làm chủ hệ thống trong 5 phút
                    </p>
                </div>
            </div>
            <button type="button" onclick="closeHelpModal()" class="w-9 h-9 rounded-xl hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white flex items-center justify-center transition" title="Đóng (Esc)">
                <i class="fa-solid fa-xmark text-base"></i>
            </button>
        </div>

        <!-- Navigation Tabs Bên Trong Modal -->
        <div class="flex items-center gap-2 px-6 pt-3 border-b border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 flex-shrink-0 overflow-x-auto text-xs font-bold">
            <button type="button" onclick="switchHelpTab('pipeline')" id="helpTabBtn-pipeline" class="help-tab-btn py-2.5 px-3.5 border-b-2 border-indigo-600 text-indigo-600 dark:text-indigo-400 flex items-center gap-2 transition">
                <i class="fa-solid fa-diagram-project"></i>
                <span>Sơ Đồ Luồng Dữ Liệu</span>
            </button>
            <button type="button" onclick="switchHelpTab('shortcuts')" id="helpTabBtn-shortcuts" class="help-tab-btn py-2.5 px-3.5 border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white flex items-center gap-2 transition">
                <i class="fa-solid fa-keyboard"></i>
                <span>Phím Tắt Nhanh (⌘K, F1...)</span>
            </button>
            <button type="button" onclick="switchHelpTab('payroll_guide')" id="helpTabBtn-payroll_guide" class="help-tab-btn py-2.5 px-3.5 border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white flex items-center gap-2 transition">
                <i class="fa-solid fa-calculator"></i>
                <span>Quy Trình Chốt Lương (4 Bước)</span>
            </button>
            <button type="button" onclick="switchHelpTab('glossary')" id="helpTabBtn-glossary" class="help-tab-btn py-2.5 px-3.5 border-b-2 border-transparent text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white flex items-center gap-2 transition">
                <i class="fa-solid fa-book-bookmark"></i>
                <span>Chú Giải Thuật Ngữ & Trạng Thái</span>
            </button>
        </div>

        <!-- Body Scrollable Content -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">

            <!-- TAB 1: SƠ ĐỒ LUỒNG DỮ LIỆU LIÊN THÔNG -->
            <div id="helpTabContent-pipeline" class="space-y-6">
                <div>
                    <h4 class="text-sm font-extrabold text-slate-900 dark:text-white mb-2 flex items-center gap-2">
                        <i class="fa-solid fa-route text-indigo-600 dark:text-indigo-400"></i>
                        Vòng Đời Nhân Sự & Sự Liên Thông Giữa Các Phân Hệ
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400 leading-relaxed">
                        Mọi dữ liệu trong AURA HRM được liên kết chặt chẽ tự động. Hiểu rõ luồng dữ liệu sẽ giúp bạn thao tác chuẩn xác và không bỏ sót khâu nào.
                    </p>
                </div>

                <!-- 4 Block trực quan -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                    
                    <!-- Khâu 1 -->
                    <div class="p-4 rounded-2xl bg-indigo-50/60 dark:bg-indigo-950/30 border border-indigo-200 dark:border-indigo-800 space-y-2">
                        <div class="flex items-center gap-2 font-bold text-indigo-900 dark:text-indigo-300">
                            <span class="w-6 h-6 rounded-lg bg-indigo-600 text-white flex items-center justify-center text-[11px]">1</span>
                            <span>Tuyển Dụng & Tiếp Nhận Onboarding</span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                            Đăng tuyển &rarr; Nhận hồ sơ ứng viên &rarr; Phỏng vấn qua Pipeline &rarr; Trúng tuyển: Tự động chuyển thành <strong>Hồ Sơ Nhân Viên</strong> & Ký <strong>Hợp Đồng Lao Động (HĐLĐ)</strong>.
                        </p>
                        <div class="text-[11px] text-indigo-700 dark:text-indigo-400 font-semibold">
                            Căn cứ pháp lý: HĐLĐ quy định mức lương đóng BHXH & phụ cấp chức danh.
                        </div>
                    </div>

                    <!-- Khâu 2 -->
                    <div class="p-4 rounded-2xl bg-sky-50/60 dark:bg-sky-950/30 border border-sky-200 dark:border-sky-800 space-y-2">
                        <div class="flex items-center gap-2 font-bold text-sky-900 dark:text-sky-300">
                            <span class="w-6 h-6 rounded-lg bg-sky-600 text-white flex items-center justify-center text-[11px]">2</span>
                            <span>Chấm Công & Quản Lý Đề Xuất Hàng Ngày</span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                            Máy chấm công ghi nhận Check-in/out &rarr; Tổng hợp phút muộn/sớm &rarr; Nhân viên gửi <strong>Đề Xuất</strong> (Nghỉ phép năm, Ốm đau, OT) &rarr; Quản lý duyệt &rarr; <strong>Tự động đồng bộ vào Bảng Chấm Công</strong>.
                        </p>
                        <div class="text-[11px] text-sky-700 dark:text-sky-400 font-semibold">
                            Căn cứ pháp lý: Đi muộn trừ theo phút thực tế, không trừ 0.5 ngày công (Điều 102 BLLĐ).
                        </div>
                    </div>

                    <!-- Khâu 3 -->
                    <div class="p-4 rounded-2xl bg-amber-50/60 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 space-y-2">
                        <div class="flex items-center gap-2 font-bold text-amber-900 dark:text-amber-300">
                            <span class="w-6 h-6 rounded-lg bg-amber-600 text-white flex items-center justify-center text-[11px]">3</span>
                            <span>Đãi Ngộ, Kỷ Luật & Đánh Giá Hiệu Suất</span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                            <strong>Khen thưởng:</strong> Tiền thưởng được cộng thẳng vào Gross &rarr; <strong>Kỷ Luật:</strong> Xử lý vi phạm bồi thường thiệt hại được khấu trừ tự động vào Net (tối đa 30%) &rarr; <strong>Đánh giá KPI</strong> hàng quý.
                        </p>
                        <div class="text-[11px] text-amber-700 dark:text-amber-400 font-semibold">
                            Căn cứ pháp lý: Nghiêm cấm trừ lương thay kỷ luật (Điều 127). Chỉ khấu trừ bồi thường (Điều 129).
                        </div>
                    </div>

                    <!-- Khâu 4 -->
                    <div class="p-4 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-800 space-y-2">
                        <div class="flex items-center gap-2 font-bold text-emerald-900 dark:text-emerald-300">
                            <span class="w-6 h-6 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-[11px]">4</span>
                            <span>Tổng Hợp Lương, Thuế TNCN & Chốt Kỳ</span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
                            Nhấn <strong>"Tính Bảng Lương"</strong> &rarr; Engine tự động gom công, OT, trích 10.5% BHXH (có áp trần), tính Thuế TNCN lũy tiến 5 bậc &rarr; Kế toán kiểm tra & <strong>"Chốt Lương"</strong> (Khóa dữ liệu chống sửa).
                        </p>
                        <div class="text-[11px] text-emerald-700 dark:text-emerald-400 font-semibold">
                            Đầu ra: Phiếu lương chi tiết 5 mục minh bạch & báo cáo chi phí doanh nghiệp đóng.
                        </div>
                    </div>

                </div>
            </div>

            <!-- TAB 2: CẨM NANG PHÍM TẮT THAO TÁC NHANH -->
            <div id="helpTabContent-shortcuts" class="hidden space-y-4">
                <div>
                    <h4 class="text-sm font-extrabold text-slate-900 dark:text-white mb-1 flex items-center gap-2">
                        <i class="fa-solid fa-keyboard text-indigo-600 dark:text-indigo-400"></i>
                        Phím Tắt Tiết Kiệm Thời Gian Dành Cho Nhân Viên Chuyên Nghiệp
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Thao tác phím tắt giúp kế toán và HR xử lý dữ liệu nhanh gấp 3 lần so với dùng chuột thuần túy.
                    </p>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-slate-700">
                    <table class="w-full text-xs text-left">
                        <thead class="bg-slate-50 dark:bg-slate-700/60 text-slate-600 dark:text-slate-300 font-bold uppercase text-[10px]">
                            <tr>
                                <th class="p-3">Phím Tắt</th>
                                <th class="p-3">Chức Năng</th>
                                <th class="p-3">Ngữ Cảnh Áp Dụng</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700 font-medium">
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                <td class="p-3">
                                    <kbd class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-indigo-700 dark:text-indigo-300 font-bold border border-slate-300 dark:border-slate-600 font-mono">⌘K</kbd> hoặc <kbd class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-indigo-700 dark:text-indigo-300 font-bold border border-slate-300 dark:border-slate-600 font-mono">Ctrl + K</kbd>
                                </td>
                                <td class="p-3 text-slate-800 dark:text-slate-100 font-bold">Mở Tìm Kiếm Toàn Cục</td>
                                <td class="p-3 text-slate-500 dark:text-slate-400">Tìm tức thì nhân viên, phòng ban, chi nhánh, mã quyết định từ bất kỳ đâu</td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                <td class="p-3">
                                    <kbd class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-indigo-700 dark:text-indigo-300 font-bold border border-slate-300 dark:border-slate-600 font-mono">F1</kbd>
                                </td>
                                <td class="p-3 text-slate-800 dark:text-slate-100 font-bold">Mở Trung Tâm Hướng Dẫn & Đào Tạo</td>
                                <td class="p-3 text-slate-500 dark:text-slate-400">Bật popup cẩm nang này bất kỳ lúc nào cần xem lại quy trình hoặc thuật ngữ</td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                <td class="p-3">
                                    <kbd class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-indigo-700 dark:text-indigo-300 font-bold border border-slate-300 dark:border-slate-600 font-mono">Alt + S</kbd>
                                </td>
                                <td class="p-3 text-slate-800 dark:text-slate-100 font-bold">Thu Gọn / Mở Rộng Sidebar (Mini Mode)</td>
                                <td class="p-3 text-slate-500 dark:text-slate-400">Tăng tối đa không gian màn hình khi làm việc với Bảng lương và Bảng công lớn</td>
                            </tr>
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40">
                                <td class="p-3">
                                    <kbd class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-700 text-indigo-700 dark:text-indigo-300 font-bold border border-slate-300 dark:border-slate-600 font-mono">Esc</kbd>
                                </td>
                                <td class="p-3 text-slate-800 dark:text-slate-100 font-bold">Đóng Cửa Sổ Popup / Modal Hiện Tại</td>
                                <td class="p-3 text-slate-500 dark:text-slate-400">Đóng nhanh Phiếu lương, Modal Help, Tìm kiếm hoặc Hộp thoại xác nhận</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TAB 3: QUY TRÌNH CHỐT LƯƠNG 4 BƯỚC -->
            <div id="helpTabContent-payroll_guide" class="hidden space-y-4">
                <div>
                    <h4 class="text-sm font-extrabold text-slate-900 dark:text-white mb-1 flex items-center gap-2">
                        <i class="fa-solid fa-list-check text-indigo-600 dark:text-indigo-400"></i>
                        Checklist 4 Bước Tính & Chốt Bảng Lương Chuẩn Doanh Nghiệp
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Thực hiện theo đúng thứ tự này mỗi cuối tháng để đảm bảo số liệu thanh toán chính xác 100%.
                    </p>
                </div>

                <div class="space-y-3 text-xs">
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-700 flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center flex-shrink-0 text-xs">1</span>
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">Bước 1: Kiểm tra & Chốt Bảng Chấm Công (Ngày 1 - cuối tháng)</div>
                            <div class="text-slate-600 dark:text-slate-300 mt-0.5">Xác nhận nhân viên đã nạp đủ dữ liệu quẹt thẻ, duyệt hết các đề xuất giải trình đi muộn/về sớm hoặc xin nghỉ phép năm.</div>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-700 flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center flex-shrink-0 text-xs">2</span>
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">Bước 2: Phê Duyệt Khen Thưởng & Biên Bản Bồi Thường Vi Phạm</div>
                            <div class="text-slate-600 dark:text-slate-300 mt-0.5">Kiểm tra các quyết định khen thưởng hoặc biên bản bồi thường thiệt hại (Điều 129 BLLĐ) đã được Ban Giám Đốc phê duyệt trạng thái "approved".</div>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-700 flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center flex-shrink-0 text-xs">3</span>
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">Bước 3: Nhấn Nút "Tính Bảng Lương" Tại Phân Hệ Lương</div>
                            <div class="text-slate-600 dark:text-slate-300 mt-0.5">Hệ thống sẽ tự động quét toàn bộ 84+ nhân sự, áp dụng mức giảm trừ 15.5M bản thân & 6.2M/NPT, biểu thuế 5 bậc, trần BHXH 20 lần mức tham chiếu.</div>
                        </div>
                    </div>

                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-700/40 border border-slate-200 dark:border-slate-700 flex items-start gap-3">
                        <span class="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center flex-shrink-0 text-xs">4</span>
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">Bước 4: Kiểm Tra Phiếu Lương Chi Tiết & Bấm "Chốt Lương"</div>
                            <div class="text-slate-600 dark:text-slate-300 mt-0.5">Xem thử các phiếu lương mẫu. Khi số liệu đã hoàn hảo, nhấn <strong>"Chốt Lương"</strong> để khóa vĩnh viễn bảng lương tháng đó, ngăn chặn mọi thay đổi chấm công hồi tố.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: CHÚ GIẢI THUẬT NGỮ & TRẠNG THÁI -->
            <div id="helpTabContent-glossary" class="hidden space-y-4">
                <div>
                    <h4 class="text-sm font-extrabold text-slate-900 dark:text-white mb-1 flex items-center gap-2">
                        <i class="fa-solid fa-book-bookmark text-indigo-600 dark:text-indigo-400"></i>
                        Từ Điển Thuật Ngữ Nghiệp Vụ & Bảng Mã Màu
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Quy chuẩn hóa cách hiểu giữa nhân sự, kế toán và cán bộ nhân viên trong tập đoàn.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-slate-700">
                        <div class="font-bold text-slate-900 dark:text-white">Lương GROSS (Tổng Thu Nhập)</div>
                        <div class="text-slate-500 dark:text-slate-400 mt-1">Là tổng thu nhập trước khi trích nộp các khoản bắt buộc = Lương thời gian + Phụ cấp HĐ + Thưởng + Tiền làm thêm giờ (OT).</div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-slate-700">
                        <div class="font-bold text-slate-900 dark:text-white">Lương NET (Thực Lĩnh)</div>
                        <div class="text-slate-500 dark:text-slate-400 mt-1">Số tiền thực tế chuyển vào tài khoản ngân hàng của nhân viên = GROSS - BHXH NLĐ (10.5%) - Thuế TNCN - Bồi thường kỷ luật.</div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-slate-700">
                        <div class="font-bold text-slate-900 dark:text-white">Giảm Trừ Gia Cảnh Luật 2026</div>
                        <div class="text-slate-500 dark:text-slate-400 mt-1">Mức giảm trừ cho bản thân là <strong>15.500.000đ/tháng</strong> và cho mỗi người phụ thuộc hợp pháp là <strong>6.200.000đ/người/tháng</strong>.</div>
                    </div>

                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-700/30 border border-slate-200 dark:border-slate-700">
                        <div class="font-bold text-slate-900 dark:text-white">Trần Đóng BHXH / BHYT (20 Lần)</div>
                        <div class="text-slate-500 dark:text-slate-400 mt-1">Mức tối đa đóng BHXH, BHYT bằng 20 lần mức lương tham chiếu (từ 01/07/2026: 2.530.000đ x 20 = 50.600.000đ). Thu nhập vượt quá không phải đóng thêm.</div>
                    </div>
                </div>

                <div class="pt-2">
                    <div class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Quy Ước Màu Sắc Trạng Thái (Badge Legends):</div>
                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="px-2.5 py-1 rounded-lg bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 font-bold flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-check"></i> Đã Duyệt / Chính Thức / Có Mặt / Đã Trả Lương
                        </span>
                        <span class="px-2.5 py-1 rounded-lg bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-700 font-bold flex items-center gap-1.5">
                            <i class="fa-solid fa-clock"></i> Chờ Xử Lý / Thử Việc / Đi Muộn / Chưa Chốt
                        </span>
                        <span class="px-2.5 py-1 rounded-lg bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 dark:border-rose-700 font-bold flex items-center gap-1.5">
                            <i class="fa-solid fa-circle-xmark"></i> Từ Chối / Kỷ Luật / Đã Thôi Việc / Vắng Mặt
                        </span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Footer Modal -->
        <div class="px-6 py-3.5 border-t border-slate-200 dark:border-slate-700 bg-slate-50/80 dark:bg-slate-900/40 flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 flex-shrink-0">
            <div>
                Mẹo: Bấm <kbd class="px-1.5 py-0.5 rounded bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 font-mono font-bold text-indigo-600 dark:text-indigo-400">Esc</kbd> để đóng cửa sổ
            </div>
            <button type="button" onclick="closeHelpModal()" class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold transition shadow-sm">
                Đã Hiểu & Đóng
            </button>
        </div>

    </div>
</div>

<script>
function openHelpModal() {
    const modal = document.getElementById('helpTrainingModal');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
}

function closeHelpModal() {
    const modal = document.getElementById('helpTrainingModal');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

function switchHelpTab(tabId) {
    // Hide all contents
    ['pipeline', 'shortcuts', 'payroll_guide', 'glossary'].forEach(id => {
        const content = document.getElementById('helpTabContent-' + id);
        const btn = document.getElementById('helpTabBtn-' + id);
        if (content) content.classList.add('hidden');
        if (btn) {
            btn.classList.remove('border-indigo-600', 'text-indigo-600', 'dark:text-indigo-400');
            btn.classList.add('border-transparent', 'text-slate-500', 'dark:text-slate-400');
        }
    });

    const activeContent = document.getElementById('helpTabContent-' + tabId);
    const activeBtn = document.getElementById('helpTabBtn-' + tabId);
    if (activeContent) activeContent.classList.remove('hidden');
    if (activeBtn) {
        activeBtn.classList.remove('border-transparent', 'text-slate-500', 'dark:text-slate-400');
        activeBtn.classList.add('border-indigo-600', 'text-indigo-600', 'dark:text-indigo-400');
    }
}

// Bắt phím tắt F1 và Esc toàn cục
document.addEventListener('keydown', function(e) {
    if (e.key === 'F1') {
        e.preventDefault();
        const modal = document.getElementById('helpTrainingModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeHelpModal();
        } else {
            openHelpModal();
        }
    } else if (e.key === 'Escape') {
        const modal = document.getElementById('helpTrainingModal');
        if (modal && !modal.classList.contains('hidden')) {
            closeHelpModal();
        }
    }
});
</script>
