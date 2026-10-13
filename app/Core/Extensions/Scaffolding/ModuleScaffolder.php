<?php

namespace App\Core\Extensions\Scaffolding;

use App\Core\Platform;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Generates a working module package: manifest, service provider, an admin
 * page with a permission and navigation entry, a migration and model, and
 * a typed setting. The result validates, installs and enables unchanged, so
 * developers start from code that follows every platform rule.
 */
final class ModuleScaffolder
{
    /**
     * @return list<string> Created files, relative to the package.
     */
    public function create(string $name, string $directory, string $displayName, string $author): array
    {
        if (! preg_match('#^([a-z0-9]+(?:-[a-z0-9]+)*)/([a-z0-9]+(?:-[a-z0-9]+)*)$#', $name, $m)) {
            throw new InvalidArgumentException('The name must look like "vendor/package" (lowercase letters, digits and dashes).');
        }

        if (File::exists($directory)) {
            throw new InvalidArgumentException("{$directory} already exists.");
        }

        [, $vendor, $package] = $m;
        $namespace = Str::studly($vendor).'\\'.Str::studly($package);
        $class = Str::studly($package);
        $table = Str::snake(str_replace('-', '_', $vendor.'_'.$package)).'_items';
        $permission = str_replace('-', '_', $package).'.manage';
        $routePrefix = 'admin.'.$vendor.'.'.$package.'.';
        $page = "module/{$vendor}/{$package}/admin/index";

        $vars = [
            '__NAME__' => $name,
            '__DISPLAY__' => $displayName,
            '__AUTHOR__' => $author,
            '__NAMESPACE__' => $namespace,
            '__NAMESPACE_JSON__' => str_replace('\\', '\\\\', $namespace),
            '__CLASS__' => $class,
            '__TABLE__' => $table,
            '__PERMISSION__' => $permission,
            '__ROUTE_PREFIX__' => $routePrefix,
            '__PAGE__' => $page,
            '__PLATFORM__' => '^'.explode('.', Platform::VERSION)[0].'.0',
            '__GROUP__' => Str::headline($package),
            '__YEAR__' => now()->format('Y'),
        ];

        $migration = 'database/migrations/'.now()->format('Y_m_d_His').'_create_'.$table.'_table.php';

        $files = [
            'module.json' => self::MANIFEST,
            "src/{$class}ServiceProvider.php" => self::PROVIDER,
            "src/Models/{$class}Item.php" => self::MODEL,
            "src/Http/{$class}AdminController.php" => self::CONTROLLER,
            'routes/admin.php' => self::ROUTES,
            $migration => self::MIGRATION,
            'resources/js/pages/admin/index.tsx' => self::PAGE,
            'README.md' => self::README,
            'LICENSE' => self::LICENSE,
        ];

        foreach ($files as $path => $template) {
            File::ensureDirectoryExists(dirname($directory.'/'.$path));
            File::put($directory.'/'.$path, strtr($template, $vars));
        }

        return array_keys($files);
    }

    private const MANIFEST = <<<'JSON'
        {
            "$schema": "../../../docs/extensions/module.schema.json",
            "name": "__NAME__",
            "display_name": "__DISPLAY__",
            "description": "Describe what __DISPLAY__ does for merchants.",
            "version": "0.1.0",
            "type": "module",
            "platform_version": "__PLATFORM__",
            "author": "__AUTHOR__",
            "license": "MIT",
            "autoload": {
                "psr-4": {
                    "__NAMESPACE_JSON__\\": "src/"
                }
            },
            "provider": "__NAMESPACE_JSON__\\__CLASS__ServiceProvider",
            "permissions": [
                {
                    "name": "__PERMISSION__",
                    "label": "Manage __DISPLAY__",
                    "group": "__GROUP__"
                }
            ],
            "capabilities": [
                "admin.routes",
                "admin.navigation",
                "database.migrations",
                "settings",
                "permissions"
            ],
            "migrations": "database/migrations",
            "routes": {
                "admin": "routes/admin.php"
            },
            "assets": {
                "pages": "resources/js/pages"
            },
            "settings": [
                {
                    "key": "max_items",
                    "type": "integer",
                    "label": "Maximum number of items",
                    "default": 50,
                    "min": 1,
                    "max": 1000
                }
            ],
            "admin_navigation": [
                {
                    "key": "items",
                    "title": "__DISPLAY__",
                    "route": "index",
                    "icon": "puzzle",
                    "group": "Extensions",
                    "permission": "__PERMISSION__"
                }
            ]
        }

