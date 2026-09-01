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
