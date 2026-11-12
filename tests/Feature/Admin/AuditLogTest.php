<?php

namespace Tests\Feature\Admin;

use App\Core\Audit\AuditLog;
use App\Core\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    public function test_successful_and_failed_logins_are_audited_without_passwords()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $failed = AuditLog::query()->where('action', 'auth.failed')->firstOrFail();
        $this->assertSame($user->email, $failed->metadata['email']);
        $this->assertStringNotContainsString('wrong-password', (string) json_encode($failed->getAttributes()));

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'actor_id' => $user->id]);
    }

    public function test_sensitive_metadata_is_redacted()
    {
        $entry = app(AuditLogger::class)->record('test.event', null, [
            'password' => 'hunter2',
            'nested' => ['api_key' => 'sk_live_123', 'safe' => 'value'],
            'card_number' => '4242424242424242',
        ]);

        $this->assertSame('[REDACTED]', $entry->metadata['password']);
        $this->assertSame('[REDACTED]', $entry->metadata['nested']['api_key']);
        $this->assertSame('value', $entry->metadata['nested']['safe']);
        $this->assertSame('[REDACTED]', $entry->metadata['card_number']);
    }

    public function test_audit_entries_are_immutable()
    {
        $entry = app(AuditLogger::class)->record('test.event');

        $this->expectException(LogicException::class);
        $entry->update(['action' => 'tampered']);
    }

    public function test_audit_entries_cannot_be_deleted()
    {
        $entry = app(AuditLogger::class)->record('test.event');

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_audit_page_filters_by_action()
    {
        $owner = $this->owner();
        app(AuditLogger::class)->record('role.created');
        app(AuditLogger::class)->record('user.created');

        $this->actingAs($owner)
            ->get(route('admin.audit-logs.index', ['action' => 'role.']))
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/audit-logs/index')
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'role.created'));
    }

    public function test_audit_page_rejects_wildcard_injection_in_action_filter()
    {
        $this->actingAs($this->owner())
            ->get(route('admin.audit-logs.index', ['action' => "%' OR 1=1"]))
            ->assertSessionHasErrors('action');
    }
}
