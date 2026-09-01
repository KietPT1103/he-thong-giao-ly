<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parishes', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->timestamps();
        }));
        Schema::create('academic_years', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('parish_id')->constrained();
            $t->string('name');
            $t->date('starts_on');
            $t->date('ends_on');
            $t->boolean('is_current')->default(false);
            $t->timestamps();
            $t->unique(['parish_id', 'name']);
        }));
        Schema::create('catechism_levels', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('parish_id')->constrained();
            $t->string('name');
            $t->string('code');
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
            $t->unique(['parish_id', 'code']);
        }));
        Schema::create('classrooms', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('parish_id')->constrained();
            $t->string('name');
            $t->unsignedSmallInteger('capacity')->nullable();
            $t->timestamps();
        }));
        Schema::create('catechism_classes', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('academic_year_id')->constrained();
            $t->foreignId('catechism_level_id')->constrained();
            $t->foreignId('classroom_id')->nullable()->constrained();
            $t->string('name');
            $t->string('code');
            $t->string('status')->default('active');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['academic_year_id', 'code']);
        }));
        Schema::create('children', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('parish_id')->constrained();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('code');
            $t->string('full_name');
            $t->string('saint_name')->nullable();
            $t->date('date_of_birth')->nullable();
            $t->string('status')->default('studying');
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['parish_id', 'code']);
            $t->index(['parish_id', 'full_name']);
        }));
        Schema::create('enrollments', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('child_id')->constrained();
            $t->foreignId('catechism_class_id')->constrained();
            $t->string('status')->default('active');
            $t->timestamps();
            $t->unique(['child_id', 'catechism_class_id']);
        }));
        Schema::create('attendance_sessions', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('catechism_class_id')->constrained();
            $t->dateTime('held_at');
            $t->foreignId('taken_by')->constrained('users');
            $t->text('note')->nullable();
            $t->timestamps();
            $t->index(['catechism_class_id', 'held_at']);
        }));
        Schema::create('attendances', fn (Blueprint $t) => tap($t, function ($t) {
            $t->id();
            $t->foreignId('attendance_session_id')->constrained();
            $t->foreignId('child_id')->constrained();
            $t->string('status')->default('unknown');
            $t->time('arrived_at')->nullable();
            $t->text('note')->nullable();
            $t->timestamps();
            $t->unique(['attendance_session_id', 'child_id']);
        }));
    }

    public function down(): void
    {
        foreach (['attendances', 'attendance_sessions', 'enrollments', 'children', 'catechism_classes', 'classrooms', 'catechism_levels', 'academic_years', 'parishes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
