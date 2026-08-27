# Spec: Teacher class management

## Objective

Allow teachers to create, edit, and archive classes from **Lớp của tôi** without
granting access to the admin class APIs. A created class is restricted to the
teacher's parish and automatically assigns the creator as its primary teacher.

## Tech Stack

- Laravel 12, Eloquent, Spatie permissions, PostgreSQL/SQLite tests.
- Vue 3, TypeScript, Ant Design Vue, Vue Router.

## API Contract

- `GET /api/teacher/classes/options`: return the authenticated teacher's single
  parish plus its academic years, levels, and classrooms.
- `POST /api/teacher/classes`: create a class in that parish and atomically add
  the creator with assignment role `primary`.
- `PATCH /api/teacher/classes/{class}`: update base class information when the
  authenticated teacher is assigned as `primary`.
- `DELETE /api/teacher/classes/{class}`: soft-delete a class when the teacher is
  assigned as `primary`; relationships and attendance history remain intact.
- Existing read APIs and admin APIs remain backward compatible.

All mutation responses use the existing `ApiResponse` and `ClassResource`
shapes. Cross-parish IDs, duplicate codes, assistant mutations, and unassigned
class mutations are rejected server-side.

## User Flow

1. Teacher opens `/teacher/classes` and selects **Tạo lớp học**.
2. The parish is fixed from the teacher profile; teacher selects academic year,
   level, optional classroom, status, name, and code.
3. The new class appears in **Lớp của tôi**, with the creator as primary teacher.
4. A primary teacher can edit from the list or detail screen.
5. A primary teacher can archive after confirmation. The class disappears from
   the active teacher list without deleting schedules, enrollments, or history.

## Project Structure

- `app/Http/Requests/Teacher`, `app/Http/Controllers/Api`: scoped write API.
- `app/Policies/CatechismClassPolicy.php`: primary-teacher authorization.
- `database/migrations`, `database/seeders`: existing-role permission rollout.
- `resources/js/api/teacher.ts`, `resources/js/views/ClassesView.vue`,
  `resources/js/components/TeacherClassFormModal.vue`: teacher UI.
- `tests/Feature/TeacherClassManagementTest.php`: contract/security coverage.

## Code Style

Follow existing request validation and audit patterns:

```php
$this->authorize('update', $catechismClass);
$catechismClass->update($request->validated());
```

## Testing Strategy

- Feature tests for create + primary assignment, update, soft-delete, parish
  scoping, assistant/unassigned denial, permission rollout, and audit events.
- Existing admin class and teacher authorization tests remain green.
- Type-check, production build, and responsive browser QA for list/detail/modal.

## Commands

```text
Focused:    php artisan test tests/Feature/TeacherClassManagementTest.php
Regression: php artisan test
PHP style:  vendor/bin/pint --test
Type check: npm run type-check
Build:      npm run build
```

## Boundaries

- Always: enforce parish and primary-assignment scope on the server; audit all
  mutations; use soft delete.
- Ask first: teacher-managed schedules, enrollments, other teacher assignments,
  restoring archived classes, or cross-parish operations.
- Never: trust hidden/disabled parish UI, hard-delete classes, or expose admin
  routes to teacher roles.

## Success Criteria

- Teachers can create, edit, and archive classes they primarily manage.
- Assistants and unassigned teachers cannot mutate a class.
- Teacher input cannot select resources outside their parish.
- Admin class workflows and historical class data are unchanged.

## Open Questions

None for this increment. Primary assignment is the ownership boundary.
