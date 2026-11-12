# Getting Started with Modrix Commerce Extensions

This guide takes you from an empty directory to a package the official marketplace accepts. For the full rules, see the [module developer guide](module-development.md) and the [theme developer guide](theme-development.md).

## Modules or themes?

| You want to…                                               | Build a |
| ---------------------------------------------------------- | ------- |
| Change colors, fonts, layout variants or home sections     | Theme   |
| Add admin pages, data, settings, checkout rules or widgets | Module  |

Themes use validated design data and can optionally include compiled React pages and scoped CSS. Modules can also run PHP server code. Review executable code before deployment.

## 1. Scaffold

```bash
php artisan ecomodular:make:module acme/gift-notes --author="Acme Ltd"
php artisan ecomodular:make:theme acme/harbor --from=modrixcommerce/noir
```

The generated module contains:

- a manifest;
- a service provider;
- an admin page with a permission and a navigation entry;
- a migration and a model;
- a typed setting;
- a README and an MIT license.

It validates, installs and enables without changes. The theme starts as a copy of the base theme under the new name, version `0.1.0`.

## 2. Run it locally

```bash
php artisan ecomodular:modules install acme/gift-notes
php artisan ecomodular:modules enable acme/gift-notes --trust
npm run build          # module pages are compiled with the application
```

Open Admin → Gift Notes. For a theme, open Admin → Appearance, preview it and activate it.

Rules worth knowing early:

- **Admin routes:** every one needs a `can:` permission. The platform adds the `/admin/m/<vendor>/<package>` prefix and the authentication middleware.
- **Page URLs:** pages receive URLs as props. Do not import the application's generated route helpers.
- **Settings:** read them with `ModuleSettingsSchema::of('<vendor>/<package>', 'key', default)`, not with `env()`.
- **Migrations:** they need `down()` methods. A failed install rolls back, and uninstall drops your tables.

## 3. Package and scan

```bash
php artisan ecomodular:package acme/gift-notes
php artisan ecomodular:package acme/harbor --type=theme
php artisan ecomodular:package acme/gift-notes --strict   # fail on critical or high findings
```

The command builds the zip in `storage/app/private/packages` and runs the same checks as a marketplace upload: archive safety, manifest validation and a matching package name. It also runs the code scan, which reports with file and line:

- code execution (`eval`, backticks, `exec`/`system`/`proc_open`);
- obfuscation (`gzinflate`, `str_rot13`, long encoded literals);
- dynamic `include`, variable functions and `unserialize`;
- raw SQL (`DB::unprepared`) and outbound network calls;
- environment access and files that never belong in a package (`.phar`, binaries, `.htaccess`, `.env`).

Reviewers see the same report. Remove what you do not need, and explain the rest in your submission notes. A clean scan does not prove a package is safe: reviewers still read the code.

## 4. Upload directly to a store

Any developer can build a theme or extension using these documented manifests and scaffold commands. Marketplace registration is not required for a direct ZIP upload.

- **Appearance → Upload theme:** upload the generated theme ZIP, then preview, customize or activate it.
- **Extensions → Upload extension:** upload the generated module ZIP, review its details, then install and enable it through the existing lifecycle controls. This requires installation permission and a recent password confirmation.
- Put `theme.json` or `module.json` at the ZIP root, alongside its assets and source directories. Do not wrap the package in an additional vendor or project folder.
- Maximum ZIP size is 50 MB; extracted size is capped at 200 MB. Unsafe paths, symlinks and invalid manifests are rejected. Themes may declare browser code under src/; PHP server code remains prohibited.
- Uploads add new packages and never overwrite an existing package. Use the normal update workflow for existing packages.
- Include a real PNG, JPG or WebP demo screenshot and set `"screenshot": "assets/screenshot.png"` in a theme manifest for its thumbnail card.
- Modules with React pages must be included in an application `npm run build` deployment after upload. Uploading does not run Composer, npm, migrations, providers or activation automatically.

## 5. Publish

On the official marketplace, the platform owner uploads releases. Approved sellers upload from the vendor portal (see [licensing](../marketplace/licensing.md)). Every release is reviewed, signed with the marketplace key, and offered to license holders as an update.

Versioning:

- Use Semantic Versioning: each upload must be higher than the last release.
- Declare `"platform_version": "^1.0"`. Within 1.x, extension points are only added, never removed.
- Keep a changelog for each release, because merchants see it before they update.

## Checklist before submitting

- [ ] `php artisan ecomodular:package <name> --strict` passes, or every finding is explained.
- [ ] Install → enable → configure → disable → update → uninstall works on a clean store.
- [ ] Every admin route has a permission. Every form validates on the server.
- [ ] No secrets, credentials or tracking scripts in the package.
- [ ] The README explains what the extension does, its settings and its data.
