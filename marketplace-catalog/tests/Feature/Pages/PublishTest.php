<?php

namespace Tests\Feature\Pages;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Marketplace\Models\Package;
use Marketplace\Models\Submission;
use Tests\Support\Fixtures;
use Tests\TestCase;

/** Publishers submit packages and updates; admins approve or reject them. */
class PublishTest extends TestCase
{
    use Fixtures, RefreshDatabase;

    private function submit(array $overrides = [], ?UploadedFile $zip = null)
    {
        return $this->post('/submit', array_merge([
            'name' => 'Acme Reviews', 'identifier' => 'acme/reviews', 'type' => 'module', 'version' => '1.0.0',
            'platform_version' => '^1.0', 'author' => 'Acme', 'license_name' => 'MIT', 'summary' => 'Star ratings',
            'tier' => 'free', 'price' => '0', 'demo_url' => 'https://demo.example.com',
            'package' => $zip ?? $this->packageZip('acme/reviews', '1.0.0'),
        ], $overrides));
    }

    public function test_submitting_needs_sign_in()
    {
        $this->get('/submit')->assertRedirect('/account/login?next=%2Fsubmit');
    }

    public function test_a_manifest_that_does_not_match_is_refused()
    {
        $this->actingAs($this->customer());

        $this->submit([], $this->packageZip('acme/other', '1.0.0'))->assertSessionHasErrors('package');
        $this->submit(['version' => '1.1.0'])->assertSessionHasErrors('package');
        $this->submit([], $this->packageZip('acme/reviews', '1.0.0', 'module', ['../evil.php' => 'x']))->assertSessionHasErrors('package');
        $this->submit(['demo_url' => 'http://insecure.example.com'])->assertSessionHasErrors('demo_url');
        $this->assertSame(0, Submission::query()->count());
    }

    public function test_approval_publishes_the_package_with_a_release()
    {
        $publisher = $this->customer();
        $this->actingAs($publisher)->submit()->assertRedirect('/submissions');
        $submission = Submission::query()->firstOrFail();
        $this->assertSame('pending', $submission->status);
        $this->get('/')->assertDontSee('Acme Reviews');

        $this->actingAs($this->admin())->post('/admin/submissions', ['id' => $submission->id, 'decision' => 'approved'])->assertRedirect('/admin/submissions');

        $package = Package::query()->where('identifier', 'acme/reviews')->firstOrFail();
        $this->assertSame('published', $package->status);
        $this->assertSame($publisher->id, $package->publisher_id);
        $this->assertSame('1.0.0', $package->latestRelease()?->version);
        $this->assertSame('approved', $submission->fresh()->status);
        $this->get('/')->assertSee('Acme Reviews');
    }

    public function test_rejection_needs_a_reason_the_publisher_sees()
    {
        $publisher = $this->customer();
        $this->actingAs($publisher)->submit();
        $submission = Submission::query()->firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/submissions', ['id' => $submission->id, 'decision' => 'rejected'])->assertSessionHasErrors('review_note');
        $this->actingAs($admin)->post('/admin/submissions', ['id' => $submission->id, 'decision' => 'rejected', 'review_note' => 'Please add screenshots.']);

        $this->assertSame('rejected', $submission->fresh()->status);
        $this->actingAs($publisher)->get('/submissions')->assertSee('Please add screenshots.');
    }

    public function test_an_update_must_be_newer_and_publishes_on_approval()
    {
        $package = $this->package(['identifier' => 'acme/reviews', 'slug' => 'acme-reviews', 'version' => '1.0.0']);
        $this->actingAs($this->customer());

        $this->post('/submit/update', ['identifier' => 'acme/reviews', 'version' => '1.0.0', 'changelog' => 'Same', 'package' => $this->packageZip('acme/reviews', '1.0.0')])
            ->assertSessionHasErrors('version');
        $this->post('/submit/update', ['identifier' => 'acme/reviews', 'version' => '1.1.0', 'changelog' => 'Faster', 'package' => $this->packageZip('acme/reviews', '1.1.0')])
            ->assertRedirect('/submissions');

        $this->actingAs($this->admin())->post('/admin/submissions', ['id' => Submission::query()->value('id'), 'decision' => 'approved']);

        $this->assertSame('1.1.0', $package->fresh()->latestRelease()?->version);
        $this->assertSame('Faster', $package->fresh()->changelog);
    }
}
