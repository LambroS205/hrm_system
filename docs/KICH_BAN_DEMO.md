# KỊCH BẢN TRÌNH DIỄN HỆ THỐNG QUẢN TRỊ NHÂN SỰ CHUỖI BÁN LẺ
## WinCommerce People (WCM People Enterprise v4.0)
**Thời lượng trình diễn tối ưu:** 12 – 15 phút  
**Chế độ hỗ trợ:** Bật `DEMO_MODE=true` & sử dụng thanh chuyển nhanh **1-Click Persona Switcher** trên Topbar.

---

## 1. TỔNG QUAN BỐI CẢNH VÀ ĐIỂM SÁNG KIẾN TRÚC

### 1.1. Bối cảnh doanh nghiệp
- **Quy mô mô phỏng:** Tập đoàn bán lẻ WinCommerce (Masan Group) gồm chuỗi WinMart, WinMart+, WiN.
- **Mạng lưới:** Trụ sở chính (TP.HCM), Văn phòng điều hành Miền Bắc (Hà Nội) và 4 Tổng kho phân phối (Central DC Bình Dương, Hải Phòng, Đà Nẵng, Cần Thơ).
- **Quy mô nhân sự demo:** 86 nhân sự, 5 vai trò nghiệp vụ chuẩn hóa.

### 1.2. Vấn đề cốt lõi đã giải quyết (Core Achievement)
- **Trước cải tiến:** Hệ thống chỉ kiểm tra *quyền chức năng (Action RBAC)* nhưng **thiếu hoàn toàn kiểm soát phạm vi dữ liệu (Data Scope)**. Giám đốc chi nhánh Miền Bắc xem được lương CEO toàn tập đoàn; Nhân viên thấy đề xuất tăng lương nhạy cảm của đồng nghiệp; Nhân viên không có cổng tự phục vụ tra cứu phiếu lương và quỹ phép.
- **Sau cải tiến:** Hoàn thiện mô hình **Ma Trận Phân Quyền 2 Chiều: Chức năng (RBAC) × Phạm vi dữ liệu (Data Scope)** (`ALL`, `BRANCH`, `DEPARTMENT`, `SELF`). Đáp ứng nghiêm ngặt **Nghị định 13/2023/NĐ-CP** về bảo vệ dữ liệu cá nhân và **Bộ luật Lao động 2019 (Điều 102 & 113)**.

---

## 2. KỊCH BẢN TRÌNH DIỄN CHI TIẾT THEO 5 PERSONA

```
[CEO / Super Admin] ──> [CHRO] ──> [C&B Specialist] ──> [Branch Manager] ──> [Employee (ESS)]
   (2 phút)              (3 phút)        (3 phút)             (3 phút)              (3 phút)
```

---

### PERSONA 1: TỔNG GIÁM ĐỐC / SUPER ADMIN (CEO)
- **Tài khoản:** `admin@wincommerce.vn` &bull; **Họ tên:** Nguyễn Hoàng Nam
- **Quyền hạn & Phạm vi:** Toàn quyền hệ thống &bull; **Data Scope:** `ALL`
- **Mục tiêu trình bày:** Thể hiện bức tranh toàn cảnh tập đoàn, dashboard chỉ huy chiến lược và ma trận phân quyền hệ thống.

#### Các bước thao tác & Lời thuyết minh:
1. **Đăng nhập hoặc chọn Persona CEO:**
   - *Thuyết minh:* "Kính thưa Hội đồng, bắt đầu với vai trò Tổng Giám Đốc điều hành chuỗi, Dashboard cung cấp góc nhìn toàn cảnh về quy mô 86 nhân sự trải dài khắp 6 chi nhánh và tổng kho toàn quốc."
2. **Trình diễn các chỉ số KPI trọng điểm:**
   - Chỉ vào: **Nhân sự đang làm việc**, **Tỷ lệ có mặt hôm nay**, **Tổng quỹ lương tháng**, **Cơ cấu nhân sự theo khối phòng ban**.
3. **Mở Ma Trận Phân Quyền (Menu: Quản Trị Hệ Thống -> Ma Trận Phân Quyền):**
   - *Thuyết minh:* "Điểm đặc biệt của hệ thống là ma trận phân quyền 2 chiều: Quản trị viên không chỉ cấp các quyền View/Create/Edit/Delete cho từng module, mà còn có thể cấu hình trực tiếp **Phạm vi dữ liệu (Data Scope)** cho từng vai trò: Toàn tập đoàn (All), Chi nhánh (Branch), Khối phòng ban (Dept) hay Cá nhân (Self)."

