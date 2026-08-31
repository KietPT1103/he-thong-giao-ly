<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_bank_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parish_id')->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users');
            $table->string('scope')->default('personal')->index();
            $table->string('type')->index();
            $table->text('prompt');
            $table->text('explanation')->nullable();
            $table->decimal('default_points', 8, 2)->default(1);
            $table->string('difficulty')->default('medium')->index();
            $table->json('tags')->nullable();
            $table->json('options')->nullable();
            $table->json('accepted_answers')->nullable();
            $table->json('rubric')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['parish_id', 'scope', 'type']);
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type')->default('hybrid')->index();
            $table->string('status')->default('draft')->index();
            $table->decimal('max_score', 8, 2)->default(10);
            $table->decimal('passing_score', 8, 2)->default(5);
            $table->timestamp('opens_at')->nullable()->index();
            $table->timestamp('due_at')->nullable()->index();
            $table->unsignedInteger('time_limit_minutes')->nullable();
            $table->unsignedSmallInteger('allowed_attempts')->default(1);
            $table->string('score_method')->default('highest');
            $table->boolean('allow_resume')->default(true);
            $table->boolean('allow_late')->default(false);
            $table->decimal('late_penalty_percent', 5, 2)->default(0);
            $table->boolean('shuffle_questions')->default(false);
            $table->boolean('shuffle_options')->default(false);
            $table->boolean('allow_backtracking')->default(true);
            $table->string('result_release_mode')->default('manual');
            $table->timestamp('results_release_at')->nullable();
            $table->boolean('show_answers')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assignment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_question_id')->nullable()->constrained('question_bank_items')->nullOnDelete();
            $table->string('type');
            $table->text('prompt');
            $table->text('explanation')->nullable();
            $table->decimal('points', 8, 2)->default(1);
            $table->unsignedInteger('position');
            $table->json('options')->nullable();
            $table->json('accepted_answers')->nullable();
            $table->json('rubric')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'position']);
        });

        Schema::create('assignment_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catechism_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->unsignedSmallInteger('attempt_limit')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'catechism_class_id', 'child_id'], 'assignment_target_unique');
        });

        Schema::create('assignment_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catechism_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('due_at')->nullable();
            $table->string('access_status')->default('active')->index();
            $table->timestamps();
            $table->unique(['assignment_id', 'child_id']);
        });

        Schema::create('assignment_accommodations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->timestamp('due_at')->nullable();
            $table->unsignedSmallInteger('extra_attempts')->default(0);
            $table->foreignId('granted_by')->constrained('users');
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'child_id']);
        });

        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('status')->default('in_progress')->index();
            $table->timestamp('started_at');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->decimal('auto_score', 8, 2)->default(0);
            $table->decimal('manual_score', 8, 2)->default(0);
            $table->decimal('final_score', 8, 2)->nullable();
            $table->boolean('is_late')->default(false);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('assigned_grader_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('general_feedback')->nullable();
            $table->timestamps();
            $table->unique(['assignment_id', 'child_id', 'attempt_number']);
        });

        Schema::create('submission_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_question_id')->constrained()->cascadeOnDelete();
            $table->json('answer')->nullable();
            $table->decimal('auto_score', 8, 2)->default(0);
            $table->decimal('manual_score', 8, 2)->default(0);
            $table->json('rubric_scores')->nullable();
            $table->text('feedback')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('graded_at')->nullable();
            $table->timestamp('saved_at')->nullable();
            $table->timestamps();
            $table->unique(['submission_id', 'assignment_question_id']);
        });

        Schema::create('submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('size');
            $table->unsignedSmallInteger('version')->default(1);
            $table->timestamps();
        });

        Schema::create('grade_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->constrained('users');
            $table->decimal('old_score', 8, 2)->nullable();
            $table->decimal('new_score', 8, 2)->nullable();
            $table->text('reason');
            $table->json('details')->nullable();
            $table->timestamps();
        });

        Schema::table('announcements', function (Blueprint $table) {
            $table->string('status')->default('draft')->index();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('requires_acknowledgement')->default(false);
            $table->string('source_type')->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedInteger('version')->default(1);
        });
        Schema::table('announcement_recipients', function (Blueprint $table) {
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
        });
        Schema::create('announcement_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catechism_class_id')->constrained()->cascadeOnDelete();
            $table->foreignId('child_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('audience')->default('children');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_targets');
        Schema::table('announcement_recipients', function (Blueprint $table) {
            $table->dropColumn(['acknowledged_at', 'reminded_at']);
        });
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn([
                'status', 'scheduled_at', 'sent_at', 'expires_at', 'withdrawn_at',
                'is_pinned', 'requires_acknowledgement', 'source_type', 'source_id', 'version',
            ]);
        });
        foreach ([
            'grade_histories', 'submission_files', 'submission_answers', 'submissions',
            'assignment_accommodations', 'assignment_recipients', 'assignment_targets',
            'assignment_questions', 'assignments', 'question_bank_items',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
