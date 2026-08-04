<?php

namespace Tests\Feature\Migrations;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AddContactFieldsToParishesTableTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('parishes');
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('parishes');

        parent::tearDown();
    }

    public function test_it_adds_only_email_when_phone_already_exists(): void
    {
        Schema::create('parishes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('parishes', 'phone'));
        $this->assertTrue(Schema::hasColumn('parishes', 'email'));
    }

    public function test_it_adds_only_phone_when_email_already_exists(): void
    {
        Schema::create('parishes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('parishes', 'phone'));
        $this->assertTrue(Schema::hasColumn('parishes', 'email'));
    }

    private function migration(): object
    {
        return require database_path(
            'migrations/2026_08_02_000100_add_contact_fields_to_parishes_table.php',
        );
    }
}
