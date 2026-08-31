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

    public function test_teacher_can_manage_personal_and_parish_question_bank_items(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $assistant = User::factory()->create();
        $assistant->assignRole('teacher');
        TeacherProfile::create([
            'user_id' => $assistant->id,
            'parish_id' => $teacher->teacherProfile->parish_id,
            'code' => 'GLV-NHCH',
        ]);

        $shared = $this->actingAs($teacher)->postJson('/api/teacher/question-bank', [
            'scope' => 'parish',
            'type' => 'single_choice',
            'prompt' => 'Kinh Tin Kính bắt đầu bằng lời nào?',
            'default_points' => 2,
            'difficulty' => 'easy',
            'tags' => ['Kinh căn bản'],
            'options' => [
                ['content' => 'Tôi tin kính', 'is_correct' => true],
                ['content' => 'Lạy Cha chúng con', 'is_correct' => false],
            ],
        ])->assertCreated()->assertJsonPath('data.scope', 'parish')->json('data');

        $personal = $this->actingAs($teacher)->postJson('/api/teacher/question-bank', [
            'scope' => 'personal',
            'type' => 'essay',
            'prompt' => 'Em hãy trình bày ý nghĩa của đức tin.',
            'default_points' => 5,
            'difficulty' => 'medium',
            'rubric' => [['label' => 'Nội dung', 'points' => 5]],
        ])->assertCreated()->json('data');

        $visibleIds = collect($this->actingAs($assistant)->getJson('/api/teacher/question-bank')
            ->assertOk()->json('data.data'))->pluck('id');
        $this->assertTrue($visibleIds->contains($shared['id']));
        $this->assertFalse($visibleIds->contains($personal['id']));
        $this->actingAs($assistant)->patchJson("/api/teacher/question-bank/{$shared['id']}", [
            'scope' => 'parish', 'type' => 'essay', 'prompt' => 'Không được sửa',
            'default_points' => 1, 'difficulty' => 'easy', 'version' => 1,
        ])->assertForbidden();
        $this->actingAs($teacher)->deleteJson("/api/teacher/question-bank/{$personal['id']}")
            ->assertOk();
        $this->assertSoftDeleted('question_bank_items', ['id' => $personal['id']]);
    }

    public function test_question_bank_rejects_an_invalid_auto_graded_question(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();

        $this->actingAs($teacher)->postJson('/api/teacher/question-bank', [
            'scope' => 'personal',
            'type' => 'single_choice',
            'prompt' => 'Câu hỏi thiếu đáp án đúng',
            'default_points' => 1,
            'difficulty' => 'easy',
            'options' => [
                ['content' => 'A', 'is_correct' => false],
                ['content' => 'B', 'is_correct' => false],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('options');
    }

    public function test_primary_teacher_can_create_list_show_and_update_a_hybrid_assignment(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();

        $assignment = $this->actingAs($teacher)->postJson(
            '/api/teacher/assignments',
            $this->validAssignmentPayload($class->id),
        )->assertCreated()
            ->assertJsonPath('data.title', 'Ôn tập Đức Tin')
            ->assertJsonCount(2, 'data.questions')
            ->assertJsonCount(1, 'data.targets')
            ->json('data');

        $this->actingAs($teacher)->getJson('/api/teacher/assignments?status=draft')
            ->assertOk()->assertJsonPath('data.data.0.id', $assignment['id']);
        $this->actingAs($teacher)->getJson("/api/teacher/assignments/{$assignment['id']}")
            ->assertOk()->assertJsonPath('data.questions.1.type', 'essay');

        $updated = $this->validAssignmentPayload($class->id);
        $updated['title'] = 'Ôn tập Đức Tin — đã chỉnh sửa';
        $updated['version'] = $assignment['version'];
        $this->actingAs($teacher)->patchJson("/api/teacher/assignments/{$assignment['id']}", $updated)
            ->assertOk()
            ->assertJsonPath('data.title', $updated['title'])
            ->assertJsonPath('data.version', $assignment['version'] + 1);

        $this->actingAs($teacher)->patchJson("/api/teacher/assignments/{$assignment['id']}", $updated)
            ->assertStatus(409)->assertJsonPath('code', 'VERSION_CONFLICT');
    }

    public function test_teacher_cannot_target_a_class_they_are_not_assigned_to(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $outsider = User::factory()->create();
        $outsider->assignRole('teacher');
        TeacherProfile::create([
            'user_id' => $outsider->id,
            'parish_id' => $teacher->teacherProfile->parish_id,
            'code' => 'GLV-KHONG-LOP',
        ]);

        $this->actingAs($outsider)->postJson('/api/teacher/assignments', $this->validAssignmentPayload($class->id))
            ->assertUnprocessable()->assertJsonPath('code', 'CLASS_NOT_ASSIGNED');
    }

    public function test_publishing_snapshots_active_recipients_and_preserves_history(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $assignmentId = $this->actingAs($teacher)->postJson(
            '/api/teacher/assignments',
            $this->validAssignmentPayload($class->id),
        )->assertCreated()->json('data.id');
        $activeEnrollmentIds = $class->activeEnrollments()->pluck('id');

        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignmentId}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonPath('data.recipients_count', $activeEnrollmentIds->count());

        $this->assertDatabaseCount('assignment_recipients', $activeEnrollmentIds->count());
        $this->assertEqualsCanonicalizing(
            $activeEnrollmentIds->all(),
            AssignmentRecipient::where('assignment_id', $assignmentId)->pluck('enrollment_id')->all(),
        );

        $class->activeEnrollments()->firstOrFail()->update(['status' => 'inactive']);
        $this->assertSame($activeEnrollmentIds->count(), AssignmentRecipient::where('assignment_id', $assignmentId)->count());
        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignmentId}/publish")
            ->assertUnprocessable()->assertJsonPath('code', 'ASSIGNMENT_ALREADY_PUBLISHED');
    }

    public function test_recipient_child_can_start_resume_and_autosave_without_seeing_answers(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $child = User::where('email', 'child@giaoly.test')->firstOrFail();
        $class = $child->child->activeEnrollment->catechismClass;
        $assignmentId = $this->actingAs($teacher)->postJson(
            '/api/teacher/assignments', $this->validAssignmentPayload($class->id),
        )->assertCreated()->json('data.id');
        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignmentId}/publish")->assertOk();

        $this->actingAs($child)->getJson("/api/child/assignments/{$assignmentId}")
            ->assertOk()
            ->assertJsonMissingPath('data.questions.0.options.0.is_correct')
            ->assertJsonMissingPath('data.questions.0.explanation');
        $this->actingAs($child)->postJson("/api/child/assignments/{$assignmentId}/attempts")
            ->assertUnprocessable()->assertJsonPath('code', 'ASSIGNMENT_NOT_OPEN');

        $assignment = Assignment::findOrFail($assignmentId);
        $assignment->update(['opens_at' => now()->subMinute(), 'status' => Assignment::STATUS_PUBLISHED]);
        $submission = $this->actingAs($child)->postJson("/api/child/assignments/{$assignmentId}/attempts")
            ->assertCreated()
            ->assertJsonPath('data.attempt_number', 1)
            ->assertJsonPath('data.version', 1)
            ->json('data');
        $this->actingAs($child)->postJson("/api/child/assignments/{$assignmentId}/attempts")
            ->assertOk()->assertJsonPath('data.id', $submission['id']);

        $questionId = $assignment->questions()->orderBy('position')->value('id');
        $saved = $this->actingAs($child)->patchJson("/api/child/submissions/{$submission['id']}/answers", [
            'version' => 1,
            'answers' => [['question_id' => $questionId, 'answer' => ['selected' => [0]]]],
        ])->assertOk()->assertJsonPath('data.version', 2)->json('data');
        $this->assertDatabaseHas('submission_answers', [
            'submission_id' => $submission['id'], 'assignment_question_id' => $questionId,
        ]);
        $this->actingAs($child)->patchJson("/api/child/submissions/{$submission['id']}/answers", [
            'version' => 1,
            'answers' => [['question_id' => $questionId, 'answer' => ['selected' => [1]]]],
        ])->assertStatus(409)->assertJsonPath('code', 'VERSION_CONFLICT');
        $this->assertSame(2, $saved['version']);
    }

    private function validAssignmentPayload(int $classId): array
    {
        return [
            'title' => 'Ôn tập Đức Tin',
            'description' => 'Hoàn thành các câu hỏi trước giờ học Chúa nhật.',
            'type' => 'hybrid',
            'max_score' => 10,
            'passing_score' => 5,
            'opens_at' => now()->addHour()->toIso8601String(),
            'due_at' => now()->addWeek()->toIso8601String(),
            'time_limit_minutes' => 30,
            'allowed_attempts' => 2,
            'score_method' => 'highest',
            'allow_resume' => true,
            'allow_late' => false,
            'late_penalty_percent' => 0,
            'shuffle_questions' => true,
            'shuffle_options' => true,
            'allow_backtracking' => true,
            'result_release_mode' => 'manual',
            'show_answers' => true,
            'targets' => [['catechism_class_id' => $classId, 'child_ids' => []]],
            'questions' => [
                [
                    'type' => 'single_choice', 'prompt' => 'Ai dựng nên trời đất?',
                    'points' => 4, 'position' => 1,
                    'options' => [
                        ['content' => 'Thiên Chúa', 'is_correct' => true],
                        ['content' => 'Con người', 'is_correct' => false],
                    ],
                ],
                [
                    'type' => 'essay', 'prompt' => 'Em sống đức tin như thế nào?',
                    'points' => 6, 'position' => 2,
                    'rubric' => [['label' => 'Nội dung', 'points' => 6]],
                ],
            ],
        ];
    }
}
