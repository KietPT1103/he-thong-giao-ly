<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            // Existing rows are history; only an explicit "Mở phiên" action creates an active session.
            $table->string('status', 20)->default('ended')->after('qr_expires_at')->index();
            $table->dateTime('started_at')->nullable()->after('status');
            $table->dateTime('ended_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'started_at', 'ended_at']);
        });
    }
};
