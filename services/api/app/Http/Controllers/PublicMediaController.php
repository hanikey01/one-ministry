<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Delivers published ministry-library assets that a public page references. */
class PublicMediaController extends Controller
{
    public function show(string $asset): BinaryFileResponse
    {
        $record = DB::table('media_assets')->where([
            'id' => $asset,
            'library' => 'media_library',
            'status' => 'published',
        ])->first();

        abort_unless($record && $record->provider === 'local' && $record->file_path, 404);

        $path = Storage::disk($record->file_disk ?: 'local')->path($record->file_path);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Disposition' => 'inline; filename="'.addslashes($record->file_name ?: $record->title).'"',
        ]);
    }
}
