Status: ready-for-agent

# Twig Support — route navigation, completion, and hover in Twig templates

## Problem Statement

Symfony developers write a large share of their route references in Twig templates — links, forms, and redirects all call `path()` and `url()`. Symfony Captain currently only understands PHP source, so those references are dead: no go-to-definition, no completion, no hover. Developers must switch between template and controller by hand.

## Solution

Extend the language server to Twig templates. A route reference inside a `path()` or `url()` call in a `.twig` file gets the same three capabilities as PHP: `Ctrl+Click` jumps to the controller, typing a route name offers completion, and hovering shows the route's method, path, and controller. Everything stays silent where it should: non-route strings, non-Symfony projects, and projects without Twig installed.

## User Stories

1. As a Symfony developer, I want to `Ctrl+Click` a route name inside `{{ path('...') }}` in a Twig template to open its controller, so that I can follow a request from a template link to its handler.
2. As a Symfony developer, I want the same navigation on `{{ url('...') }}` references, so that absolute-URL generation navigates too.
3. As a Symfony developer, I want route name completion to appear while typing inside `path('…')` and `url('…')`, so that I don't have to recall route names.
4. As a Symfony developer, I want completion to appear immediately after the opening quote (`{{ path('`), so that I can pick a route before typing anything.
5. As a Symfony developer, I want completion to keep working on half-typed, mid-edit templates (`{{ path('use…`), so that suggestions appear while I type.
6. As a Symfony developer, I want hover on a route name in a Twig template to show the route's method, path, and controller, so that I can inspect it without navigating.
7. As a Symfony developer, I want a method call such as `app.request.path('…')` to be ignored, so that only real `path()`/`url()` function calls trigger.
8. As a Symfony developer, I want route references inside Twig logic blocks like `{% set u = path('…') %}` to work too, so that the features cover more than plain output tags.
9. As a Symfony developer, I want the features to stay silent in non-Symfony projects, in files that are not Twig templates, and on route names that don't exist, so that the extension never interferes.
10. As a Symfony developer, I want the same experience whether the route name uses single or double quotes, so that I'm not forced to change quoting.
11. As a Symfony developer, I want the extension to attach to Twig files automatically in Zed, so that no per-file configuration is needed.
12. As a Symfony developer, I want the features to stay silent when the project has no Twig installed, so that API-only projects are unaffected.
13. As a maintainer, I want the Twig parsing covered by unit tests at the detector seam, so that edge cases (guards, half-typed source, empty strings) are locked down.
14. As a maintainer, I want the LSP covered by end-to-end tests against a Twig fixture, so that definition, completion, and hover are verified over the real protocol.

## Implementation Decisions

- **RouteReferenceFinder seam.** A small interface with the capabilities the features need — finding occurrences, finding the occurrence at a cursor, and deciding whether a cursor is at a route reference (including half-typed source). The existing PHP finder implements it (its half-typed fallback moves onto it), and a new Twig finder implements it too. The completion and hover builders receive the finder by constructor; the LSP selects the finder per request by file type.
- **Twig parsing uses Twig's own lexer** (see ADR-0001). The finder tokenizes the template with `Environment::tokenize()` and walks the token stream for a `NAME` token `path` or `url` — whose preceding token is not a `.` punctuation — followed by `(` and a string token. Only the positional first string argument counts. Both quote styles are handled by the lexer.
- **Byte offsets.** Twig tokens expose line numbers but not byte offsets, so the finder tracks a byte cursor as it walks the stream.
- **Completion trigger.** Clean source uses the lexer walk; when the lexer throws on half-typed source (`{{ path('`), a line-scoped backward scan for `path('`/`url('` provides suggestions. Completion and go-to-definition agree on clean source.
- **File detection.** The LSP treats files ending in `.twig` or `.twig.html` as Twig templates and dispatches to the Twig finder; `.php` continues to use the PHP finder. PHP behavior is unchanged, only refactored behind the interface.
- **Zed registration.** The extension advertises the Twig language so the language server attaches to Twig files.
- **Twig dependency.** `twig/twig` is added as a dev dependency of this repository for tests and source mode; in the shipped bundle the finder uses the project's own Twig (loaded via the project autoloader the LSP already requires) and stays silent when it is absent.
- **Scope of recognized calls.** `path()` and `url()`, positional first string argument only. The `name = '...'` named-argument form and the `asset()`/`absolute_url()` helpers are out of scope.

## Testing Decisions

- A good test exercises externally observable behavior (a message in, a response out) rather than internal state.
- **LSP seam (highest, reused):** the existing protocol tests feed raw JSON-RPC `textDocument/definition`, `textDocument/completion`, and `textDocument/hover` requests for a Twig fixture file and assert the responses — including silent cases (non-route position, non-Symfony project).
- **Detector seam (new, parallel to the existing PHP finder tests):** `TwigRouteNameFinder` is unit-tested with template snippets covering clean calls in single and double quotes, the empty string, logic-block usage, the method-call guard, and the half-typed fallback.
- The route index, controller resolver, and refresh logic are unchanged and rely on their existing tests.

## Out of Scope

- Route name completion or navigation with named arguments (`path(name = '...')`).
- `asset()`, `absolute_url()`, or any non-route URL helper.
- Route definition or editing from templates — templates consume routes, they don't declare them.
- Non-Symfony Twig projects.
- Behavior changes to the existing PHP features.

## Further Notes

- Twig support completes the roadmap. The remaining item after it is marketplace publication.
- Real Symfony template projects always ship `twig/twig`, so the shipped bundle's reliance on the project's Twig covers the actual user base.
