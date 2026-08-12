Status: resolved

# 01 — Bootstrap and launch the language server

**What to build:** Lay the repository foundation as a Zed extension and create the smallest possible PHP language server. The extension must be loadable as a dev extension in Zed and must successfully launch the LSP. The LSP must speak enough of the Language Server Protocol to initialize and shut down cleanly.

**Blocked by:** None — can start immediately.

**Acceptance criteria:**
- [x] A Zed extension manifest and Rust crate exist at the repo root.
- [x] A PHP script exists that reads JSON-RPC messages from stdin and writes responses to stdout.
- [x] The LSP responds correctly to `initialize` and `shutdown`/`exit`.
- [x] PHPUnit tests verify the JSON-RPC initialize/shutdown flow.
- [x] The extension can be loaded as a dev extension in Zed and the LSP process starts without errors.
- [x] A user setting can override the LSP path for local development.

## Completed

- `extension.toml` + Rust crate (`Cargo.toml`) at repo root; `src/lib.rs` registers the extension and resolves the LSP binary.
- `lsp/symfony-captain-lsp.php` boots the server; `lsp/src/MessageStream.php` handles Content-Length framed JSON-RPC over stdio.
- `LspServer` answers `initialize` (with capabilities), `shutdown`, and `exit`; covered by `tests/LspServerTest.php`.
- The extension is installed as a dev extension and the LSP launches in Zed without errors (see Zed log); `lsp.symfony-captain.binary.path` overrides the script path.
