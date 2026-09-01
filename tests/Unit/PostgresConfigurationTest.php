<?php

namespace Tests\Unit;

use PDO;
use Tests\TestCase;

class PostgresConfigurationTest extends TestCase
{
    public function test_postgres_emulates_prepares_by_default_for_pooled_connections(): void
    {
        $options = config('database.connections.pgsql.options', []);

        $this->assertArrayHasKey(PDO::ATTR_EMULATE_PREPARES, $options);
        $this->assertTrue($options[PDO::ATTR_EMULATE_PREPARES]);
    }
}
