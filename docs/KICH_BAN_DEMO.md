# KỊCH BẢN TRÌNH DIỄN HỆ THỐNG QUẢN TRỊ NHÂN SỰ CHUỖI BÁN LẺ
## WinCommerce People (WCM People Enterprise v4.0)
**Thời lượng trình diễn tối ưu:** 12 – 15 phút  
**Chế độ hỗ trợ:** Bật `DEMO_MODE=true` trong `.env` để sử dụng **1-Click Persona Switcher** trên Topbar, **Interactive Demo Presenter Bar** (thanh gợi ý thuyết minh & nút test nhanh 403 theo vai trò) và nút **Khôi Phục Dữ Liệu Demo** (tự động cập nhật chấm công đến ngày hiện tại, giữ 5 hợp đồng sắp hết hạn trong 30 ngày & mở khóa bảng lương mẫu).

---

## 1. TỔNG QUAN BỐI CẢNH VÀ ĐIỂM SÁNG KIẾN TRÚC

### 1.1. Bối cảnh doanh nghiệp
- **Quy mô mô phỏng:** Tập đoàn bán lẻ WinCommerce (Masan Group) gồm chuỗi WinMart, WinMart+, WiN.
- **Mạng lưới:** Trụ sở chính (TP.HCM), Văn phòng điều hành Miền Bắc (Hà Nội) và 4 Tổng kho phân phối (Central DC Bình Dương, Hải Phòng, Đà Nẵng, Cần Thơ).
- **Quy mô nhân sự demo:** **170 nhân sự đang làm việc** (172 hồ sơ lịch sử), **6 chi nhánh**, **16 khối phòng ban**, **6 vai trò nghiệp vụ chuẩn hóa** & **7 Persona trình diễn** bao phủ đầy đủ 4 phạm vi dữ liệu (`ALL`, `BRANCH`, `DEPARTMENT`, `SELF`).

### 1.2. Vấn đề cốt lõi đã giải quyết (Core Achievement)
- **Trước cải tiến:** Hệ thống chỉ kiểm tra *quyền chức năng (Action RBAC)* nhưng **thiếu kiểm soát phạm vi dữ liệu (Data Scope)** và chưa tách nhiệm vụ giữa HR Executive và C&B.
- **Sau cải tiến:** Hoàn thiện mô hình **Ma Trận Phân Quyền 2 Chiều: Chức năng (RBAC) × Phạm vi dữ liệu (Data Scope)** (`ALL`, `BRANCH`, `DEPARTMENT`, `SELF`). Tuân thủ **Nghị định 13/2023/NĐ-CP** về bảo vệ dữ liệu cá nhân, **Bộ luật Lao động 2019 (Điều 102 & 113)** và **Nghị quyết 110/2025/UBTVQH15** (giảm trừ gia cảnh 15,5 triệu/bản thân & 6,2 triệu/người phụ thuộc, biểu thuế TNCN 5 bậc).

---

## 2. KỊCH BẢN TRÌNH DIỄN CHI TIẾT THEO 7 PERSONA (6 VAI TRÒ)

```
[CEO] ──> [CHRO] ──> [HR Exec (Che Lương)] ──> [C&B] ──> [Branch Mgr (37 NV)] ──> [Dept Mgr (14 NV)] ──> [ESS (Self)]
```

---

### PERSONA 1: TỔNG GIÁM ĐỐC / SUPER ADMIN (CEO)
- **Tài khoản:** `admin@wincommerce.vn` &bull; **Họ tên:** Nguyễn Hoàng Nam
- **Quyền hạn & Phạm vi:** Super Admin &bull; **Data Scope:** `ALL`
- **Các bước thao tác:**
  1. Quan sát Dashboard Tổng Quan: **170 nhân sự đang làm việc**, **6 chi nhánh**, **16 khối phòng ban**, tỷ lệ có mặt hôm nay, và danh sách **Top Nhân Sự Xuất Sắc** theo kỳ KPI đang mở (`PERIOD-2026-Q4`).
  2. Mở **Quản Trị Hệ Thống -> Ma Trận Phân Quyền**: Trình bày cách cấu hình động quyền Action × Data Scope cho 6 vai trò.
  3. Mở **Nhân Sự -> Kế Hoạch Tối Ưu (Workforce Planning)**: Xem nhu cầu tuyển dụng/điều động liên chi nhánh và quỹ lương tổng hợp.

---

