# Spec: Quản lý lớp học

## Objective

Xây dựng trang quản lý lớp học riêng tại `/admin/classes` để quản trị viên có thể
tìm kiếm, xem chi tiết, tạo, cập nhật, phân giáo lý viên, ghi danh thiếu nhi,
thiết lập lịch học, lưu trữ và khôi phục lớp bằng dữ liệu thật.

Người dùng chính là quản trị viên. Chức năng phải nối đúng dữ liệu giáo xứ,
niên khóa, khối giáo lý, phòng học, giáo lý viên, thiếu nhi và lịch sử điểm danh.

### User stories

- Quản trị viên xem và lọc lớp theo giáo xứ, niên khóa, khối và trạng thái.
- Quản trị viên tạo hoặc cập nhật thông tin lớp.
- Quản trị viên phân giáo lý viên chính và giáo lý viên phụ tá cho lớp.
- Quản trị viên ghi danh hoặc rút thiếu nhi khỏi lớp.
- Quản trị viên thiết lập một hoặc nhiều lịch học định kỳ.
- Quản trị viên xem sĩ số, người phụ trách, lịch học và dữ liệu liên quan.
- Quản trị viên lưu trữ lớp nhưng không làm mất lịch sử.

## Functional Requirements

### Class fields

| Field | Requirement |
| --- | --- |
| `name` | Bắt buộc, trim, tối đa 255 ký tự |
| `code` | Bắt buộc, trim, tối đa 50 ký tự, duy nhất trong niên khóa |
| `academic_year_id` | Bắt buộc, niên khóa đang tồn tại |
| `catechism_level_id` | Bắt buộc, khối cùng giáo xứ với niên khóa |
| `classroom_id` | Không bắt buộc; nếu có phải là phòng cùng giáo xứ |
| `status` | Chỉ nhận `active` hoặc `inactive` |

Giáo xứ của lớp được xác định từ `academic_year.parish_id`, không thêm
`parish_id` trực tiếp vào `catechism_classes`.

### List and filters

- Danh sách mặc định hiển thị lớp chưa lưu trữ.
- Tìm kiếm theo tên hoặc mã lớp.
- Lọc theo giáo xứ, niên khóa, khối và trạng thái `active`, `inactive`, `archived`.
- Có phân trang; mặc định ưu tiên niên khóa hiện tại, sau đó sắp theo khối và tên.
- Mỗi dòng hiển thị tên, mã, giáo xứ, niên khóa, khối, phòng, sĩ số, số giáo lý
  viên, lịch học gần nhất và trạng thái.
- Có đầy đủ loading, empty, error và retry state.

### Details

- Chi tiết trả về thông tin lớp, giáo xứ, niên khóa, khối và phòng học.
- Hiển thị danh sách giáo lý viên kèm vai trò `primary` hoặc `assistant`.
- Hiển thị danh sách thiếu nhi đang ghi danh và trạng thái ghi danh.
- Hiển thị toàn bộ lịch học của lớp và số phiên điểm danh đã phát sinh.
- Lớp đã lưu trữ vẫn xem được từ bộ lọc `archived`.

### Create and update

- Tạo lớp trong một database transaction và ghi activity log `class.created`.
- Niên khóa, khối và phòng được chọn phải cùng một giáo xứ.
- Mã lớp chỉ cần duy nhất trong cùng niên khóa.
- Cho phép tạo lớp chưa có phòng, giáo lý viên, thiếu nhi hoặc lịch học.
- Khi lớp đã có phiên điểm danh, không cho đổi niên khóa hoặc khối; API trả
  HTTP `422`, code `CLASS_HISTORY_LOCKED`.
- Cập nhật thành công ghi activity log `class.updated` với giá trị cũ và mới.

### Teacher assignment

- Chỉ phân giáo lý viên chưa lưu trữ, có tài khoản đang hoạt động và thuộc cùng
  giáo xứ với lớp.
- Mỗi giáo lý viên chỉ xuất hiện một lần trong lớp, với vai trò `primary` hoặc
  `assistant`.
- Lớp có thể chưa có giáo lý viên hoặc có nhiều giáo lý viên.
- Cập nhật danh sách phân công trong một transaction; request không hợp lệ không
  được thay đổi một phần dữ liệu.
- Phải cảnh báo khi một giáo lý viên có lịch trùng với lớp khác mà họ đang phụ trách.
- Ghi activity log `class.teachers_assigned` khi phân công thay đổi.