---

### PERSONA 2: GIÁM ĐỐC KHỐI NHÂN SỰ TOÀN QUYỀN (CHRO)
- **Tài khoản:** `phuong.le@wincommerce.vn` &bull; **Họ tên:** Lê Mai Phương
- **Quyền hạn & Phạm vi:** Giám Đốc Nhân Sự (HR Director) &bull; **Data Scope:** `ALL` &bull; `is_superadmin = 0`
- **Mục tiêu trình bày:** Phân biệt rõ giữa *Admin kỹ thuật hệ thống (Super Admin)* và *Lãnh đạo nghiệp vụ nhân sự cấp cao (CHRO)*.

#### Các bước thao tác & Lời thuyết minh:
1. **Chuyển Persona sang `Lê Mai Phương (CHRO)`:**
   - Quan sát Topbar: Badge hiển thị `Admin Phân Hệ [Scope: ALL]`, không còn biểu tượng vương miện Super Admin.
   - *Thuyết minh:* "CHRO có toàn quyền nghiệp vụ nhân sự toàn tập đoàn nhưng không can thiệp vào các thông số hạ tầng IT, đảm bảo nguyên tắc phân nhiệm (Separation of Duties)."
2. **Mở Module Tuyển Dụng & Sơ Đồ Tổ Chức:**
   - Trình diễn Pipeline ứng viên chuỗi bán lẻ, điều động nhân sự chiến lược giữa Trụ sở chính và các Trung tâm phân phối (DC).
3. **Mở Module Đánh Giá & KPI:**
   - Xem ma trận tiến độ hoàn thành KPI của các khối phòng ban, phê duyệt tổng kết hiệu suất chuỗi.

---

### PERSONA 3: CHUYÊN VIÊN TIỀN LƯƠNG & PHÚC LỢI (C&B SPECIALIST)
- **Tài khoản:** `cb@wincommerce.vn` &bull; **Họ tên:** Phạm Thu Thảo
- **Quyền hạn & Phạm vi:** Chuyên Viên C&B Chuỗi &bull; **Data Scope:** `ALL`
- **Mục tiêu trình bày:** Minh chứng tính chính xác của nghiệp vụ tính lương theo BLLĐ 2019, đóng bảo hiểm 10.5% NLĐ - 21.5% NSDLĐ, và thuế TNCN lũy tiến từng phần.

#### Các bước thao tác & Lời thuyết minh:
1. **Chuyển Persona sang `Phạm Thu Thảo (C&B)`:**
   - *Thuyết minh:* "Tiếp theo là vai trò Chuyên viên C&B - người trực tiếp vận hành bảng tính lương và đãi ngộ toàn chuỗi siêu thị."
2. **Mở Menu: Lương & Đãi Ngộ -> Bảng Lương Tháng:**
   - Xem bảng quyết toán lương tháng hiện tại.
   - Bấm nút **"Xem Phiếu Lương (Payslip)"** của một nhân viên bất kỳ.
   - *Thuyết minh:* "Phiếu lương được chuẩn hóa theo Điều 102 Bộ luật Lao động 2019: Thể hiện chi tiết tiền lương cơ bản, lương thời gian, tiền làm thêm giờ (OT), tiền thưởng, các khoản khấu trừ BHXH (8%), BHYT (1.5%), BHTN (1%), và Thuế TNCN chi tiết từng bậc."
   - Bấm thử nút **In Phiếu Lương (A4)** -> Mở bản in chuẩn sẵn sàng xuất PDF.

---

### PERSONA 4: GIÁM ĐỐC CHI NHÁNH / VẬN HÀNH (BRANCH MANAGER)
- **Tài khoản:** `mienbac@wincommerce.vn` &bull; **Họ tên:** Trần Quốc Bảo
- **Quyền hạn & Phạm vi:** Giám Đốc Chi Nhánh &bull; **Data Scope:** `BRANCH` (VP Điều Hành Miền Bắc - 15 nhân sự)
- **Mục tiêu trình bày:** **ĐÂY LÀ ĐIỂM ĂN ĐIỂM CAO NHẤT:** Chứng minh hệ thống đã chặn lộ dữ liệu chi nhánh khác một cách triệt để.

