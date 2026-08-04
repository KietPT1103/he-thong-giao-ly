<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PostgreSQL aborts the whole transaction after a failed DDL statement.
     * Run this idempotent migration without the migrator transaction so a
     * concurrent deploy cannot leave the connection in an aborted state.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(<<<'SQL'
                ALTER TABLE "parishes"
                    ADD COLUMN IF NOT EXISTS "phone" VARCHAR(30) NULL,
                    ADD COLUMN IF NOT EXISTS "email" VARCHAR(255) NULL
                SQL);

            return;
        }

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
