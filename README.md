# Symfony Captain

A [Zed](https://zed.dev) extension that brings Symfony route navigation to your editor through a lightweight PHP language server.

- **Workspace symbols** — every route appears in the symbol palette (`Ctrl+T` / `Cmd+T`) as `Route: <name>`, with the HTTP method and path as detail. Selecting a symbol opens the controller at the defining line.
- **Go to definition** — `Ctrl+Click` on a route name string inside Symfony URL-generation methods (`generate`, `generateUrl`, `redirectToRoute`) jumps to the controller method.

## Requirements

- Zed
- PHP 8.2+ available on your `PATH`
- A Symfony project that is bootable (`bin/console debug:router --format=json` must succeed)

## Installation

The extension is installed from the Zed extensions page. On first use it downloads the language server script (`symfony-captain-lsp.php`) from the project's [GitHub releases](https://github.com/barberob/zed-symfony-captain/releases) and caches it in the extension working directory, then launches it with `php`.

### Restricted environments

If downloads are not available, point Zed at a local copy of the LSP script with a user setting. The downloaded/cached script is used unless this setting is present:

```json
{
  "lsp": {
    "symfony-captain": {
      "binary": {
        "path": "/absolute/path/to/symfony-captain-lsp.php",
        "arguments": []
      }
    }
  }
}
```

You can obtain a copy of the script by running the build locally (see below) or from a release asset.

## Local development

### Installing as a dev extension

1. Clone the repository.
2. Build the extension binary:

   ```sh
   rustup target add wasm32-wasip2
   cargo build --release --target wasm32-wasip2
   cp target/wasm32-wasip2/release/zed_symfony_captain.wasm extension.wasm
   ```

3. From the Zed extensions page choose **Install Dev Extension** and select the repository root.
4. Add a dev setting overriding the LSP path so the extension does not download a release asset:

   ```json
   {
     "lsp": {
       "symfony-captain": {
         "binary": {
           "path": "/absolute/path/to/this/repo/lsp/symfony-captain-lsp.php"
         }
       }
     }
   }
   ```

   Put it in `.zed/settings.json` at the repository root (it is gitignored, so keep it out of version control). The source script `lsp/symfony-captain-lsp.php` loads its classes through the repository autoloader, so run `composer install` first.

### Building the LSP distribution bundle

The release asset is a single self-contained PHP file generated from `lsp/src/`:

```sh
php lsp/build.php   # writes dist/symfony-captain-lsp.php
```

### Tests

```sh
composer install
vendor/bin/phpunit
```

### Releasing

Push a version tag matching the `LSP_RELEASE_TAG` constant in `src/lib.rs` (and the `version` in `extension.toml`). `.github/workflows/release.yml` builds the LSP bundle and attaches it to the GitHub release.

```sh
git tag v0.1.0
git push origin v0.1.0
```

## How it works

The Rust extension (`src/lib.rs`) resolves the LSP script — from the user setting override, or by downloading the release asset into the extension working directory — and launches `php <script>`. The PHP language server reads JSON-RPC messages over stdio, discovers routes via `bin/console debug:router --format=json`, and resolves controllers by reflection against the project autoloader.

## License

MIT
