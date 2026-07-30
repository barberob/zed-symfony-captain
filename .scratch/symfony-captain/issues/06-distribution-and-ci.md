Status: ready-for-agent

# 06 — Distribute the LSP via GitHub releases and add CI

**What to build:** Ship the PHP language server as a release asset and make the Zed extension download it automatically. Provide a fallback so users can point Zed to a local copy of the LSP.

**Blocked by:** 05 — Refresh the index on save and handle errors gracefully.

**Acceptance criteria:**
- [ ] The Rust extension downloads the PHP LSP script from the repo’s GitHub release assets on first use and caches it in the extension working directory.
- [ ] The download capability is declared in `extension.toml` and restricted to the trusted host.
- [ ] A Zed user setting allows overriding the downloaded LSP path for local development or restricted environments.
- [ ] A GitHub Actions workflow creates a release and attaches the PHP LSP script when a version tag is pushed.
- [ ] The extension can be installed as a dev extension and successfully downloads and launches the released LSP script.
- [ ] Documentation explains installation and local development setup.
