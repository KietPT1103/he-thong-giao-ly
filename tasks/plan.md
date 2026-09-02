# Implementation Plan: Bài tập và thông báo lớp

## Overview

Triển khai sáu lát dọc đã được duyệt, ưu tiên contract và ranh giới quyền trước,
sau đó nối UI Giáo lý viên/Thiếu nhi và hoàn thiện báo cáo, tệp, audit.

## Architecture decisions

- Snapshot câu hỏi vào bài và snapshot người nhận lúc phát hành để giữ lịch sử.
- Dùng optimistic `version` cho bài và bài nộp để phát hiện ghi đè đồng thời.
- Dùng `announcements` làm hộp thư bền vững cho cả thông báo thủ công và sự kiện
  bài tập; `announcement_recipients` là biên đọc/xác nhận từng người.
- Tách Form Request, policy, service vòng đời/tính điểm khỏi controller.
- Giữ UI mới trong ngôn ngữ vận hành hiện có và mở route thật thay trang pending.

## Phases

1. Contract, schema, permission và model relationships.
2. Tạo/list/phát hành bài và ngân hàng câu hỏi.
3. Lượt làm, tự lưu, nộp và chấm tự động.
4. Chấm thủ công, công bố kết quả và báo cáo.
5. Thông báo lớp, thông báo tự động, đọc/xác nhận.
6. UI teacher/child, upload/download, hardening và regression.

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Truy cập chéo lớp/giáo xứ | Cao | Policy + query scope + abuse tests |
| Sửa đề làm sai lịch sử | Cao | Snapshot + khóa khi có lượt làm |
| Nộp hai lần/ghi đè autosave | Cao | Transaction + unique index + version |
| Lộ đáp án/điểm sớm | Cao | Resource payload phụ thuộc trạng thái |
| Upload độc hại | Cao | MIME/size/count allowlist + private storage |
| UI quá tải trên điện thoại | Trung bình | Work queue, progressive disclosure, mobile-first |

## Checkpoints

- Foundation: migration/model/permission và focused tests xanh.
- Core learning: teacher → publish → child → submit → grade → release chạy E2E.
- Communication: send/read/acknowledge và auto-event chạy E2E.
- Complete: regression, Pint, type-check, build, browser QA và detector sạch.

## Open questions

Không có.

---

# Implementation Plan: Danh mục lớp học

## Overview

Thêm màn quản trị tập trung theo giáo xứ cho Niên khóa, Khối giáo lý và Phòng
học. Mỗi danh mục có thể tạo, sửa, ngừng sử dụng và chỉ được xóa khi chưa có
lớp tham chiếu.

## Architecture decisions

- Dùng một màn `/admin/class-catalogs` với ba tab nhưng giữ API resource riêng
  cho từng loại để validation và permission không bị nhập nhằng.
- Thêm `is_active` vào cả ba bảng; danh mục ngừng sử dụng không xuất hiện khi
  tạo lớp mới, nhưng giá trị hiện tại của lớp vẫn hiển thị khi chỉnh sửa.
- Niên khóa hiện tại là duy nhất trong một giáo xứ; khi chọn niên khóa mới làm
  hiện tại, hệ thống tự bỏ cờ ở niên khóa cũ trong transaction.
- Chặn xóa với mã lỗi ổn định khi danh mục đã có lớp; mọi thao tác ghi đều được
  kiểm tra quyền, validate ở Form Request và ghi audit log.

## Risks and mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Chọn danh mục khác giáo xứ | Cao | Validation phía server theo `parish_id` |
| Vô hiệu hóa làm lớp cũ mất nhãn | Cao | API options luôn giữ danh mục của lớp đang sửa |
| Hai niên khóa cùng là hiện tại | Cao | Transaction bỏ cờ các niên khóa còn lại |
| Xóa làm hỏng khóa ngoại lớp | Cao | Kiểm tra `classes_count` và trả lỗi nghiệp vụ 422 |
| Admin thiếu quyền vẫn sửa được | Cao | Middleware `can:*` riêng cho từng resource/action |

## Checkpoints

- Foundation: migration/model/permission và API contract test đỏ rồi xanh.
- CRUD: tạo/sửa/ngừng sử dụng/xóa an toàn và audit test xanh.
- UI: ba tab hoạt động, responsive từ mobile đến màn 24 inch.
- Complete: full regression, Pint, type-check, build và browser console sạch.

## Open questions

Không có; người dùng đã chốt luồng.
