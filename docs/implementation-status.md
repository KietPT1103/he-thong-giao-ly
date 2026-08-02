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

## 17. Cập nhật 02/08/2026: quản lý giáo xứ

- Đã bổ sung API riêng cho danh sách, chi tiết, tạo, sửa và xóa giáo xứ.
- Đã bổ sung luồng phân nhiều giáo lý viên vào giáo xứ trong một giao dịch và
  ghi activity log cho từng giáo lý viên được chuyển.
- Chỉ cho phép xóa giáo xứ rỗng; API trả số lượng từng loại dữ liệu đang chặn
  xóa và không cascade dữ liệu liên quan.
- Đã bổ sung trang quản trị giáo xứ, form tạo/sửa, modal phân giáo lý viên,
  xác nhận xóa và trạng thái loading/empty/error.
- Sidebar đã ẩn overflow ngang và giới hạn chiều rộng link để hover không tạo
  thanh cuộn ngang.
- Kiểm thử runtime đã xác nhận luồng tạo/xóa giáo xứ rỗng, chặn xóa giáo xứ có
  dữ liệu và trạng thái giáo lý viên hiện tại trong modal phân công.
- Regression suite đạt 50 tests/226 assertions; `vue-tsc`, production build,
  route contract và `git diff --check` đều đạt.

## 18. Cập nhật 02/08/2026: quản lý giáo lý viên

- Đã bổ sung sáu API riêng cho danh sách, chi tiết, tạo, sửa, lưu trữ và khôi phục
  giáo lý viên; tất cả endpoint yêu cầu quyền `manage-users`.
- Danh sách hỗ trợ tìm theo tên/email/mã, lọc giáo xứ và trạng thái đang hoạt động,
  đã khóa hoặc đã lưu trữ; chi tiết hiển thị các lớp và vai trò đang phụ trách.
- Tạo mới đồng bộ tài khoản, vai trò giáo lý viên, hồ sơ, giáo xứ và số điện thoại
  trong một giao dịch; cập nhật không làm thay đổi mật khẩu, vai trò hoặc lớp đã phân công.
- Chỉ cho phép lưu trữ giáo lý viên không còn lớp. Khi bị chặn, giao diện liệt kê rõ
  tên và mã từng lớp cần chuyển giao; hồ sơ đã lưu trữ có thể được lọc và khôi phục.
- Đã bổ sung trang quản trị chuyên biệt, form tạo/sửa có validation và cảnh báo khi
  đổi giáo xứ của người đang phụ trách lớp, drawer chi tiết và xác nhận bỏ thay đổi.
- Đã chốt một luồng tạo duy nhất: trang quản lý tài khoản không còn cho chọn vai trò
  giáo lý viên; nút tạo giáo lý viên chuyển sang trang chuyên biệt và tự mở form.
  API tạo tài khoản và API phân quyền cũng từ chối role `teacher` bằng mã lỗi có
  cấu trúc nếu tài khoản chưa có hồ sơ giáo lý viên.
- QA runtime đã xác nhận tạo, sửa và đồng bộ số điện thoại, chặn lưu trữ khi còn lớp,
  lưu trữ hồ sơ rỗng, lọc hồ sơ lưu trữ và khôi phục; không có lỗi console hoặc tràn
  ngang ở viewport desktop 1280×720.
- Regression suite đạt 62 tests/311 assertions; `vue-tsc`, production build, sáu route
  API giáo lý viên, Impeccable detector và `git diff --check` đều đạt.

## 19. Cập nhật 02/08/2026: validation số điện thoại và hướng tiếp theo

- Các API tài khoản, giáo lý viên và giáo xứ chỉ chấp nhận số điện thoại Việt Nam
  hợp lệ dạng `0...` hoặc `+84...`; hỗ trợ khoảng trắng, dấu chấm, gạch nối và ngoặc.
- Các form tương ứng kiểm tra cùng quy tắc và hiển thị lỗi ngay tại trường nhập.
- Chức năng tiếp theo được chốt là **Quản lý lớp học**.
- Regression suite đạt 63 tests/320 assertions; `vue-tsc`, production build,
  kiểm tra runtime và `git diff --check` đều đạt.

## 20. Cập nhật 02/08/2026: quản lý lớp học

- Đã bổ sung 10 API quản lý lớp: danh sách/bộ lọc, danh mục theo giáo xứ, chi tiết,
  tạo, sửa, lưu trữ, khôi phục, phân giáo lý viên, ghi danh và lịch học.
- Mã lớp duy nhất trong niên khóa; khối và phòng phải cùng giáo xứ. Lớp đã có
  điểm danh không được đổi niên khóa hoặc khối.
- Phân công hỗ trợ vai trò chính/phụ tá và cảnh báo trùng lịch giáo lý viên;
  ghi danh giữ lịch sử active/inactive, chặn trùng niên khóa và vượt sức chứa.
- Lịch học chuẩn hóa thứ `1–7`, vẫn đọc dữ liệu Chủ nhật legacy `0`; trùng phòng
  luôn bị chặn, trùng giáo lý viên cần xác nhận rõ trước khi lưu.
- Lưu trữ dùng soft delete và khôi phục giữ nguyên trạng thái, phân công, ghi danh,
  lịch học cùng lịch sử điểm danh. Mọi mutation đều có activity log.
- Trang `/admin/classes` có bộ lọc, phân trang, drawer chi tiết, form lớp và modal
  chuyên biệt cho phân công, ghi danh, lịch học; có loading/error/empty/confirm state.
- Browser QA đạt ở desktop và `390×844`; bảng mobile thu về cột quan trọng,
  drawer không tràn ngang và bốn modal render đúng contract. Không thực hiện
  mutation trong QA trình duyệt để giữ nguyên dữ liệu kiểm thử.
- Regression suite đạt **80 tests/441 assertions**; 10 route API, type-check,
  production build, Impeccable detector và `git diff --check` đều đạt.
- Pint trên toàn bộ file thuộc tính năng lớp học đạt. Pint toàn repo vẫn báo các
  file legacy ngoài phạm vi chưa theo formatter; không tự động format hàng loạt
  để tránh tạo diff không liên quan.
