<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\VersionChecker;

class CheckVersionCommand extends Command
{
    protected $signature = 'app:check-version {--force}';

    protected $description = 'Check if a new application version is available';

    public function handle(VersionChecker $checker): int
    {
        $result = $checker->check($this->option('force'));

        if (!$result['success']) {
            $this->error($result['message']);

            return self::FAILURE;
        }

        $this->info("Current version : {$result['current']}");
        $this->info("Latest version  : {$result['latest']}");

        if ($result['has_update']) {
            $this->warn('A new version is available!');
        } else {
            $this->info('Application is up to date.');
        }

        return self::SUCCESS;
    }
}