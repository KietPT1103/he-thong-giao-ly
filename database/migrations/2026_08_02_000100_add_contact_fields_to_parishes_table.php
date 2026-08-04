<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('parishes', 'phone')) {
            Schema::table('parishes', function (Blueprint $table) {
                $table->string('phone', 30)->nullable();
            });
        }

        if (! Schema::hasColumn('parishes', 'email')) {
            Schema::table('parishes', function (Blueprint $table) {
                $table->string('email')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('parishes', 'email')) {
            Schema::table('parishes', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }

        if (Schema::hasColumn('parishes', 'phone')) {
            Schema::table('parishes', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
