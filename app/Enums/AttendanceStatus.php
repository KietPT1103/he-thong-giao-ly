<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case ExcusedAbsence = 'excused_absence';
    case UnexcusedAbsence = 'unexcused_absence';
    case LeftEarly = 'left_early';
    case Unknown = 'unknown';
}
