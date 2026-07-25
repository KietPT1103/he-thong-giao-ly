# Rà soát hiện trạng hệ thống

Ngày rà soát: 25/07/2026

## 1. Hiện trạng

Repository là một ứng dụng Laravel 12 + Vue 3 + TypeScript, sử dụng Sanctum,
Spatie Permission, Pinia, Vue Router, Axios, Tailwind CSS và Vite. Ứng dụng hiện
có một SPA, một layout dùng chung và sáu mục dành cho giáo lý viên.

### Phần đã dùng API thật

| Màn hình/luồng | API | Ghi chú |
| --- | --- | --- |
| Đăng nhập, đăng xuất, khôi phục phiên | `/api/auth/*` | Sanctum session; có kiểm tra tài khoản bị khóa |
| Dashboard giáo lý viên | `GET /api/teacher/dashboard` | Chỉ truy cập được khi có hồ sơ giáo lý viên |
| Lớp được phân công | `GET /api/teachers/me/classes` | Backend đã phân trang, UI cũ chưa sử dụng |
| Chi tiết lớp | `GET /api/classes/{class}` | Có policy |
| Thiếu nhi trong lớp | `GET /api/classes/{class}/children` | Có policy và phân trang |
| Phiên điểm danh lớp | `/api/classes/{class}/attendance-sessions` | Có tạo/xem phiên |
| Ghi nhận điểm danh | `/api/attendance-sessions/{session}/mark` | Có transaction trong service |

### Phần đang dùng dữ liệu demo

| Màn hình | Nguồn demo/vấn đề |
| --- | --- |
| Lớp học | `resources/js/data/demo.js` |
| Thiếu nhi | `resources/js/data/demo.js` |
| Điểm danh | Sao chép mảng demo; nút lưu chỉ bật thông báo client |
| Bài tập | Mảng literal trong template; chưa có backend |
| Lịch học | Dùng lại nguyên component Lớp học |
| Landing page | Lịch, tin tức và nhiều liên kết `href="#"` là nội dung tĩnh |

### Backend/database đã có nhưng frontend chưa sử dụng

- Hồ sơ phụ huynh và liên kết phụ huynh–thiếu nhi.
- Đơn xin nghỉ.
- Thông báo và người nhận thông báo.
- Nhật ký hoạt động.
- Lịch học của lớp (chỉ dashboard hiển thị gián tiếp).
- API danh sách lớp, chi tiết lớp, danh sách thiếu nhi và toàn bộ API điểm danh.
- API quản lý phiên đăng nhập và đổi/khôi phục mật khẩu.

### Module mới chỉ có migration/model

- Phụ huynh, đơn xin nghỉ, thông báo, nhật ký hoạt động.
- Niên khóa, khối giáo lý, phòng học và lịch lớp chưa có API quản trị/UI riêng.

### Thành phần có thể tái sử dụng

- Axios client và auth store.
- `ApiController`, API Resources, Policies và `AttendanceService`.
- Các pattern loading/error/empty đang có trong dashboard có thể chuẩn hóa thành
  `LoadingSkeleton`, `ErrorState`, `EmptyState`.
- Shell sidebar/header hiện tại có thể tách thành layout điều khiển bởi cấu hình
  navigation theo role.

## 2. Lỗi UX

- Một sidebar ghi cố định “Không gian giáo lý viên” được dùng cho mọi role.
- Không có breadcrumb, search, notification, profile menu hoặc trạng thái collapse.
- `/lich-hoc` hiển thị trang lớp, làm người dùng hiểu sai ngữ cảnh.
- Nút “Tạo/Giao bài tập” không có action; nút “Lưu điểm danh” báo thành công dù
  không gọi API.
- Link “Xem tất cả”, mạng xã hội, hướng dẫn và chính sách trên landing page dùng
  `href="#"`.
- Các bảng/danh sách thiếu pagination, filter thực và trạng thái empty/error.
- Chưa có điều hướng riêng cho admin, phụ huynh, thiếu nhi; chưa có bottom
  navigation trên mobile.

## 3. Lỗi nghiệp vụ và kỹ thuật

- Frontend chưa có route guard theo role/permission; chỉ kiểm tra đăng nhập.
- Login luôn chuyển tới `/dashboard`, không chuyển dashboard theo role.
- Policy admin đang suy ra quyền quản trị qua permission `manage-users` thay vì
  gate quản trị rõ ràng.
- API tạo phiên điểm danh dùng validation trực tiếp trong controller, chưa dùng
  Form Request; message backend còn tiếng Anh.
- `ActivityLog` tắt timestamps trong khi migration tạo cả `created_at` và
  `updated_at`.
- Cấu trúc mới chỉ có giáo xứ, chưa có bảng giáo phận.
- `children.code` chỉ unique theo giáo xứ, chưa có `child_code` toàn hệ thống và
  chưa có QR token.
- Chưa có bài học, bài tập, bài nộp, Thánh lễ, QR, điểm thưởng, huy hiệu và tin tức.
- Seeder hiện có 1 giáo xứ, 6 lớp, 8 giáo lý viên, 20 phụ huynh và 30 thiếu nhi,
  thấp hơn dữ liệu nghiệm thu; chưa có dữ liệu các module mới.

## 4. Route thiếu hoặc sai

- Thiếu toàn bộ nhóm `/admin/*`, `/parent/*`, `/child/*` và phần lớn `/teacher/*`
  theo đặc tả.
- Các route cũ `/dashboard`, `/lop-hoc`, `/diem-danh`, `/bai-tap`, `/thieu-nhi`,
  `/lich-hoc` không thể hiện role.
- `/lich-hoc` trỏ sai component.
- Chưa có route public `/news`, `/events`.
- Backend mới có nhóm auth, teacher/classes và attendance; chưa có các REST
  resource còn lại trong mục 12 của đặc tả.

## 5. API và module còn thiếu

Ưu tiên cao: admin dashboard, dioceses/parishes, users/teachers/parents/children,
classes/schedules, mass events/sessions, QR check-in/out, assignments/submissions,
points, leave requests, announcements, reports. Mỗi nhóm cần Form Request,
Resource, Policy, pagination/filter/sort whitelist và activity log.

## 6. Kế hoạch tái cấu trúc

1. **Phase 1 – Audit và navigation:** tạo tài liệu này và button matrix; route
   theo role; navigation riêng; role redirect/guard; tách lịch học; nối các UI
   đang demo vào API hiện có; ẩn/disabled CTA chưa có backend.
2. **Phase 2 – Admin foundation:** bổ sung giáo phận, CRUD quản trị tổ chức/nhân
   sự/lớp, dashboard và policies theo phạm vi.
3. **Phase 3 – Mass và QR:** schema token có thể rotate/revoke, sự kiện/phiên,
   scanner camera + fallback, transaction chống quét trùng.
4. **Phase 4 – Learning:** bài học, bài tập, câu hỏi, bài nộp/chấm bài và điểm.
5. **Phase 5 – Parent và Child:** dashboard theo dữ liệu liên kết, lịch sử tham
   dự, QR cá nhân, điểm/huy hiệu.
6. **Phase 6 – Communication:** thông báo, tin tức, sự kiện và landing page dùng
   nội dung thật.
7. **Phase 7 – Test và tài liệu:** seed đủ dữ liệu nghiệm thu, feature tests,
   API/user-flow/permission docs và kiểm thử responsive/browser.

Không module nào được đánh dấu hoàn thành nếu còn thiếu database, policy, API,
validation, UI state và test cơ bản.
