# Third-Party Notices

EcoModular's own code is MIT-licensed (see `LICENSE`). It depends on third-party packages that remain under their own licenses. Licenses were last reviewed on 2026-10-13 (release candidate 1.0.0-rc.1) from `composer.lock` and `package-lock.json` with `composer licenses --no-dev` and a scan of every installed `package.json`. Regenerate this list before each release.

## Runtime PHP dependencies (`composer licenses --no-dev`)

| License                                      | Count | Packages (non-MIT listed)                                                                                                                                     |
| -------------------------------------------- | ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| MIT                                          | 91    | Laravel framework, Fortify, Inertia Laravel, Wayfinder, Symfony components, Guzzle, Monolog, Carbon, Flysystem, brick/math, web-auth/webauthn-lib, and others |
| BSD-3-Clause                                 | 5     | league/commonmark, league/config, nikic/php-parser, tijsverkoyen/css-to-inline-styles, vlucas/phpdotenv                                                       |
| BSD-2-Clause                                 | 2     | bacon/bacon-qr-code, dasprid/enum                                                                                                                             |
| BSD-3-Clause OR GPL-2.0-only OR GPL-3.0-only | 2     | nette/schema, nette/utils (used under BSD-3-Clause)                                                                                                           |
| ISC                                          | 1     | paragonie/sodium_compat (Ed25519 signatures for marketplace releases)                                                                                         |
| Apache-2.0                                   | 1     | phpoption/phpoption                                                                                                                                           |

## Frontend dependencies (`package-lock.json`)

The compiled JavaScript and CSS shipped to browsers contain code from the MIT, ISC, Apache-2.0 and 0BSD packages below. Everything else is build tooling that is not shipped.

| License        | Packages (non-MIT listed)                                                                                                                                              |
| -------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| MIT            | React, Inertia React, Radix UI, Tailwind CSS, Vite, sonner, input-otp, and most others                                                                                 |
| ISC            | lucide-react, picocolors, graceful-fs, and others                                                                                                                      |
| Apache-2.0     | class-variance-authority, typescript, rxjs, detect-libc, baseline-browser-mapping                                                                                      |
| 0BSD           | tslib                                                                                                                                                                  |
| MPL-2.0        | lightningcss and its platform binaries (build-time CSS tooling, used unmodified, not shipped)                                                                          |
| CC-BY-4.0      | caniuse-lite (browser support data, build-time)                                                                                                                        |
| MIT OR CC0-1.0 | type-fest                                                                                                                                                              |
| (none stated)  | `@yuku-codegen/binding-*`, `@yuku-parser/binding-*`: platform binaries of the MIT-licensed `yuku-codegen` and `yuku-parser` (dev tooling via `vite-plus`, not shipped) |

## Fonts and assets

- **Instrument Sans** (SIL Open Font License 1.1) is downloaded from Bunny Fonts at build time by the `laravel-vite-plugin` font integration and served from the application's own build directory.
- Bundled themes (Essence, Noir, Bloom, Harvest) use system font stacks and ship only their own images, created for this project (MIT).

## Notes

- No dependency is licensed under a copyleft license that conflicts with distributing EcoModular under MIT.
- The application was scaffolded from `laravel/react-starter-kit` (MIT, Copyright (c) Taylor Otwell).
- Commercial extensions sold on the official marketplace are separate works under their own terms; see `docs/marketplace/licensing.md`. They are not part of this repository's MIT distribution.
