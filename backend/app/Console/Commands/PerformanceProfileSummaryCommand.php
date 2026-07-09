<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PerformanceProfileSummaryCommand extends Command
{
    protected $signature = 'performance:profile-summary {--json}';
    protected $description = 'Summarize Sprint 38 performance benchmark profiles.';

    public function handle(): int
    {
        $data = ['default_profile' => config('performance_governance.default_profile'), 'profiles' => config('performance_governance.profiles')];
        $this->line($this->option('json') ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 'Profiles: '.implode(', ', array_keys($data['profiles'])));
        return self::SUCCESS;
    }
}
