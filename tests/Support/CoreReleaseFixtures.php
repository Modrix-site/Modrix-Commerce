<?php

namespace Tests\Support;

use App\Core\Marketplace\Signing;
use App\Core\Settings\SecretSettings;
use App\Core\Updates\CorePackage;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Signed core release items in the Modrix Cloud contract shape
 * (`GET core/releases`), signed with a throwaway key the test trusts.
 */
trait CoreReleaseFixtures
{
    /** @var array{secret: string, public: string}|null */
    private ?array $coreSigningKey = null;

    protected string $coreCloudBase = 'https://cloud.test';

    /** Scratch app tree standing in for the updates root. */
    protected string $root;

    /**
     * Creates a scratch app tree at 1.0.0 and points the updater at it. It lives
     * under the system temp dir, not storage/: file watchers on the project tree
     * can hold directory handles there, and Windows then refuses to rename them.
     */
    protected function scratchRoot(): void
    {
        $this->root = str_replace('\\', '/', sys_get_temp_dir()).'/core-root-'.Str::random(8);
        $this->writeTree($this->root, '1.0.0');
        File::put($this->root.'/.env', 'APP_KEY=keep-me');
        File::ensureDirectoryExists($this->root.'/storage/app/public');
        File::put($this->root.'/storage/app/public/marker.txt', 'storage-marker');
        File::ensureDirectoryExists($this->root.'/storage/app/private/backups');

        config([
            'ecomodular.updates.root' => $this->root,
            'ecomodular.backup.files_root' => $this->root,
            'ecomodular.backup.path' => $this->root.'/storage/app/private/backups',
            'ecomodular.backup.password' => '',
            'ecomodular.marketplace.url' => $this->coreCloudBase,
        ]);

        $this->beforeApplicationDestroyed(function () {
            if (app()->isDownForMaintenance()) {
                Artisan::call('up');
            }
            foreach (glob($this->root.'*') ?: [] as $dir) {
                is_dir($dir) ? File::deleteDirectory($dir) : File::delete($dir);
            }
        });
    }

    protected function useTestSigningKey(): void
    {
        $this->coreSigningKey = Signing::generateKeyPair();
        config(['ecomodular.marketplace.trusted_keys' => [$this->coreSigningKey['public']]]);
    }

    /**
     * @param  array<string, mixed>  $override  merged into the metadata BEFORE signing
     * @return array<string, mixed>
     */
    protected function signedItem(string $version, string $sha256, int $size, string $minPhp = '8.3', array $override = [], bool $tamper = false): array
    {
        if ($this->coreSigningKey === null) {
            $this->useTestSigningKey();
        }

        $id = $override['release_id'] ?? random_int(1, 100000);
        $metadata = array_merge([
            'kind' => 'core',
            'release_id' => $id,
            'version' => $version,
            'min_php' => $minPhp,
            'sha256' => $sha256,
            'size' => $size,
            'key_id' => Signing::keyId(base64_decode($this->coreSigningKey['public'])),
        ], $override);

        $signature = Signing::sign($metadata, $this->coreSigningKey['secret']);

        if ($tamper) {
            $metadata['sha256'] = str_repeat('0', 64);
        }

        return [
            'id' => $id,
            'version' => $version,
            'min_php' => $minPhp,
            'notes' => 'Release notes for '.$version,
            'published_at' => '2026-11-08T10:00:00+00:00',
            'verification' => ['metadata' => $metadata, 'signature' => $signature],
        ];
    }

    protected function writeTree(string $dir, string $version, array $extra = []): void
    {
        $files = [
            'app/Core/Platform.php' => "<?php\n\nnamespace App\\Core;\n\nfinal class Platform\n{\n    public const VERSION = '{$version}';\n}\n",
            'app/marker.txt' => "code {$version}",
            'bootstrap/app.php' => "<?php // {$version}",
            'bootstrap/providers.php' => '<?php return [];',
            'config/app.php' => '<?php return [];',
            'database/migrations/.gitkeep' => '',
            'lang/en/x.php' => '<?php return [];',
            'public/build/manifest.json' => '{}',
            'public/index.php' => '<?php',
            'resources/views/x.blade.php' => 'x',
            'routes/web.php' => '<?php',
            'vendor/autoload.php' => '<?php',
            'artisan' => "#!/usr/bin/env php {$version}",
            'composer.json' => '{}',
            'composer.lock' => '{}',
            'package.json' => '{}',
        ];

        foreach (array_merge($files, $extra) as $name => $content) {
            File::ensureDirectoryExists(dirname($dir.'/'.$name));
            File::put($dir.'/'.$name, $content);
        }
    }

    /** Builds a release package with the real packager from a scratch release tree. */
    protected function releaseZip(string $declared, array $extra = []): string
    {
        $tree = $this->root.'-release-'.Str::random(4);
        $this->writeTree($tree, $declared, $extra);

        return (new CorePackage)->build($tree, $this->root.'-out-'.Str::random(4));
    }

    protected function probeMigration(bool $fails = false): array
    {
        $body = $fails
            ? "Schema::create('core_probe', fn (Blueprint \$t) => \$t->id());\n        throw new RuntimeException('Migration exploded');"
            : "Schema::create('core_probe', fn (Blueprint \$t) => \$t->id());";

        return ['database/migrations/2026_12_01_000000_create_core_probe_table.php' => <<<PHP
<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    // Outside a transaction, so only the database restore can undo it.
    public \$withinTransaction = false;

    public function up(): void
    {
        {$body}
    }
};
PHP];
    }

    protected function connect(): void
    {
        app(SecretSettings::class)->put('cloud.connection_token', 'ct_'.str_repeat('a', 61));
    }

    /** @param  list<array<string, mixed>>  $releases served by `core/releases` */
    protected function fakeCoreCloud(string $zip, array $releases = []): void
    {
        Http::fake([
            $this->coreCloudBase.'/api/marketplace/v1/core/releases*' => Http::response(['releases' => $releases]),
            $this->coreCloudBase.'/api/marketplace/v1/core/downloads' => Http::response(['url' => $this->coreCloudBase.'/d/pkg', 'expires_at' => 'soon']),
            $this->coreCloudBase.'/d/pkg' => Http::response((string) file_get_contents($zip)),
        ]);
    }

    protected function platformVersion(): string
    {
        preg_match("/const\s+VERSION\s*=\s*'([^']+)'/", (string) file_get_contents($this->root.'/app/Core/Platform.php'), $m);

        return $m[1] ?? '';
    }
}
