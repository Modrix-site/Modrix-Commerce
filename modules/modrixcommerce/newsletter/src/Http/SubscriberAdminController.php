<?php

namespace EcoModular\Newsletter\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\GeneralSettings;
use App\Core\Settings\MailSettings;
use App\Core\Settings\StoreSettings;
use App\Http\Controllers\Controller;
use EcoModular\Newsletter\Mail\CampaignMail;
use EcoModular\Newsletter\Models\NewsletterCampaign;
use EcoModular\Newsletter\Models\NewsletterGroup;
use EcoModular\Newsletter\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin: Email marketing system, subscriber management, groupings/segments,
 * campaign broadcasting, and App Core SMTP delivery configuration.
 */
class SubscriberAdminController extends Controller
{
    private const ROUTE = 'admin.modrixcommerce.newsletter.';

    public function index(Request $request, GeneralSettings $general): Response
    {
        $status = $request->query('status', 'all');
        $segment = $request->query('segment', 'all');
        $search = trim((string) $request->query('search', ''));
        $tab = $request->query('tab', 'subscribers');

        $query = Subscriber::query()->latest('id');

        if ($status === 'active') {
            $query->active();
        } elseif ($status === 'unsubscribed') {
            $query->unsubscribed();
        }

        if ($segment === 'customers') {
            $query->customers();
        } elseif ($segment === 'leads') {
            $query->where('source', '!=', 'order');
        } elseif ($segment !== 'all') {
            $query->inGroup($segment);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $mailConfig = $general->mail();
        $hasPassword = $general->mailPassword() !== false && $mailConfig['has_password'];

        $groups = NewsletterGroup::query()->orderBy('name')->get()->map(fn (NewsletterGroup $g) => [
            'id' => $g->id,
            'name' => $g->name,
            'slug' => $g->slug,
            'description' => $g->description,
            'color' => $g->color ?: '#0ea5e9',
            'subscribers_count' => $g->subscribersCount(),
            'delete_url' => route(self::ROUTE.'groups.destroy', $g),
        ]);

        $campaigns = NewsletterCampaign::query()->latest('id')->get()->map(fn (NewsletterCampaign $c) => [
            'id' => $c->id,
            'title' => $c->title,
            'subject' => $c->subject,
            'preview_text' => $c->preview_text,
            'target_type' => $c->target_type,
            'target_group' => $c->target_group,
            'content_html' => $c->content_html,
            'status' => $c->status,
            'recipients_count' => $c->recipients_count,
            'sent_count' => $c->sent_count,
            'failed_count' => $c->failed_count,
            'sent_at' => $c->sent_at?->toIso8601String(),
            'created_at' => $c->created_at->toIso8601String(),
            'last_error' => $c->last_error,
            'update_url' => route(self::ROUTE.'campaigns.update', $c),
            'delete_url' => route(self::ROUTE.'campaigns.destroy', $c),
            'send_url' => route(self::ROUTE.'campaigns.send', $c),
            'send_test_url' => route(self::ROUTE.'campaigns.send_test', $c),
        ]);

        $subscribers = $query->limit(200)->get()->map(fn (Subscriber $s) => [
            'id' => $s->id,
            'email' => $s->email,
            'name' => $s->name,
            'source' => $s->source,
            'groups' => (array) ($s->groups ?? []),
            'consented_at' => $s->consented_at->toDateString(),
            'unsubscribed_at' => $s->unsubscribed_at?->toDateString(),
            'update_groups_url' => route(self::ROUTE.'subscribers.groups.update', $s),
            'delete_url' => route(self::ROUTE.'destroy', $s),
        ]);

        return Inertia::render('module/modrixcommerce/newsletter/admin/index', [
            'counts' => [
                'total' => Subscriber::query()->count(),
                'active' => Subscriber::query()->whereNull('unsubscribed_at')->count(),
                'unsubscribed' => Subscriber::query()->whereNotNull('unsubscribed_at')->count(),
                'customers' => Subscriber::query()->where('source', 'order')->count(),
                'campaigns' => NewsletterCampaign::query()->count(),
                'groups' => NewsletterGroup::query()->count(),
            ],
            'exportUrl' => route(self::ROUTE.'export'),
            'filters' => [
                'status' => $status,
                'segment' => $segment,
                'search' => $search,
                'tab' => $tab,
            ],
            'subscribers' => $subscribers,
            'groups' => $groups,
            'campaigns' => $campaigns,
            'smtp' => [
                'driver' => $mailConfig['driver'],
                'host' => $mailConfig['host'],
                'port' => $mailConfig['port'],
                'encryption' => $mailConfig['encryption'],
                'username' => $mailConfig['username'],
                'has_password' => $hasPassword,
                'from_address' => $mailConfig['from_address'] ?: (string) config('mail.from.address'),
                'from_name' => $mailConfig['from_name'] ?: (string) config('mail.from.name'),
                'reply_to' => $mailConfig['reply_to'],
                'is_connected' => $mailConfig['driver'] === 'smtp' && $mailConfig['host'] !== '',
            ],
            'urls' => [
                'index' => route(self::ROUTE.'index'),
                'export' => route(self::ROUTE.'export'),
                'store_subscriber' => route(self::ROUTE.'subscribers.store'),
                'bulk_group' => route(self::ROUTE.'subscribers.bulk_group'),
                'store_group' => route(self::ROUTE.'groups.store'),
                'store_campaign' => route(self::ROUTE.'campaigns.store'),
                'save_smtp' => route(self::ROUTE.'smtp.save'),
                'test_smtp' => route(self::ROUTE.'smtp.test'),
            ],
        ]);
    }

    public function storeSubscriber(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191', 'unique:newsletter_subscribers,email'],
            'name' => ['nullable', 'string', 'max:100'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['string', 'max:50'],
        ]);

        $subscriber = Subscriber::create([
            'email' => Str::lower($data['email']),
            'name' => $data['name'] ?? null,
            'groups' => $data['groups'] ?? [],
            'source' => 'manual',
            'consent_text' => 'Manually registered in administration.',
            'consented_at' => now(),
            'unsubscribe_token' => Str::random(32),
        ]);

        $audit->record('newsletter.subscriber_created', null, ['email' => $subscriber->email]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Subscriber {$subscriber->email} added."]);

        return back();
    }

    public function updateSubscriberGroups(Subscriber $subscriber, Request $request): RedirectResponse
    {
        $data = $request->validate([
            'groups' => ['nullable', 'array'],
            'groups.*' => ['string', 'max:50'],
        ]);

        $subscriber->update([
            'groups' => $data['groups'] ?? [],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Updated groups for {$subscriber->email}."]);

        return back();
    }

    public function bulkGroup(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subscriber_ids' => ['required', 'array'],
            'subscriber_ids.*' => ['integer'],
            'group_slug' => ['required', 'string'],
            'action' => ['required', 'in:add,remove'],
        ]);

        $subscribers = Subscriber::query()->whereIn('id', $data['subscriber_ids'])->get();

        foreach ($subscribers as $s) {
            $existing = (array) ($s->groups ?? []);
            if ($data['action'] === 'add' && !in_array($data['group_slug'], $existing, true)) {
                $existing[] = $data['group_slug'];
            } elseif ($data['action'] === 'remove') {
                $existing = array_values(array_filter($existing, fn ($g) => $g !== $data['group_slug']));
            }
            $s->update(['groups' => $existing]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Updated group for {$subscribers->count()} subscriber(s)."]);

        return back();
    }

    public function storeGroup(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', 'unique:newsletter_groups,slug'],
            'description' => ['nullable', 'string', 'max:500'],
            'color' => ['nullable', 'string', 'max:30'],
        ]);

        $slug = Str::slug(!empty($data['slug']) ? $data['slug'] : $data['name']);
        if ($slug === '') {
            $slug = 'group-' . Str::random(6);
        }
        if (NewsletterGroup::query()->where('slug', $slug)->exists()) {
            $slug .= '-' . Str::random(4);
        }

        $group = NewsletterGroup::create([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? '#0ea5e9',
        ]);

        $audit->record('newsletter.group_created', null, ['name' => $group->name, 'slug' => $group->slug]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Audience group \"{$group->name}\" created."]);

        return back();
    }