        JSON;

    private const PROVIDER = <<<'PHP'
        <?php

        namespace __NAMESPACE__;

        use Illuminate\Support\ServiceProvider;

        /**
         * Routes, permissions, navigation, settings and migrations come from
         * module.json. Register services, listeners and extension points here
         * (storefront slots, dashboard widgets, checkout validators…).
         */
        class __CLASS__ServiceProvider extends ServiceProvider
        {
            public const MODULE = '__NAME__';

            public function register(): void
            {
                //
            }

            public function boot(): void
            {
                //
            }
        }

        PHP;

    private const MODEL = <<<'PHP'
        <?php

        namespace __NAMESPACE__\Models;

        use Illuminate\Database\Eloquent\Model;

        /**
         * @property int $id
         * @property string $name
         */
        class __CLASS__Item extends Model
        {
            protected $table = '__TABLE__';

            protected $fillable = ['name'];
        }

        PHP;

    private const CONTROLLER = <<<'PHP'
        <?php

        namespace __NAMESPACE__\Http;

        use App\Core\Extensions\ModuleSettingsSchema;
        use App\Http\Controllers\Controller;
        use __NAMESPACE__\Models\__CLASS__Item;
        use Illuminate\Http\RedirectResponse;
        use Illuminate\Http\Request;
        use Illuminate\Validation\ValidationException;
        use Inertia\Inertia;
        use Inertia\Response;

        /**
         * Every route is protected by `can:__PERMISSION__` (routes/admin.php).
         * URLs go to the page as props: module pages do not import route helpers.
         */
        class __CLASS__AdminController extends Controller
        {
            private const ROUTE = '__ROUTE_PREFIX__';

            public function index(): Response
            {
                return Inertia::render('__PAGE__', [
                    'items' => __CLASS__Item::query()->latest('id')->limit(100)->get()->map(fn (__CLASS__Item $item) => [
                        'id' => $item->id,
                        'name' => $item->name,
                        'delete_url' => route(self::ROUTE.'destroy', $item),
                    ]),
                    'storeUrl' => route(self::ROUTE.'store'),
                ]);
            }

            public function store(Request $request, ModuleSettingsSchema $settings): RedirectResponse
            {
                $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
                $max = (int) $settings->of('__NAME__', 'max_items', 50);

                if (__CLASS__Item::query()->count() >= $max) {
                    throw ValidationException::withMessages(['name' => "The limit of {$max} items is reached (see the module settings)."]);
                }

                __CLASS__Item::query()->create($data);
                Inertia::flash('toast', ['type' => 'success', 'message' => 'Item added.']);

                return back();
            }

            public function destroy(__CLASS__Item $item): RedirectResponse
            {
                $item->delete();

                return back();
            }
        }

        PHP;

    private const ROUTES = <<<'PHP'
        <?php

        /*
        | Loaded by the platform under /admin/m/__NAME__ with the admin middleware
        | and the "__ROUTE_PREFIX__" name prefix. Every route must declare a permission.
        */

        use __NAMESPACE__\Http\__CLASS__AdminController;
        use Illuminate\Support\Facades\Route;

