<?php

namespace Database\Seeders;

use App\Enums\AttendanceStatus;
use App\Models\{AcademicYear, Attendance, AttendanceSession, CatechismClass, CatechismLevel, Child, Classroom, ClassSchedule, Enrollment, Parish, ParentProfile, TeacherProfile, User};
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RoleAndPermissionSeeder::class);
        $parish = Parish::create(['name' => 'Giáo xứ Cái Răng', 'code' => 'CAI-RANG']);
        $current = AcademicYear::create(['parish_id' => $parish->id, 'name' => '2025–2026', 'starts_on' => '2025-08-01', 'ends_on' => '2026-05-31', 'is_current' => true]);
        AcademicYear::create(['parish_id' => $parish->id, 'name' => '2024–2025', 'starts_on' => '2024-08-01', 'ends_on' => '2025-05-31']);
        $levels = collect(['Ấu Nhi', 'Thiếu Nhi', 'Nghĩa Sĩ', 'Hiệp Sĩ'])->map(fn($n, $i) => CatechismLevel::create(['parish_id' => $parish->id, 'name' => $n, 'code' => 'L' . ($i + 1), 'sort_order' => $i + 1]));
        $rooms = collect(range(1, 3))->map(fn($i) => Classroom::create(['parish_id' => $parish->id, 'name' => 'Phòng A' . $i, 'capacity' => 25]));
        $password = Hash::make('password');
        $admin = User::create(['name' => 'Quản trị viên', 'email' => 'admin@giaoly.test', 'password' => $password]);
        $admin->assignRole('admin');
        $teacher = User::create(['name' => 'Giáo lý viên 1', 'email' => 'teacher@giaoly.test', 'password' => $password]);
        $teacher->assignRole('teacher');
        $teacherProfile = TeacherProfile::create(['user_id' => $teacher->id, 'parish_id' => $parish->id, 'code' => 'GLV-1']);
        $classes = collect();
        foreach (range(1, 6) as $i) {
            $class = CatechismClass::create(['academic_year_id' => $current->id, 'catechism_level_id' => $levels[($i - 1) % 4]->id, 'classroom_id' => $rooms[($i - 1) % 3]->id, 'name' => $levels[($i - 1) % 4]->name . ' ' . ceil($i / 2) . 'A', 'code' => 'L' . $i]);
            $class->teachers()->attach($teacherProfile->id, ['role' => 'primary']);
            ClassSchedule::create(['catechism_class_id' => $class->id, 'weekday' => 0, 'starts_at' => '08:00', 'ends_at' => '09:30']);
            $classes->push($class);
        }
        $parent = User::create(['name' => 'Phụ huynh 1', 'email' => 'parent@giaoly.test', 'password' => $password]);
        $parent->assignRole('parent');
        $parentProfile = ParentProfile::create(['user_id' => $parent->id, 'parish_id' => $parish->id, 'phone' => '0900000001']);
        foreach (range(1, 30) as $i) {
            $u = null;
            if ($i === 1) {
                $u = User::create(['name' => 'Thiếu nhi 1', 'email' => 'child@giaoly.test', 'password' => $password]);
                $u->assignRole('child');
            }
            $child = Child::create(['parish_id' => $parish->id, 'user_id' => $u?->id, 'code' => 'TN-' . str_pad((string)$i, 3, '0', STR_PAD_LEFT), 'full_name' => 'Thiếu nhi ' . $i, 'saint_name' => 'Thánh Giuse', 'date_of_birth' => '201' . ($i % 5) . '-01-01']);
            $child->parents()->attach($parentProfile->id);
            Enrollment::create(['child_id' => $child->id, 'catechism_class_id' => $classes[($i - 1) % 6]->id]);
        }
        foreach ($classes as $class) {
            $session = AttendanceSession::create(['catechism_class_id' => $class->id, 'held_at' => now()->subWeek(), 'taken_by' => $class->teachers()->first()->user_id]);
            foreach ($class->children as $child) Attendance::create(['attendance_session_id' => $session->id, 'child_id' => $child->id, 'status' => AttendanceStatus::Present->value]);
        }
    }
}