### Child enrollment

- Chỉ ghi danh thiếu nhi chưa lưu trữ, có trạng thái `studying` và thuộc cùng
  giáo xứ với lớp.
- Một thiếu nhi chỉ có một enrollment `active` trong cùng niên khóa.
- Nếu phòng có `capacity`, không cho sĩ số hoạt động vượt sức chứa; API trả
  HTTP `422`, code `CLASS_CAPACITY_EXCEEDED`.
- Rút thiếu nhi khỏi lớp bằng cách chuyển enrollment sang `inactive`, không xóa
  bản ghi để giữ lịch sử.
- Không cho rút thiếu nhi nếu thao tác làm mất liên kết cần thiết với dữ liệu
  điểm danh đã tồn tại; trường hợp này chỉ chuyển enrollment sang `inactive`.
- Cập nhật ghi danh trong một transaction và ghi activity log
  `class.enrollments_updated`.

### Schedule

- Một lớp có thể có nhiều lịch học.
- `weekday` nhận giá trị từ 1 đến 7; `starts_at` phải nhỏ hơn `ends_at`.
- Dữ liệu legacy `weekday = 0` được đọc và so sánh như Chủ nhật (`7`); mọi lần
  ghi mới hoặc cập nhật chỉ lưu giá trị từ 1 đến 7.
- `starts_on` và `ends_on` không bắt buộc; nếu có phải nằm trong niên khóa và
  `starts_on` không lớn hơn `ends_on`.
- Không cho cùng một phòng có hai lịch giao nhau trong cùng khoảng ngày.
- Khi trùng lịch phòng, API trả HTTP `422`, code `CLASSROOM_SCHEDULE_CONFLICT` và
  thông tin lớp đang xung đột.
- Khi trùng lịch giáo lý viên, API trả cảnh báo có cấu trúc để UI yêu cầu quản trị
  viên xác nhận trước khi lưu; không âm thầm bỏ qua xung đột.
- Cập nhật lịch trong một transaction và ghi activity log
  `class.schedules_updated`.

### Archive and restore

- Không hard delete lớp, enrollment, phân công, lịch hoặc dữ liệu điểm danh.
- Lưu trữ dùng soft delete trên `catechism_classes` và giữ nguyên dữ liệu liên quan.
- Lưu trữ yêu cầu xác nhận; lớp đã lưu trữ không xuất hiện trong danh sách mặc định
  và không được dùng để tạo phiên điểm danh mới.
- Khôi phục mở lại chính lớp cũ, không tự thay đổi trạng thái, phân công, ghi danh
  hoặc lịch học.
- Lưu trữ và khôi phục lần lượt ghi `class.archived` và `class.restored`.

### Authorization

- Danh sách/chi tiết yêu cầu `access-admin` và `view-classes`.
- Tạo yêu cầu `create-classes`; cập nhật và lịch yêu cầu `update-classes`.
- Lưu trữ/khôi phục yêu cầu `delete-classes`.
- Phân giáo lý viên yêu cầu `assign-teachers`.
- Ghi danh thiếu nhi yêu cầu `enroll-children`.
- API phải trả `401` cho người chưa đăng nhập và `403` khi thiếu quyền.

