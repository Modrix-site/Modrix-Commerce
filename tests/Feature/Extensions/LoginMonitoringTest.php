<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Models\User;
use EcoModular\LoginMonitoring\Models\LoginEvent;
use EcoModular\LoginMonitoring\Models\LoginSecurityIncident;
use EcoModular\LoginMonitoring\Models\LoginUserSession;
use EcoModular\LoginMonitoring\Services\LoginTrackerService;
use EcoModular\LoginMonitoring\Services\MfaManagerService;
use EcoModular\LoginMonitoring\Services\SessionManagerService;
use EcoModular\LoginMonitoring\Services\SuspiciousLoginService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class LoginMonitoringTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/login-monitoring';

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(base_path('modules/modrixcommerce/login-monitoring'), $this->modulesPath.'/modrixcommerce/login-monitoring');
        $modules = app(ModuleManager::class);
        $modules->install(self::MODULE);
        $modules->enable(self::MODULE, true);
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_module_installs_and_enables_successfully(): void
    {
        $extension = Extension::query()->where('name', self::MODULE)->first();
        $this->assertNotNull($extension);
        $this->assertEquals('enabled', $extension->state->value);
    }

    public function test_successful_login_event_recorded_with_session(): void
    {
        $user = User::factory()->create(['email' => 'legit.shopper@example.com']);
        $tracker = app(LoginTrackerService::class);

        $request = Request::create('https://store.example.com/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.50',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0 Safari/537.36',
        ]);

        $event = $tracker->recordSuccess($user, $request);

        $this->assertInstanceOf(LoginEvent::class, $event);
        $this->assertEquals('success', $event->status);
        $this->assertEquals('legit.shopper@example.com', $event->email);
        $this->assertEquals('203.0.113.50', $event->ip_address);
        $this->assertEquals('Chrome', $event->browser);
        $this->assertLessThan(40, $event->risk_score);

        // Active session created
        $session = LoginUserSession::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($session);
        $this->assertFalse($session->is_revoked);
    }

    public function test_failed_login_increments_attempts_and_triggers_lockout(): void
    {
        $email = 'victim.account@example.com';
        $ip = '198.51.100.22';
        $tracker = app(LoginTrackerService::class);

        $request = Request::create('https://store.example.com/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
        ]);

        // Record 5 failed attempts
        for ($i = 1; $i <= 5; $i++) {
            $event = $tracker->recordFailed($email, $request, 'Bad password');
            $this->assertEquals('failed', $event->status);
        }

        // Must be locked out now
        $lockout = $tracker->isLockedOut($email, $ip);
        $this->assertNotNull($lockout);
        $this->assertTrue($lockout->isLockedOutActive());
        $this->assertGreaterThanOrEqual(5, $lockout->attempt_count);

        // Security Incident created
        $incident = LoginSecurityIncident::query()
            ->where('email', $email)
            ->where('incident_type', 'brute_force')
            ->first();
        $this->assertNotNull($incident);
        $this->assertEquals('high', $incident->severity);
    }

    public function test_impossible_travel_detection(): void
    {
        $user = User::factory()->create(['email' => 'traveler@example.com']);
        $suspiciousService = app(SuspiciousLoginService::class);

        // First login in New York (lat: 40.71, lon: -74.00) 10 minutes ago
        LoginEvent::create([
            'event_uuid' => (string) Str::uuid(),
            'user_id' => $user->id,
            'email' => $user->email,
            'ip_address' => '198.51.100.10',
            'status' => 'success',
            'risk_score' => 10,
            'threat_level' => 'low',
            'device_fingerprint' => 'test-fp-1',
            'device_type' => 'desktop',
            'browser' => 'Chrome',
            'operating_system' => 'Windows',
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'city' => 'New York',
            'country_code' => 'US',
            'created_at' => now()->subMinutes(10),
        ]);

        // Second login from London (lat: 51.50, lon: -0.12) right now
        $request = Request::create('https://store.example.com/login', 'POST', [], [], [], [
            'REMOTE_ADDR' => '203.0.113.80',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
        ]);

        $eval = $suspiciousService->evaluateLogin(
            $request,
            $user->email,
            $user,
            ['fingerprint' => 'test-fp-2', 'browser' => 'Firefox', 'operating_system' => 'Windows', 'device_type' => 'desktop'],
            ['latitude' => 51.5074, 'longitude' => -0.1278, 'city' => 'London', 'country_code' => 'GB']
        );

        // Over 5,500 km in 10 minutes is > 30,000 km/h, far exceeding threshold
        $this->assertTrue($eval['is_suspicious']);
        $this->assertGreaterThanOrEqual(45, $eval['risk_score']);

        $incident = LoginSecurityIncident::query()
            ->where('email', $user->email)
            ->where('incident_type', 'impossible_travel')
            ->first();
        $this->assertNotNull($incident);
    }

    public function test_remote_session_revocation(): void
    {
        $user = User::factory()->create(['email' => 'user.with.session@example.com']);
        $sessionManager = app(SessionManagerService::class);

        $sessionId = 'mock-session-'.uniqid();
        LoginUserSession::create([
            'session_id' => $sessionId,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'browser' => 'Chrome',
            'operating_system' => 'Windows',
            'is_current' => true,
            'is_revoked' => false,
            'last_activity' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        $revoked = $sessionManager->revokeSession($sessionId);
        $this->assertTrue($revoked);

        $session = LoginUserSession::query()->where('session_id', $sessionId)->first();
        $this->assertTrue($session->is_revoked);
    }

    public function test_mfa_email_otp_generation_and_verification(): void
    {
        $user = User::factory()->create(['email' => 'mfa.user@example.com']);
        $mfa = app(MfaManagerService::class);

        $otp = $mfa->sendEmailOtp($user);
        $this->assertNotEmpty($otp);
        $this->assertEquals(6, strlen($otp));

        $valid = $mfa->verifyEmailOtp($user, $otp);
        $this->assertTrue($valid);

        // Verification codes should be single use
        $secondAttempt = $mfa->verifyEmailOtp($user, $otp);
        $this->assertFalse($secondAttempt);
    }

    public function test_all_admin_navigation_pages_render_successfully(): void
    {
        $admin = $this->owner();
        $this->actingAs($admin);

        // 1. Dashboard
        $response = $this->get('/admin/m/modrixcommerce/login-monitoring');
        $response->assertOk();

        // 2. Live Activity
        $response = $this->get('/admin/m/modrixcommerce/login-monitoring/activity');
        $response->assertOk();

        // 3. Suspicious Queue
        $response = $this->get('/admin/m/modrixcommerce/login-monitoring/queue');
        $response->assertOk();

        // 4. Active Sessions
        $response = $this->get('/admin/m/modrixcommerce/login-monitoring/sessions');
        $response->assertOk();

        // 5. Security Incidents
        $response = $this->get('/admin/m/modrixcommerce/login-monitoring/incidents');
        $response->assertOk();

        // 6. Reports & Analytics
        $response = $this->get('/admin/m/modrixcommerce/login-monitoring/reports');
        $response->assertOk();

        // 7. MFA Settings
        $response = $this->get('/admin/m/modrixcommerce/login-monitoring/mfa');
        $response->assertOk();

        // 8. Access Policies
        $response = $this->get('/admin/m/modrixcommerce/login-monitoring/policies');
        $response->assertOk();

        // 9. Audit Logs
        $response = $this->get('/admin/m/modrixcommerce/login-monitoring/audit');
        $response->assertOk();
    }
}
