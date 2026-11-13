<?php

namespace App\Console\Commands;

use App\Services\EmailCampaignService;
use Illuminate\Console\Command;

final class SendEmailCampaignsCommand extends Command
{
    protected $signature = 'ministry:send-email-campaigns {--limit=50 : Maximum recipients to process}';
    protected $description = 'Send a bounded batch of due ministry email campaigns';

    public function handle(EmailCampaignService $campaigns): int
    {
        $count = $campaigns->processDue((int) $this->option('limit'));
        $this->line("Processed {$count} campaign email deliveries.");
        return self::SUCCESS;
    }
}
