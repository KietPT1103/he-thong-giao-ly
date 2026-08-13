# Điểm danh bằng QR theo phiên

## Luồng sử dụng

1. Giáo lý viên mở `/teacher/qr-scanner`, chọn lớp được phân công, ngày giờ buổi học và thời điểm QR hết hạn.
2. Backend tạo một `attendance_session` có `qr_expires_at` và trả QR đã ký cho đúng phiên đó.
3. Giáo lý viên hiển thị QR trên màn hình lớp.
4. Thiếu nhi đăng nhập tài khoản của mình, mở `/child/my-qr`, bật camera và quét QR.
5. Backend xác minh chữ ký, hạn dùng, trạng thái hồ sơ và enrollment của thiếu nhi trong đúng lớp.
6. Lượt hợp lệ được ghi `present`; sau giờ học 15 phút được ghi `late`. Quét lại trả kết quả “đã điểm danh” và không tạo thêm bản ghi.

Phụ huynh và quản trị viên không còn xem hoặc xoay QR cá nhân của thiếu nhi.

## Bảo mật và toàn vẹn dữ liệu

- QR chứa prefix định dạng, ID phiên, Unix timestamp hết hạn và chữ ký HMAC-SHA256 từ khóa ứng dụng.
- Token không chứa tên, mã thiếu nhi, email hoặc dữ liệu cá nhân và không được ghi vào activity log.
- Timestamp trong token phải trùng `attendance_sessions.qr_expires_at`; sửa ID hoặc hạn dùng làm chữ ký mất hiệu lực.
- Chỉ giáo lý viên được phân công đúng lớp và có `create-attendance-qr` mới tạo hoặc mở lại QR.
- Chỉ tài khoản thiếu nhi có `check-in-attendance-qr` mới tự điểm danh.
- Thiếu nhi phải có hồ sơ `studying` và enrollment `active` trong đúng lớp.
- Unique key `(attendance_session_id, child_id)` và `insertOrIgnore` bảo đảm mỗi em chỉ có một bản ghi, kể cả khi hai request đến đồng thời.
- Endpoint check-in giới hạn 120 request/phút theo tài khoản và IP.

## API

- `POST /api/classes/{class}/attendance-qr`
  - Input: `{ held_at, qr_expires_at, note? }`.
  - `qr_expires_at` phải ở tương lai và sau `held_at`.
  - Output: `{ token, session: { id, held_at, qr_expires_at, note, class } }`.
- `GET /api/attendance-sessions/{session}/qr`
  - Giáo lý viên mở lại QR đã tạo cho lớp mình phụ trách.
- `POST /api/attendance/qr/check-in`
  - Input: `{ token }`, tối đa 512 ký tự.
  - Output: `{ attendance, session, checked_in_at, was_duplicate }`.
  - Mã lỗi nghiệp vụ: `INVALID_QR_CODE`, `QR_CODE_EXPIRED`, `CHILD_NOT_ACTIVE`, `CHILD_NOT_IN_SESSION_CLASS`.

Mọi response dùng envelope `{ success, message, data, meta }` hiện có.

## Giao diện

- Giáo viên: form lớp/ngày giờ/hết hạn/ghi chú; QR có đồng hồ đếm ngược, trạng thái hoạt động/hết hạn và tải ảnh.
- Thiếu nhi: camera sau, khung quét, fallback nhập token thủ công và màn hình kết quả rõ trạng thái có mặt/đi trễ/trùng.
- Camera chỉ được yêu cầu sau thao tác bấm của người dùng và tự tắt sau khi ghi nhận thành công.
- Các nút chính có vùng chạm tối thiểu 44px; số đếm ngược dùng tabular numerals; animation scanner tắt khi `prefers-reduced-motion`.

## Kiểm thử

- Giáo viên đúng lớp tạo QR; hạn dùng không hợp lệ bị từ chối.
- Token sửa nội dung, hết hạn hoặc thuộc lớp khác bị từ chối.
- Học sinh quét hợp lệ và quét trùng chỉ tạo một attendance.
- Teacher, parent và anonymous không thể gọi endpoint check-in.
- Permission migration cấp đúng quyền cho admin, teacher và child.
- Chạy `php artisan test`, `npm run type-check` và `npm run build` trước deploy.
