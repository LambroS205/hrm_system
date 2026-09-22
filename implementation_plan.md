# Kế Hoạch Mở Rộng Module HR: Tuyển Dụng, Khen Thưởng/Kỷ Luật & Hồ Sơ Nhân Viên

## Phân Tích Hiện Trạng

Sau khi kiểm tra toàn bộ codebase và database hiện tại, hệ thống **đang có** và **đang thiếu** như sau:

| Tính năng | Trạng thái | Chi tiết |
|---|---|---|
| **Hồ sơ nhân viên cơ bản** | ✅ Có | Họ tên, SĐT, email, CMND, avatar, phòng ban, chức vụ |
| **Chấm công** | ✅ Có | Module `attendance` với bảng chấm công đầy đủ |
| **Bảng tính lương** | ✅ Có (cơ bản) | Tính lương theo công, phụ cấp, khấu trừ. Nhưng **chưa có** quản lý thưởng/phạt riêng biệt |
| **Chi nhánh & Phòng ban** | ✅ Có | Multi-branch, org chart |
| **Thuyên chuyển công tác** | ✅ Có | Transfers & Planning |
| **Quy trình tuyển dụng** | ❌ Chưa có | Không có bảng candidates, jobs, pipelines |
| **Khen thưởng & Kỷ luật** | ❌ Chưa có | Không có bảng rewards/disciplines |
| **Hồ sơ nhân viên nâng cao** | ⚠️ Thiếu nhiều | Thiếu: học vấn, chứng chỉ, hợp đồng lao động, bảo hiểm, người thân |

## Proposed Changes

Triển khai **3 Module lớn** theo thứ tự ưu tiên:

---

### Phase 1: Module Khen Thưởng & Kỷ Luật (Rewards & Disciplines)

> **Ưu tiên cao nhất** — Module đơn giản nhất, tích hợp trực tiếp với payroll hiện có.

#### Database Migration

##### [NEW] `database/migration_rewards_disciplines.sql`

Tạo 2 bảng mới:

**Bảng `rewards`** (Khen thưởng):
| Cột | Kiểu | Mô tả |
|---|---|---|
| id | INT PK | ID tự tăng |
| employee_id | INT FK→employees | Nhân viên được khen thưởng |
| reward_type | ENUM | `bonus`, `certificate`, `promotion_bonus`, `achievement` |
| title | VARCHAR(255) | Tiêu đề khen thưởng |
| description | TEXT | Lý do chi tiết |
| amount | DECIMAL(15,2) | Số tiền thưởng (nếu có) |
| decision_number | VARCHAR(100) | Số quyết định |
| reward_date | DATE | Ngày khen thưởng |
| status | ENUM | `pending`, `approved`, `executed` |
| created_by | INT FK→users | Người tạo |
| approved_by | INT FK→users | Người phê duyệt |
| created_at | TIMESTAMP | Thời gian tạo |

**Bảng `disciplines`** (Kỷ luật):
| Cột | Kiểu | Mô tả |
|---|---|---|
| id | INT PK | ID tự tăng |
| employee_id | INT FK→employees | Nhân viên bị kỷ luật |
| discipline_type | ENUM | `warning`, `reprimand`, `salary_cut`, `demotion`, `termination` |
| title | VARCHAR(255) | Tiêu đề vi phạm |
| description | TEXT | Nội dung vi phạm chi tiết |
| penalty_amount | DECIMAL(15,2) | Mức phạt tiền (nếu có) |
| decision_number | VARCHAR(100) | Số quyết định |
| discipline_date | DATE | Ngày xử lý kỷ luật |
| status | ENUM | `pending`, `approved`, `executed` |
| created_by | INT FK→users | Người lập |
| approved_by | INT FK→users | Người phê duyệt |
| created_at | TIMESTAMP | Thời gian tạo |