    public function destroyGroup(NewsletterGroup $group, AuditLogger $audit): RedirectResponse
    {
        $name = $group->name;
        $slug = $group->slug;

        // Remove slug from any subscribers
        Subscriber::query()->inGroup($slug)->chunk(200, function ($chunk) use ($slug) {
            foreach ($chunk as $s) {
                $groups = array_values(array_filter((array) ($s->groups ?? []), fn ($g) => $g !== $slug));
                $s->update(['groups' => $groups]);
            }
        });

        $group->delete();
        $audit->record('newsletter.group_deleted', null, ['slug' => $slug]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Group \"{$name}\" deleted."]);

        return back();
    }

    public function storeCampaign(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'preview_text' => ['nullable', 'string', 'max:255'],
            'target_type' => ['required', 'in:all,active,customers,leads,group'],
            'target_group' => ['nullable', 'string'],
            'content_html' => ['required', 'string'],
        ]);

        $campaign = NewsletterCampaign::create([
            ...$data,
            'status' => 'draft',
        ]);

        $audit->record('newsletter.campaign_created', null, ['title' => $campaign->title]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Campaign \"{$campaign->title}\" saved as draft."]);

        return back();
    }

    public function updateCampaign(NewsletterCampaign $campaign, Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'subject' => ['required', 'string', 'max:255'],
            'preview_text' => ['nullable', 'string', 'max:255'],
            'target_type' => ['required', 'in:all,active,customers,leads,group'],
            'target_group' => ['nullable', 'string'],
            'content_html' => ['required', 'string'],
        ]);

        $campaign->update($data);
        $audit->record('newsletter.campaign_updated', null, ['title' => $campaign->title]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Campaign \"{$campaign->title}\" updated."]);

        return back();
    }

    public function destroyCampaign(NewsletterCampaign $campaign, AuditLogger $audit): RedirectResponse
    {
        $title = $campaign->title;
        $campaign->delete();

        $audit->record('newsletter.campaign_deleted', null, ['title' => $title]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Campaign \"{$title}\" deleted."]);

        return back();
    }

    public function sendTestCampaign(NewsletterCampaign $campaign, Request $request, MailSettings $mailSettings, StoreSettings $storeSettings): RedirectResponse
    {
        $data = $request->validate([
            'test_email' => ['required', 'email'],
        ]);

        $mailSettings->apply();
        $storeName = $storeSettings->name() ?: (string) config('app.name', 'Store');

        try {
            Mail::to($data['test_email'])->send(new CampaignMail(
                campaignSubject: '[PREVIEW] ' . $campaign->subject,
                contentHtml: $campaign->content_html,
                previewText: $campaign->preview_text,
                subscriber: null,
                storeName: $storeName,
                unsubscribeUrl: url('/newsletter/unsubscribe/sample-preview-token'),
            ));

            Inertia::flash('toast', ['type' => 'success', 'message' => "Test preview email delivered to {$data['test_email']}."]);
        } catch (\Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Delivery failed: ' . $mailSettings->describeFailure($e)]);
        }

        return back();
    }

    public function sendCampaign(NewsletterCampaign $campaign, Request $request, MailSettings $mailSettings, StoreSettings $storeSettings, AuditLogger $audit): RedirectResponse
    {
        $mailSettings->apply();

        $query = Subscriber::query()->active();

        if ($campaign->target_type === 'customers') {
            $query->customers();
        } elseif ($campaign->target_type === 'leads') {
            $query->where('source', '!=', 'order');
        } elseif ($campaign->target_type === 'group' && $campaign->target_group) {
            $query->inGroup($campaign->target_group);
        }

        $subscribers = $query->get();
        $total = $subscribers->count();

        if ($total === 0) {
            Inertia::flash('toast', ['type' => 'warning', 'message' => 'No active subscribers found in the selected target segment.']);
            return back();
        }

        $sent = 0;
        $failed = 0;
        $lastError = null;
        $storeName = $storeSettings->name() ?: (string) config('app.name', 'Store');

        foreach ($subscribers as $s) {
            try {
                $unsubUrl = route('modrixcommerce.newsletter.unsubscribe', $s->unsubscribe_token);
                Mail::to($s->email)->send(new CampaignMail(
                    campaignSubject: $campaign->subject,
                    contentHtml: $campaign->content_html,
                    previewText: $campaign->preview_text,
                    subscriber: $s,
                    storeName: $storeName,
                    unsubscribeUrl: $unsubUrl,
                ));
                $sent++;
            } catch (\Throwable $e) {
                $failed++;
                $lastError = $mailSettings->describeFailure($e);
            }
        }

        $campaign->update([
            'status' => $failed > 0 && $sent === 0 ? 'failed' : 'sent',
            'recipients_count' => $total,
            'sent_count' => $sent,
            'failed_count' => $failed,
            'sent_at' => now(),
            'last_error' => $lastError,
        ]);

        $audit->record('newsletter.campaign_sent', null, [
            'campaign_id' => $campaign->id,
            'title' => $campaign->title,
            'sent' => $sent,
            'failed' => $failed,
        ]);

        if ($failed > 0 && $sent === 0) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Campaign broadcasting failed: {$lastError}"]);
        } else {
            Inertia::flash('toast', ['type' => 'success', 'message' => "Campaign \"{$campaign->title}\" sent to {$sent} subscriber(s)." . ($failed > 0 ? " ({$failed} failed)" : '')]);
        }

        return back();
    }

    public function saveSmtp(Request $request, GeneralSettings $general, MailSettings $mailSettings, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validate([
            'driver' => ['required', 'in:smtp,env'],
            'host' => ['nullable', 'string', 'max:255'],
            'port' => ['nullable', 'integer', 'between:1,65535'],
            'encryption' => ['nullable', 'in:tls,ssl,none'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => ['nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
            'reply_to' => ['nullable', 'email', 'max:255'],
        ]);

        $general->saveMail([
            'driver' => $data['driver'],
            'host' => $data['host'] ?? '',
            'port' => (int) ($data['port'] ?? 587),
            'encryption' => $data['encryption'] ?? 'tls',
            'username' => $data['username'] ?? '',
            'from_address' => $data['from_address'] ?? '',
            'from_name' => $data['from_name'] ?? '',
            'reply_to' => $data['reply_to'] ?? '',
        ]);

        if (!empty($data['password'])) {
            $general->setMailPassword($data['password']);
        }

        $mailSettings->apply();
        $audit->record('newsletter.smtp_configured', null, ['driver' => $data['driver'], 'host' => $data['host'] ?? '']);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'App Core SMTP settings saved and connected.']);
        return back();
    }

    public function testSmtp(Request $request, MailSettings $mailSettings): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email'],
        ]);

        $mailSettings->apply();

        try {
            $mailSettings->sendTest($data['to']);
            Inertia::flash('toast', ['type' => 'success', 'message' => "SMTP test email successfully delivered to {$data['to']}."]);
        } catch (\Throwable $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'SMTP test failed: ' . $mailSettings->describeFailure($e)]);
        }

        return back();
    }

    public function export(AuditLogger $audit): StreamedResponse
    {
        $audit->record('newsletter.exported', null, ['count' => Subscriber::query()->whereNull('unsubscribed_at')->count()]);

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, ['email', 'name', 'groups', 'consented_at', 'consent_text', 'source', 'unsubscribe_url'], escape: '');
            Subscriber::query()->whereNull('unsubscribed_at')->orderBy('id')->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $s) {
                    /** @var Subscriber $s */
                    fputcsv($out, array_map(self::safe(...), [
                        $s->email,
                        $s->name ?? '',
                        implode(', ', (array) ($s->groups ?? [])),
                        $s->consented_at->toIso8601String(),
                        $s->consent_text,
                        $s->source,
                        route('modrixcommerce.newsletter.unsubscribe', $s->unsubscribe_token),
                    ]), escape: '');
                }
            });
            fclose($out);
        }, 'newsletter-subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function destroy(Subscriber $subscriber, AuditLogger $audit): RedirectResponse
    {
        $subscriber->delete();
        $audit->record('newsletter.subscriber_erased', null, ['subscriber' => $subscriber->id]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Subscriber erased.']);

        return back();
    }

    private static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}
