Status: ready-for-agent

# 01 — Bootstrap and launch the language server

**What to build:** Lay the repository foundation as a Zed extension and create the smallest possible PHP language server. The extension must be loadable as a dev extension in Zed and must successfully launch the LSP. The LSP must speak enough of the Language Server Protocol to initialize and shut down cleanly.

**Blocked by:** None — can start immediately.

**Acceptance criteria:**
- [ ] A Zed extension manifest and Rust crate exist at the repo root.
- [ ] A PHP script exists that reads JSON-RPC messages from stdin and writes responses to stdout.
- [ ] The LSP responds correctly to `initialize` and `shutdown`/`exit`.
- [ ] PHPUnit tests verify the JSON-RPC initialize/shutdown flow.
- [ ] The extension can be loaded as a dev extension in Zed and the LSP process starts without errors.
- [ ] A user setting can override the LSP path for local development.
