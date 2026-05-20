<?php

use App\Jobs\CleanupExpiredAliases;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// ── Scheduled jobs ─────────────────────────────────────────────────────────
Schedule::job(new CleanupExpiredAliases)->hourly()->withoutOverlapping();

// ── Dev helpers ────────────────────────────────────────────────────────────
Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');
