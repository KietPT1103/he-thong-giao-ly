<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AuditLogger
{
    private const SENSITIVE = [
        'password', 'password_confirmation', 'current_password', 'token',
        'session_id', 'secret', 'mfa_secret', 'code', 'recovery_code', 'recovery_codes',
    ];

    public function record(Request $request, string $action, ?Model $subject = null, mixed $old = null, mixed $new = null): ActivityLog
    {
        return ActivityLog::create([
            'user_id' => $request->user()?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old_values' => $this->sanitize($old),
            'new_values' => $this->sanitize($new),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500),
        ]);
    }

    private function sanitize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $clean = Arr::except($value, self::SENSITIVE);

        return array_map(fn (mixed $item) => $this->sanitize($item), $clean);
    }
}
