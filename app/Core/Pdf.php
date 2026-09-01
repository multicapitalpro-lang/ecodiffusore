<?php

namespace App\Core;

use Dompdf\Dompdf;
use Dompdf\Options;

class Pdf
{
    public static function download(string $html, string $filename, string $orientation = 'landscape'): void
    {
        require_once BASE_PATH . '/vendor/autoload.php';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', $orientation);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }
}
