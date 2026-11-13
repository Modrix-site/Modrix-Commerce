#!/usr/bin/env node
/**
 * Prebuilds a module's or theme's screens so a store can load them at runtime.
 *
 *   node scripts/build-module.mjs <package directory> [<package directory> ...]
 *   (or: php artisan ecomodular:module:build <package directory>)
 *
 * Modules: reads resources/js/pages/**\/*.tsx and resources/js/slots/*.tsx,
 *          writes dist/module-ui.json  { sdk, pages, slots, css }.
 * Themes:  reads every src/**\/*.tsx (header, footer, layout, pages, sections…)
 *          and the declared stylesheet, writes dist/theme-ui.json
 *          { sdk, entries: { "src/header.tsx": "<file>" }, css }.
 *
 * Each source file becomes an ES module in dist/, with shared chunks and one
 * stylesheet holding the Tailwind classes the package uses (against the
 * store's theme, resources/css/app.css).
 *
 * React, react-dom, @inertiajs/react and the store's own files (@/…) are not
 * bundled: those imports become `await __modrix.load('<specifier>')`, answered
 * by the store (resources/js/lib/module-sdk.ts), so the package shares React,
 * contexts, components and styles with the page it renders in. Other packages
 * it imports (lucide-react icons, for example) are bundled.
 *
 * Run it from the store's repository: it uses the store's Vite and Tailwind.
 */
import {
    existsSync,
    mkdirSync,
    readFileSync,
    readdirSync,
    rmSync,
    statSync,
    writeFileSync,
} from 'node:fs';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { build } from 'vite';

const SDK_VERSION = 1;
const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const hostCss = join(root, 'resources', 'css', 'app.css');
const utilitiesCss = join(root, 'node_modules', 'tailwindcss', 'utilities.css');

const SHARED =
    /^(react|react-dom|react\/jsx-runtime|react\/jsx-dev-runtime|@inertiajs\/react)$|^@\//;

/** Relative, absolute and drive-letter ids are not packages (nor virtual ids, see below). */
const NOT_A_PACKAGE = /^(\.|\/|[A-Za-z]:)/;
const VIRTUAL_PREFIX = String.fromCharCode(0);

const posix = (path) => path.split(sep).join('/');

function files(dir, pattern, recursive) {
    if (!existsSync(dir)) {
        return [];
    }

    return readdirSync(dir).flatMap((entry) => {
        const path = join(dir, entry);

        if (statSync(path).isDirectory()) {
            return recursive ? files(path, pattern, recursive) : [];
        }

        return pattern.test(entry) ? [path] : [];
    });
}

/**
 * Rewrites the static imports of shared specifiers that the bundler left in place:
 *   import X from "s"              → const X = __modrix.def(await __modrix.load("s"));
 *   import { a as b, c } from "s"  → const { a: b, c } = await __modrix.load("s");
 *   import * as N from "s"         → const N = await __modrix.load("s");
 *   import X, { a } from "s"       → both
 *   import "s"                     → await __modrix.load("s");
 */
