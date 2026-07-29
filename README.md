# Hành Trang Đức Tin

MVP quản lý học giáo lý cho Giáo xứ Cái Răng. Bản này gồm giao diện Vue 3 responsive dành cho giáo lý viên và nền Laravel cho lớp học, thiếu nhi, ghi danh và điểm danh.

## Chạy dự án

```bash
composer install
npm install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm run dev
php artisan serve
```

Mở `http://127.0.0.1:8000`. Giao diện hiện dùng dữ liệu mô phỏng để duyệt luồng; migration lõi đã sẵn sàng để thay bằng API.

## Có sẵn

- Tổng quan giáo lý viên, lớp học và danh sách thiếu nhi.
- Điểm danh nhanh: đổi trạng thái, đánh dấu cả lớp và thông báo lưu.
- Theo dõi bài tập, giao diện mobile-first.
- Thiết kế token xanh–trắng; code tách router, views và dữ liệu.

Xem [kiến trúc](docs/architecture.md) để tiếp tục hiện thực API, Sanctum, Policies, assignment/exam và notification.
