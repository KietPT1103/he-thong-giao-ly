<?php

use App\Services\DemoCatechismCleanupService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:collapse-catechism {--execute} {--force}', function (DemoCatechismCleanupService $cleanup) {
    try {
        $plan = $cleanup->plan();
    } catch (DomainException $exception) {
        $this->error($exception->getMessage());

        return self::FAILURE;
    }
    $this->table(['Mục', 'Giá trị'], collect($plan)->map(fn ($value, $key) => [$key, $value])->values()->all());

    if (! $this->option('execute')) {
        $this->warn('Chỉ xem trước; chưa có dữ liệu nào bị xóa.');

        return self::SUCCESS;
    }
    if (! $this->option('force')) {
        $this->error('Phải truyền đồng thời --execute --force để xóa dữ liệu demo.');

        return self::FAILURE;
    }

    try {
        $result = $cleanup->execute();
    } catch (DomainException $exception) {
        $this->error($exception->getMessage());

        return self::FAILURE;
    }
    $this->info("Đã giữ lại {$result['kept_child_name']} và lớp {$result['kept_class_name']}.");

    return self::SUCCESS;
})->purpose('Giữ lại một lớp và Thiếu nhi 1 trong bộ dữ liệu demo');