Thêm quyền mới vào bảng `permissions`: `rewards.view/create/approve/delete` + `disciplines.view/create/approve/delete`

#### Module Files

##### [NEW] `modules/rewards/index.php`
- Danh sách khen thưởng với bộ lọc: nhân viên, loại, trạng thái, khoảng ngày
- Thống kê: tổng số khen thưởng, tổng tiền thưởng, đang chờ duyệt
- Actions: xem chi tiết, phê duyệt, xóa

##### [NEW] `modules/rewards/form.php`
- Form tạo/sửa quyết định khen thưởng
- Chọn nhân viên (dropdown), loại khen thưởng, số tiền, lý do
- Upload file đính kèm quyết định (optional)

##### [NEW] `modules/disciplines/index.php`
- Danh sách kỷ luật với bộ lọc tương tự
- Timeline vi phạm của từng nhân viên
- Thống kê: cảnh cáo, khiển trách, hạ lương

##### [NEW] `modules/disciplines/form.php`
- Form tạo/sửa biên bản kỷ luật
- Chọn mức độ vi phạm, mức phạt, ngày hiệu lực

#### Tích hợp

##### [MODIFY] `modules/employees/view.php`
- Thêm tab "Khen thưởng & Kỷ luật" vào trang chi tiết nhân viên
- Hiển thị timeline toàn bộ lịch sử khen thưởng/kỷ luật

##### [MODIFY] `includes/sidebar.php`
- Thêm mục "Khen Thưởng" và "Kỷ Luật" vào sidebar dưới nhóm "Quản Lý Nhân Sự"

---

### Phase 2: Module Tuyển Dụng (Recruitment Pipeline)

> Quy trình tuyển dụng chuyên nghiệp từ đăng tin → sàng lọc → phỏng vấn → tuyển dụng → chuyển thành nhân viên.

#### Database Migration

##### [NEW] `database/migration_recruitment.sql`

**Bảng `job_positions`** (Vị trí tuyển dụng):
| Cột | Kiểu | Mô tả |
|---|---|---|
| id | INT PK | |
| title | VARCHAR(255) | Tên vị trí tuyển dụng |
| department_id | INT FK | Phòng ban cần tuyển |
| branch_id | INT FK | Chi nhánh |
| quantity | INT | Số lượng cần tuyển |
| salary_range_min | DECIMAL | Mức lương tối thiểu |
| salary_range_max | DECIMAL | Mức lương tối đa |
| requirements | TEXT | Yêu cầu công việc |
| description | TEXT | Mô tả chi tiết |
| deadline | DATE | Hạn nộp hồ sơ |
| status | ENUM | `open`, `closed`, `paused`, `filled` |
| created_by | INT FK | Người tạo |
| created_at | TIMESTAMP | |

**Bảng `candidates`** (Hồ sơ ứng viên):
| Cột | Kiểu | Mô tả |
|---|---|---|
| id | INT PK | |
| job_position_id | INT FK | Vị trí ứng tuyển |
| fullname | VARCHAR(100) | Họ tên ứng viên |
| email | VARCHAR(100) | Email liên hệ |
| phone | VARCHAR(20) | Số điện thoại |
| gender | ENUM | Nam, Nu, Khac |
| birth_date | DATE | Ngày sinh |
| education | VARCHAR(255) | Trình độ học vấn |
| experience_years | INT | Số năm kinh nghiệm |
| cv_file | VARCHAR(255) | File CV đã upload |
| cover_letter | TEXT | Thư xin việc |
| source | ENUM | `website`, `referral`, `headhunt`, `job_board`, `other` |
| stage | ENUM | `applied`, `screening`, `interview`, `offer`, `hired`, `rejected` |
| rating | TINYINT(1-5) | Đánh giá ứng viên |
| interview_date | DATETIME | Lịch phỏng vấn |
| interview_notes | TEXT | Ghi chú phỏng vấn |
| hired_employee_id | INT FK | Liên kết khi đã tuyển thành nhân viên |
| created_at | TIMESTAMP | |

