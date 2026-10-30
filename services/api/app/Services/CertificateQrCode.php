<?php

namespace App\Services;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

final class CertificateQrCode
{
    public function svg(string $url): string
    {
        if (! class_exists(QRCode::class)) {
            return '';
        }

        return (new QRCode(new QROptions([
            'outputBase64' => false,
            'svgAddXmlHeader' => false,
            'drawLightModules' => false,
        ])))->render($url);
    }
}
