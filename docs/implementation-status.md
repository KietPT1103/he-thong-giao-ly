# Báo cáo triển khai

Ngày cập nhật: 25/07/2026

## 1. Hiện trạng trước khi sửa

Ứng dụng chỉ có navigation giáo lý viên; route không phân role. Lớp học, thiếu
nhi, điểm danh và bài tập dùng dữ liệu demo; lịch học dùng nhầm component lớp.
Backend có API thật cho auth, lớp của giáo lý viên và điểm danh nhưng phần lớn
chưa được frontend gọi. Policy có tồn tại nhưng controller nền thiếu
`AuthorizesRequests`, khiến các endpoint gọi `authorize()` trả lỗi 500. Auth
session trong `api.php` cũng thiếu middleware session.

## 2. Phase đã hoàn thành

**Phase 1 – Audit và navigation**

- Đã tạo audit và button action matrix.
- Đã tạo namespace route public/admin/teacher/parent/child, redirect route cũ,
  role guard và permission guard.
- Đã tạo sidebar riêng theo role, collapse desktop, drawer mobile và bottom
  navigation cho thiếu nhi.
- Đã tách lịch dạy khỏi trang lớp.
- Đã nối lớp, thiếu nhi, lịch và điểm danh giáo lý viên với API thật.
- Điểm danh có chọn lớp/phiên, tạo phiên, trạng thái loading/error/success,
  đánh dấu và lưu thật.
- CTA chưa có backend được disabled/hiển thị trạng thái “Đang phát triển”.
- Landing nhận biết phiên đăng nhập; các link `#` không có action đã được bỏ
  hoặc chuyển sang route có chủ đích.
- Đã sửa authorization trait, session middleware và chuẩn hóa thời điểm chống
  tạo phiên điểm danh trùng.

**Phase 2 – phần Dashboard Admin**

- `/admin` không còn dùng placeholder.
- Dashboard lấy số liệu thật về giáo xứ, giáo lý viên, thiếu nhi, lớp hoạt động,
  chuyên cần tuần, đơn xin nghỉ và phiên điểm danh.
- `GET /api/admin/dashboard` dùng API Resource và Gate `access-admin`.
- Có loading/error/empty state và responsive grid.
- Có feature test xác nhận admin được truy cập và giáo lý viên bị chặn 403.
- Các trang Giáo xứ, Giáo lý viên, Phụ huynh, Thiếu nhi, Lớp học và Thông báo
  đã dùng danh sách API thật, có tìm kiếm, phân trang và responsive state.
- Module chưa có schema/backend đã được gỡ khỏi sidebar Admin thay vì dẫn tới
  placeholder.

## 3. Phase chưa hoàn thành

- Phase 2: còn form tạo/sửa/xóa và trang chi tiết cho giáo xứ, giáo lý viên,
  phụ huynh, thiếu nhi và lớp.
- Phase 3: Mass và QR.
- Phase 4: Learning.
- Phase 5: Parent và Child.
- Phase 6: Communication.
- Phase 7: bộ test/API docs đầy đủ cho toàn hệ thống.

Các route của module chưa có backend hiển thị trạng thái chưa mở, không giả lập
dữ liệu hoặc thao tác.

## 4. Migration đã thêm

Chưa thêm migration trong Phase 1. Migration hiện có chạy thành công với SQLite.
Schema giáo phận, QR, Thánh lễ, bài tập và điểm thưởng vẫn thuộc các phase sau.

## 5. Model đã thêm

Không thêm model trong Phase 1.

## 6. API đã tạo/sửa

- Thêm frontend service tập trung `resources/js/api/teacher.ts`.
- Thêm `GET /api/admin/dashboard` và frontend service
  `resources/js/api/admin.ts`.
- Thêm sáu endpoint danh sách `/api/admin/{parishes|teachers|parents|children|classes|announcements}`
  với Form Request, API Resource, pagination, search và Gate Admin.
