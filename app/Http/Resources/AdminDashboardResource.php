<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this['summary'],
            'attendance' => $this['attendance'],
            'parishes' => $this['parishes'],
            'recent_announcements' => $this['recent_announcements'],
            'recent_sessions' => $this['recent_sessions'],
        ];
    }
}
