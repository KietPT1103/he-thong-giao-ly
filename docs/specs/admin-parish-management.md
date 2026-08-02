# Spec: Quan ly giao xu va phan giao ly vien

## Objective

Xay dung chuc nang de quan tri vien tao, xem, sua, xoa giao xu va chuyen mot
hoac nhieu giao ly vien vao giao xu. Chuc nang mo rong trang
`/admin/parishes` hien co va su dung du lieu that tu Laravel API.

Nguoi dung chinh la quan tri vien co quyen `manage-system-settings`.

### User stories

- Quan tri vien co the tim kiem va xem danh sach giao xu.
- Quan tri vien co the tao giao xu voi ten, ma, so dien thoai va email.
- Quan tri vien co the xem chi tiet, so lieu lien quan va danh sach giao ly vien
  cua mot giao xu.
- Quan tri vien co the cap nhat thong tin giao xu.
- Quan tri vien co the chon mot hoac nhieu giao ly vien va chuyen ho vao giao xu.
- Quan tri vien co the xoa giao xu khi giao xu khong con du lieu phu thuoc.
- Quan tri vien nhan duoc thong bao ro rang khi thao tac thanh cong hoac bi tu
  choi.

## Functional Requirements

### Parish fields

| Field | Requirement |
| --- | --- |
| `name` | Bat buoc, chuoi da trim, toi da 255 ky tu |
| `code` | Bat buoc, chuoi da trim, toi da 50 ky tu, duy nhat toan he thong |
| `phone` | Khong bat buoc, chuoi da trim, toi da 30 ky tu |
| `email` | Khong bat buoc, email hop le, toi da 255 ky tu |

Can them migration nullable cho `parishes.phone` va `parishes.email`. Khong sua
lai migration da ton tai.

### List and details

- Giu tim kiem theo ten hoac ma va phan trang hien co.
- Danh sach hien thi ten, ma, lien he, so giao ly vien, so thieu nhi va so nien
  khoa.
- Chi tiet giao xu tra ve day du thong tin giao xu, cac count phu thuoc va danh
  sach giao ly vien dang thuoc giao xu.
- Trang thai loading, empty, validation error, server error va success phai duoc
  hien thi ro rang.

### Create and update

- Tao va cap nhat qua form modal hoac drawer tren `/admin/parishes`.
- Loi validation phai gan voi dung truong du lieu.
- API tra ve resource giao xu da chuan hoa sau khi ghi thanh cong.
- Moi thao tac tao/sua duoc ghi vao `activity_logs`, bao gom gia tri cu va moi
  khi phu hop.

### Assign teachers

- Moi `TeacherProfile` chi thuoc mot giao xu tai mot thoi diem.
- Quan tri vien co the chon mot hoac nhieu giao ly vien va gan vao giao xu dang
  xem.
- Neu giao ly vien dang thuoc giao xu khac, thao tac se chuyen giao ly vien sang
  giao xu moi sau mot buoc xac nhan trong UI.
- Giao ly vien da thuoc giao xu dich la no-op, khong tao ban ghi trung lap.
- Khong cho phep bo gan ma khong chon giao xu thay the vi `parish_id` la bat buoc.
- Cap nhat nhieu giao ly vien phai nam trong mot database transaction: thanh
  cong tat ca hoac rollback tat ca.
- Activity log ghi giao xu cu va giao xu moi cho tung giao ly vien bi chuyen.

### Delete rule

- Xoa la hard delete, chi duoc thuc hien khi giao xu khong co bat ky du lieu phu
  thuoc nao.
- Cac phu thuoc can kiem tra gom: giao ly vien, thieu nhi, nien khoa, cap giao ly,
  phong hoc va thong bao. Rang buoc database van la lop bao ve cuoi cung.
- Khong cascade xoa hoac tu dong chuyen bat ky du lieu nao.
- Neu con phu thuoc, API tra `422` voi code `PARISH_HAS_DEPENDENCIES`, message de
  hieu va count theo tung loai de UI hien thi.
- UI bat buoc hien hop thoai xac nhan truoc khi gui yeu cau xoa.
- Xoa thanh cong duoc ghi vao `activity_logs` voi snapshot du lieu giao xu.

## API Contract

Tat ca endpoint nam trong middleware `web`, `auth:sanctum`,
`session.absolute`, `can:access-admin` va `can:manage-system-settings`.

| Method | Endpoint | Purpose |
| --- | --- | --- |
| `GET` | `/api/admin/parishes` | Tim kiem va phan trang giao xu |
| `POST` | `/api/admin/parishes` | Tao giao xu |
| `GET` | `/api/admin/parishes/{parish}` | Lay chi tiet va giao ly vien |
| `PATCH` | `/api/admin/parishes/{parish}` | Sua giao xu |
| `PUT` | `/api/admin/parishes/{parish}/teachers` | Gan/chuyen danh sach `teacher_ids` vao giao xu |
| `DELETE` | `/api/admin/parishes/{parish}` | Xoa giao xu neu khong co phu thuoc |

Mutation response su dung envelope hien co:

```json
{
  "success": true,
  "message": "Da cap nhat giao xu.",
  "data": {}
}
```

