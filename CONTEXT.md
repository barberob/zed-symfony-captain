# Symfony Captain — Domain Glossary

The product: a Zed extension that brings Symfony route navigation to the editor, delivered through a PHP language server.

## Terms

- **Symfony project** — a PHP project that is bootable (`bin/console` works and a kernel is present). The editor stays silent outside one.
- **Route** — a single named Symfony route: name, HTTP method(s), path, and the controller that handles it.
- **Route name** — the canonical string identifier of a route; how code refers to a route.
- **Route definition** — where a route is declared: a `#[Route]` attribute on a controller method, or an entry in `config/routes*`.
- **Controller** — the class and method a route points to, e.g. `App\Controller\UserController::show`.
- **Route reference** — a place in source code where a route name appears as a string inside a URL-generation call. The unit of navigation, completion, and hover.
- **URL-generation call** — a recognized call that turns a route name into a URL. In PHP: `generate()`, `generateUrl()`, `redirectToRoute()`. In Twig templates: `path()`, `url()`. A route reference is an argument to one of these.
- **Route index** — the catalog of all routes in the project as the editor knows it at a point in time.
- **Go to definition** — navigating from a route reference to its controller.
- **Route name completion** — the editor suggesting route names at a route reference.
- **Workspace symbol** — a route surfaced in the editor's symbol palette.

## Explicitly avoided

- **Autocompletion** — too vague; use **route name completion**.
- **URL / path** — a completed value, never a primary concept; the product navigates by **route name**.
