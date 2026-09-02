<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\CatechismClassResource;
use Illuminate\Http\Request;

class ChildScheduleController extends ApiController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        abort_unless($user->hasRole('child') && $user->child, 403);

        $child = $user->child;
        $child->load([
            'activeEnrollment.catechismClass.academicYear.parish',
            'activeEnrollment.catechismClass.level',
            'activeEnrollment.catechismClass.classroom',
            'activeEnrollment.catechismClass.schedules',
            'activeEnrollment.catechismClass.teachers.user',
        ]);
        $class = $child->activeEnrollment?->catechismClass;

        return $this->success([
            'child' => [
                'id' => $child->id,
                'code' => $child->code,
                'full_name' => $child->full_name,
            ],
            'class' => $class
                ? CatechismClassResource::make($class)->resolve($request)
                : null,
        ], 'Đã tải lịch học của em.');
    }
}