        Route::middleware('can:__PERMISSION__')->group(function () {
            Route::get('/', [__CLASS__AdminController::class, 'index'])->name('index');
            Route::post('items', [__CLASS__AdminController::class, 'store'])->name('store');
            Route::delete('items/{item}', [__CLASS__AdminController::class, 'destroy'])->name('destroy');
        });

        PHP;

    private const MIGRATION = <<<'PHP'
        <?php

        use Illuminate\Database\Migrations\Migration;
        use Illuminate\Database\Schema\Blueprint;
        use Illuminate\Support\Facades\Schema;

        return new class extends Migration
        {
            public function up(): void
            {
                Schema::create('__TABLE__', function (Blueprint $table) {
                    $table->id();
                    $table->string('name', 120);
                    $table->timestamps();
                });
            }

            public function down(): void
            {
                Schema::dropIfExists('__TABLE__');
            }
        };

        PHP;

    private const PAGE = <<<'TSX'
        import { Form, Head } from '@inertiajs/react';
        import { Trash2 } from 'lucide-react';
        import { EmptyState } from '@/components/admin/empty-state';
        import { AdminPage, PageHeader } from '@/components/admin/page-header';
        import InputError from '@/components/input-error';
        import { Button } from '@/components/ui/button';
        import { Input } from '@/components/ui/input';

        type Props = {
            items: { id: number; name: string; delete_url: string }[];
            storeUrl: string;
        };

        export default function Index({ items, storeUrl }: Props) {
            return (
                <>
                    <Head title="__DISPLAY__" />
                    <AdminPage>
                        <PageHeader title="__DISPLAY__" description="Generated by ecomodular:make:module." />

                        <Form action={storeUrl} method="post" resetOnSuccess className="flex flex-wrap items-start gap-2">
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-1">
                                        <Input name="name" aria-label="Item name" placeholder="New item" required />
                                        <InputError message={errors.name} />
                                    </div>
                                    <Button type="submit" disabled={processing}>
                                        Add
                                    </Button>
                                </>
                            )}
                        </Form>

                        {items.length === 0 ? (
                            <EmptyState title="No items yet" />
                        ) : (
                            <ul className="divide-y rounded-lg border">
                                {items.map((item) => (
                                    <li key={item.id} className="flex items-center justify-between gap-2 px-4 py-2">
                                        <span>{item.name}</span>
                                        <Form action={item.delete_url} method="delete" options={{ preserveScroll: true }}>
                                            <Button size="sm" variant="ghost" aria-label={`Delete ${item.name}`}>
                                                <Trash2 />
                                            </Button>
                                        </Form>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </AdminPage>
                </>
            );
        }

        TSX;

    private const README = <<<'MD'
        # __DISPLAY__

        An EcoModular module (`__NAME__`), generated with `php artisan ecomodular:make:module`.

        ## Develop

        1. Edit `module.json`, the provider in `src/`, routes, migrations and the page in `resources/js/pages/`.
        2. `php artisan ecomodular:modules install __NAME__` and `php artisan ecomodular:modules enable __NAME__ --trust`.
        3. `npm run build` (module pages are compiled with the application).
        4. Open Admin → __DISPLAY__.

        ## Package

        `php artisan ecomodular:package __NAME__` validates the manifest, scans the code and writes the zip the marketplace accepts.

        See `docs/extensions/getting-started.md` and `docs/extensions/module-development.md` in the EcoModular repository.

        MD;

    private const LICENSE = <<<'TXT'
        MIT License

        Copyright (c) __YEAR__ __AUTHOR__

        Permission is hereby granted, free of charge, to any person obtaining a copy
        of this software and associated documentation files (the "Software"), to deal
        in the Software without restriction, including without limitation the rights
        to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
        copies of the Software, and to permit persons to whom the Software is
        furnished to do so, subject to the following conditions:

        The above copyright notice and this permission notice shall be included in all
        copies or substantial portions of the Software.

        THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
        IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
        FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
        AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
        LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
        OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
        SOFTWARE.

        TXT;
}
