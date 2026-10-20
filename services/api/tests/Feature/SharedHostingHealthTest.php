<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SharedHostingHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_heartbeat_is_recorded_and_shared_profile_accepts_database_fallbacks(): void
    {
        Storage::disk('local')->delete('ministry-health/scheduler.json');
        config(['cache.default' => 'database', 'queue.default' => 'database']);

        $this->artisan('ministry:record-scheduler-heartbeat', ['--json' => true])->assertSuccessful();
        Storage::disk('local')->assertExists('ministry-health/scheduler.json');

        $this->artisan('ministry:doctor', ['--profile' => 'shared', '--json' => true])->assertSuccessful();

        Storage::disk('local')->delete('ministry-health/scheduler.json');
    }

    public function test_doctor_rejects_unknown_profile(): void
    {
        $this->artisan('ministry:doctor', ['--profile' => 'unsupported'])
            ->expectsOutput('Profile must be local, shared, or production.')
            ->assertFailed();
    }
}
