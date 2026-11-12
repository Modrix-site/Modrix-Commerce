<?php

namespace App\Domains\Orders\Documents;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * Renders a Blade view to PDF bytes. Documents never load anything from
 * the network or from arbitrary local paths: remote loading is off, local
 * reads are confined to an empty directory, and images arrive as data URIs.
 */
final class PdfRenderer
{
    /**
     * @param  view-string  $view
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data): string
    {
        $work = storage_path('framework/dompdf');
        File::ensureDirectoryExists($work);

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setIsJavascriptEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setDefaultPaperSize('a4');
        $options->setChroot([$work]);
        $options->setTempDir($work);
        $options->setFontCache($work);
        $options->setAllowedProtocols(['data://' => ['rules' => []]]);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view($view, $data)->render(), 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
