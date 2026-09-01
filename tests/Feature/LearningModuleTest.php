<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\Submission;
use App\Models\TeacherProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
            'assignment_files',
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
        $publishedPayload = $this->validAssignmentPayload($class->id);
        $publishedPayload['version'] = Assignment::findOrFail($assignmentId)->version;
        $this->actingAs($teacher)->patchJson("/api/teacher/assignments/{$assignmentId}", $publishedPayload)
            ->assertUnprocessable()->assertJsonPath('code', 'ASSIGNMENT_PUBLISHED_LOCKED');
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
        $this->actingAs($child)->getJson("/api/child/assignments/{$assignmentId}")
            ->assertOk()
            ->assertJsonPath('data.submissions.0.answers.0.answer.selected.0', 0)
            ->assertJsonMissingPath('data.submissions.0.answers.0.auto_score');
        $this->actingAs($child)->patchJson("/api/child/submissions/{$submission['id']}/answers", [
            'version' => 1,
            'answers' => [['question_id' => $questionId, 'answer' => ['selected' => [1]]]],
        ])->assertStatus(409)->assertJsonPath('code', 'VERSION_CONFLICT');
        $this->assertSame(2, $saved['version']);
    }

    public function test_submitting_a_hybrid_attempt_auto_grades_objective_answers_and_locks_it(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $child = User::where('email', 'child@giaoly.test')->firstOrFail();
        $class = $child->child->activeEnrollment->catechismClass;
        $payload = $this->validAssignmentPayload($class->id);
        $payload['opens_at'] = now()->subMinute()->toIso8601String();
        $assignmentId = $this->actingAs($teacher)->postJson('/api/teacher/assignments', $payload)
            ->assertCreated()->json('data.id');
        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignmentId}/publish")->assertOk();
        $submission = $this->actingAs($child)->postJson("/api/child/assignments/{$assignmentId}/attempts")
            ->assertCreated()->json('data');
        $questions = Assignment::findOrFail($assignmentId)->questions()->orderBy('position')->get();
        $this->actingAs($child)->patchJson("/api/child/submissions/{$submission['id']}/answers", [
            'version' => 1,
            'answers' => [
                ['question_id' => $questions[0]->id, 'answer' => ['selected' => [0]]],
                ['question_id' => $questions[1]->id, 'answer' => ['text' => 'Sống yêu thương mỗi ngày']],
            ],
        ])->assertOk();

        $this->actingAs($child)->postJson("/api/child/submissions/{$submission['id']}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', Submission::STATUS_GRADING)
            ->assertJsonPath('data.auto_score', 4)
            ->assertJsonPath('data.final_score', null);
        $this->assertDatabaseHas('submission_answers', [
            'submission_id' => $submission['id'], 'assignment_question_id' => $questions[0]->id,
            'auto_score' => 4,
        ]);
        $this->actingAs($child)->patchJson("/api/child/submissions/{$submission['id']}/answers", [
            'version' => 3,
            'answers' => [['question_id' => $questions[0]->id, 'answer' => ['selected' => [1]]]],
        ])->assertUnprocessable()->assertJsonPath('code', 'SUBMISSION_LOCKED');
    }

    public function test_all_objective_question_types_are_scored_automatically(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $child = User::where('email', 'child@giaoly.test')->firstOrFail();
        $class = $child->child->activeEnrollment->catechismClass;
        $payload = $this->validAssignmentPayload($class->id);
        $payload['type'] = 'quiz';
        $payload['opens_at'] = now()->subMinute()->toIso8601String();
        $payload['questions'] = [
            ['type' => 'single_choice', 'prompt' => 'Một lựa chọn', 'points' => 2, 'position' => 1,
                'options' => [['content' => 'Đúng', 'is_correct' => true], ['content' => 'Sai', 'is_correct' => false]]],
            ['type' => 'multiple_choice', 'prompt' => 'Nhiều lựa chọn', 'points' => 3, 'position' => 2,
                'settings' => ['partial_credit' => true],
                'options' => [['content' => 'A', 'is_correct' => true], ['content' => 'B', 'is_correct' => true], ['content' => 'C', 'is_correct' => false]]],
            ['type' => 'true_false', 'prompt' => 'Đúng hay sai', 'points' => 2, 'position' => 3,
                'options' => [['content' => 'Đúng', 'is_correct' => true], ['content' => 'Sai', 'is_correct' => false]]],
            ['type' => 'short_answer', 'prompt' => 'Trả lời ngắn', 'points' => 3, 'position' => 4,
                'accepted_answers' => ['Thiên Chúa']],
        ];
        $assignmentId = $this->actingAs($teacher)->postJson('/api/teacher/assignments', $payload)
            ->assertCreated()->json('data.id');
        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignmentId}/publish")->assertOk();
        $submission = $this->actingAs($child)->postJson("/api/child/assignments/{$assignmentId}/attempts")
            ->assertCreated()->json('data');
        $questions = Assignment::findOrFail($assignmentId)->questions()->orderBy('position')->get();
        $this->actingAs($child)->patchJson("/api/child/submissions/{$submission['id']}/answers", [
            'version' => 1,
            'answers' => [
                ['question_id' => $questions[0]->id, 'answer' => ['selected' => [0]]],
                ['question_id' => $questions[1]->id, 'answer' => ['selected' => [0]]],
                ['question_id' => $questions[2]->id, 'answer' => ['selected' => [0]]],
                ['question_id' => $questions[3]->id, 'answer' => ['text' => '  thiên chúa  ']],
            ],
        ])->assertOk();

        $this->actingAs($child)->postJson("/api/child/submissions/{$submission['id']}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', Submission::STATUS_GRADED)
            ->assertJsonPath('data.auto_score', 8.5)
            ->assertJsonPath('data.final_score', 8.5);
    }

    public function test_teacher_grades_essay_then_releases_results_with_audited_corrections(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $child = User::where('email', 'child@giaoly.test')->firstOrFail();
        $class = $child->child->activeEnrollment->catechismClass;
        $payload = $this->validAssignmentPayload($class->id);
        $payload['opens_at'] = now()->subMinute()->toIso8601String();
        $assignmentId = $this->actingAs($teacher)->postJson('/api/teacher/assignments', $payload)
            ->assertCreated()->json('data.id');
        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignmentId}/publish")->assertOk();
        $submission = $this->actingAs($child)->postJson("/api/child/assignments/{$assignmentId}/attempts")
            ->assertCreated()->json('data');
        $questions = Assignment::findOrFail($assignmentId)->questions()->orderBy('position')->get();
        $this->actingAs($child)->patchJson("/api/child/submissions/{$submission['id']}/answers", [
            'version' => 1,
            'answers' => [
                ['question_id' => $questions[0]->id, 'answer' => ['selected' => [0]]],
                ['question_id' => $questions[1]->id, 'answer' => ['text' => 'Sống yêu thương']],
            ],
        ])->assertOk();
        $submitted = $this->actingAs($child)->postJson("/api/child/submissions/{$submission['id']}/submit")
            ->assertOk()->json('data');

        $this->actingAs($child)->getJson("/api/child/assignments/{$assignmentId}")
            ->assertOk()
            ->assertJsonMissingPath('data.submissions.0.final_score')
            ->assertJsonMissingPath('data.submissions.0.answers');
        $this->actingAs($teacher)->getJson("/api/teacher/assignments/{$assignmentId}/submissions")
            ->assertOk()->assertJsonPath('data.data.0.id', $submission['id']);

        $graded = $this->actingAs($teacher)->patchJson("/api/teacher/submissions/{$submission['id']}/grade", [
            'version' => $submitted['version'],
            'general_feedback' => 'Em trình bày rõ ràng.',
            'answers' => [[
                'question_id' => $questions[1]->id,
                'score' => 6,
                'feedback' => 'Có ví dụ thực tế.',
                'rubric_scores' => [['label' => 'Nội dung', 'score' => 6]],
            ]],
        ])->assertOk()
            ->assertJsonPath('data.status', Submission::STATUS_GRADED)
            ->assertJsonPath('data.final_score', 10)
            ->json('data');

        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignmentId}/release")
            ->assertOk()->assertJsonPath('data.status', Assignment::STATUS_RELEASED);
        $this->actingAs($child)->getJson("/api/child/assignments/{$assignmentId}")
            ->assertOk()
            ->assertJsonPath('data.submissions.0.final_score', 10)
            ->assertJsonPath('data.submissions.0.general_feedback', 'Em trình bày rõ ràng.')
            ->assertJsonPath('data.questions.0.options.0.is_correct', true);

        $correction = [
            'version' => $graded['version'] + 1,
            'general_feedback' => 'Điều chỉnh sau đối soát.',
            'answers' => [[
                'question_id' => $questions[1]->id, 'score' => 5,
                'feedback' => 'Cần thêm ví dụ.', 'rubric_scores' => [],
            ]],
        ];
        $this->actingAs($teacher)->patchJson("/api/teacher/submissions/{$submission['id']}/grade", $correction)
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $correction['reason'] = 'Rà soát lại rubric';
        $this->actingAs($teacher)->patchJson("/api/teacher/submissions/{$submission['id']}/grade", $correction)
            ->assertOk()->assertJsonPath('data.final_score', 9);
        $this->assertDatabaseHas('grade_histories', [
            'submission_id' => $submission['id'], 'reason' => 'Rà soát lại rubric',
        ]);
    }

    public function test_teacher_can_view_and_export_assignment_result_statistics(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $enrollments = $class->activeEnrollments()->with('child')->limit(2)->get();
        $assignment = Assignment::create([
            'created_by' => $teacher->id, 'title' => 'Báo cáo thử nghiệm',
            'status' => Assignment::STATUS_RELEASED, 'passing_score' => 5,
        ]);
        $assignment->targets()->create(['catechism_class_id' => $class->id]);
        foreach ($enrollments as $index => $enrollment) {
            $assignment->recipients()->create([
                'catechism_class_id' => $class->id,
                'child_id' => $enrollment->child_id,
                'enrollment_id' => $enrollment->id,
                'assigned_at' => now(),
            ]);
            Submission::create([
                'assignment_id' => $assignment->id,
                'child_id' => $enrollment->child_id,
                'attempt_number' => 1,
                'status' => Submission::STATUS_RELEASED,
                'started_at' => now()->subHour(),
                'submitted_at' => now()->subMinutes(30),
                'released_at' => now(),
                'final_score' => $index === 0 ? 8 : 4,
                'is_late' => $index === 1,
            ]);
        }

        $this->actingAs($teacher)->getJson("/api/teacher/assignments/{$assignment->id}/report")
            ->assertOk()
            ->assertJsonPath('data.summary.recipient_count', 2)
            ->assertJsonPath('data.summary.submitted_count', 2)
            ->assertJsonPath('data.summary.late_count', 1)
            ->assertJsonPath('data.summary.average_score', 6)
            ->assertJsonPath('data.summary.pass_rate', 50)
            ->assertJsonPath('data.distribution.below_5', 1)
            ->assertJsonPath('data.distribution.from_7_to_8_5', 1);

        $export = $this->actingAs($teacher)->get("/api/teacher/assignments/{$assignment->id}/report/export");
        $export->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Báo cáo thử nghiệm', $export->streamedContent());
    }

    public function test_teacher_can_send_an_important_class_announcement_and_track_acknowledgements(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $children = $class->activeEnrollments()->with('child.user')->limit(2)->get()->pluck('child');
        $secondChildUser = User::factory()->create(['email' => 'child2@giaoly.test']);
        $secondChildUser->assignRole('child');
        $children->last()->update(['user_id' => $secondChildUser->id]);

        $announcement = $this->actingAs($teacher)->postJson('/api/teacher/announcements', [
            'title' => 'Chuẩn bị Thánh lễ Chúa nhật',
            'body' => 'Các em có mặt trước giờ lễ 15 phút và mang theo khăn quàng.',
            'importance' => 'important',
            'is_pinned' => true,
            'requires_acknowledgement' => true,
            'targets' => [[
                'catechism_class_id' => $class->id,
                'audience' => 'children',
                'child_ids' => [],
            ]],
        ])->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->json('data');

        $this->actingAs($teacher)->postJson("/api/teacher/announcements/{$announcement['id']}/send")
            ->assertOk()
            ->assertJsonPath('data.status', 'sent')
            ->assertJsonPath('data.recipient_count', 2);

        $firstChildUser = $children->first()->user;
        $this->actingAs($firstChildUser)->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Chuẩn bị Thánh lễ Chúa nhật')
            ->assertJsonPath('meta.unread_count', 1);
        $this->actingAs($firstChildUser)->postJson("/api/notifications/{$announcement['id']}/read")
            ->assertOk()->assertJsonPath('data.is_read', true);
        $this->actingAs($firstChildUser)->postJson("/api/notifications/{$announcement['id']}/acknowledge")
            ->assertOk()->assertJsonPath('data.is_acknowledged', true);

        $this->actingAs($teacher)->postJson("/api/teacher/announcements/{$announcement['id']}/remind")
            ->assertOk()->assertJsonPath('data.reminded_count', 1);
        $this->assertDatabaseHas('announcement_recipients', [
            'announcement_id' => $announcement['id'],
            'user_id' => $secondChildUser->id,
        ]);
        $this->assertNotNull(Announcement::findOrFail($announcement['id'])
            ->recipients()->where('users.id', $secondChildUser->id)->first()->pivot->reminded_at);
    }

    public function test_publishing_an_assignment_creates_a_notification_for_every_recipient(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $class = $teacher->teacherProfile->classes()->firstOrFail();
        $assignment = $this->actingAs($teacher)->postJson(
            '/api/teacher/assignments',
            $this->validAssignmentPayload($class->id),
        )->assertCreated()->json('data');

        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignment['id']}/publish")
            ->assertOk();

        $notification = Announcement::where('source_type', 'assignment_published')
            ->where('source_id', $assignment['id'])->firstOrFail();
        $this->assertSame('sent', $notification->status);
        $this->assertSame(
            Assignment::findOrFail($assignment['id'])->recipients()
                ->whereHas('child', fn ($query) => $query->whereNotNull('user_id'))->count(),
            $notification->recipients()->count(),
        );
    }

    public function test_submission_files_are_private_limited_and_reject_executables(): void
    {
        Storage::fake('local');
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $child = User::where('email', 'child@giaoly.test')->firstOrFail();
        $class = $child->child->activeEnrollment->catechismClass;
        $payload = $this->validAssignmentPayload($class->id);
        $payload['opens_at'] = now()->subMinute()->toIso8601String();
        $assignmentId = $this->actingAs($teacher)->postJson('/api/teacher/assignments', $payload)
            ->assertCreated()->json('data.id');
        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignmentId}/publish")->assertOk();
        $submission = $this->actingAs($child)->postJson("/api/child/assignments/{$assignmentId}/attempts")
            ->assertCreated()->json('data');

        $file = $this->actingAs($child)->post("/api/child/submissions/{$submission['id']}/files", [
            'file' => UploadedFile::fake()->create('bai-lam.pdf', 100, 'application/pdf'),
        ])->assertCreated()->assertJsonMissingPath('data.path')->json('data');
        $this->assertDatabaseHas('submission_files', [
            'id' => $file['id'], 'submission_id' => $submission['id'], 'original_name' => 'bai-lam.pdf',
        ]);

        $otherChild = User::factory()->create(['email' => 'private-file-outsider@giaoly.test']);
        $otherChild->assignRole('child');
        $otherProfile = $class->activeEnrollments()->where('child_id', '!=', $child->child->id)->firstOrFail()->child;
        $otherProfile->update(['user_id' => $otherChild->id]);
        $this->actingAs($otherChild)->get("/api/learning-files/submissions/{$file['id']}")->assertForbidden();
        $this->actingAs($teacher)->get("/api/learning-files/submissions/{$file['id']}")->assertOk();

        $this->actingAs($child)->withHeaders(['Accept' => 'application/json'])->post("/api/child/submissions/{$submission['id']}/files", [
            'file' => UploadedFile::fake()->create('ma-doc.exe', 20, 'application/x-msdownload'),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        foreach (range(2, 5) as $version) {
            $submissionModel = Submission::findOrFail($submission['id']);
            $submissionModel->files()->create([
                'uploaded_by' => $child->id, 'path' => "learning/fake-{$version}.pdf",
                'original_name' => "fake-{$version}.pdf", 'mime_type' => 'application/pdf', 'size' => 100,
            ]);
        }
        $this->actingAs($child)->withHeaders(['Accept' => 'application/json'])->post("/api/child/submissions/{$submission['id']}/files", [
            'file' => UploadedFile::fake()->create('thu-sau.pdf', 20, 'application/pdf'),
        ])->assertUnprocessable();
    }

    public function test_teacher_can_change_due_date_grant_extra_attempt_close_and_withdraw(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $child = User::where('email', 'child@giaoly.test')->firstOrFail();
        $class = $child->child->activeEnrollment->catechismClass;
        $payload = $this->validAssignmentPayload($class->id);
        $payload['opens_at'] = now()->subMinute()->toIso8601String();
        $assignmentId = $this->actingAs($teacher)->postJson('/api/teacher/assignments', $payload)
            ->assertCreated()->json('data.id');
        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignmentId}/publish")->assertOk();

        $newDue = now()->addWeeks(2)->startOfMinute();
        $this->actingAs($teacher)->patchJson("/api/teacher/assignments/{$assignmentId}/due-date", [
            'due_at' => $newDue->toIso8601String(),
        ])->assertOk()->assertJsonPath('data.due_at', $newDue->toJSON());
        $this->assertDatabaseHas('announcements', [
            'source_type' => 'assignment_due_changed', 'source_id' => $assignmentId,
        ]);

        $this->actingAs($teacher)->putJson("/api/teacher/assignments/{$assignmentId}/accommodations/{$child->child->id}", [
            'extra_attempts' => 2,
            'due_at' => now()->addWeeks(3)->toIso8601String(),
            'reason' => 'Nghỉ bệnh có phép',
        ])->assertOk()->assertJsonPath('data.extra_attempts', 2);
        $extraAttemptNotice = Announcement::where('source_type', "assignment_extra_attempt_{$child->child->id}")
            ->where('source_id', $assignmentId)->firstOrFail();
        $this->assertSame(1, $extraAttemptNotice->recipients()->count());

        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignmentId}/close")
            ->assertOk()->assertJsonPath('data.status', Assignment::STATUS_CLOSED);
        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignmentId}/withdraw", [
            'reason' => 'Đề bài cần được thay thế',
        ])->assertOk()->assertJsonPath('data.status', Assignment::STATUS_WITHDRAWN);
    }

    public function test_teacher_can_reopen_a_graded_submission_for_the_child(): void
    {
        $teacher = User::where('email', 'teacher@giaoly.test')->firstOrFail();
        $child = User::where('email', 'child@giaoly.test')->firstOrFail();
        $class = $child->child->activeEnrollment->catechismClass;
        $payload = $this->validAssignmentPayload($class->id);
        $payload['opens_at'] = now()->subMinute()->toIso8601String();
        $payload['questions'] = [$payload['questions'][0]];
        $assignmentId = $this->actingAs($teacher)->postJson('/api/teacher/assignments', $payload)
            ->assertCreated()->json('data.id');
        $this->actingAs($teacher)->postJson("/api/teacher/assignments/{$assignmentId}/publish")->assertOk();
        $submission = $this->actingAs($child)->postJson("/api/child/assignments/{$assignmentId}/attempts")
            ->assertCreated()->json('data');
        $questionId = Assignment::findOrFail($assignmentId)->questions()->value('id');
        $this->actingAs($child)->patchJson("/api/child/submissions/{$submission['id']}/answers", [
            'version' => 1, 'answers' => [['question_id' => $questionId, 'answer' => ['selected' => [0]]]],
        ])->assertOk();
        $this->actingAs($child)->postJson("/api/child/submissions/{$submission['id']}/submit")
            ->assertOk()->assertJsonPath('data.status', Submission::STATUS_GRADED);

        $this->actingAs($teacher)->postJson("/api/teacher/submissions/{$submission['id']}/reopen", [
            'reason' => 'Cho em sửa lại theo hướng dẫn',
        ])->assertOk()->assertJsonPath('data.status', Submission::STATUS_REOPENED);
        $this->actingAs($child)->postJson("/api/child/assignments/{$assignmentId}/attempts")
            ->assertOk()->assertJsonPath('data.id', $submission['id']);
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
