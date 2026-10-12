# Phase 4 Plan: Module Engine

Branch: `feature/phase-4-modules` (based on `feature/phase-3-payments`, which is waiting on its Stripe test purchase). Each slice ships with tests and docs and is committed once the quality gates pass.

## Design summary

- **Packages on disk, state in the database.** A module is a directory `modules/<vendor>/<name>/` with a data-only `module.json`. It is never executed or `include`d to read it. The `extensions` table records what the merchant did with it: installed version, state and timestamps. `extension_events` is the append-only lifecycle log (ADR-028).
- **States:** `discovered` → `installed` → `enabled` ⇄ `disabled`. `update_pending` is set when the files on disk carry a newer version than the installed one. `failed` covers a failed install, update or boot, and `invalid` covers a manifest that does not validate. Only `enabled` modules are loaded. An `update_pending` module is not loaded until its migrations have run, so new code never runs against an old schema.
- **Validation** is done by a PHP validator, with a published JSON Schema for authors. It checks:
  - name, semver version, `platform_version` constraint, provider class inside the module's own PSR-4 namespace;
  - reserved namespaces, `requires` constraints, permissions (format, and no collisions with core or other modules);
  - capabilities, from a known list, and they must cover what the manifest declares;
  - every path stays inside the package (no `..`, no absolute paths, no symlink escape);
  - settings fields, from a fixed set of types with no code;
  - admin navigation, which must stay inside the module's own route names.
- **Dependencies:** `composer/semver` constraints. Cycles, missing or incompatible dependencies and incompatible platform versions are rejected. A module cannot be disabled or uninstalled while an enabled or installed module depends on it.
- **Loading:** the core `ModuleServiceProvider` registers PSR-4 autoloading and the provider of each enabled module through a guard. If a module throws during register or boot, it is quarantined (marked `failed`, logged, audited) and the rest of the application keeps working (ADR-029). Declared routes are loaded by the core under fixed prefixes and middleware (`/admin/m/<name>` behind `staff`; storefront routes behind `installed`). Declared permissions, admin navigation and settings are registered from the manifest.
- **Safe mode:** `ECOMODULAR_SAFE_MODE=true` boots without any module, for recovery when a module breaks every request. The `ecomodular:modules:*` commands work in safe mode.
- **Lifecycle operations** (`ModuleManager`) run under a global lock and are written to `extension_events` and the audit log:
  - **Install:** runs the module's migrations.
  - **Enable:** requires explicit confirmation, satisfied dependencies and a successful trial boot.
  - **Configure:** applies the declared settings schema.
  - **Update:** runs new migrations, then restores the previous enabled state.
  - **Disable:** keeps all data.
  - **Uninstall:** requires typing the module name; rolls back its migrations and removes its permissions and settings.
  - Route and event caches are cleared after each change.
- **Nothing is enabled automatically.** There is no upload in this phase: packages arrive through deployment or Composer. Uploads and signed downloads come with the marketplace in Phase 6.
- **Module UI:** bundled modules ship React pages under `resources/js/pages`. They are resolved as `module/<vendor>/<name>/<page>` and compiled with the application, so a module with UI needs an asset rebuild. This is documented.

## Slices

| #   | Slice                         | Key areas                                                                                                                                                                                                           |
| --- | ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 4.1 | Manifest, registry, lifecycle | `extensions` and `extension_events` tables, `Manifest` and `ManifestValidator`, discovery, `ModuleManager` state machine, migrations lifecycle, dependency resolution, guarded loader, safe mode, CLI commands |
| 4.2 | Extension contracts           | Module routes, permissions, navigation and settings from the manifest; `CheckoutValidator`, `StorefrontSlots`, `DashboardWidgets` and `SearchProvider` contracts; `CustomerRegistered` event; module page resolution in Inertia |
| 4.3 | Extensions admin              | List with states, dependencies and capabilities; install, enable (with code-trust confirmation), configure, disable, update and uninstall (typed confirmation); lifecycle log; all behind password confirmation |
| 4.4 | Sample modules                | `ecomodular/product-reviews` (migrations, settings, permissions, admin moderation, storefront slot, checkout-safe; full lifecycle including an update), `ecomodular/sales-widget` (dashboard widget), `ecomodular/order-alerts` (queued idempotent notification listener) |
| 4.5 | Docs                          | Module developer guide, manifest JSON Schema, extension point catalogue with compatibility policy, recovery runbook, ADRs, roadmap                                                                                  |

## Acceptance criteria

- Invalid manifests, unknown capabilities, name or permission conflicts, incompatible platform versions, unsatisfied dependencies, dependency cycles and path escapes are rejected (tested).
- Install → enable → configure → disable → update → uninstall works for the reviews module (tested). Disable preserves data (tested).
- A module that throws during boot is quarantined and the store keeps working (tested). Failed install and update leave a recoverable state; the recovery process is documented.
- Disabling a dependency of an enabled module is refused (tested).
- Every lifecycle action is audited and requires the matching `extensions.*` permission (tested).
