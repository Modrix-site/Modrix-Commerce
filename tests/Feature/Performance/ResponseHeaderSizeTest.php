<?php

namespace Tests\Feature\Performance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nginx reads a PHP-FPM response's headers into one fastcgi_buffer_size buffer
 * (4 KB or 8 KB by default) and answers 502 Bad Gateway when they do not fit
 * ("upstream sent too big header"). An uncapped Vite Link preload header listed
 * every chunk (about 7 KB), so /install and every rendered page failed behind a
 * stock Nginx (Uplary, cPanel with Nginx, Plesk) while redirects still worked.
 */
class ResponseHeaderSizeTest extends TestCase
{
    use RefreshDatabase;

    /** Stock Nginx: fastcgi_buffer_size is one memory page, 4 KB on x86-64. */
    private const NGINX_DEFAULT_BUFFER = 4096;

    /** Headroom for a long production domain in every Link URL and larger cookies. */
    private const LINK_HEADER_BUDGET = 2048;

    public function test_a_rendered_page_fits_nginx_default_fastcgi_buffer(): void
    {
        $response = $this->withServerVariables(['SCRIPT_FILENAME' => public_path('index.php'), 'DOCUMENT_ROOT' => public_path()])
            ->get('/install');

        $response->assertOk();

        $headers = (string) $response->baseResponse->headers;
        $link = (string) $response->headers->get('Link', '');

        $this->assertLessThan(self::LINK_HEADER_BUDGET, strlen($link), 'The Link preload header must be capped.');
        $this->assertLessThan(self::NGINX_DEFAULT_BUFFER, strlen($headers), 'All response headers must fit one stock Nginx FastCGI buffer.');
    }
}
