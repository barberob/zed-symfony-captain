Status: resolved

## Completed

- `lsp/build.php` concatenates the `SymfonyCaptain\Lsp` classes into a single self-contained `dist/symfony-captain-lsp.php` bundle (no dependency on the repo composer autoloader), which is what gets attached to releases.
- `src/lib.rs` resolves the LSP path: a user `lsp.symfony-captain.binary.path` setting overrides it; otherwise the extension downloads `symfony-captain-lsp.php` from the GitHub release asset into the extension working directory on first use and caches it. The `LSP_RELEASE_TAG` constant must stay in sync with `extension.toml` `version` and the pushed tag.
- `extension.toml` declares the `download_file` capability restricted to `github.com` and the `symfony-captain/symfony-captain` repository path.
- `.github/workflows/release.yml` builds the LSP bundle and attaches it to a GitHub release when a `v*` tag is pushed.
- `.zed/settings.json` points the dev extension at the local `lsp/symfony-captain-lsp.php`, bypassing the download while developing.
- `README.md` documents installation, the restricted-environment override, local development, tests, and releasing.
- Tests: `tests/DistBundleTest.php` builds the bundle and asserts it is valid PHP, self-contained, and answers the JSON-RPC initialize/shutdown flow over stdin.
- Typechecking: `cargo check`/`clippy` clean on `wasm32-wasip2`, full PHPUnit suite green (47 tests).

# 06 — Distribute the LSP via GitHub releases and add CI

**What to build:** Ship the PHP language server as a release asset and make the Zed extension download it automatically. Provide a fallback so users can point Zed to a local copy of the LSP.

**Blocked by:** 05 — Refresh the index on save and handle errors gracefully.

**Acceptance criteria:**
- [x] The Rust extension downloads the PHP LSP script from the repo’s GitHub release assets on first use and caches it in the extension working directory.
- [x] The download capability is declared in `extension.toml` and restricted to the trusted host.
- [x] A Zed user setting allows overriding the downloaded LSP path for local development or restricted environments.
- [x] A GitHub Actions workflow creates a release and attaches the PHP LSP script when a version tag is pushed.
- [ ] The extension can be installed as a dev extension and successfully downloads and launches the released LSP script. (Manual validation against a real GitHub release; requires pushing the first `v*` tag.)
- [x] Documentation explains installation and local development setup.
