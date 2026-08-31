<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Submission;
use App\Models\TeacherProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LearningModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_learning_schema_contains_the_versioned_assignment_domain(): void
    {
        foreach ([
            'question_bank_items',
            'assignments',
            'assignment_questions',
            'assignment_targets',
            'assignment_recipients',
            'assignment_accommodations',
            'submissions',
            'submission_answers',
            'submission_files',
            'grade_histories',
            'announcement_targets',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Thiếu bảng {$table}.");
        }

        $this->assertTrue(Schema::hasColumns('assignments', [
            'status', 'version', 'opens_at', 'due_at', 'allowed_attempts',
            'score_method', 'result_release_mode', 'show_answers',
        ]));
        $this->assertTrue(Schema::hasColumns('announcement_recipients', [
            'read_at', 'acknowledged_at', 'reminded_at',
        ]));
    }

    public function test_learning_permissions_are_granted_to_the_default_roles(): void
    {
        $teacher = Role::findByName('teacher');
        $child = Role::findByName('child');
        $admin = Role::findByName('admin');

        $this->assertTrue($teacher->hasAllPermissions([
            'view-assignments',
            'create-assignments',
            'update-assignments',
            'grade-assignments',
            'view-assignment-reports',
        ]));
        $this->assertFalse($teacher->hasPermissionTo('submit-assignments'));
        $this->assertTrue($child->hasAllPermissions([
            'view-assignments',
            'submit-assignments',
            'view-notifications',
        ]));
        $this->assertFalse($child->hasPermissionTo('grade-assignments'));
        $this->assertTrue($admin->hasAllPermissions([
            'view-assignments',
            'create-assignments',
            'update-assignments',
            'archive-assignments',
            'grade-assignments',
            'submit-assignments',
            'view-assignment-reports',
        ]));
    }

    public function test_assignment_policy_enforces_teacher_ownership_and_recipient_snapshot(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $child = User::where('email', 'child@giaoly.test')->firstOrFail();
        $class = $child->child->activeEnrollment->catechismClass;
        $assignment = Assignment::create([
            'created_by' => $teacher->id,
            'title' => 'Ôn tập Kinh Tin Kính',
        ]);
        $assignment->targets()->create(['catechism_class_id' => $class->id]);
        AssignmentRecipient::create([
            'assignment_id' => $assignment->id,
            'catechism_class_id' => $class->id,
            'child_id' => $child->child->id,
            'enrollment_id' => $child->child->activeEnrollment->id,
            'assigned_at' => now(),
        ]);

        $assistant = User::factory()->create();
        $assistant->assignRole('teacher');
        $assistantProfile = TeacherProfile::create([
            'user_id' => $assistant->id,
            'parish_id' => $teacher->teacherProfile->parish_id,
            'code' => 'GLV-PHU',
        ]);
        $class->teachers()->attach($assistantProfile->id, ['role' => 'assistant']);

        $outsider = User::factory()->create();
        $outsider->assignRole('teacher');
        TeacherProfile::create([
            'user_id' => $outsider->id,
            'parish_id' => $teacher->teacherProfile->parish_id,
            'code' => 'GLV-NGOAI',
        ]);

        $this->assertTrue(Gate::forUser($teacher)->allows('view', $assignment));
        $this->assertTrue(Gate::forUser($teacher)->allows('update', $assignment));
        $this->assertTrue(Gate::forUser($assistant)->allows('view', $assignment));
        $this->assertFalse(Gate::forUser($assistant)->allows('update', $assignment));
        $this->assertTrue(Gate::forUser($assistant)->allows('grade', $assignment));
        $this->assertFalse(Gate::forUser($outsider)->allows('view', $assignment));
        $this->assertTrue(Gate::forUser($child)->allows('view', $assignment));

        $assignment->recipients()->delete();
        $this->assertFalse(Gate::forUser($child)->allows('view', $assignment->fresh()));
    }

    public function test_submission_policy_only_allows_the_owner_and_assigned_teachers(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $child = User::where('email', 'child@giaoly.test')->firstOrFail();
        $class = $child->child->activeEnrollment->catechismClass;
        $assignment = Assignment::create(['created_by' => $teacher->id, 'title' => 'Bài kiểm tra']);
        $assignment->targets()->create(['catechism_class_id' => $class->id]);
        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'child_id' => $child->child->id,
            'attempt_number' => 1,
            'started_at' => now(),
        ]);

        $otherChildUser = User::factory()->create();
        $otherChildUser->assignRole('child');

        $this->assertTrue(Gate::forUser($child)->allows('view', $submission));
        $this->assertTrue(Gate::forUser($child)->allows('update', $submission));
        $this->assertTrue(Gate::forUser($teacher)->allows('view', $submission));
        $this->assertTrue(Gate::forUser($teacher)->allows('grade', $submission));
        $this->assertFalse(Gate::forUser($otherChildUser)->allows('view', $submission));
    }
}
