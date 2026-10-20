<?php

namespace App\Console\Commands;

use App\Models\HomepageSection;
use App\Models\HomepageSlide;
use App\Models\User;
use App\Services\HomepageService;
use Illuminate\Console\Command;

class PopulateSampleHomepage extends Command
{
    protected $signature = 'ministry:sample-homepage';

    protected $description = 'Add a safe, editable starter homepage to an otherwise empty installation';

    public function handle(HomepageService $homepage): int
    {
        if (HomepageSlide::query()->exists() || HomepageSection::query()->exists()) {
            $this->error('Homepage content already exists; no sample content was added.');

            return self::FAILURE;
        }

        $user = User::query()->first();
        if (! $user) {
            $this->error('Create an administrator before adding sample homepage content.');

            return self::FAILURE;
        }

        $homepage->applyStarterTheme('sanctuary', $user, '127.0.0.1');

        $this->info('Sanctuary sample homepage created with editable slides, media, and sections.');

        return self::SUCCESS;
    }
}