### PERSONA 2: GIÁM ĐỐC KHỐI NHÂN SỰ TOÀN QUYỀN (CHRO)
- **Tài khoản:** `phuong.le@wincommerce.vn` &bull; **Họ tên:** Lê Mai Phương
- **Quyền hạn & Phạm vi:** Admin Phân Hệ (`is_superadmin = 0`) &bull; **Data Scope:** `ALL`
- **Các bước thao tác:**
  1. Mở **Đánh Giá & KPI**: Trình diễn tiến độ đánh giá **Quý 4/2026**, lọc bộ phận và phê duyệt cấp HR.
  2. Mở **Pipeline Tuyển Dụng** & **Thuyên Chuyển Công Tác**.

---

### PERSONA 3: CHUYÊN VIÊN NHÂN SỰ (HR EXECUTIVE — PHÂN TÁCH NHIỆM VỤ SoD)
- **Tài khoản:** `giang.tran@wincommerce.vn` &bull; **Họ tên:** Trần Thị Hương Giang
- **Quyền hạn & Phạm vi:** Chuyên Viên Nhân Sự (Role #3) &bull; **Data Scope:** `ALL` (KHÔNG có quyền `payroll:view`)
- **Mục tiêu trình bày:** Minh họa nguyên tắc **Separation of Duties (SoD)**: quản lý toàn bộ hồ sơ nhân sự & hợp đồng toàn quốc nhưng không được xem bảng lương nhạy cảm.
- **Các bước thao tác:**
  1. Quan sát Dashboard: Thẻ Quỹ Lương được che hoàn toàn; Trung tâm Hành động hiển thị **Đánh Giá KPI Cần Duyệt** thay vì Kỳ Lương.
  2. Mở **Kế Hoạch Tối Ưu**: Chỉ số Quỹ Lương Cơ Bản hiển thị **Giới hạn quyền C&B** thay vì số tiền.
  3. Bấm nút **Test Chặn Lương 403** ngay trên *Demo Presenter Bar* -> Hệ thống chặn truy cập `modules/payroll/index.php` với mã **403**.

---

### PERSONA 4: CHUYÊN VIÊN TIỀN LƯƠNG & PHÚC LỢI (C&B SPECIALIST)
- **Tài khoản:** `cb@wincommerce.vn` &bull; **Họ tên:** Phạm Thu Thảo
- **Quyền hạn & Phạm vi:** Chuyên Viên C&B (Role #5) &bull; **Data Scope:** `ALL`
- **Các bước thao tác:**
  1. Mở **Lương & Đãi Ngộ -> Bảng Lương Tháng**: Tính bảng lương theo chấm công thực tế tháng hiện tại (tự động đồng bộ bởi `DemoSyncService`), khấu trừ BHXH (10,5% NLĐ / 21,5% NSDLĐ) theo từng Vùng lương I–IV, và thuế TNCN 5 bậc (NQ 110/2025).
  2. Mở **Xem Phiếu Lương (Payslip)** và thử **In Phiếu Lương (A4)** chuẩn Điều 102 BLLĐ 2019.
  3. Thử nút **Test Chặn RBAC 403** trên *Presenter Bar* -> C&B không thể truy cập Ma Trận Phân Quyền Hệ Thống.

---

### PERSONA 5: GIÁM ĐỐC CHI NHÁNH MIỀN BẮC (BRANCH MANAGER)
- **Tài khoản:** `mienbac@wincommerce.vn` &bull; **Họ tên:** Trần Quốc Bảo
- **Quyền hạn & Phạm vi:** Quản Lý Chi Nhánh (Role #2) &bull; **Data Scope:** `BRANCH` (**37 nhân sự** thuộc VP Điều Hành Miền Bắc)
- **Các bước thao tác:**
  1. Quan sát Dashboard: Quân số tự động thu gọn về **37 nhân sự** Miền Bắc; bảng Chấm công, Top Nhân Sự & Hợp Đồng sắp hết hạn chỉ tính trong chi nhánh.
  2. Mở **Kế Hoạch Tối Ưu**: Tab yêu cầu chi nhánh chỉ hiện đề xuất thuộc Miền Bắc.
  3. Bấm **Test Chặn 403 (Xem HS CEO)** trên *Presenter Bar* (`modules/employees/view.php?id=1`) -> Trả về **403 Giới Hạn Phạm Vi Dữ Liệu**.

---

### PERSONA 6: TRƯỞNG PHÒNG CNTT & SỐ HÓA (DEPARTMENT MANAGER)
- **Tài khoản:** `long.vu@wincommerce.vn` &bull; **Họ tên:** Vũ Hoàng Long
- **Quyền hạn & Phạm vi:** Quản Lý Phòng Ban (Role #6) &bull; **Data Scope:** `DEPARTMENT` (**14 nhân sự** thuộc Khối CNTT & Số Hóa)
- **Các bước thao tác:**
  1. Quan sát Dashboard & **Hồ Sơ Nhân Viên**: Chỉ thấy đúng **14 thành viên** Khối CNTT & Số Hóa; không thấy chi nhánh hay phòng ban khác.
  2. Mở **Trung Tâm Đề Xuất** & **Đánh Giá & KPI**: Duyệt đơn nghỉ phép/tăng ca và chấm điểm `manager_review` cho cấp dưới trực tiếp.
  3. Bấm **Test Chặn 403 (Xem HS CEO)** trên *Presenter Bar* -> Xác thực cô lập cấp phòng ban.

---

### PERSONA 7: NHÂN VIÊN TỰ PHỤC VỤ (EMPLOYEE SELF-SERVICE - ESS)
- **Tài khoản:** `ngoc.do@wincommerce.vn` &bull; **Họ tên:** Đỗ Thị Bích Ngọc (Tech Lead)
- **Quyền hạn & Phạm vi:** Nhân Viên (Role #4) &bull; **Data Scope:** `SELF`
- **Các bước thao tác:**
  1. Dashboard tự động chuyển sang **Giao diện ESS Cá Nhân**: Số công tích lũy, Quỹ phép năm (BLLĐ 2019 Điều 113), Phiếu lương Net gần nhất & Điểm KPI cá nhân.
  2. Kiểm tra **Chấm Công Cá Nhân**, **Phiếu Lương Của Tôi**, **Tạo Đề Xuất Nghỉ Phép** (tự động tính số ngày phép còn lại) và **Tự Đánh Giá KPI**.
  3. Bấm **Test Chặn 403 (Xem HS CEO)** -> Khẳng định bảo mật dữ liệu cá nhân theo Nghị định 13/2023/NĐ-CP.

---

## 3. CÔNG CỤ HỖ TRỢ LIVE DEMO & KHÔI PHỤC NHANH

- **Auto-Freshness (`DemoSyncService::ensureLiveDemoFreshness`)**: Tự động bổ sung chấm công từ đầu tháng đến ngày thuyết trình hiện tại (kể cả Thứ 7/Chủ Nhật) và giữ 5 hợp đồng xác định thời hạn sắp hết hạn trong 30 ngày tới để Dashboard không bao giờ bị trống số liệu theo thời gian thực.
- **1-Click Reset (`Khôi Phục Dữ Liệu Demo` trong Persona Switcher)**: Mở khóa bảng lương tháng hiện tại nếu vừa chạy chốt lương, đảm bảo kỳ KPI `PERIOD-2026-Q4` ở trạng thái `active`, khôi phục đơn đề xuất/điều động chờ duyệt mẫu và đồng bộ lại chấm công/hợp đồng.

---

## 4. BỘ CÂU HỎI THƯỜNG GẶP KHI BẢO VỆ (DEFENSE Q&A)

**Q1: Tại sao chọn mô hình Data Scope theo Enum (`all`, `branch`, `department`, `self`)?**  
*Trả lời:* Kiến trúc Lightweight Multi-Tenant Data Scoping tận dụng Index trực tiếp trên khóa ngoại `branch_id`, `department_id`, `employee_id`, đảm bảo hiệu năng truy vấn cao và dễ kiểm soát nhất quán qua `core/scope.php`.

**Q2: Làm sao chống thao túng URL (IDOR) để xem trộm hồ sơ hoặc bảng lương người khác?**  
*Trả lời:* Defense-in-Depth: Mọi endpoint nghiệp vụ kiểm tra cả quyền Action (`require_permission()`) lẫn phạm vi sở hữu (`require_employee_access($id)` / SQL scope filter), trả về HTTP 403 ngay khi vượt phạm vi.

**Q3: Chế độ DEMO_MODE và nút 1-Click Switch có rủi ro trên Production không?**  
*Trả lời:* Khi đặt `DEMO_MODE=false` trong `.env`, toàn bộ `demo_switch.php`, Persona Switcher, Interactive Presenter Bar và nút Khôi phục Demo đều bị vô hiệu hóa hoàn toàn.
