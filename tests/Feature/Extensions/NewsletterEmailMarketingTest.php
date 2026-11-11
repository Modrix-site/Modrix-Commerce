<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Settings\GeneralSettings;
use App\Models\User;
use EcoModular\Newsletter\Mail\CampaignMail;
use EcoModular\Newsletter\Models\NewsletterCampaign;
use EcoModular\Newsletter\Models\NewsletterGroup;
use EcoModular\Newsletter\Models\Subscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class NewsletterEmailMarketingTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(base_path('modules/modrixcommerce/newsletter'), $this->modulesPath.'/modrixcommerce/newsletter');
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('modrixcommerce/newsletter');
        app(ModuleManager::class)->enable('modrixcommerce/newsletter', true);

        $this->owner = $this->owner();
    }

    public function test_admin_index_renders_marketing_dashboard_with_all_tabs_data(): void
    {
        Subscriber::create([
            'email' => 'buyer@example.test',
            'name' => 'Alice Buyer',
            'groups' => ['vip'],
            'source' => 'order',
            'consent_text' => 'Consented at order.',
            'consented_at' => now(),
            'unsubscribe_token' => 'token-buyer-123',
        ]);

        NewsletterGroup::create([
            'name' => 'VIP Customers',
            'slug' => 'vip',
            'description' => 'Top buyers',
            'color' => '#8b5cf6',
        ]);

        NewsletterCampaign::create([
            'title' => 'Welcome Promo',
            'subject' => 'Welcome to the Store!',
            'target_type' => 'all',
            'content_html' => '<p>Hello {{name}}</p>',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->owner)->get(route('admin.modrixcommerce.newsletter.index'));
        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('module/modrixcommerce/newsletter/admin/index', false)
            ->has('subscribers', 1)
            ->has('groups', 1)
            ->has('campaigns', 1)
            ->where('counts.total', 1)
            ->where('counts.customers', 1)
            ->has('smtp')
        );
    }

    public function test_can_manually_add_subscriber_with_groups(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.modrixcommerce.newsletter.subscribers.store'), [
            'email' => 'new.member@example.test',
            'name' => 'New Member',
            'groups' => ['vip', 'early-birds'],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'new.member@example.test',
            'name' => 'New Member',
            'source' => 'manual',
        ]);

        $sub = Subscriber::where('email', 'new.member@example.test')->firstOrFail();
        $this->assertContains('vip', $sub->groups);
        $this->assertContains('early-birds', $sub->groups);
    }

    public function test_can_update_subscriber_groups(): void
    {
        $sub = Subscriber::create([
            'email' => 'client@example.test',
            'source' => 'home',
            'groups' => ['general'],
            'consent_text' => 'Consent.',
            'consented_at' => now(),
            'unsubscribe_token' => 'token-client-abc',
        ]);

        $response = $this->actingAs($this->owner)->post(route('admin.modrixcommerce.newsletter.subscribers.groups.update', $sub->id), [
            'groups' => ['vip', 'promotions'],
        ]);

        $response->assertRedirect();
        $sub->refresh();
        $this->assertSame(['vip', 'promotions'], $sub->groups);
    }

    public function test_bulk_group_assignment(): void
    {
        $s1 = Subscriber::create([
            'email' => 'bulk1@example.test',
            'source' => 'footer',
            'groups' => [],
            'consent_text' => 'Consent.',
            'consented_at' => now(),
            'unsubscribe_token' => 'bulk1-token',
        ]);
        $s2 = Subscriber::create([
            'email' => 'bulk2@example.test',
            'source' => 'footer',
            'groups' => ['newsletter'],
            'consent_text' => 'Consent.',
            'consented_at' => now(),
            'unsubscribe_token' => 'bulk2-token',
        ]);

        // Bulk add 'vip'
        $response = $this->actingAs($this->owner)->post(route('admin.modrixcommerce.newsletter.subscribers.bulk_group'), [
            'subscriber_ids' => [$s1->id, $s2->id],
            'group_slug' => 'vip',
            'action' => 'add',
        ]);
        $response->assertRedirect();

        $this->assertContains('vip', $s1->fresh()->groups);
        $this->assertContains('vip', $s2->fresh()->groups);
        $this->assertContains('newsletter', $s2->fresh()->groups);

        // Bulk remove 'vip'
        $this->actingAs($this->owner)->post(route('admin.modrixcommerce.newsletter.subscribers.bulk_group'), [
            'subscriber_ids' => [$s1->id, $s2->id],
            'group_slug' => 'vip',
            'action' => 'remove',
        ]);
        $this->assertNotContains('vip', $s1->fresh()->groups);
        $this->assertNotContains('vip', $s2->fresh()->groups);
    }

    public function test_group_crud_lifecycle(): void
    {
        $response = $this->actingAs($this->owner)->post(route('admin.modrixcommerce.newsletter.groups.store'), [
            'name' => 'Black Friday VIPs',
            'description' => 'Shoppers interested in Black Friday deals',
            'color' => '#ef4444',
        ]);
        $response->assertRedirect();

        $this->assertDatabaseHas('newsletter_groups', [
            'name' => 'Black Friday VIPs',
            'slug' => 'black-friday-vips',
            'color' => '#ef4444',
        ]);

        $group = NewsletterGroup::where('slug', 'black-friday-vips')->firstOrFail();

        // Delete group
        $del = $this->actingAs($this->owner)->delete(route('admin.modrixcommerce.newsletter.groups.destroy', $group->id));
        $del->assertRedirect();
        $this->assertDatabaseMissing('newsletter_groups', ['id' => $group->id]);
    }

    public function test_campaign_crud_and_preview_test(): void
    {
        Mail::fake();

        // 1. Create campaign
        $response = $this->actingAs($this->owner)->post(route('admin.modrixcommerce.newsletter.campaigns.store'), [
            'title' => 'Weekend Flash Sale',
            'subject' => '⚡ 50% Off Everything!',
            'preview_text' => 'Don\'t miss out on our biggest weekend drop.',
            'target_type' => 'all',
            'content_html' => '<h2>Special Discount for {{name}}</h2><p>Visit {{store_name}} today.</p>',
        ]);
        $response->assertRedirect();

        $campaign = NewsletterCampaign::where('title', 'Weekend Flash Sale')->firstOrFail();
        $this->assertSame('draft', $campaign->status);

        // 2. Update campaign
        $this->actingAs($this->owner)->put(route('admin.modrixcommerce.newsletter.campaigns.update', $campaign->id), [
            'title' => 'Weekend Flash Sale v2',
            'subject' => '⚡ 50% Off Everything (Updated)',
            'target_type' => 'customers',
            'content_html' => '<h2>Updated for {{name}}</h2>',
        ]);
        $this->assertSame('Weekend Flash Sale v2', $campaign->fresh()->title);

        // 3. Send test preview email
        $targetUrl = route('admin.modrixcommerce.newsletter.campaigns.send_test', $campaign->id);
        $res = $this->actingAs($this->owner)->post($targetUrl, [
            'test_email' => 'admin@example.test',
        ]);
        $res->assertSessionHasNoErrors();
        $res->assertRedirect();

        Mail::assertSent(CampaignMail::class, function (CampaignMail $mail) {
            return $mail->hasTo('admin@example.test') &&
                   str_contains($mail->campaignSubject, '[PREVIEW]');
        });
    }

    public function test_campaign_broadcast_delivers_to_targeted_subscribers(): void
    {
        Mail::fake();

        $s1 = Subscriber::create([
            'email' => 'target1@example.test',
            'name' => 'Target One',
            'source' => 'order',
            'groups' => ['vip'],
            'consent_text' => 'Consented.',
            'consented_at' => now(),
            'unsubscribe_token' => 't1-token',
        ]);

        $s2 = Subscriber::create([
            'email' => 'target2@example.test',
            'name' => 'Target Two',
            'source' => 'home',
            'groups' => ['newsletter'],
            'consent_text' => 'Consented.',
            'consented_at' => now(),
            'unsubscribe_token' => 't2-token',
        ]);

        $campaign = NewsletterCampaign::create([
            'title' => 'VIP Exclusive Event',
            'subject' => 'Private VIP Sale',
            'target_type' => 'group',
            'target_group' => 'vip',
            'content_html' => '<p>VIP access for {{name}}.</p>',
            'status' => 'draft',
        ]);

        // Send broadcast
        $this->actingAs($this->owner)->post(route('admin.modrixcommerce.newsletter.campaigns.send', $campaign->id))
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame('sent', $campaign->status);
        $this->assertSame(1, $campaign->sent_count);

        Mail::assertSent(CampaignMail::class, function (CampaignMail $mail) {
            return $mail->hasTo('target1@example.test') &&
                   ! $mail->hasTo('target2@example.test');
        });
    }

    public function test_save_app_core_smtp_settings_and_test_connection(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->owner)->post(route('admin.modrixcommerce.newsletter.smtp.save'), [
            'driver' => 'smtp',
            'host' => 'smtp.testmail.local',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'api-user',
            'password' => 'secret-smtp-key',
            'from_address' => 'store@example.test',
            'from_name' => 'Store Team',
            'reply_to' => 'support@example.test',
        ]);

        $response->assertRedirect();

        $general = app(GeneralSettings::class);
        $mail = $general->mail();
        $this->assertSame('smtp', $mail['driver']);
        $this->assertSame('smtp.testmail.local', $mail['host']);
        $this->assertSame(587, $mail['port']);
        $this->assertSame('tls', $mail['encryption']);
        $this->assertSame('api-user', $mail['username']);
        $this->assertSame('store@example.test', $mail['from_address']);
        $this->assertSame('secret-smtp-key', $general->mailPassword());

        // Test sending an SMTP check email
        $this->actingAs($this->owner)->post(route('admin.modrixcommerce.newsletter.smtp.test'), [
            'to' => 'verify@example.test',
        ])->assertRedirect();
    }
}
