<?php

use App\Jobs\CleanupAuditLogs;
use App\Jobs\CleanupExpiredAliases;
use App\Jobs\SendExpiryWarnings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// ── Scheduled jobs ─────────────────────────────────────────────────────────
Schedule::job(new CleanupExpiredAliases)->hourly()->withoutOverlapping();
Schedule::job(new CleanupAuditLogs)->daily()->withoutOverlapping();
Schedule::job(new SendExpiryWarnings)->hourly()->withoutOverlapping();

// ── Dev helpers ────────────────────────────────────────────────────────────
Artisan::command('inspire', function () {
    $this->comment(\Illuminate\Foundation\Inspiring::quote());
})->purpose('Display an inspiring quote');
