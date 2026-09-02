<?php

namespace Tests\Unit;

use App\Database\PostgresConnection;
use Illuminate\Database\Connection;
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

    public function test_postgres_boolean_bindings_are_safe_for_emulated_prepares(): void
    {
        $resolver = Connection::getResolver('pgsql');

        $this->assertNotNull($resolver);

        $connection = $resolver(new PDO('sqlite::memory:'), '', '', []);

        $this->assertInstanceOf(PostgresConnection::class, $connection);

        $this->assertSame(
            ['true', 'false', 1, 'unchanged'],
            $connection->prepareBindings([true, false, 1, 'unchanged']),
        );
    }
}
