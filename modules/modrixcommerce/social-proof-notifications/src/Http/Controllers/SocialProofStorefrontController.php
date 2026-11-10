<?php

namespace EcoModular\SocialProofNotifications\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\SocialProofNotifications\Models\LiveProductViewer;
use EcoModular\SocialProofNotifications\Models\SocialProofEvent;
use EcoModular\SocialProofNotifications\Services\SocialProofService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SocialProofStorefrontController extends Controller
{
    public function __construct(
        protected SocialProofService $socialProofService
    ) {}

    /**
     * Return active notification feed for storefront toast sequence.
     */
    public function feed(Request $request): JsonResponse
    {
        $productId = $request->integer('product_id') ?: null;

        $feed = $this->socialProofService->getStorefrontFeed($productId);

        return response()->json([
            'enabled' => $this->socialProofService->isEnabled(),
            'config' => $this->socialProofService->config(),
            'feed' => $feed,
        ]);
    }

    /**
     * Record visitor product heartbeat for live viewer counts.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
        ]);

        $productId = (int) $validated['product_id'];
        $sessionId = (string) $request->session()->getId();

        LiveProductViewer::recordHeartbeat($productId, $sessionId);

        $count = LiveProductViewer::countForProduct($productId, 5);

        return response()->json([
            'ok' => true,
            'viewers' => $count,
        ]);
    }

    /**
     * Track notification impression.
     */
    public function trackImpression(SocialProofEvent $event): JsonResponse
    {
        $this->socialProofService->recordImpression($event->id);

        return response()->json(['ok' => true]);
    }

    /**
     * Track notification click.
     */
    public function trackClick(SocialProofEvent $event): JsonResponse
    {
        $this->socialProofService->recordClick($event->id);

        return response()->json(['ok' => true]);
    }
}
