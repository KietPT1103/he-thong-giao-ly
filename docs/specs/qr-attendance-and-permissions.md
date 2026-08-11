# Đặc tả dự thảo: Điểm danh bằng QR và phân quyền

## Mục tiêu

Giáo lý viên dùng camera trên điện thoại, iPad hoặc laptop để quét mã QR cá nhân
của thiếu nhi vào một phiên điểm danh đã chọn. Thiếu nhi, phụ huynh liên kết và
quản trị viên có thể xem đúng mã QR thuộc phạm vi của mình. Mọi thao tác được
kiểm tra quyền ở backend, không dựa vào việc ẩn menu ở frontend.

## Luồng người dùng đã chọn

1. Mỗi thiếu nhi có một mã QR cá nhân, dùng lâu dài và có thể in.
2. Giáo lý viên chọn lớp và phiên điểm danh, sau đó chủ động bật camera để quét.
3. Backend xác minh chữ ký QR, trạng thái hồ sơ, enrollment đang hoạt động, lớp
   của phiên và quyền phụ trách lớp của giáo lý viên.
4. Quét hợp lệ ghi `present`; nếu sau giờ phiên 15 phút thì ghi `late`, đồng thời
   lưu `arrived_at` và activity log.
5. Quét lại cùng QR trong cùng phiên là idempotent: không tạo bản ghi trùng và UI
   thông báo “Đã điểm danh trước đó”.
6. Quản trị viên có thể xoay vòng mã QR; mã cũ mất hiệu lực ngay. Thao tác này yêu
   cầu xác nhận mật khẩu gần đây theo chính sách 15 phút hiện có.

Không triển khai mô hình giáo viên hiển thị QR phiên để thiếu nhi tự quét.

## Mô hình bảo mật

- Thêm `children.qr_version` (số nguyên, mặc định 1); không lưu token QR thô.
- Payload QR chỉ chứa phiên bản định dạng, ID thiếu nhi, `qr_version` và chữ ký
  HMAC-SHA256 dùng khóa ứng dụng. Không chứa tên, email, ngày sinh hoặc dữ liệu PII.
- Xác minh chữ ký bằng so sánh constant-time; lỗi token luôn trả thông báo chung.
- Xoay vòng QR tăng `qr_version`, khiến mọi mã cũ không còn hợp lệ.
- Scan chỉ chấp nhận thiếu nhi chưa lưu trữ, trạng thái `studying`, có enrollment
  `active` trong đúng lớp của phiên.
- API scan yêu cầu giáo lý viên đang được phân công đúng lớp; quyền frontend không
  được xem là biên bảo mật.
- Giới hạn tốc độ dự kiến: 120 lượt quét/phút/tài khoản để chống lạm dụng nhưng vẫn
  đáp ứng lớp đông.
- Activity log ghi người quét, phiên, thiếu nhi và kết quả; không ghi token QR.

## Phân quyền

| Permission | Mặc định | Phạm vi |
| --- | --- | --- |
| `view-child-qr` | admin, parent, child | Admin xem mọi hồ sơ; phụ huynh chỉ con liên kết; thiếu nhi chỉ chính mình |
| `scan-attendance-qr` | admin, teacher | Giáo lý viên chỉ phiên thuộc lớp được phân công |
| `rotate-child-qr` | admin | Xoay mã QR, yêu cầu mật khẩu gần đây |

Các quyền `view-attendance`, `create-attendance-session` và `update-attendance`
hiện có vẫn điều khiển danh sách phiên, tạo phiên và điểm danh thủ công. Quyền bị
admin “Chặn” tiếp tục ưu tiên hơn quyền mặc định theo vai trò.

## Hợp đồng API

- `GET /api/children/{child}/qr`
  - Trả `{ token, child: { id, code, full_name }, version }` theo ownership/policy.
- `POST /api/admin/children/{child}/qr/rotate`
  - Tăng version, trả token mới; permission `rotate-child-qr`, password recent.
- `POST /api/attendance-sessions/{session}/qr/scan`
  - Input `{ token }` tối đa 512 ký tự.
  - Output `{ attendance, child, scanned_at, was_duplicate }`.
  - Lỗi nghiệp vụ ổn định: `INVALID_QR_CODE`, `CHILD_NOT_IN_SESSION_CLASS`,
    `CHILD_NOT_ACTIVE`, `QR_SCAN_FORBIDDEN`.

