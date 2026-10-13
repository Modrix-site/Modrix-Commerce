<?php

namespace App\Http\Responses;

use Illuminate\Http\Response;

/**
 * A generated PDF sent as a download. Documents carry personal data, so
 * they are never cached by shared caches or the browser.
 */
final class PdfDownload
{
    public static function make(string $bytes, string $filename): Response
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?? 'document.pdf';

        return new Response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename='.$safe,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
