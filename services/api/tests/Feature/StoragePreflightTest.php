<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoragePreflightTest extends TestCase
{
    public function test_preflight_requires_s3_configuration(): void
    {
        config(['filesystems.default' => 'local']);
        $this->artisan('ministry:storage-preflight --json')->assertExitCode(1);
    }

    public function test_preflight_checks_a_private_s3_probe_lifecycle(): void
    {
        config(['filesystems.default' => 's3', 'filesystems.disks.s3.bucket' => 'ministry-test', 'filesystems.disks.s3.key' => 'key', 'filesystems.disks.s3.secret' => 'secret', 'filesystems.disks.s3.region' => 'us-east-1']);
        Storage::fake('s3');
        $this->artisan('ministry:storage-preflight --json')->assertExitCode(0);
        Storage::disk('s3')->assertDirectoryEmpty('preflight');
    }
}
