Status: resolved

## Completed

- `textDocument/didSave` rebuilds the route index for files under `src/Controller/`, `config/routes.yaml`, `config/routes.yml` and `config/routes/`; other saves are ignored.
- `isSymfonyProject()` now checks for `bin/console` plus a bootable kernel marker (`src/Kernel.php` or `config/bundles.php`).
- `debug:router` failure throws `RouteProviderException`; the LSP catches it, emits `window/logMessage` (type Error) and returns empty results.
- Reflection-failed routes remain listed without a location (existing behavior, still covered).
- New fixtures: `Refresh` (dynamic routes.json), `Broken` (failing console), `ConsoleOnly` (console without kernel); `Project` gained a `src/Kernel.php`.
- Tests: didSave rebuild on config/routes + controller, no rebuild on unrelated save, didSave failure path, debug:router failure log, Symfony detection cases.
- LSP logs activity to stderr (`[symfony-captain] ...`) and advertises `textDocumentSync.save`, so clients send `didSave`.
- Manual test in Zed against a real Symfony project: route changes are reflected after saving a controller — verified.

## Answer

Ticket fully implemented and manually verified in Zed against a real Symfony project (attributes-based routes refreshed on save after the `textDocumentSync` capability fix).

# 05 — Refresh the index on save and handle errors gracefully

**What to build:** Keep the route index up to date without requiring a Zed restart, and ensure the LSP stays stable when the project is not bootable, is not a Symfony project, or reflection fails.

**Blocked by:** 03 — Expose routes as workspace symbols, 04 — Go to definition from PHP route name strings.

**Acceptance criteria:**
- [x] The LSP rebuilds the route index on `didSave` for files under `src/Controller/` and `config/routes/`.
- [x] The LSP detects whether the workspace is a Symfony project by checking for `bin/console` and a bootable Symfony kernel.
- [x] If `debug:router` fails, the LSP logs the error and returns empty results instead of crashing.
- [x] If reflection fails for a controller, that route is still listed but has no location.
- [x] Tests verify refresh behavior and error paths.
- [x] Manual test in Zed confirms route changes are reflected after saving a controller.
