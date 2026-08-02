# Spec: Quản lý giáo lý viên

## Objective

Xây dựng trang quản lý giáo lý viên riêng tại `/admin/teachers` để quản trị viên
có thể tìm kiếm, xem chi tiết, tạo, cập nhật, khóa/mở khóa, lưu trữ và khôi phục
giáo lý viên bằng dữ liệu thật.

Người dùng chính là quản trị viên có quyền `manage-users`. Chức năng phải quản lý
đồng thời tài khoản đăng nhập (`users`) và hồ sơ giáo lý viên
(`teacher_profiles`) nhưng trình bày cho người dùng như một hồ sơ thống nhất.

### User stories

- Quản trị viên xem danh sách giáo lý viên và lọc theo trạng thái hoặc giáo xứ.
- Quản trị viên tìm kiếm theo họ tên, email hoặc mã giáo lý viên.
- Quản trị viên tạo tài khoản và hồ sơ giáo lý viên trong một thao tác.
- Quản trị viên xem thông tin giáo xứ và các lớp giáo lý viên đang phụ trách.
- Quản trị viên cập nhật thông tin cá nhân, mã, giáo xứ và trạng thái tài khoản.
- Quản trị viên lưu trữ giáo lý viên không còn phụ trách lớp và có thể khôi phục.
- Quản trị viên nhận thông báo rõ ràng khi thao tác thành công hoặc bị từ chối.

## Functional Requirements

### Teacher fields

| Field | Requirement |
| --- | --- |
| `name` | Bắt buộc, trim, tối đa 255 ký tự |
| `email` | Bắt buộc, email hợp lệ, duy nhất trong `users`, tối đa 255 ký tự |
| `phone` | Không bắt buộc, trim, tối đa 30 ký tự |
| `code` | Bắt buộc, trim, duy nhất trong `teacher_profiles`, tối đa 50 ký tự |
| `parish_id` | Bắt buộc, phải là giáo xứ đang tồn tại |
| `status` | Chỉ nhận `active` hoặc `blocked` |
| `password` | Bắt buộc khi tạo, tối thiểu 8 ký tự và phải xác nhận |

Số điện thoại hiện tồn tại ở cả `users.phone` và `teacher_profiles.phone`.
Trong phạm vi này, API phải ghi cùng một giá trị vào cả hai nơi để tránh dữ liệu
hiển thị không nhất quán. Không thêm migration để thay đổi schema hiện tại.

### List and filters

- Danh sách mặc định hiển thị giáo lý viên chưa lưu trữ.
- Tìm kiếm theo tên, email hoặc mã giáo lý viên.
- Lọc theo giáo xứ và trạng thái `active`, `blocked` hoặc `archived`.
- Có phân trang; mặc định sắp xếp theo tên tăng dần.
- Mỗi dòng hiển thị họ tên, mã, email, số điện thoại, giáo xứ, số lớp phụ trách
  và trạng thái dễ hiểu.
- Có đầy đủ loading, empty, error và retry state.

### Details

- Chi tiết trả về hồ sơ thống nhất từ `User` và `TeacherProfile`.
- Hiển thị giáo xứ hiện tại và danh sách lớp đang phụ trách.
- Mỗi lớp gồm tên, mã, niên khóa, khối giáo lý và vai trò phân công nếu có.
- Hồ sơ đã lưu trữ vẫn xem được khi truy cập từ bộ lọc `archived`.

### Create

- Tạo `User`, gán role `teacher` và tạo `TeacherProfile` trong cùng một database
  transaction; lỗi ở bất kỳ bước nào phải rollback toàn bộ.
- Đây là luồng duy nhất để tạo giáo lý viên. API tạo tài khoản thông thường phải
  từ chối role `teacher` và hướng quản trị viên sang màn hình quản lý giáo lý viên.
- API phân quyền cũng không được gán role `teacher` cho tài khoản chưa có
  `TeacherProfile`, nhằm ngăn đường vòng tạo dữ liệu thiếu hồ sơ.
- Tại màn hình quản lý tài khoản, nút tạo giáo lý viên phải chuyển sang
  `/admin/teachers` và tự mở form tạo hồ sơ đầy đủ.
- Tài khoản mới có trạng thái `active` và `must_change_password = true`.
- Không cho phép dùng email của tài khoản đang hoạt động hoặc đã lưu trữ.
- Tạo thành công ghi activity log `teacher.created`.

### Update and status

