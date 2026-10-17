<?php

namespace EcoModular\Newsletter\Http;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Http\Controllers\Controller;
use EcoModular\Newsletter\Models\Subscriber;
use EcoModular\Newsletter\NewsletterServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * Storefront: subscribe with explicit consent, unsubscribe in one click.
 * The response never reveals whether an address was already subscribed.
 */
class SubscriptionController extends Controller
{
    public function subscribe(Request $request, ModuleSettingsSchema $settings): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'consent' => ['accepted'],
            'source' => ['nullable', 'in:home,order,footer'],
        ], ['consent.accepted' => 'Tick the box to agree to receive emails.']);

        $email = Str::lower(trim($data['email']));
        $subscriber = Subscriber::query()->where('email', $email)->first() ?? new Subscriber;

        if (! $subscriber->exists || $subscriber->unsubscribed_at !== null) {
            $subscriber->forceFill([
                'email' => $email,
                'consent_text' => (string) $settings->of(NewsletterServiceProvider::MODULE, 'consent_text', ''),
                'consented_at' => now(),
                'source' => $data['source'] ?? 'home',
                'unsubscribe_token' => $subscriber->unsubscribe_token ?? Str::random(48),
                'unsubscribed_at' => null,
            ])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Thank you for subscribing.']);

        return back();
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        $subscriber = Subscriber::query()->where('unsubscribe_token', $token)->first();

        if ($subscriber !== null && $subscriber->unsubscribed_at === null) {
            $subscriber->forceFill(['unsubscribed_at' => now()])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'You are unsubscribed and will not receive more emails.']);

        return redirect('/');
    }
}
