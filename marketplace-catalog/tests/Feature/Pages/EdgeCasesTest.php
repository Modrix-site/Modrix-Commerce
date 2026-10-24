<?php

namespace Tests\Feature\Pages;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Marketplace\Models\License;
use Marketplace\Models\Submission;
use Marketplace\Models\User;
use Marketplace\Services\Catalog;
use Marketplace\Services\Licensing;
use Marketplace\Services\Releases;
use Tests\Support\Fixtures;
use Tests\TestCase;

/** Publishing races, hidden-package owners, sign-in emails and revoked keys. */
class EdgeCasesTest extends TestCase
{
    use Fixtures, RefreshDatabase;

    public function test_only_one_update_per_package_waits_for_review()
    {
        $this->package(['identifier' => 'acme/reviews', 'version' => '1.0.0']);
        $this->actingAs($this->customer());
        $update = fn (string $version) => $this->post('/submit/update', ['identifier' => 'acme/reviews', 'version' => $version, 'changelog' => 'x', 'package' => $this->packageZip('acme/reviews', $version)]);

        $update('1.1.0')->assertRedirect('/submissions');
        $update('1.1.0')->assertSessionHasErrors('identifier');

        $this->assertSame(1, Submission::query()->count());
    }

    public function test_approval_refuses_a_version_that_is_no_longer_newer()
    {
        $package = $this->package(['identifier' => 'acme/reviews', 'version' => '1.0.0']);
        $this->actingAs($this->customer())->post('/submit/update', ['identifier' => 'acme/reviews', 'version' => '1.1.0', 'changelog' => 'x', 'package' => $this->packageZip('acme/reviews', '1.1.0')]);
        app(Releases::class)->publish($package, $this->packageZip('acme/reviews', '1.2.0')->getPathname(), '1.2.0', '^1.0');

        $this->actingAs($this->admin())->post('/admin/submissions', ['id' => Submission::query()->value('id'), 'decision' => 'approved'])->assertSessionHasErrors('id');

        $this->assertSame('1.2.0', $package->fresh()->latestRelease()?->version);
        $this->assertSame('pending', Submission::query()->value('status'));
    }

    public function test_approving_a_new_package_twice_is_refused_not_an_error()
    {
        $this->actingAs($this->customer())->post('/submit', [
            'name' => 'Acme Reviews', 'identifier' => 'acme/reviews', 'type' => 'module', 'version' => '1.0.0', 'platform_version' => '^1.0',
            'author' => 'Acme', 'license_name' => 'MIT', 'summary' => 'Stars', 'tier' => 'free', 'price' => '0', 'package' => $this->packageZip('acme/reviews', '1.0.0'),
        ]);
        $this->package(['identifier' => 'acme/reviews', 'slug' => 'acme-reviews-x']); // Listed meanwhile by another route.

        $this->actingAs($this->admin())->post('/admin/submissions', ['id' => Submission::query()->value('id'), 'decision' => 'approved'])->assertSessionHasErrors('id');
    }

    public function test_owners_of_a_hidden_package_can_still_download_it()
    {
        $package = $this->package(['identifier' => 'acme/paid', 'slug' => 'acme-paid', 'is_free' => false, 'price' => '9.00', 'status' => 'hidden']);
        app(Releases::class)->publish($package, $this->packageZip('acme/paid', '1.0.0')->getPathname(), '1.0.0', '^1.0');
        $owner = $this->customer();
        app(Licensing::class)->issue($owner, $package, 'paid');

        $this->get('/download/acme-paid')->assertNotFound();
        $this->actingAs($this->customer())->get('/download/acme-paid')->assertNotFound();
        $this->actingAs($owner)->get('/download/acme-paid')->assertOk();
    }

    public function test_emails_are_not_case_sensitive()
    {
        $this->post('/account/register', ['name' => 'Bob', 'email' => 'Bob@Example.TEST', 'password' => 'Secret-pass-1', 'password_confirmation' => 'Secret-pass-1']);
        $this->post('/account/logout');

        $this->assertSame('bob@example.test', User::query()->value('email'));
        $this->post('/account/register', ['name' => 'Bob', 'email' => 'bob@example.test', 'password' => 'Secret-pass-1', 'password_confirmation' => 'Secret-pass-1'])->assertSessionHasErrors('email');
        $this->post('/account/login', ['email' => 'BOB@example.test', 'password' => 'Secret-pass-1'])->assertRedirect('/account');
    }

    public function test_a_revoked_license_is_not_reported_as_ready()
    {
        $user = $this->customer();
        $package = $this->package();
        app(Licensing::class)->issue($user, $package, 'free')->forceFill(['status' => 'revoked'])->save();

        $this->actingAs($user)->post('/account/licenses', ['product' => $package->slug])->assertSessionHasErrors('product');
        $this->assertSame('revoked', License::query()->value('status'));
    }

    public function test_catalog_query_count_does_not_grow_with_packages()
    {
        $count = function () {
            DB::flushQueryLog();
            DB::enableQueryLog();
            // A fresh catalog each time (the controller keeps its own between test requests).
            $catalog = app(Catalog::class);
            $catalog->browse(['type' => 'all'], 1);
            $catalog->counts();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };
        $add = function (int $n) {
            for ($i = 0; $i < $n; $i++) {
                $p = $this->package();
                app(Releases::class)->publish($p, $this->packageZip($p->identifier, '1.0.0')->getPathname(), '1.0.0', '^1.0');
            }
        };

        $add(2);
        $few = $count();
        $add(8);

        $this->assertSame($few, $count());
    }
}
