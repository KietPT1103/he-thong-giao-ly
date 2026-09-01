<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $t) => tap($t, function ($t) {
            $t->string('status')->default('active')->index();
            $t->timestamp('last_login_at')->nullable();
            $t->boolean('must_change_password')->default(false);
        }));
        Schema::create('teacher_profiles', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('parish_id')->constrained();
            $t->string('code')->nullable()->unique();
            $t->string('phone')->nullable();
            $t->timestamps();
        }));
        Schema::create('parent_profiles', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('parish_id')->constrained();
            $t->string('phone')->nullable();
            $t->timestamps();
        }));
        Schema::create('parent_child', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('parent_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('child_id')->constrained()->cascadeOnDelete();
            $t->string('relationship')->default('parent');
            $t->timestamps();
            $t->unique(['parent_profile_id', 'child_id']);
        }));
        Schema::create('teacher_class_assignments', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('teacher_profile_id')->constrained()->cascadeOnDelete();
            $t->foreignId('catechism_class_id')->constrained()->cascadeOnDelete();
            $t->string('role')->default('primary');
            $t->timestamps();
            $t->unique(['teacher_profile_id', 'catechism_class_id']);
        }));
        Schema::create('class_schedules', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('catechism_class_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('weekday');
            $t->time('starts_at');
            $t->time('ends_at');
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->timestamps();
        }));
        Schema::create('leave_requests', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('child_id')->constrained();
            $t->foreignId('attendance_session_id')->nullable()->constrained();
            $t->date('absence_date');
            $t->text('reason');
            $t->string('status')->default('pending')->index();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('review_note')->nullable();
            $t->timestamps();
        }));
        Schema::create('announcements', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('parish_id')->constrained();
            $t->foreignId('created_by')->constrained('users');
            $t->string('title');
            $t->text('body');
            $t->string('importance')->default('normal');
            $t->timestamps();
        }));
        Schema::create('announcement_recipients', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->unique(['announcement_id', 'user_id']);
        }));
        Schema::create('activity_logs', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->nullableMorphs('subject');
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->timestamps();
            $t->index(['action', 'created_at']);
        }));
    }

    public function down(): void
    {
        foreach (['activity_logs', 'announcement_recipients', 'announcements', 'leave_requests', 'class_schedules', 'teacher_class_assignments', 'parent_child', 'parent_profiles', 'teacher_profiles'] as $t) {
            Schema::dropIfExists($t);
        } Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['status', 'last_login_at', 'must_change_password']));
    }
};