Validation dung HTTP `422`; khong du quyen dung `403`; khong tim thay dung
`404`. Assignment nhan body `{ "teacher_ids": [1, 2] }`, bat buoc la mang ID
khong trung lap va moi ID phai ton tai trong `teacher_profiles`.

## Tech Stack

- PHP 8.2+, Laravel 12, Sanctum 4 va Spatie Permission 6.
- Vue 3.5, TypeScript 5.7, Vue Router, Pinia va Ant Design Vue 4.
- PHPUnit 11 cho feature test.
- Vite 7 cho type-check va production build.

## Commands

```powershell
# Development
composer dev

# Backend feature tests for this feature
php artisan test --filter=AdminParishManagementTest

# Full backend test suite
composer test

# Frontend verification
npm run type-check
npm run build
```

Project hien khong co script lint trong `package.json`; khong them dependency chi
de lint trong pham vi tinh nang nay.

## Project Structure

```text
app/Http/Controllers/Api/       Laravel API controllers
app/Http/Requests/Admin/        Validation cho parish va assignment
app/Http/Resources/             Response resources
app/Models/                     Parish, TeacherProfile va ActivityLog
database/migrations/            Migration them phone/email
resources/js/api/admin.ts       Typed frontend API client
resources/js/types/api.ts       Shared API types
resources/js/views/             Trang quan ly giao xu
resources/js/components/        Form, drawer/modal va confirm UI neu can tach
routes/api.php                  Protected API routes
tests/Feature/                  Feature tests cho quyen va CRUD
docs/specs/                     Feature specifications
```

## Code Style

Giu style Laravel controller mong, tach validation sang Form Request, response
qua Resource va logic ghi nhieu bang trong transaction. Frontend dung Composition
API voi `<script setup lang="ts">`, API co type ro rang va khong dung `any`.

```php
public function update(UpdateParishRequest $request, Parish $parish)
{
    $oldValues = $parish->only(['name', 'code', 'phone', 'email']);
    $parish->update($request->validated());

    $this->audit($request, 'parish.updated', $parish, $oldValues, $parish->fresh());

    return $this->success(new ParishResource($parish), 'Da cap nhat giao xu.');
}
```

Quy uoc: class/component dung PascalCase, method va bien dung camelCase, database
field dung snake_case, endpoint dung danh tu so nhieu. Noi dung hien thi tren UI
dung tieng Viet co dau; file source phai luu UTF-8.

## Testing Strategy

Feature test dung `RefreshDatabase` va seed role/permission. Toi thieu phai co:

1. Admin co dung quyen co the tao giao xu voi du lieu hop le.
2. Ma giao xu trung bi tu choi khi tao va sua.
3. Du lieu email/phone khong hop le bi tu choi.
4. Admin co the xem va sua giao xu.
5. Admin co the gan nhieu giao ly vien vao mot giao xu.
6. Chuyen giao ly vien cap nhat `parish_id` va tao activity log dung du lieu cu/moi.
7. Assignment co ID khong hop le rollback toan bo transaction.
8. Giao xu trong co the bi xoa va co activity log.
9. Giao xu co tung loai phu thuoc bi tu choi xoa, khong mat du lieu.
10. Admin thieu `manage-system-settings` va nguoi dung khac role nhan `403`.
11. Nguoi chua dang nhap khong truy cap duoc endpoint.
12. API list/search/pagination hien co khong bi regression.

Frontend phai qua `npm run type-check` va `npm run build`. Kiem tra thu cong ca
desktop va mobile cho create, edit, assignment confirm, delete confirm, loading,
empty va error states.

## Boundaries

### Always do

- Validate input o server, enforce permission o route/controller va dung
  transaction cho assignment.
- Ghi activity log cho moi mutation.
- Tai lai du lieu/count sau mutation va hien thong bao ket qua.
- Chay feature test, full backend tests, type-check va build truoc khi hoan tat.

### Ask first

- Thay doi quan he mot giao ly vien - mot giao xu.
- Them field giao xu ngoai `name`, `code`, `phone`, `email`.
- Them package frontend/backend, thay doi permission matrix hoac migration du lieu
  ngoai hai cot lien he.
- Doi hard delete thanh archive/soft delete.

### Never do

- Cascade xoa du lieu khi xoa giao xu.
- Bo qua `manage-system-settings` hoac chi an nut o frontend thay cho backend
  authorization.
- Tao du lieu demo de gia lap tinh nang.
- Sua migration da chay, commit secret, sua `vendor/` hoac `node_modules/`.
- Xoa/sua test hien co de lam test suite pass.

## Success Criteria

- Admin hoan thanh CRUD giao xu tu `/admin/parishes` bang API that.
- Admin gan/chuyen nhieu giao ly vien vao giao xu va thay doi duoc phan anh ngay
  trong danh sach/chi tiet.
- Khong the xoa giao xu con phu thuoc; response neu ro cac dependency dang chan.
- Tat ca mutation duoc bao ve bang permission va co activity log.
- UI responsive, co day du loading/empty/error/success/confirm states.
- Feature tests moi va toan bo test cu pass; type-check va production build pass.

## Open Questions

Khong con cau hoi nghiep vu dang mo. Cac gia dinh trong spec can duoc nguoi dung
duyet truoc khi chuyen sang Phase 2: Plan.
