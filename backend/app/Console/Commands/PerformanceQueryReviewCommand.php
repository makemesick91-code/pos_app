<?php

namespace App\Console\Commands;

use App\Services\Performance\IndexReviewService;
use Illuminate\Console\Command;

class PerformanceQueryReviewCommand extends Command
{
    protected $signature = 'performance:query-review {--execute} {--json}';
    protected $description = 'Record safe Sprint 38 query/index review evidence; dry-run by default.';

    public function handle(IndexReviewService $service): int
    {
        $reviews = $service->review((bool) $this->option('execute'));
        $this->line(json_encode(['mode' => $this->option('execute') ? 'execute' : 'dry_run', 'reviews' => $reviews], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return self::SUCCESS;
    }
}
