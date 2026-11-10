<?php

namespace EcoModular\BotProtection\Http\Controllers;

use App\Http\Controllers\Controller;
use EcoModular\BotProtection\Models\BotCaptchaChallenge;
use EcoModular\BotProtection\Services\CaptchaManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BotChallengeController extends Controller
{
    public function __construct(
        private readonly CaptchaManagerService $captcha,
    ) {}

    public function show(string $token): View|RedirectResponse
    {
        $challenge = BotCaptchaChallenge::query()
            ->where('challenge_token', $token)
            ->where('status', 'pending')
            ->first();

        if (!$challenge || $challenge->isExpired()) {
            return redirect()->to('/')->with('error', 'Challenge expired. Please try again.');
        }

        $clientToken = $this->captcha->generateClientChallengeToken($challenge);

        return view('bot-protection::challenge', [
            'challenge' => $challenge,
            'clientToken' => $clientToken,
        ]);
    }

    public function verify(Request $request): RedirectResponse
    {
        $token = $request->input('token');
        $returnTo = $request->input('return_to', '/');

        $verified = $this->captcha->verifyChallenge($request, (string) $token, $request->all());

        if ($verified) {
            return redirect()->to($returnTo)->with('success', 'Security verification completed successfully.');
        }

        return redirect()->back()->with('error', 'Verification failed. Please complete the check again.');
    }

    public function honeypotTrap(Request $request): RedirectResponse
    {
        // When a bot crawls this invisible link, it gets recorded immediately
        return redirect()->to('/');
    }
}