- Sửa auth API dùng session middleware.
- Sửa toàn bộ controller có thể dùng policy authorization.
- API tạo phiên điểm danh dùng `StoreAttendanceSessionRequest`.
- Chuẩn hóa `held_at` và trả 422 khi trùng phiên.

## 7. UI đã tạo

- Layout/navigation theo role.
- Dashboard Admin dùng dữ liệu thật.
- Sáu trang danh mục Admin dùng dữ liệu database thật.
- Lịch dạy thật.
- Danh sách lớp thật.
- Danh sách thiếu nhi thật.
- Điểm danh lớp thật.
- Empty state cho module chưa đủ backend.

## 8. Route đã tạo

Frontend đã khai báo đầy đủ các route chính trong đặc tả cho bốn role; route
guard kiểm tra `roles` và `permission`. Backend hiện có 19 API route ứng dụng
(không tính vendor/web fallback), đúng với phạm vi API đã thực sự triển khai.

## 9. Button đã sửa

- Lưu điểm danh gọi API thay vì bật toast giả.
- Mở phiên tạo bản ghi thật.
- Link lớp/điểm danh truyền đúng ngữ cảnh.
- Giao bài tập disabled kèm lý do vì chưa có backend.
- Link landing “Xem tất cả” đi tới `/events` và `/news`; link placeholder bị bỏ.

Chi tiết tại `docs/button-action-matrix.md`.

## 10. Màn hình đã bỏ `demo.js`

- Lớp học.
- Thiếu nhi.
- Điểm danh.
- Lịch học/lịch dạy.
- Bài tập không còn mảng demo; module được khóa rõ ràng.

Không còn import `resources/js/data/demo.js` trong ứng dụng.

## 11. Màn hình vẫn còn demo/tĩnh

- Nội dung thống kê, lịch và thông báo trên landing page.
- Admin, phụ huynh, thiếu nhi và các module teacher ngoài phạm vi Phase 1 mới có
  route/navigation, chưa có backend/UI nghiệp vụ.

## 12. Kết quả test

- `php artisan migrate:fresh --seed`: đạt.
- `php artisan route:list --except-vendor`: đạt.
- `php artisan test`: **15 test, 44 assertions, tất cả đạt**.
- Bao phủ auth/login/logout/blocked account, giới hạn lớp giáo lý viên và luồng
  tạo/lưu/validate điểm danh.

## 13. Kết quả build

- `npm run type-check`: đạt.
- `npm run build`: đạt.
- Không có script lint trong `package.json`.
- Edge headless đã render ở 1440×1000, 1280×800, 768×1024, 390×844 và
  360×800. Vòng kiểm tra ảnh phát hiện và đã sửa chồng nội dung hero desktop và
  lưới mobile bị cắt ngang.

## 14. Lỗi còn tồn tại

- Vite cảnh báo `/images/home-hero-parish.png` không resolve ở build time; URL
  được giữ để resolve runtime. Asset chính của landing vẫn render.
- Chưa kiểm tra camera vì module QR chưa được cài đặt.
- Chưa có automated browser/E2E suite và chưa thu thập console log có cấu trúc.

## 15. Technical debt

- Các file PHP ban đầu viết một dòng, message có chỗ chưa chuẩn hóa tiếng Việt.
- API list attendance trả paginator thô thay vì Resource/meta chuẩn thống nhất.
- Danh sách thiếu nhi frontend hiện ghép nhiều request theo lớp; cần endpoint
  teacher children có pagination/filter ở Phase 2.
- Seeder chưa đạt số lượng và phạm vi dữ liệu nghiệm thu.
- `demo.js` không còn được sử dụng nhưng vẫn giữ lại để tránh xóa code khi chưa
  xác nhận phạm vi.

## 16. Hướng triển khai phase tiếp theo

Thực hiện Phase 2 theo lát dọc hoàn chỉnh: thêm `dioceses`, mở rộng parish,
resource admin dashboard, CRUD parish/teacher/parent/child/class với Form
Request, Resource, Policy, filter/sort/pagination, activity log, UI states và
feature test trước khi chuyển sang QR/Mass.
