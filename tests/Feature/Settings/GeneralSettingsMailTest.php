<?php

namespace Tests\Feature\Settings;

use App\Core\Audit\AuditLog;
use App\Core\Settings\GeneralSettings;
use App\Core\Settings\MailSettings;
use App\Core\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\SyncJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Inertia\Testing\AssertableInertia as Assert;
use RuntimeException;
use Tests\TestCase;

class GeneralSettingsMailTest extends TestCase
{
    use RefreshDatabase;

    private const SMTP = [
        'driver' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls',
        'username' => 'mailer@example.com', 'password' => 'S3cret-pass!', 'from_address' => 'shop@example.com',
        'from_name' => 'Acme Shop', 'reply_to' => 'help@example.com',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_the_password_is_encrypted_at_rest_and_never_sent_back()
    {
        $owner = $this->owner();
        $this->actingAs($owner)->put(route('admin.settings.general.mail'), self::SMTP)->assertSessionHasNoErrors();

        $stored = (string) DB::table('settings')->where('key', 'general.mail.password_encrypted')->value('value');
        $this->assertStringNotContainsString('S3cret-pass!', $stored);
        $this->assertSame('S3cret-pass!', app(GeneralSettings::class)->mailPassword());

        $response = $this->actingAs($owner)->get(route('admin.settings.general.edit'));
        $response->assertInertia(fn (Assert $page) => $page->where('mail.has_password', true)->missing('mail.password'));
        $this->assertStringNotContainsString('S3cret-pass!', $response->getContent());

        $audit = AuditLog::query()->where('action', 'settings.general_updated')->latest('id')->firstOrFail();
        $this->assertStringNotContainsString('S3cret-pass!', json_encode($audit->metadata));
        // The audit logger redacts it; the entry still shows the password changed.
        $this->assertArrayHasKey('password', $audit->metadata['changes']);
    }

    public function test_a_blank_password_keeps_the_saved_one_and_it_can_be_removed()
    {
        $owner = $this->owner();
        $this->actingAs($owner)->put(route('admin.settings.general.mail'), self::SMTP);
        $this->actingAs($owner)->put(route('admin.settings.general.mail'), ['password' => ''] + self::SMTP)->assertSessionHasNoErrors();
        $this->assertSame('S3cret-pass!', app(GeneralSettings::class)->mailPassword());

        $this->actingAs($owner)->put(route('admin.settings.general.mail'), ['password' => '', 'remove_password' => true] + self::SMTP);
        $this->assertNull(app(GeneralSettings::class)->mailPassword());
    }

    public function test_smtp_settings_are_applied_to_the_mailer_and_server_default_restores_env()
    {
        $envHost = config('mail.mailers.smtp.host');
        $this->actingAs($this->owner())->put(route('admin.settings.general.mail'), self::SMTP);

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame('S3cret-pass!', config('mail.mailers.smtp.password'));
        $this->assertSame('shop@example.com', config('mail.from.address'));
        $this->assertSame('help@example.com', config('mail.reply_to.address'));

        $this->actingAs($this->owner())->put(route('admin.settings.general.mail'), ['driver' => 'env'] + self::SMTP);
        $this->assertSame($envHost, config('mail.mailers.smtp.host'));
    }

    public function test_queued_jobs_pick_up_changes_made_after_the_worker_started()
    {
        app(MailSettings::class)->apply();
        // Another process saves new settings; this "worker" still holds the old ones in memory.
        DB::table('settings')->updateOrInsert(['key' => 'general.mail'], ['value' => json_encode(['driver' => 'smtp', 'host' => 'smtp.worker.test', 'port' => 25, 'encryption' => 'none']), 'created_at' => now(), 'updated_at' => now()]);
        cache()->forget('settings.all');
        app(SettingsRepository::class)->flush();
        app(SettingsRepository::class)->all();

        Event::dispatch(new JobProcessing('database', new SyncJob($this->app, '{}', 'sync', 'default')));

        $this->assertSame('smtp.worker.test', config('mail.mailers.smtp.host'));
    }

    public function test_a_test_email_is_sent_to_the_given_address()
    {
        // The server default (phpunit: the "array" transport) delivers it.
        $this->actingAs($this->owner())->post(route('admin.settings.general.mail.test'), ['to' => 'me@example.com'])->assertSessionHasNoErrors();

        $messages = app('mailer')->getSymfonyTransport()->messages();
        $this->assertCount(1, $messages);
        $this->assertSame('me@example.com', $messages[0]->getEnvelope()->getRecipients()[0]->getAddress());
    }

    public function test_failure_messages_never_show_the_credentials()
    {
        $this->actingAs($this->owner())->put(route('admin.settings.general.mail'), self::SMTP);

        $message = app(MailSettings::class)->describeFailure(new RuntimeException('Failed to authenticate as "mailer@example.com" with password "S3cret-pass!"'));

        $this->assertStringNotContainsString('S3cret-pass!', $message);
        $this->assertStringNotContainsString('mailer@example.com', $message);
    }

    public function test_smtp_needs_a_host_and_a_sender()
    {
        $this->actingAs($this->owner())->put(route('admin.settings.general.mail'), ['host' => '', 'from_address' => ''] + self::SMTP)
            ->assertSessionHasErrors(['host', 'from_address']);
        $this->actingAs($this->owner())->put(route('admin.settings.general.mail'), ['host' => 'smtp.example.com; rm -rf'] + self::SMTP)
            ->assertSessionHasErrors('host');
    }
}
