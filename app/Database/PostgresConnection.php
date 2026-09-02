<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as BasePostgresConnection;

class PostgresConnection extends BasePostgresConnection
{
    /**
     * Keep boolean values valid when PDO emulates prepared statements.
     *
     * Laravel normally converts booleans to integers before binding. With
     * emulated PostgreSQL prepares those values are inserted as the integer
     * literals 0/1, which PostgreSQL cannot assign to boolean columns.
     *
     * @param  array<int|string, mixed>  $bindings
     * @return array<int|string, mixed>
     */
    public function prepareBindings(array $bindings)
    {
        foreach ($bindings as $key => $value) {
            if (is_bool($value)) {
                $bindings[$key] = $value ? 'true' : 'false';
            }
        }

        return parent::prepareBindings($bindings);
    }
}