#### Các bước thao tác & Lời thuyết minh:
1. **Chuyển Persona sang `Trần Quốc Bảo (GĐ Chi Nhánh Miền Bắc)`:**
   - *Thuyết minh:* "Khi Giám đốc Chi nhánh Miền Bắc đăng nhập, quý Hội đồng có thể quan sát thấy sự thay đổi ngay trên Topbar: Badge hiển thị `[Scope: BRANCH]`."
2. **Quan sát Dashboard Chi Nhánh:**
   - Tiêu đề Dashboard: *"Trung Tâm Điều Hành Nhân Sự • Văn Phòng Điều Hành Miền Bắc - Hà Nội"*.
   - Quân số hiển thị: **Đúng 15 nhân sự** (thay vì 86 của toàn tập đoàn).
   - Tỷ lệ chấm công và biểu đồ cơ cấu: **Chỉ tính trên 15 nhân sự thuộc chi nhánh Hà Nội**.
3. **Mở Menu: Hồ Sơ Nhân Viên:**
   - Danh sách chỉ hiện đúng 15 nhân viên thuộc chi nhánh Miền Bắc. Không hiển thị nhân sự Trụ sở chính TP.HCM hay các kho Mekong, Cần Thơ.
   - *Thử nghiệm kiểm tra an ninh (Security Demo):* Thử nhập trực tiếp URL xem nhân viên ID = 1 (Tổng Giám đốc ở TP.HCM) -> Hệ thống lập tức trả về **Trang 403: Giới Hạn Phạm Vi Dữ Liệu**.
4. **Mở Menu: Trung Tâm Đề Xuất:**
   - Badge và danh sách đề xuất chỉ hiển thị các đơn xin nghỉ phép, tăng ca của nhân sự Miền Bắc.
   - Giám đốc chỉ duyệt được đơn của cấp dưới mình, không thể duyệt đơn của chi nhánh khác.

---

### PERSONA 5: NHÂN VIÊN TỰ PHỤC VỤ (EMPLOYEE SELF-SERVICE - ESS)
- **Tài khoản:** `ngoc.do@wincommerce.vn` &bull; **Họ tên:** Đỗ Thị Bích Ngọc (Tech Lead)
- **Quyền hạn & Phạm vi:** Nhân Viên Tác Nghiệp &bull; **Data Scope:** `SELF`
- **Mục tiêu trình bày:** Cổng tự phục vụ hiện đại, bảo mật thông tin thu nhập, quản lý ngày công và quỹ phép năm theo luật.

#### Các bước thao tác & Lời thuyết minh:
1. **Chuyển Persona sang `Đỗ Thị Bích Ngọc (Nhân Viên)`:**
   - Dashboard lập tức chuyển sang **Giao diện Tự Phục Vụ Cá Nhân (ESS Dashboard)**.
   - *Thuyết minh:* "Đối với nhân viên, hệ thống hoàn toàn ẩn đi các con số tài chính nhạy cảm của công ty. Nhân viên được chào đón bằng Dashboard cá nhân trực quan."
2. **Trình diễn 4 khối thông tin cá nhân:**
   - **Số ngày công đã tích lũy trong tháng** & Trạng thái Check-in hôm nay.
   - **Quỹ phép năm:** Thể hiện rõ số ngày được hưởng theo thâm niên (BLLĐ 2019 Điều 113: 12 ngày + 1 ngày/5 năm), số ngày đã nghỉ, và số ngày còn lại.
   - **Phiếu lương cá nhân gần nhất:** Thực lĩnh Net và nút xem chi tiết.
   - **Điểm KPI cá nhân.**
3. **Mở Menu: Cá Nhân (ESS) -> Phiếu Lương Của Tôi:**
   - *Thuyết minh:* "Nhân viên có thể chủ động tra cứu lịch sử phiếu lương các tháng trong năm, xem bảng kê chi tiết bảo hiểm, thuế TNCN và tự in phiếu lương của mình mà không cần hỏi phòng Nhân sự."
