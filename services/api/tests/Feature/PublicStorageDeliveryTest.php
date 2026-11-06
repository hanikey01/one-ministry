<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicStorageDeliveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'session.driver' => 'array',
            'cache.default' => 'array',
        ]);
    }

    public function test_public_upload_is_delivered_without_a_symbolic_link(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('profiles/member.png', 'profile image');

        $this->get('/storage/profiles/member.png')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=86400, public')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_missing_public_upload_returns_not_found(): void
    {
        Storage::fake('public');

        $this->get('/storage/profiles/missing.png')->assertNotFound();
    }
}
