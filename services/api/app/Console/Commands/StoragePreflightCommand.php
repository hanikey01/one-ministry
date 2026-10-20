<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StoragePreflightCommand extends Command
{
    protected $signature = 'ministry:storage-preflight {--json : Output JSON}';

    protected $description = 'Verify configured private object storage by writing, reading, and deleting a random probe object';

    public function handle(): int
    {
        $disk = config('filesystems.default');
        $bucket = config('filesystems.disks.'.$disk.'.bucket');
        if ($disk !== 's3' || ! is_string($bucket) || $bucket === '') {
            return $this->result(false, 'Configure FILESYSTEM_DISK=s3 and the S3 bucket before preflight.');
        }
        $path = 'preflight/'.Str::uuid().'.txt';
        $contents = 'ministry-storage-preflight-'.Str::random(32);
        try {
            $storage = Storage::disk('s3');
            if (! $storage->put($path, $contents, ['visibility' => 'private'])) {
                throw new \RuntimeException('The probe object could not be written.');
            }
            if ($storage->get($path) !== $contents) {
                throw new \RuntimeException('The probe object could not be read back unchanged.');
            }
            if (! $storage->delete($path)) {
                throw new \RuntimeException('The probe object could not be deleted.');
            }
        } catch (\Throwable) {
            try {
                Storage::disk('s3')->delete($path);
            } catch (\Throwable) {
            }

            return $this->result(false, 'Object-storage preflight failed. Check the endpoint, credentials, bucket policy, and private read/write/delete permissions.');
        }

        return $this->result(true, 'Private object storage write/read/delete preflight passed.');
    }

    private function result(bool $passed, string $message): int
    {
        if ($this->option('json')) {
            $this->line(json_encode(['ready' => $passed, 'message' => $message], JSON_THROW_ON_ERROR));
        } else {
            $this->line($message);
        }

        return $passed ? self::SUCCESS : self::FAILURE;
    }
}
