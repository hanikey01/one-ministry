<?php

namespace App\Console\Commands;

use App\Services\Ai\AiModelSettingsService;
use App\Services\Ai\AiProviderRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RefreshAiModelCatalogsCommand extends Command
{
    protected $signature = 'ministry:refresh-ai-model-catalogs';

    protected $description = 'Refresh enabled AI provider catalogs and flag removed models';

    public function handle(AiModelSettingsService $settings): int
    {
        $failed = false;
        foreach (AiProviderRegistry::labels() as $provider => $label) {
            $connection = DB::table('ai_provider_connections')->where('provider_key', $provider)->where('enabled', true)->whereNotNull('api_key_encrypted')->first();
            if (! $connection) continue;
            try {
                $result = $settings->refreshCatalog($provider, null, null);
                $this->info("{$label}: {$result['seen']} catalog models checked; {$result['unavailable']} unavailable.");
            } catch (Throwable) {
                $failed = true;
                $this->error("{$label}: catalog refresh failed; the previous catalog remains in place.");
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
