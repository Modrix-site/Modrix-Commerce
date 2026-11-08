<?php

namespace EcoModular\ImageStudio\Removers;

use EcoModular\ImageStudio\Contracts\BackgroundRemover;
use GdImage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Removes the background through the remove.bg API. The API key is only ever
 * sent as a request header; error messages are built here and never include it.
 */
class RemoveBgRemover implements BackgroundRemover
{
    public const KEY = 'removebg';

    public const URL = 'https://api.remove.bg/v1.0/removebg';

    public function __construct(private readonly ?string $apiKey) {}

    public function remove(GdImage $image, string $originalBytes): GdImage
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            throw new RuntimeException('remove.bg API key is not set');
        }

        try {
            $response = Http::timeout(60)
                ->withHeaders(['X-Api-Key' => $this->apiKey])
                ->attach('image_file', $originalBytes, 'image')
                ->post(self::URL, ['size' => 'auto', 'format' => 'png']);
        } catch (ConnectionException) {
            throw new RuntimeException('remove.bg request failed (connection error)');
        }

        if ($response->status() !== 200) {
            throw new RuntimeException("remove.bg request failed (HTTP {$response->status()})");
        }

        $result = @imagecreatefromstring($response->body());
        if (! $result instanceof GdImage) {
            throw new RuntimeException('remove.bg returned an image that could not be decoded');
        }

        // Match the size of the working image so the variant is consistent with other drivers.
        $w = imagesx($image);
        $h = imagesy($image);
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, (int) imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $result, 0, 0, 0, 0, $w, $h, imagesx($result), imagesy($result));

        return $out;
    }
}