4. **Mở Menu: Tạo Đề Xuất Mới (Xin Nghỉ Phép):**
   - Chọn loại đề xuất `Nghỉ Phép`.
   - Hệ thống tự động hiển thị hộp thông tin: **Khả dụng còn lại X ngày phép**.
   - Dropdown người đề xuất tự động khóa cứng theo chính nhân viên, không cho phép gửi đơn hộ người khác.
5. **Mở Menu: Hồ Sơ & Bảo Mật (Profile):**
   - Xem thẻ thông tin hợp đồng lao động, số sổ BHXH, số người phụ thuộc giảm trừ gia cảnh.

---

## 3. BẢNG SO SÁNH TRƯỚC VÀ SAU CẢI TIẾN (TRÌNH CHIẾU SLIDE KẾT LUẬN)

| Tiêu chí đánh giá | Phiên bản cũ (Trước) | Phiên bản nâng cấp (Hiện tại) | Lợi ích cho Doanh nghiệp |
|---|---|---|---|
| **Mô hình Phân quyền** | 1 chiều (Chức năng Action) | **2 chiều: Chức năng (RBAC) × Phạm vi (Data Scope)** | Đạt chuẩn an toàn thông tin doanh nghiệp lớn |
| **Bảo mật Dữ liệu Chi nhánh** | Bị lộ: GĐ chi nhánh xem được lương & hồ sơ toàn quốc | **Giới hạn nghiêm ngặt theo Branch Scope** | Tuân thủ Nghị định 13/2023/NĐ-CP |
| **Trải nghiệm Nhân viên** | Không có ESS; Dashboard hiện quỹ lương công ty | **Dashboard ESS cá nhân hóa, Phiếu lương cá nhân** | Nâng cao gắn kết nhân viên, minh bạch chế độ |
| **Quản lý Quỹ Phép** | Chưa có logic tính ngày phép còn lại | **Tự động tính phép theo BLLĐ 2019 Điều 113** | Chuẩn hóa pháp lý, tránh tranh chấp lao động |
| **Vai trò C&B** | Gộp chung thiếu quyền bảng lương | **Tách biệt chuyên trách C&B Specialist** | Đúng cơ cấu tổ chức phòng HR thực tế |
| **Công cụ Demo Hội đồng** | Phải logout/login gõ password nhiều lần | **1-Click Persona Switcher trên Topbar** | Trình diễn mượt mà, chuyên nghiệp, thuyết phục |

---

## 4. BỘ CÂU HỎI THƯỜNG GẶP KHI BẢO VỆ (DEFENSE Q&A)

**Q1: Tại sao lại chọn mô hình Data Scope theo Enum ('all', 'branch', 'department', 'self') thay vì lưu bảng trung gian?**  
*Trả lời:* "Mô hình này là kiến trúc tối ưu (Lightweight Multi-Tenant Data Scoping) thường thấy trong các hệ thống ERP/HRM doanh nghiệp (như Odoo, SAP). Nó đảm bảo tốc độ truy vấn cực nhanh thông qua Index trực tiếp trên các khóa ngoại `branch_id`, `department_id`, và `employee_id`, không gây suy giảm hiệu năng khi mở rộng dữ liệu hàng chục nghìn nhân viên."

**Q2: Làm sao đảm bảo nhân viên không sửa URL trên thanh địa chỉ để xem trộm hồ sơ người khác?**  
*Trả lời:* "Hệ thống áp dụng cơ chế phòng vệ theo chiều sâu (Defense-in-Depth): Mọi endpoint nghiệp vụ (`view.php`, `form.php`, `proposals/view.php`) đều được chặn tầng controller bằng hàm `require_employee_access($id)`. Nếu ID mục tiêu nằm ngoài phạm vi được phân bổ, hệ thống lập tức ngắt kết nối và trả về mã lỗi HTTP 403 với giao diện cảnh báo vi phạm bảo mật."

**Q3: Tính năng DEMO_MODE có an toàn trên môi trường Production không?**  
*Trả lời:* "Hoàn toàn an toàn. Tính năng này được cô lập nghiêm ngặt qua biến môi trường `DEMO_MODE=false` trong file `.env`. Khi tắt cờ này, toàn bộ thanh Persona Switcher và endpoint `demo_switch.php` bị vô hiệu hóa hoàn toàn, người dùng bắt buộc phải xác thực thông qua mã hóa Bcrypt tiêu chuẩn."