- Cho phép sửa tên, email, số điện thoại, mã giáo lý viên, giáo xứ và trạng thái.
- Không thay đổi role hoặc mật khẩu trong form giáo lý viên; các thao tác đó tiếp
  tục thuộc trang quản lý tài khoản.
- Thay đổi giáo xứ không tự thay đổi các lớp đang phụ trách.
- Nếu giáo xứ mới khác giáo xứ của lớp đang phụ trách, UI phải cảnh báo nhưng
  vẫn giữ nguyên phân công lớp; việc chuyển lớp thuộc chức năng quản lý lớp.
- Cập nhật thành công ghi activity log `teacher.updated` với giá trị cũ và mới.

### Archive and restore rule

- Không hard delete `User`, `TeacherProfile`, lịch sử lớp hoặc điểm danh.
- Chỉ được lưu trữ khi giáo lý viên không còn lớp đang phụ trách.
- Nếu còn lớp, API trả HTTP `422`, code `TEACHER_HAS_CLASSES`, message dễ hiểu và
  danh sách lớp đang chặn thao tác.
- Lưu trữ dùng soft delete trên `users`; `teacher_profiles` được giữ lại để bảo
  toàn lịch sử và phục vụ khôi phục.
- Resource/query giáo lý viên phải đọc được tài khoản đã soft delete.
- Khôi phục chỉ mở lại tài khoản và hồ sơ cũ, không tự gán lại lớp.
- Lưu trữ và khôi phục lần lượt ghi `teacher.archived` và `teacher.restored`.
- UI bắt buộc có bước xác nhận trước khi lưu trữ hoặc khôi phục.

### Class assignment boundary

- Trang giáo lý viên chỉ hiển thị các lớp đang phụ trách.
- Không thêm, xóa hoặc chuyển phân công lớp trong chức năng này.
- Nút/hướng dẫn khi bị chặn lưu trữ phải đưa người dùng sang bước xử lý lớp,
  nhưng không tự động gỡ phân công.

## API Contract

Tất cả endpoint nằm trong middleware `web`, `auth:sanctum`,
`session.absolute`, `can:access-admin` và `can:manage-users`.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/admin/teachers` | Tìm kiếm, lọc và phân trang |
| `POST` | `/api/admin/teachers` | Tạo tài khoản và hồ sơ giáo lý viên |
| `GET` | `/api/admin/teachers/{teacher}` | Xem chi tiết và lớp phụ trách |
| `PATCH` | `/api/admin/teachers/{teacher}` | Cập nhật hồ sơ và trạng thái |
| `DELETE` | `/api/admin/teachers/{teacher}` | Lưu trữ nếu không còn lớp |
| `POST` | `/api/admin/teachers/{teacher}/restore` | Khôi phục giáo lý viên đã lưu trữ |

Response thành công dùng envelope hiện có:

```json
{
  "success": true,
  "message": "Đã cập nhật giáo lý viên.",
  "data": {}
}
```

Validation dùng HTTP `422`; không đủ quyền dùng `403`; không tìm thấy dùng
`404`. List response trả `meta` gồm `current_page`, `last_page`, `per_page` và
`total`.

## Tech Stack

- PHP 8.2+, Laravel 12, Sanctum 4 và Spatie Permission 6.
- Vue 3.5, TypeScript 5.7, Vue Router, Pinia và Ant Design Vue 4.
- PHPUnit 11 cho feature test.
- Vite 7 cho type-check và production build.

## Commands

```powershell
# Development
composer dev

# Backend feature tests
php artisan test --filter=AdminTeacherManagementTest

# Full backend suite
composer test