## API Contract

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/admin/classes` | Danh sách và bộ lọc |
| `POST` | `/api/admin/classes` | Tạo lớp |
| `GET` | `/api/admin/classes/{class}` | Chi tiết lớp |
| `PATCH` | `/api/admin/classes/{class}` | Cập nhật lớp |
| `PUT` | `/api/admin/classes/{class}/teachers` | Đồng bộ phân công giáo lý viên |
| `PUT` | `/api/admin/classes/{class}/enrollments` | Đồng bộ ghi danh thiếu nhi |
| `PUT` | `/api/admin/classes/{class}/schedules` | Đồng bộ lịch học |
| `DELETE` | `/api/admin/classes/{class}` | Lưu trữ lớp |
| `POST` | `/api/admin/classes/{class}/restore` | Khôi phục lớp |
| `GET` | `/api/admin/classes/options` | Danh mục chọn theo giáo xứ |

Mọi response dùng envelope hiện tại gồm `success`, `message`, `data`, `meta` khi
có phân trang và `code` cho lỗi nghiệp vụ có cấu trúc.

## Tech Stack

- Backend: PHP 8.2+, Laravel 12, Eloquent, Sanctum và Spatie Permission.
- Frontend: Vue 3, TypeScript, Vue Router, Ant Design Vue và Lucide icons.
- Database: schema hiện có; không thêm dependency mới.

## Commands

- Backend tests: `php artisan test`
- Feature tests: `php artisan test --filter=AdminClassManagementTest`
- Route verification: `php artisan route:list --path=api/admin/classes`
- Frontend type-check: `npm.cmd run type-check`
- Production build: `npm.cmd run build`
- Formatting check: `php vendor/bin/pint --test`
- Diff check: `git diff --check`

## Project Structure

- `app/Http/Controllers/Api/` - admin class endpoints.
- `app/Http/Requests/Admin/` - filter/create/update/assignment validation.
- `app/Http/Resources/` - class response contract.
- `app/Models/` và `app/Policies/` - relationships and authorization.
- `resources/js/api/` - typed API contracts.
- `resources/js/components/` - class forms and assignment modals.
- `resources/js/views/` - dedicated admin class page.
- `tests/Feature/` - API, authorization and transaction tests.
- `docs/specs/` - approved feature specification.

## Code Style

Tuân theo style đang dùng ở module giáo xứ và giáo lý viên: Form Request cho
validation, Resource cho response, transaction cho thao tác nhiều bảng và typed
payload ở frontend.

```php
public function store(StoreClassRequest $request): JsonResponse
{
    $class = DB::transaction(fn () => CatechismClass::create($request->validated()));

    return $this->success(new ClassResource($class), 'Đã tạo lớp học.', status: 201);
}
```

## Testing Strategy

- Feature test bao phủ list/filter/detail, authorization và validation chéo giáo xứ.
- Test transaction cho tạo/sửa, phân giáo lý viên, ghi danh và lịch học.
- Test quy tắc một lớp mỗi niên khóa, sức chứa, khóa lịch sử và xung đột lịch.
- Test lưu trữ/khôi phục bảo toàn enrollment, phân công, lịch và điểm danh.
- Regression toàn bộ backend, type-check, build và kiểm tra trình duyệt desktop/mobile.

## Boundaries

### Always

- Dùng dữ liệu thật, validation backend và lỗi nghiệp vụ có mã cấu trúc.
- Giữ nguyên lịch sử điểm danh và các quan hệ khi lưu trữ lớp.
- Ghi activity log cho mọi mutation.
- Có loading, empty, error, retry, validation và confirmation states.

### Ask first

- Thay đổi schema hoặc unique constraint hiện tại.
- Thay đổi ý nghĩa `weekday`, trạng thái lớp hoặc trạng thái enrollment.
- Thêm dependency mới hoặc mở rộng sang CRUD niên khóa/khối/phòng.

### Never

- Hard delete lớp hoặc cascade xóa dữ liệu lịch sử.
- Cho phép dữ liệu khác giáo xứ được gắn vào lớp.
- Dùng dữ liệu demo hoặc chỉ kiểm tra validation ở frontend.
- Thay đổi API giáo lý viên/điểm danh hiện có mà không có regression test.

## Success Criteria

1. Admin tìm kiếm, lọc và xem đầy đủ chi tiết lớp bằng dữ liệu thật.
2. Admin tạo và cập nhật lớp với dữ liệu cùng giáo xứ và mã duy nhất trong niên khóa.
3. Admin phân giáo lý viên theo vai trò trong một transaction.
4. Admin ghi danh/rút thiếu nhi đúng quy tắc niên khóa và sức chứa.
5. Admin tạo lịch hợp lệ; xung đột phòng hoặc giáo lý viên được báo rõ.
6. Lớp có lịch sử được lưu trữ/khôi phục mà không mất dữ liệu.
7. Người thiếu quyền và người chưa đăng nhập bị chặn đúng `401/403`.
8. API cũ của giáo lý viên, lớp và điểm danh không bị regression.
9. Backend suite, type-check, build, route contract, browser QA và diff check đều đạt.

## Approved Decisions

1. Xung đột lịch giáo lý viên được phép tiếp tục sau khi quản trị viên xác nhận;
   xung đột phòng học luôn bị chặn.
2. Khôi phục lớp giữ nguyên trạng thái `active` hoặc `inactive` trước khi lưu trữ.
3. Không bắt buộc giáo lý viên `primary` khi tạo hoặc chuyển lớp sang `active`.
