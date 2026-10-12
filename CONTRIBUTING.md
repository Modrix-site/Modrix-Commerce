# Contributing to EcoModular

Thank you for helping. By contributing you agree that your contribution is licensed under the project's MIT License.

## Ground rules

- Discuss large changes in an issue first. Architecture changes need an ADR entry in `docs/architecture/decisions.md`.
- Keep business logic in Actions/services under `app/Core` or `app/Domains`, not in controllers or React components. `tests/Architecture` enforces the layer boundaries.
- Every authorization decision happens on the server. UI checks (`useCan`) are hints only.
- Never trust prices, totals, discounts or tax values supplied by the client.
- Money is never a float. Use DECIMAL columns and the Money value object (ADR-007).
- Wrap state changes to orders, payments, inventory and licenses in a transaction.
- Don't add dependencies speculatively. When you add one, document its purpose, license and Laravel/PHP compatibility in the PR and in `THIRD_PARTY_NOTICES.md`.
- No gradients, no inert buttons, and no fake success states in the UI.

## Workflow

1. Fork the repository and branch from `main` (`feature/…`, `fix/…`, `chore/…`).
2. Write tests first, or alongside the code. Cover permitted and forbidden paths for anything access-controlled.
3. Run the quality gates locally:
    ```bash
    composer test && npm run types:check && npm run check && npm run build
    ```
4. Update the docs and the `CHANGELOG.md` "Unreleased" section.
5. Open a pull request that describes the behaviour change, the security considerations and how you tested it.

## Commit messages

Use the imperative mood ("Add coupon usage limits"). Reference issues where relevant.

## Reporting security issues

Don't open public issues for vulnerabilities. See [SECURITY.md](SECURITY.md).
