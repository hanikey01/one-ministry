<?php

namespace App\Console\Commands;

use App\Services\Ai\AiPageGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProcessAiPageGenerationsCommand extends Command
{
    protected $signature = 'ministry:process-ai-pages {--limit=1}';

    protected $description = 'Process bounded database-backed AI page proposal stages';

    public function handle(AiPageGenerationService $generations): int
    {
        $limit = max(1, min(5, (int) $this->option('limit')));
        for ($i = 0; $i < $limit; $i++) {
            if (! $generations->processNext()) {
                break;
            }
        }
        DB::table('ai_page_generations')->whereIn('state', ['completed', 'failed', 'cancelled'])->where('completed_at', '<', now()->subDays(7))->delete();

        return self::SUCCESS;
    }
}