# Frontend verification
npm run type-check
npm run build
```

Project hiện không có script lint trong `package.json`; không thêm dependency chỉ
để lint trong phạm vi này.

## Project Structure

```text
app/Http/Controllers/Api/       AdminTeacherController
app/Http/Requests/Admin/        Validation list/create/update teacher
app/Http/Resources/             TeacherResource
app/Models/                     User, TeacherProfile và quan hệ lớp
resources/js/api/admin.ts       Typed frontend API client
resources/js/views/             AdminTeachersView.vue
resources/js/components/        Form, detail drawer và confirm UI
routes/api.php                  Protected teacher routes
tests/Feature/                  AdminTeacherManagementTest.php
docs/specs/                     Feature specification
```

## Code Style

Controller giữ mỏng, validation đặt trong Form Request, response qua Resource và
mọi thao tác ghi nhiều bảng phải nằm trong transaction. Frontend dùng
Composition API với `<script setup lang="ts">`, API có type rõ ràng và không
dùng `any`.

```php
public function update(UpdateTeacherRequest $request, TeacherProfile $teacher)
{
    $teacher = DB::transaction(function () use ($request, $teacher) {
        $teacher->user->update($request->safe()->only(['name', 'email', 'phone', 'status']));
        $teacher->update($request->safe()->only(['code', 'parish_id', 'phone']));

        return $teacher;
    });

    return $this->success(new TeacherResource($teacher), 'Đã cập nhật giáo lý viên.');
}
```

Quy ước: class/component dùng PascalCase, method và biến dùng camelCase, database
field dùng snake_case, endpoint dùng danh từ số nhiều. Nội dung UI dùng tiếng
Việt có dấu và file source lưu UTF-8.

## Testing Strategy

Feature test dùng `RefreshDatabase` và seed role/permission. Tối thiểu phải có:

1. Admin có quyền xem danh sách, tìm kiếm, lọc giáo xứ/trạng thái và phân trang.
2. Admin tạo giáo lý viên thành công, có role `teacher` và hai bảng đồng bộ.
3. Create rollback toàn bộ khi hồ sơ không tạo được.
4. Email và mã giáo lý viên trùng bị từ chối.
5. Email, password, parish và status không hợp lệ bị từ chối.
6. Admin xem chi tiết cùng danh sách lớp phụ trách.
7. Admin cập nhật thông tin và chuyển giáo xứ, số điện thoại được đồng bộ.
8. Cập nhật không làm mất phân công lớp.
9. Giáo lý viên không có lớp được lưu trữ và có activity log.
10. Giáo lý viên còn lớp bị chặn lưu trữ, không mất dữ liệu.
11. Giáo lý viên đã lưu trữ được lọc, xem và khôi phục.
12. Người thiếu `manage-users`, role khác và người chưa đăng nhập bị chặn.
13. Directory/admin account/parish assignment hiện có không bị regression.

Frontend phải qua `npm run type-check` và `npm run build`. Kiểm tra thủ công cả
desktop và mobile cho list, filters, details, create, edit, archive blocked,
archive allowed, restore, loading, empty và error states.

## Boundaries

### Always do

- Validate server-side, enforce permission ở backend và dùng transaction cho
  create/update nhiều bảng.
- Đồng bộ số điện thoại giữa `users` và `teacher_profiles`.
- Ghi activity log cho mọi mutation.
- Giữ nguyên lịch sử và chặn lưu trữ khi còn lớp phụ trách.
- Chạy feature test, full backend tests, type-check và build trước khi hoàn tất.

### Ask first

- Thay đổi quan hệ một giáo lý viên - một giáo xứ.
- Thêm trường hồ sơ ngoài các trường đã nêu.
- Cho phép quản lý phân công lớp trực tiếp từ trang giáo lý viên.
- Thêm package, thay đổi permission matrix hoặc migration schema.
- Cho phép hard delete giáo lý viên hoặc tự động gỡ lớp khi lưu trữ.

### Never do

- Xóa cascade lịch sử lớp/điểm danh khi lưu trữ giáo lý viên.
- Tự động gỡ giáo lý viên khỏi lớp để vượt qua quy tắc lưu trữ.
- Chỉ ẩn nút ở frontend thay cho authorization backend.
- Tạo dữ liệu giả để mô phỏng chức năng.
- Sửa migration đã chạy, `vendor/`, `node_modules/` hoặc xóa test để làm suite pass.

## Success Criteria

- Admin hoàn thành list/detail/create/update/archive/restore giáo lý viên tại
  `/admin/teachers` bằng dữ liệu thật.
- Tài khoản và hồ sơ được tạo/cập nhật nhất quán, có role/quyền đúng.
- Không thể lưu trữ giáo lý viên còn phụ trách lớp; không mất lịch sử dữ liệu.
- Giáo lý viên đã lưu trữ có thể được tìm, xem và khôi phục.
- Mọi mutation có activity log và mọi endpoint được bảo vệ bằng `manage-users`.
- UI responsive, có đầy đủ loading/empty/error/success/confirm states.
- Feature tests mới, full backend suite, type-check và production build đều pass.

## Open Questions

Không còn câu hỏi mở. Quy tắc lưu trữ thay cho hard delete và yêu cầu chuyển lớp
trước khi lưu trữ đã được người dùng duyệt ngày 02/08/2026.
