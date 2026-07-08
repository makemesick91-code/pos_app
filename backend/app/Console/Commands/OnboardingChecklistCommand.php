<?php

namespace App\Console\Commands;

use App\Models\TenantProvisioningRun;
use App\Services\TenantOnboarding\OnboardingChecklistService;
use Illuminate\Console\Command;

/**
 * Sprint 33 — onboarding:checklist. Prints the deterministic checklist for a run
 * (ONB-R022). No PII/secrets (ONB-R024).
 */
class OnboardingChecklistCommand extends Command
{
    protected $signature = 'onboarding:checklist {run : Provisioning run id} {--json : Output JSON}';

    protected $description = 'Show the deterministic onboarding checklist for a provisioning run.';

    public function handle(OnboardingChecklistService $service): int
    {
        $run = TenantProvisioningRun::query()->find((int) $this->argument('run'));

        if (! $run instanceof TenantProvisioningRun) {
            $this->error('Provisioning run not found.');

            return self::FAILURE;
        }

        $checklist = $service->build($run);

        if ($this->option('json')) {
            $this->line((string) json_encode($checklist, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Onboarding Checklist — run #'.$run->id.' ('.$run->status.')');
        foreach ($checklist['items'] as $key => $item) {
            $this->line(sprintf('[%s] %s — %s', $item['done'] ? 'x' : ' ', $key, $item['reason_code']));
        }
        $this->line('Complete: '.($checklist['complete'] ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