function sharedImports() {
    const statement =
        /import\s*(?:([\w$]+)\s*,?\s*)?(?:\{([^}]*)\}|\*\s*as\s*([\w$]+))?\s*(?:from\s*)?(["'`])([^"'`]+)\4\s*;?/g;
    let counter = 0;

    return {
        name: 'modrix-shared-imports',
        renderChunk(code) {
            let changed = false;
            const out = code.replace(
                statement,
                (whole, def, named, ns, _quote, spec) => {
                    if (!SHARED.test(spec)) {
                        return whole;
                    }

                    changed = true;
                    const load = `await __modrix.load(${JSON.stringify(spec)})`;

                    if (!def && !named && !ns) {
                        return `${load};`;
                    }

                    const temp = `__modrix_${++counter}`;
                    const parts = [`const ${temp} = ${load};`];

                    if (def) {
                        parts.push(`const ${def} = __modrix.def(${temp});`);
                    }

                    if (ns) {
                        parts.push(`const ${ns} = ${temp};`);
                    }

                    if (named && named.trim() !== '') {
                        const bindings = named
                            .split(',')
                            .map((item) => item.trim())
                            .filter(Boolean)
                            .map((item) => item.replace(/\s+as\s+/, ': '));
                        parts.push(
                            `const { ${bindings.join(', ')} } = ${temp};`,
                        );
                    }

                    return parts.join('\n');
                },
            );

            return changed ? { code: out, map: null } : null;
        },
    };
}

/** Packages the code imports (lucide-react, …) come from the store's node_modules. */
function hostPackages() {
    const anchor = join(root, 'package.json');

    return {
        name: 'modrix-host-packages',
        enforce: 'pre',
        async resolveId(id, importer, options) {
            if (
                !importer ||
                importer === anchor ||
                SHARED.test(id) ||
                NOT_A_PACKAGE.test(id) ||
                id.startsWith(VIRTUAL_PREFIX)
            ) {
                return null;
            }

            return this.resolve(id, anchor, { ...options, skipSelf: true });
        },
    };
}

/** What to build for one package directory. */
function describe(dir) {
    if (existsSync(join(dir, 'theme.json'))) {
        const manifest = JSON.parse(
            readFileSync(join(dir, 'theme.json'), 'utf8'),
        );
        const src = join(dir, 'src');
        const stylesheet = manifest.frontend?.stylesheet
            ? join(dir, manifest.frontend.stylesheet)
            : null;

        return {
            kind: 'theme',
            indexFile: 'theme-ui.json',
            sources: src,
            stylesheet:
                stylesheet && existsSync(stylesheet) ? stylesheet : null,
            entries: files(src, /\.tsx$/, true).map((file) => ({
                key: posix(relative(dir, file)),
                file,
            })),
        };
    }

    if (existsSync(join(dir, 'module.json'))) {
        const js = join(dir, 'resources', 'js');

        return {
            kind: 'module',
            indexFile: 'module-ui.json',
            sources: js,
            stylesheet: null,
            entries: [
                ...files(join(js, 'pages'), /\.tsx$/, true).map((file) => ({
                    group: 'pages',
                    key: posix(relative(join(js, 'pages'), file)).replace(
                        /\.tsx$/,
                        '',
                    ),
                    file,
                })),
                ...files(join(js, 'slots'), /\.tsx$/, false).map((file) => ({
                    group: 'slots',
                    key: posix(relative(join(js, 'slots'), file)).replace(
                        /\.tsx$/,
                        '',
                    ),
                    file,
                })),
            ],
        };
    }

    return null;
}

async function buildPackage(packageDir) {
    const dir = resolve(packageDir);
    const label = posix(relative(process.cwd(), dir));
    const plan = describe(dir);

    if (!plan) {
        throw new Error('no module.json or theme.json here');
    }

    const dist = join(dir, 'dist');
    rmSync(dist, { recursive: true, force: true });

    if (plan.entries.length === 0) {
        console.log(`${label}: no screens, nothing to build.`);

        return;
    }

    // Tailwind: only the utilities this package's files use, against the store's theme.
    const work = join(dir, '.package-build');
    mkdirSync(work, { recursive: true });
    const css = join(work, 'package.css');
    writeFileSync(
        css,
        [
            `@reference ${JSON.stringify(posix(hostCss))};`,
            `@import ${JSON.stringify(posix(utilitiesCss))} layer(utilities) source(none);`,
            `@source ${JSON.stringify(posix(plan.sources))};`,
            ...(plan.stylesheet
                ? [`@import ${JSON.stringify(posix(plan.stylesheet))};`]
                : []),
            '',
        ].join('\n'),
    );

    const input = { 'package-style': css };
    const byChunk = {};

    plan.entries.forEach((entry, i) => {
        const chunk = `${plan.kind === 'theme' ? 'src' : entry.group}/${entry.key.replace(/^src\//, '').replace(/\.tsx$/, '')}`;
        input[chunk] = entry.file;
        byChunk[chunk] = entry;
        void i;
    });

    const index =
        plan.kind === 'theme'
            ? { sdk: SDK_VERSION, entries: {}, css: [] }
            : { sdk: SDK_VERSION, pages: {}, slots: {}, css: [] };

    try {
        await build({
            configFile: false,
            root: dir,
            logLevel: 'warn',
            plugins: [hostPackages(), react(), tailwindcss(), sharedImports()],
            css: { devSourcemap: false },
            build: {
                outDir: dist,
                emptyOutDir: true,
                target: 'es2022',
                minify: true,
                sourcemap: false,
                cssCodeSplit: true,
                modulePreload: false,
                rollupOptions: {
                    input,
                    external: (id) => SHARED.test(id),
                    preserveEntrySignatures: 'exports-only',
                    output: {
                        format: 'es',
                        entryFileNames: '[name]-[hash].js',
                        chunkFileNames: 'chunks/[name]-[hash].js',
                        assetFileNames: '[name]-[hash][extname]',
                    },
                    plugins: [
                        {
                            name: 'modrix-package-index',
                            generateBundle(_options, bundle) {
                                for (const item of Object.values(bundle)) {
                                    if (item.type === 'asset') {
                                        if (item.fileName.endsWith('.css')) {
                                            index.css.push(item.fileName);
                                        }
                                        continue;
                                    }

                                    const entry = item.isEntry
                                        ? byChunk[item.name]
                                        : undefined;

                                    if (!entry) {
                                        continue;
                                    }

                                    if (plan.kind === 'theme') {
                                        index.entries[entry.key] =
                                            item.fileName;
                                    } else {
                                        index[entry.group][entry.key] =
                                            item.fileName;
                                    }
                                }
                            },
                        },
                    ],
                },
            },
        });
    } finally {
        rmSync(work, { recursive: true, force: true });
    }

    // The stylesheet entry leaves an empty script behind; nothing loads it.
    for (const file of readdirSync(dist)) {
        if (/^package-style-.*\.js$/.test(file)) {
            rmSync(join(dist, file));
        }
    }

    writeFileSync(
        join(dist, plan.indexFile),
        `${JSON.stringify(index, null, 4)}\n`,
    );

    const count =
        plan.kind === 'theme'
            ? `${Object.keys(index.entries).length} files`
            : `${Object.keys(index.pages).length} pages, ${Object.keys(index.slots).length} slots`;
    console.log(
        `${label}: ${plan.kind}, ${count}, ${index.css.length} stylesheet(s) → dist/`,
    );
}

const targets = process.argv.slice(2);

if (targets.length === 0) {
    console.error(
        'Usage: node scripts/build-module.mjs <package directory> [...]',
    );
    process.exit(1);
}

for (const target of targets) {
    try {
        await buildPackage(target);
    } catch (error) {
        console.error(`${target}: build failed`);
        console.error(error);
        process.exitCode = 1;
    }
}
