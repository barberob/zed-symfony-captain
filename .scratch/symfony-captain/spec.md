Status: ready-for-agent

# Symfony Captain — Zed extension for Symfony route navigation

## Problem Statement

Symfony developers who use Zed currently have no IDE-assisted way to explore or navigate the application’s routes. They cannot list routes, search by name, or jump directly from a route name string in PHP to the controller method or `#[Route]` attribute that defines it. This slows down day-to-day navigation in Symfony projects.

## Solution

Build **Symfony Captain**, a Zed extension that ships a lightweight PHP language server. The MVP provides two IDE features:

1. **Workspace symbols** — every Symfony route appears as a searchable symbol (`Cmd/Ctrl + T`). Each symbol shows the HTTP method and path as detail, and selecting it opens the controller file at the relevant line.
2. **Go to definition** — in PHP code, `Ctrl + Click` on a route name string inside Symfony URL-generation methods jumps to the controller.

Because Zed does not allow custom side panels or extension-provided slash commands, the feature is delivered through the Language Server Protocol, which is the native, integrated way to expose navigation in Zed.

## User Stories

1. As a Symfony developer, I want to open the symbol palette and type a route name to jump to its controller, so that I can navigate the project without memorizing file paths.
2. As a Symfony developer, I want to `Ctrl + Click` on a route name inside `$urlGenerator->generate('...')` to open the controller, so that I can follow the flow from a URL helper to its destination.
3. As a Symfony developer, I want the route index to refresh when I save a controller or a route configuration file, so that the IDE stays in sync with the code.
4. As a Symfony developer, I want the extension to stay silent and do nothing when I open a non-Symfony PHP project, so that it does not interfere with other codebases.
5. As a Symfony developer, I want the route list to include every route, including those with closures or service-based controllers, so that the symbol search is complete even if some entries cannot be mapped to a file.
6. As a maintainer, I want the LSP to be covered by unit tests for the protocol, parser, and route provider, so that I can refactor safely.
7. As a maintainer, I want an integration test against a real Symfony project fixture, so that I can verify the route discovery and reflection pipeline end-to-end.
8. As a maintainer, I want the Zed extension wrapper to download the LSP automatically from a GitHub release, so that users do not have to configure paths manually.
9. As a user, I want the extension to fall back to a manually configured LSP path if the automatic download is not available, so that I can still use it in restricted environments.

## Implementation Decisions

- **Language server in PHP 8.2 minimum** — the server runs as a stdio JSON-RPC process launched by the Zed extension. PHP is chosen because the server needs to load the project’s `vendor/autoload.php` and use reflection to locate controllers.
- **Zed extension Rust wrapper** — the extension is minimal: it downloads the LSP script from the repo’s GitHub release assets and returns the command to launch it with `php`. A user setting override is accepted for local development.
- **Route discovery via Symfony console** — the LSP executes `bin/console debug:router --format=json` in the project root. The project must be bootable for this command to work.
- **Controller resolution via reflection** — given a controller string such as `App\Controller\UserController::show`, the LSP requires the project autoloader and uses `ReflectionClass` and `ReflectionMethod` to find the file path and start line.
- **Source parsing with PHP’s native tokenizer** — `token_get_all` is used to detect route name string arguments in calls to Symfony URL-generation methods (`generate`, `generateUrl`, `redirectToRoute`).
- **Refresh strategy** — the route index is built on initialization and rebuilt on `didSave` for files under `src/Controller/` and `config/routes/`.
- **Symbol representation** — each route is exposed as a workspace symbol named `Route: <name>` with detail `<METHOD> <path>`.
- **Go-to-definition scope** — only PHP is supported in the MVP; navigation triggers on route name strings passed to the recognized Symfony URL-generation methods.
- **Error handling** — if the project is not a Symfony project, if `bin/console` is missing, or if reflection fails, the LSP logs the error and returns empty results rather than crashing.
- **Distribution** — the PHP LSP script is attached as a release asset. The Rust extension uses `download_file` to fetch it into the extension’s working directory on first use.
- **Repository layout** — the repo root is the Zed extension root, with the Rust extension code and `extension.toml` at the top level and a dedicated `lsp/` directory for the PHP language server.

## Testing Decisions

- **Highest seam: JSON-RPC protocol** — feed raw LSP messages to the LSP process over stdin and assert the responses. This tests the whole pipeline without requiring Zed to be running.
- **Route index seam** — unit test the route provider against a minimal Symfony project fixture that contains a few controllers and routes.
- **Parser seam** — unit test the PHP tokenizer-based parser with code snippets that contain route name strings inside and outside the recognized methods.
- **Reflection seam** — verify that the controller resolver maps a controller string to the correct file path and line number in the fixture.
- **Zed extension wrapper seam** — validated manually by loading the extension as a dev extension in Zed and verifying that the language server starts and produces symbols.
- A good test exercises externally observable behavior (a message in, a response out) rather than internal state.

## Out of Scope

- Twig template navigation or route name completion in `{{ path('...') }}`.
- Completion or hover information for route names.
- Route generation, code actions, or refactoring helpers.
- Integration with Symfony CLI (`symfony` binary) — plain `php` is used.
- Custom Zed panels, webviews, or slash commands — Zed’s current extension API does not support them.
- Non-Symfony PHP frameworks.
- Multi-workspace or multi-root projects beyond the current worktree.
- PHAR distribution of the LSP — the first release ships a plain PHP file.

## Further Notes

- The next phase after the MVP should add route name completion and hover, then Twig support.
- The ultimate goal is to publish the extension to the Zed extension marketplace once the MVP is stable.