#### Module Files

##### [NEW] `modules/recruitment/index.php`
- **Dashboard tuyển dụng** với Kanban Board (Ứng tuyển → Sàng lọc → Phỏng vấn → Offer → Tuyển dụng)
- Thống kê: số ứng viên theo giai đoạn, tỷ lệ chuyển đổi, vị trí đang mở
- Bộ lọc: theo vị trí, chi nhánh, trạng thái

##### [NEW] `modules/recruitment/jobs.php`
- CRUD quản lý vị trí tuyển dụng
- Hiển thị số ứng viên đã ứng tuyển / chỉ tiêu tuyển

##### [NEW] `modules/recruitment/candidate_form.php`
- Form thêm/sửa hồ sơ ứng viên
- Upload CV (PDF, DOC)
- Chọn vị trí ứng tuyển, nguồn tuyển dụng

##### [NEW] `modules/recruitment/candidate_view.php`
- Chi tiết hồ sơ ứng viên
- Lịch sử thay đổi trạng thái (pipeline stages)
- Nút chuyển giai đoạn: "Lên sàng lọc", "Đặt lịch phỏng vấn", "Gửi offer", "Tuyển dụng"
- **Nút "Tuyển dụng → Tạo hồ sơ nhân viên"**: Tự động chuyển dữ liệu ứng viên sang bảng `employees`

##### [MODIFY] `includes/sidebar.php`
- Thêm nhóm "Tuyển Dụng" với menu: Dashboard Tuyển Dụng, Vị Trí Tuyển

---

### Phase 3: Hồ Sơ Nhân Viên Nâng Cao (Enhanced Employee Profiles)

> Mở rộng hồ sơ nhân viên với đầy đủ thông tin: học vấn, chứng chỉ, hợp đồng, bảo hiểm, người thân.

#### Database Migration

##### [NEW] `database/migration_employee_profiles.sql`

**Bảng `employee_education`** (Trình độ học vấn):
| Cột | Kiểu | Mô tả |
|---|---|---|
| id | INT PK | |
| employee_id | INT FK | |
| degree | VARCHAR(100) | Bằng cấp (Cử nhân, Thạc sĩ...) |
| institution | VARCHAR(200) | Trường / Tổ chức đào tạo |
| major | VARCHAR(150) | Chuyên ngành |
| graduation_year | YEAR | Năm tốt nghiệp |
| gpa | DECIMAL(3,2) | Điểm trung bình |
| certificate_file | VARCHAR(255) | File bằng cấp scan |

**Bảng `employee_certificates`** (Chứng chỉ chuyên môn):
| Cột | Kiểu | Mô tả |
|---|---|---|
| id | INT PK | |
| employee_id | INT FK | |
| name | VARCHAR(200) | Tên chứng chỉ |
| issuer | VARCHAR(200) | Tổ chức cấp |
| issue_date | DATE | Ngày cấp |
| expiry_date | DATE | Ngày hết hạn (NULL = vĩnh viễn) |
| certificate_file | VARCHAR(255) | File scan chứng chỉ |

**Bảng `employee_contracts`** (Hợp đồng lao động):
| Cột | Kiểu | Mô tả |
|---|---|---|
| id | INT PK | |
| employee_id | INT FK | |
| contract_number | VARCHAR(50) | Số hợp đồng |
| contract_type | ENUM | `probation`, `fixed_term`, `indefinite` |
| start_date | DATE | Ngày bắt đầu |
| end_date | DATE | Ngày kết thúc (NULL = vô thời hạn) |
| base_salary | DECIMAL(15,2) | Mức lương ghi trên hợp đồng |
| contract_file | VARCHAR(255) | File hợp đồng scan |
| status | ENUM | `active`, `expired`, `terminated` |

