# Ma trận hành động button

Trạng thái tại thời điểm audit; cập nhật sau mỗi phase.

| Page | Button | Action | Route/API | Permission | Status |
| --- | --- | --- | --- | --- | --- |
| Dashboard Admin | Quản lý giáo xứ | Điều hướng | `/admin/parishes` | Admin / `access-admin` | Hoạt động; CRUD thuộc Phase 2 |
| Dashboard Admin | Thử lại | Tải lại số liệu | `GET /api/admin/dashboard` | Admin / `access-admin` | Hoạt động |
| Danh mục Admin | Tìm kiếm | Lọc dữ liệu thật | `GET /api/admin/{module}?search=` | Admin / `access-admin` | Hoạt động |
| Danh mục Admin | Tải lại | Gọi lại API trang hiện tại | `GET /api/admin/{module}` | Admin / `access-admin` | Hoạt động |
| Danh mục Admin | Trang trước/sau | Phân trang API | `GET /api/admin/{module}?page=` | Admin / `access-admin` | Hoạt động |
| Login | Đăng nhập | Submit form | `POST /api/auth/login` | Public | Hoạt động |
| Login | Hiện/ẩn mật khẩu | Toggle input | Client | Public | Hoạt động |
| App shell | Đăng xuất | Gọi API và về login | `POST /api/auth/logout` | Authenticated | Hoạt động |
| Dashboard giáo lý viên | Điểm danh nhanh | Điều hướng | `/teacher/attendance` | `view-attendance` | Hoạt động |
| Danh sách lớp | Xem lớp | Điều hướng | `/teacher/classes/:id` | `view-classes` | Phase 1 |
| Danh sách lớp | Tạo bài tập | Điều hướng form | `/teacher/assignments/new` | `create-assignments` | Ẩn đến Phase 4 |
| Điểm danh | Tạo phiên | Submit form | `POST /api/classes/{id}/attendance-sessions` | `create-attendance-session` | Phase 1 |
| Điểm danh | Đánh dấu tất cả | Cập nhật API | `POST /api/attendance-sessions/{id}/mark-all-present` | `update-attendance` | Phase 1 |
| Điểm danh | Lưu điểm danh | Submit API | `POST /api/attendance-sessions/{id}/mark` | `update-attendance` | Phase 1 |
| Bài tập | Giao bài tập | Mở form | `/teacher/assignments/new` | `create-assignments` | Disabled – Phase 4 |
| Landing | Đăng nhập | Điều hướng | `/login` | Public | Hoạt động |
| Landing | Vào hệ thống | Điều hướng dashboard role | dashboard tương ứng | Authenticated | Phase 1 |
| Landing | Xem lịch/tin | Điều hướng | `/events`, `/news` | Public | Chưa có API – Phase 6 |
| Landing | Liên kết `#` hỗ trợ/mạng xã hội | Không có đích hợp lệ | N/A | Public | Phải ẩn hoặc bổ sung URL ở Phase 6 |
