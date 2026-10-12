# Third-Party Notices

EcoModular's own code is MIT-licensed (see `LICENSE`). It depends on third-party packages that remain under their own licenses. Licenses were reviewed on 2026-10-12 from `composer.lock` and `package-lock.json`. Run `composer licenses` and `npx license-checker` (or equivalent) to regenerate this list before each release.

## Runtime PHP dependencies (`composer licenses --no-dev`)

| License                                      | Count | Packages (non-MIT listed)                                                                                                                                     |
| -------------------------------------------- | ----- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| MIT                                          | 90    | Laravel framework, Fortify, Inertia Laravel, Wayfinder, Symfony components, Guzzle, Monolog, Carbon, Flysystem, brick/math, web-auth/webauthn-lib, and others |
| BSD-3-Clause                                 | 5     | league/commonmark, league/config, nikic/php-parser, tijsverkoyen/css-to-inline-styles, vlucas/phpdotenv                                                       |
| BSD-2-Clause                                 | 2     | bacon/bacon-qr-code, dasprid/enum                                                                                                                             |
| BSD-3-Clause OR GPL-2.0-only OR GPL-3.0-only | 2     | nette/schema, nette/utils (used under BSD-3-Clause)                                                                                                           |
| Apache-2.0                                   | 1     | phpoption/phpoption                                                                                                                                           |

## Frontend production dependencies (`package-lock.json`, non-dev)

| License        | Count | Packages (non-MIT listed)                                                                     |
| -------------- | ----- | --------------------------------------------------------------------------------------------- |
| MIT            | 215   | React, Inertia React, Radix UI, Tailwind CSS, Vite, class-variance-authority deps, and others |
| Apache-2.0     | 5     | class-variance-authority, typescript, rxjs, detect-libc, baseline-browser-mapping             |
| ISC            | 9     | lucide-react, picocolors, graceful-fs, and others                                             |
| MPL-2.0        | 24    | lightningcss and its platform binaries (build-time CSS tooling, unmodified)                   |
| CC-BY-4.0      | 1     | caniuse-lite (browser support data, build-time)                                               |
| 0BSD           | 1     | tslib                                                                                         |
| MIT OR CC0-1.0 | 1     | type-fest                                                                                     |

## Notes

- No dependency is licensed under a copyleft license that conflicts with distributing EcoModular under MIT. The MPL-2.0 packages (`lightningcss`) are build tools, used unmodified, and are not shipped in the compiled application.
- The application was scaffolded from `laravel/react-starter-kit` (MIT, Copyright (c) Taylor Otwell).
- The Instrument Sans typeface (SIL Open Font License 1.1) is obtained via Bunny Fonts through the `laravel-vite-plugin` font integration.
