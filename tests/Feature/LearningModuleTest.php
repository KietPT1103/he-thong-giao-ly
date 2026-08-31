<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
