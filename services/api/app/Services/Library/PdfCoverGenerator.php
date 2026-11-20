<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/** Creates a lightweight archive cover from the first page when Imagick PDF support is available. */
class PdfCoverGenerator
{
    public function generate(string $disk, string $sourcePath): ?string
    {
        if (! class_exists(\Imagick::class)) {
            return null;
        }

        try {
            $image = new \Imagick();
            $image->setResolution(144, 144);
            $image->readImage(Storage::disk($disk)->path($sourcePath).'[0]');
            $image->setIteratorIndex(0);
            $image->setImageBackgroundColor('white');
            $image->setImageAlphaChannel(\Imagick::ALPHACHANNEL_REMOVE);
            $cover = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
            $cover->setImageFormat('webp');
            $cover->setImageCompressionQuality(82);
            $cover->thumbnailImage(720, 960, true, true);
            $coverPath = 'library/covers/'.Str::uuid().'.webp';
            Storage::disk($disk)->put($coverPath, $cover->getImageBlob());
            $cover->clear();
            $cover->destroy();
            $image->clear();
            $image->destroy();

            return $coverPath;
        } catch (Throwable) {
            return null;
        }
    }
}
