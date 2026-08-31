# Tasks: Bài tập và thông báo lớp

## Phase 1 — Foundation

- [x] Task 1: Thêm schema học tập và permission rollout
  - Acceptance: migration up/down được; role teacher/child/admin nhận đúng quyền.
  - Verify: focused migration/permission tests.
  - Files: migration schema, migration permission, seeder, feature test.
- [x] Task 2: Thêm model relationships và policy
  - Acceptance: assignment/question/submission/announcement scope đúng lớp.
  - Verify: policy abuse tests.
  - Files: models, policies, provider, feature test.

## Phase 2 — Authoring and publishing

- [ ] Task 3: CRUD ngân hàng câu hỏi
  - Acceptance: phạm vi personal/parish; người khác không sửa bản gốc.
  - Verify: question-bank API tests.
- [ ] Task 4: Tạo, sửa, list và xem bài nháp
  - Acceptance: 5 dạng câu, hybrid, validation và optimistic conflict.
  - Verify: assignment authoring tests.
- [ ] Task 5: Phát hành và snapshot người nhận/câu hỏi
  - Acceptance: chỉ lớp phụ trách; cá nhân phải thuộc lớp; lịch sử bất biến.
  - Verify: publish/security tests.

## Phase 3 — Attempts and grading

- [ ] Task 6: Bắt đầu lượt và tự lưu câu trả lời
  - Acceptance: giới hạn lượt, thời gian, quyền người nhận và version.
  - Verify: attempt/autosave tests.
- [ ] Task 7: Nộp và chấm tự động
  - Acceptance: 4 dạng tự động, essay chờ chấm, nộp muộn và quy đổi thang 10.
  - Verify: scoring tests.
- [ ] Task 8: Chấm thủ công, rubric và công bố
  - Acceptance: co-teacher chấm; release gate; sửa điểm có lý do/audit.
  - Verify: grade/release tests.
- [ ] Task 9: Báo cáo và xuất dữ liệu
  - Acceptance: tỷ lệ nộp/đạt, điểm TB, phân bố và CSV mở được bằng Excel.
  - Verify: report/export tests.

## Phase 4 — Communication

- [ ] Task 10: CRUD/phát hành thông báo lớp
  - Acceptance: class/group/individual snapshot, scheduling, withdraw.
  - Verify: announcement scope tests.
- [ ] Task 11: Hộp thư đọc/xác nhận và nhắc lại
  - Acceptance: read/ack timestamps; reminder chỉ người chưa hoàn thành.
  - Verify: inbox tests.
- [ ] Task 12: Tạo thông báo từ sự kiện bài tập
  - Acceptance: publish, due change, extra attempt và release tạo link phù hợp.
  - Verify: event notification tests.

## Phase 5 — UI

- [ ] Task 13: API types/client và route thật
  - Acceptance: contract typed; sidebar/header badge dùng dữ liệu thật.
  - Verify: type-check.
- [ ] Task 14: Bàn điều phối và trình soạn Giáo lý viên
  - Acceptance: list/filter/empty/error; 5-step composer và 5 question types.
  - Verify: type-check, build, responsive browser QA.
- [ ] Task 15: Làm bài và kết quả của Thiếu nhi
  - Acceptance: todo list, timer, autosave, submit confirm và released result.
  - Verify: critical browser flow.
- [ ] Task 16: Chấm bài và thông báo
  - Acceptance: master-detail grading, rubric, compose/inbox/read/ack UI.
  - Verify: critical browser flow.

## Phase 6 — Hardening

- [ ] Task 17: Upload/download riêng tư
  - Acceptance: allowlist MIME, 20 MB, tối đa 5 tệp và authorization tải.
  - Verify: upload abuse tests.
- [ ] Task 18: Final verification
  - Acceptance: focused/regression/Pint/type-check/build/detector/browser sạch.
  - Verify: toàn bộ command trong spec.
