# Spec: Bài tập và thông báo lớp

## Objective

Xây dựng một LMS thu gọn cho Giáo lý viên và Thiếu nhi. Giáo lý viên có thể
soạn bài nộp hoặc bài kiểm tra hỗn hợp, giao theo lớp/cá nhân, theo dõi lượt
làm, chấm phần tự luận, công bố kết quả và gửi thông báo lớp. Thiếu nhi có thể
xem việc cần làm, làm bài an toàn với tự lưu, nộp bài và xem kết quả đã công
bố. Mọi dữ liệu luôn bị giới hạn theo giáo xứ, phân công lớp và ghi danh.

## Assumptions đã được duyệt

1. Đây là Vue 3 SPA dùng Laravel 12 REST API và cookie Sanctum hiện có.
2. Thang điểm báo cáo là 10; điểm câu hỏi được quy đổi về thang này.
3. MVP hỗ trợ câu một lựa chọn, nhiều lựa chọn, đúng/sai, trả lời ngắn và tự
   luận; một bài có thể trộn các dạng câu.
4. Thông báo chỉ gửi trong hệ thống. Email, SMS, web push, giám sát webcam và
   khóa trình duyệt nằm ngoài phạm vi.
5. Bài đã có lượt làm và danh sách người nhận đã phát hành là dữ liệu lịch sử,
   không bị thay đổi khi người học chuyển lớp.
6. Tệp dùng storage riêng tư, tối đa 5 tệp mỗi bài nộp và 20 MB mỗi tệp.

## Tech stack

- PHP 8.2, Laravel 12, Eloquent, Spatie Permission, Sanctum.
- Vue 3, TypeScript, Vue Router, Ant Design Vue, Tailwind CSS.
- SQLite in-memory cho test; schema tương thích MySQL/PostgreSQL.

## API contract

Response tiếp tục dùng `{ success, message, data, meta }`. List API có phân
trang. Validation trả 422, sai quyền 403, xung đột phiên bản 409.

### Giáo lý viên

- `GET|POST /api/teacher/assignments`
- `GET|PATCH /api/teacher/assignments/{assignment}`
- `POST /api/teacher/assignments/{assignment}/{publish|close|release|archive|withdraw}`
- `GET /api/teacher/assignments/{assignment}/submissions`
- `PATCH /api/teacher/submissions/{submission}/grade`
- `GET /api/teacher/assignments/{assignment}/report`
- `GET|POST /api/teacher/question-bank`
- `PATCH|DELETE /api/teacher/question-bank/{question}`
- `GET|POST /api/teacher/announcements`
- `PATCH /api/teacher/announcements/{announcement}`
- `POST /api/teacher/announcements/{announcement}/{send|withdraw|remind}`

### Thiếu nhi và hộp thư

- `GET /api/child/assignments`
- `GET /api/child/assignments/{assignment}`
- `POST /api/child/assignments/{assignment}/attempts`
- `PATCH /api/child/submissions/{submission}/answers`
- `POST /api/child/submissions/{submission}/submit`
- `GET /api/notifications`
- `GET /api/notifications/{announcement}`
- `POST /api/notifications/{announcement}/{read|acknowledge}`
- `POST /api/notifications/read-all`

## Domain rules

- Bài tập đi qua `draft → scheduled/published → closed → grading → released →
  archived`; bài sai có thể `withdrawn` nhưng không xóa lịch sử.
- Chỉ người tạo hoặc Giáo lý viên chính được đổi cấu hình/phát hành/thu hồi;
  Giáo lý viên cùng lớp có thể xem và chấm.
- Khi phát hành, câu hỏi và người nhận được snapshot. Bài có lượt làm khóa nội
  dung câu hỏi, đáp án, điểm và giới hạn thời gian.
- Hết giờ tự nộp phần đã lưu. Lượt làm, cách lấy điểm, nộp muộn, quay lại câu
  trước, trộn đề và thời điểm hiện đáp án đều là cấu hình bài.
- Điểm trắc nghiệm được chấm tự động; câu tự luận và rubric chấm thủ công.
  Thiếu nhi chỉ thấy điểm sau khi kết quả được công bố.
- Thông báo có nháp, lên lịch, đã gửi, hết hiệu lực, lưu trữ và thu hồi; người
  nhận lưu riêng thời điểm đọc và xác nhận.
- Sự kiện bài tập tạo thông báo hệ thống có liên kết đến tài nguyên liên quan.

## UX direction

Chế độ `Operate`, cấu trúc “Bàn điều phối học tập”. Giáo lý viên mở vào hàng
đợi cần xử lý rồi tới sổ bài tập. Trình soạn đi theo các bước Thông tin → Câu
hỏi → Đối tượng & thời gian → Cài đặt → Xem trước. Thiếu nhi mở vào “Việc cần
làm”, còn màn hình làm bài ưu tiên tiến độ, đồng hồ và trạng thái tự lưu. Giữ
ngôn ngữ Be Vietnam Pro, xanh dương hành động, slate/trắng và thiết kế responsive
hiện có; không dùng gamification hoặc dashboard thẻ dày đặc.

## Commands

```text
Focused tests: php artisan test tests/Feature/LearningModuleTest.php
Regression:    php artisan test
PHP style:     vendor/bin/pint --test
Type check:    npm run type-check
Build:         npm run build
```

## Project structure

- `app/Models`, `app/Policies`, `app/Services`: domain và quyền.
- `app/Http/Requests/Learning`, `app/Http/Controllers/Api`: validation/API.
- `database/migrations`: schema và rollout permission.
- `resources/js/api`, `resources/js/types`: contract client.
- `resources/js/views/learning`, `resources/js/components/learning`: UI.
- `tests/Feature/LearningModuleTest.php`: contract, lifecycle và abuse cases.

## Testing strategy

- Feature tests cho quyền lớp/giáo xứ, snapshot người nhận, khóa đề, tự chấm,
  công bố điểm, lượt làm và trạng thái đọc/xác nhận.
- Unit tests cho tính điểm và quy đổi thang 10 nếu logic được tách riêng.
- Type-check/build và kiểm tra responsive 320/768/1024/1440; trạng thái loading,
  empty, error, permission và conflict phải có UI.

## Boundaries

- Always: validate ở Form Request, authorize ở server, transaction cho phát
  hành/nộp/chấm, audit mutation, escape nội dung, storage riêng tư.
- Approved by user: schema mới, upload tệp giới hạn, permission mới và quyền
  giám sát quản trị được mô tả trong phiên `/grilling`.
- Never: hard-delete lịch sử đã phát hành, tin validation client, lộ đáp án
  trước thời điểm cho phép, hoặc cho Giáo lý viên truy cập lớp không phân công.
- Out of scope: chat/bình luận bài nộp, import Word/Excel, email/SMS/push, webcam,
  khóa trình duyệt và các dạng ghép cặp/kéo thả.

## Success criteria

- Giáo lý viên tạo được bài hỗn hợp, phát hành đúng lớp và chấm/công bố kết quả.
- Thiếu nhi đúng danh sách nhận có thể tự lưu, nộp và chỉ xem kết quả đã công bố.
- Ngân hàng câu hỏi cá nhân/giáo xứ tái sử dụng được mà không sửa lịch sử đề.
- Thông báo lớp và tự động có đọc/xác nhận, thu hồi và thống kê người nhận.
- API, policy, migration, UI, focused tests, regression, type-check và build đạt.

## Open questions

Không còn câu hỏi nghiệp vụ chặn triển khai.