Mọi response tiếp tục dùng envelope `{ success, message, data, meta }` hiện có.

## Giao diện

- `/teacher/qr-scanner`: chọn lớp/phiên, tạo phiên nếu có quyền, bật/tắt camera,
  camera sau mặc định, vùng quét rõ ràng, kết quả thành công/trùng/lỗi, lịch sử quét
  gần nhất trong phiên và phương án nhập token thủ công khi camera không dùng được.
- `/child/my-qr`: thẻ QR cá nhân, tên/mã thiếu nhi, tải ảnh hoặc in.
- Phụ huynh xem QR của từng con từ trang hồ sơ con.
- Admin mở QR/rotate từ bảng Quản lý Thiếu nhi.
- Responsive tại 320, 768, 1024 và 1440px; thao tác bằng bàn phím; camera chỉ xin
  quyền sau khi người dùng bấm “Bật camera”.

## Công nghệ và cấu trúc

- Backend: Laravel, session auth, Spatie Permission, Eloquent transaction/policy.
- Frontend: Vue 3, TypeScript, Ant Design Vue, Tailwind CSS.
- Sinh QR: tiếp tục dùng `qrcode.vue` đang có.
- Quét QR: thêm `@zxing/browser`, lazy-load chỉ ở route scanner.
- Migration: một cột `qr_version`; không tạo bảng token và không lưu secret mới.
- Backend dự kiến: `QrCodeService`, request/resource/controller chuyên biệt và policy.
- Frontend dự kiến: API types, `ChildQrCard.vue`, `QrScannerView.vue` và admin QR modal.

## Lệnh kiểm tra

- Backend chuyên biệt: `php artisan test --filter=QrAttendanceTest`
- Toàn bộ backend: `php artisan test`
- Định dạng phạm vi: `vendor\\bin\\pint.bat --test <changed-php-files>`
- TypeScript: `npm.cmd run type-check`
- Production build: `npm.cmd run build`
- Dependency: `npm audit --omit=dev` và rà lockfile diff.

## Chiến lược kiểm thử

- Token hợp lệ, token sửa nội dung, sai version và mã đã rotate.
- Ownership: admin/parent/child/teacher và quyền bị deny.
- Giáo lý viên khác lớp không thể scan.
- Thiếu nhi không thuộc lớp, đã lưu trữ hoặc không còn học bị từ chối.
- Quét đúng lớp ghi present/late, lưu arrived_at, audit và không tạo trùng.
- API validation/rate limit không làm lộ token hay dữ liệu nội bộ.
- Frontend typecheck/build; runtime camera, fallback và responsive bằng browser QA.

## Biên phạm vi

### Luôn thực hiện

- Backend authorization, validation, transaction và audit cho mọi mutation.
- Không ghi token vào log; không đưa PII vào QR.
- Bảo toàn điểm danh thủ công hiện có.

### Cần duyệt trong đặc tả này

- Migration `qr_version`.
- Dependency `@zxing/browser`.
- Ba permission mới và rate limit QR 120/phút.

### Không thực hiện

- Nhận diện khuôn mặt, định vị GPS hoặc tự điểm danh từ thiết bị thiếu nhi.
- QR động theo từng phiên.
- Thay đổi chính sách mật khẩu hoặc session hiện có.

## Tiêu chí hoàn thành

- Ba API đúng contract và được bảo vệ bằng permission + ownership/class assignment.
- Giáo lý viên quét được QR thật trên mobile/laptop và nhận phản hồi dưới 1 giây
  trong điều kiện mạng nội bộ bình thường.
- QR cũ bị vô hiệu ngay sau rotate; scan trùng idempotent.
- Admin có thể cấp/chặn ba quyền mới bằng UI quản lý tài khoản.
- Full backend tests, typecheck, production build, Pint phạm vi và dependency audit đạt.

## Câu hỏi mở

Chỉ cần xác nhận phương án “giáo lý viên quét QR cá nhân của thiếu nhi”, migration,
dependency và ba quyền mới nêu trên. Sau khi duyệt không còn câu hỏi chặn triển khai.
