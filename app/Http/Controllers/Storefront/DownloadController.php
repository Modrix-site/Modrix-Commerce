<?php

namespace App\Http\Controllers\Storefront;

use App\Domains\Downloads\DownloadGrants;
use App\Domains\Downloads\Models\DownloadGrant;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Streams a purchased file. The token is the secret (works for guests);
 * each download is counted and logged.
 */
class DownloadController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $grant = DownloadGrant::query()->with(['file', 'order'])->where('token', $token)->first();
        abort_if($grant === null, 404);

        $file = $grant->file;
        $usable = $grant->isUsable(now())
            && in_array($grant->order->payment_status, DownloadGrants::PAID, true)
            && $file !== null && $file->isStored();

        if (! $usable) {
            return response()->view('downloads.unavailable', [], 410);
        }

        DB::transaction(function () use ($grant, $request) {
            DownloadGrant::query()->whereKey($grant->id)->increment('downloads');
            $grant->logs()->create([
                'ip' => (string) $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
            ]);
        });

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }
}
