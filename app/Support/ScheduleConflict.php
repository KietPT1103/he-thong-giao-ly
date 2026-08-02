<?php

namespace App\Support;

final class ScheduleConflict
{
    public static function overlaps(array $left, array $right): bool
    {
        if (self::weekday($left) !== self::weekday($right)) {
            return false;
        }

        if (! ($left['starts_at'] < $right['ends_at'] && $right['starts_at'] < $left['ends_at'])) {
            return false;
        }

        $leftStarts = $left['starts_on'] ?? null;
        $leftEnds = $left['ends_on'] ?? null;
        $rightStarts = $right['starts_on'] ?? null;
        $rightEnds = $right['ends_on'] ?? null;

        if ($leftEnds !== null && $rightStarts !== null && $leftEnds < $rightStarts) {
            return false;
        }

        if ($rightEnds !== null && $leftStarts !== null && $rightEnds < $leftStarts) {
            return false;
        }

        return true;
    }

    private static function weekday(array $schedule): int
    {
        $weekday = (int) $schedule['weekday'];

        return $weekday === 0 ? 7 : $weekday;
    }
}
