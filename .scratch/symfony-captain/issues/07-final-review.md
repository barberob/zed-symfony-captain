Status: resolved

# 07 — Final review and end-to-end manual verification

**What to build:** Close the MVP by running the full test suite, reviewing the diff, and verifying the extension in Zed against a real Symfony project.

**Blocked by:** 06 — Distribute the LSP via GitHub releases and add CI.

**Acceptance criteria:**
- [x] All PHPUnit tests pass, including unit and integration tests.
- [x] A code review is performed against the project standards.
- [x] The extension is loaded in Zed and workspace symbols work on a real Symfony project.
- [x] Go-to-definition works on route name strings in PHP files of a real Symfony project.
- [x] The route index refreshes correctly after saving a controller or route configuration file.
- [x] The extension stays silent and produces no errors when opened in a non-Symfony PHP project.

## Completed

- Full suite green: 49 PHPUnit tests (163 assertions), `cargo check`/`clippy`/`fmt --check` clean on `wasm32-wasip2`.
- Code review performed on `origin/main...HEAD` along two axes (Standards + Spec); actionable findings fixed, see below.
- E2E verified via LSP protocol against the real Symfony project `/home/benoit/aih/docker/approche` (128 routes): workspace symbols resolve controllers to file/line, go-to-definition on `redirectToRoute('type_equipement_index')` jumps to the controller method, and `didSave` on a controller file refreshed the index from 128 → 129 routes.
- Non-Symfony PHP project (`/tmp/opencode/non-symfony`): returns 0 symbols, emits no log/error messages.
- Zed log confirms the extension is loaded and the LSP process launches against the real project with no errors in the current session.

## Review fixes applied

- `cargo fmt --check` was failing (import block + `lsp_path` call); reformatted.
- `lsp_path()` left the LSP stuck reporting "Downloading" forever when the download failed; the installation status is now reset regardless of outcome.
- `RouteProvider::runDebugRouter()` discarded console stderr (`2>/dev/null`); it now captures stderr and includes it in the `RouteProviderException`, so the `window/logMessage` error path (ticket 05) carries real diagnostics.
- Extracted the duplicated LSP location encoding into `RouteLocation::toLocation()` (was built twice in `WorkspaceSymbols` and `LspServer`).
- Clarified `RouteNameFinder::byteOffsetAt()` UTF-16 counting by splitting into `utf8Bytes()` + `utf16Units()` helpers; added a multibyte test (é, 🎉).
- `initialize` no longer blocks the LSP handshake on `debug:router`; the index is built lazily on the first symbol/definition request, so a broken project no longer emits a `window/logMessage` before `initialize` completes.
