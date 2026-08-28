# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Giáo lý viên, quản trị viên giáo xứ, phụ huynh và thiếu nhi. Giáo lý viên dùng hệ thống trong công việc điều hành lớp: xem lịch dạy, quản lý lớp, điểm danh, tạo QR và theo dõi thiếu nhi.

## Product Purpose

Hành Trang Đức Tin tập trung dữ liệu và tác vụ giáo lý của giáo xứ vào một không gian thống nhất. Thành công nghĩa là người dùng biết việc cần làm, tìm đúng lớp và hoàn thành tác vụ mà không phải đối chiếu nhiều nguồn rời rạc.

## Operating Context

Ứng dụng web responsive được dùng trên máy tính tại văn phòng, laptop và điện thoại trong lớp học. Dữ liệu lịch dạy gắn với lớp, niên khóa, phòng học và phân công giáo lý viên.

## Capabilities and Constraints

- Role và permission được kiểm tra ở cả route lẫn backend.
- Giáo lý viên xem lịch của các lớp được phân công; lịch cố định do quản trị viên quản lý.
- Các tác vụ liên quan gồm điểm danh, tạo QR và xem danh sách lớp.
- Giao diện dùng Vue 3, Ant Design Vue, Tailwind và Be Vietnam Pro.
- Không hiển thị dữ liệu giáo trình, ghi chú hoặc loại sự kiện nếu API chưa cung cấp.

## Brand Commitments

Tên sản phẩm là “Hành Trang Đức Tin”. Màu xanh dương hiện có là màu hành động chính; giao diện dùng tiếng Việt, rõ ràng và gần gũi nhưng vẫn mang tính vận hành chuyên nghiệp.

## Evidence on Hand

- Logo và nhận diện hiện có trong app shell.
- Dữ liệu lớp, lịch, niên khóa, phòng học và sĩ số từ API.
- Ảnh tham khảo weekly calendar do người dùng cung cấp cho trang Lịch dạy.

## Product Principles

- Tác vụ chính phải nhìn thấy và thực hiện nhanh.
- Dữ liệu và phân quyền phải trung thực, không suy diễn từ giao diện.
- Giao diện ưu tiên khả năng quét nhanh trong môi trường vận hành lớp học.
- Responsive là hành vi cốt lõi, không phải phiên bản thu nhỏ của desktop.

## Accessibility & Inclusion

Các control phải dùng được bằng bàn phím, có focus state rõ ràng, vùng chạm tối thiểu phù hợp và không dựa riêng vào màu để truyền đạt trạng thái.
