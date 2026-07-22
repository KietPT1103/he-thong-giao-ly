# Kiến trúc MVP

```text
Vue 3 PWA → Laravel REST API → MySQL/PostgreSQL
                 ├─ Policies / roles
                 ├─ Queue + Notifications
                 └─ Storage riêng tư
```

Mô hình dữ liệu khởi đầu: `Parish → AcademicYear → CatechismClass → Enrollment → Child`; mỗi `AttendanceSession` thuộc một lớp và có nhiều `Attendance`.

Vai trò chuẩn: `admin`, `teacher`, `parent`, `child`. API cần Sanctum, còn Policy kiểm tra giáo lý viên có lớp được phân công, phụ huynh liên kết với thiếu nhi và thiếu nhi chỉ xem hồ sơ của mình.