**Bảng `employee_insurance`** (Bảo hiểm):
| Cột | Kiểu | Mô tả |
|---|---|---|
| id | INT PK | |
| employee_id | INT FK | |
| insurance_type | ENUM | `social` (BHXH), `health` (BHYT), `unemployment` (BHTN) |
| insurance_number | VARCHAR(50) | Số sổ bảo hiểm |
| start_date | DATE | Ngày bắt đầu tham gia |
| status | ENUM | `active`, `suspended`, `closed` |

**Bảng `employee_dependents`** (Người thân / Phụ thuộc):
| Cột | Kiểu | Mô tả |
|---|---|---|
| id | INT PK | |
| employee_id | INT FK | |
| fullname | VARCHAR(100) | Họ tên |
| relationship | ENUM | `spouse`, `child`, `parent`, `sibling` |
| birth_date | DATE | |
| phone | VARCHAR(20) | |
| is_emergency_contact | TINYINT | Là liên hệ khẩn cấp? |

#### Module Files

##### [MODIFY] `modules/employees/view.php`
- Mở rộng trang chi tiết nhân viên thành **hệ thống tabs**:
  - Tab 1: Thông tin cá nhân (hiện tại)
  - Tab 2: Học vấn & Chứng chỉ
  - Tab 3: Hợp đồng lao động
  - Tab 4: Bảo hiểm
  - Tab 5: Người thân & Liên hệ khẩn cấp
  - Tab 6: Khen thưởng & Kỷ luật (từ Phase 1)
  - Tab 7: Lịch sử thuyên chuyển (hiện tại)

##### [NEW] `modules/employees/tabs/education.php`
- CRUD inline cho trình độ học vấn và chứng chỉ
- Upload file bằng cấp

##### [NEW] `modules/employees/tabs/contracts.php`
- CRUD quản lý hợp đồng lao động
- Cảnh báo hợp đồng sắp hết hạn

##### [NEW] `modules/employees/tabs/insurance.php`
- Quản lý sổ bảo hiểm BHXH/BHYT/BHTN

##### [NEW] `modules/employees/tabs/dependents.php`
- Quản lý thông tin người thân và liên hệ khẩn cấp

---

## User Review Required

> [!IMPORTANT]
> **Về thứ tự triển khai**: Kế hoạch đề xuất bắt đầu từ Phase 1 (Khen thưởng/Kỷ luật) vì module này đơn giản nhất và bổ sung trực tiếp cho payroll hiện có. Bạn có muốn thay đổi thứ tự ưu tiên không?

> [!IMPORTANT]
> **Về phạm vi Phase 2 (Tuyển dụng)**: Bạn muốn Kanban Board đơn giản (chuyển trạng thái bằng nút bấm) hay cần kéo-thả (drag & drop) giữa các cột? Kéo-thả sẽ cần thêm thư viện JavaScript.

> [!WARNING]
> **Về upload file**: Phase 2 và 3 đều cần upload file (CV, bằng cấp, hợp đồng). Hiện tại hệ thống chỉ hỗ trợ upload ảnh avatar. Cần mở rộng hỗ trợ thêm PDF, DOC và tăng giới hạn file size.

## Verification Plan

### Automated Tests
- Kiểm tra PHP syntax toàn bộ file mới: `find modules -name "*.php" -exec php -l {} \;`
- Chạy migration SQL kiểm tra không có lỗi cú pháp
- Script tự động test CSRF trên tất cả form POST mới

### Manual Verification
- Tạo mới / sửa / xóa khen thưởng → kiểm tra DB
- Tạo vị trí tuyển dụng → thêm ứng viên → chuyển stages → tuyển dụng → kiểm tra tự động tạo employee
- Thêm học vấn, chứng chỉ, hợp đồng → kiểm tra hiển thị trên trang view employee
- Kiểm tra phân quyền: user không có quyền → bị chặn truy cập
- Kiểm tra giao diện responsive trên mobile
