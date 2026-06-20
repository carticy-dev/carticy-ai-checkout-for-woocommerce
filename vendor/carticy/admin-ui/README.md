# Carticy Admin UI (`carticy/admin-ui`)

The unified Carticy admin design system. This package **replaces** the three
fragmented packages `carticy/design-system`, `carticy/admin-layout`, and
`carticy/admin-components` (BUILD-PLAN Workstream A): one repo carrying the
design tokens, the component CSS, React 18 primitives, and the
server-validated onboarding framework, adapted from the SiteVitals v2
redesign language (calm, dense, near-black + sparing violet accent).

License: GPL-2.0-or-later — compiled output may ship inside WordPress.org
plugin artifacts.

**This package is the standard admin foundation for every current and
future Carticy plugin.** Do not create per-plugin design systems, and do
not resurrect the retired packages — extend this one instead. Nothing in
here may be product-specific: product mappings, copy, and PHP page shells
live in each plugin.

## What's inside

```
css/tokens.css        Design tokens (--cv-*), scoped to .cv-root
css/components.css    All .cv-* components, global resets scoped to .cv-root
css/admin-ui.css      @import entry point for bundlers
js/src/index.js       React exports (JSX source, compiled by the consumer)
js/src/mount.js       adminUiRoot() mount-scoping helper
js/src/components/    Button, Card, StatusPill, Chip, Banner, Tabs,
                      TextField/TextareaField/SelectField, Toggle/ToggleRow,
                      Modal, Spinner, EmptyState, SectionHeader
js/src/onboarding/    Stepper + useServerOnboarding (server-validated
                      state machine client)
```

## Scoping doctrine (load-bearing)

Every token and global reset is scoped to the `.cv-root` class — never
`:root`, never `html`/`body` — so multiple Carticy plugins (and versions of
this package) can coexist on one wp-admin without leaking styles. Each
consuming plugin must:

1. Add `cv-root` to its app mount element (use `adminUiRoot( element )`).
2. Enqueue the CSS under a **plugin-unique handle** with the plugin's own
   version string (e.g. `myplugin-admin-ui`), never a shared handle.

Violet (`--cv-accent`) is the brand accent used sparingly (active tab,
toggle-on, focus ring, plan pill) — never a button background. Primary
actions are near-black. Our own components use inline SVG/Lucide-style
icons; WP-native shells (`.notice`, PHP-rendered modals) keep dashicons —
that hybrid stance is deliberate (see the v2 audit).

## Consuming from a WordPress plugin

composer.json:

```json
{
    "repositories": [ { "type": "vcs", "url": "https://github.com/carticy-dev/admin-ui" } ],
    "require": { "carticy/admin-ui": "dev-master" }
}
```

**JS (React/JSX):** alias the package into your `@wordpress/scripts` webpack
build and import from it — the JSX compiles with your app:

```js
// webpack.config.js
resolve: {
    alias: { '@carticy/admin-ui': path.resolve( __dirname, 'vendor/carticy/admin-ui/js/src' ) },
}
```

```js
import { adminUiRoot, Button, Banner, Stepper, useServerOnboarding } from '@carticy/admin-ui';
```

**CSS:** either import `vendor/carticy/admin-ui/css/admin-ui.css` through
your build (postcss-import resolves the two files into your bundle —
recommended, so the WordPress.org artifact ships compiled assets instead of
`vendor/carticy`), or copy `tokens.css` + `components.css` into your
plugin's `assets/css/dist/` at build time and enqueue them in that order.

Do **not** ship `vendor/carticy/admin-ui` inside a WordPress.org artifact;
bundle the compiled output instead.

## Taking over the wp-admin page (required for plugin screens)

A Carticy screen must own the whole inner page — not render as a boxed
island on wp-admin's gray canvas. Three pieces, all required:

1. **Mount markup:** the wrapper element gets `cv-root cv-root--app`
   (full-bleed background + full height) and contains a `cv-page`
   (max-width, centered) for the content.
2. **Body class:** add `cv-takeover` via `admin_body_class` on your own
   screens only, and enqueue `css/chrome.css` (separate handle — it styles
   host chrome *outside* `.cv-root`, gated entirely by the body class).
   It removes wp-admin's content gutters, hides `#wpfooter`, and hides
   stray third-party notices.
3. **Notice suppression (PHP):** on your screens run
   `remove_all_actions( 'admin_notices' ); remove_all_actions( 'all_admin_notices' );`
   from `in_admin_header` at a late priority. Surface your own messages
   inside the app with `cv-banner` components instead.

The admin sidebar and admin bar stay visible — the takeover is the inner
page only. Inside it, use cv-* components for EVERYTHING (tabs, tables,
buttons, fields, badges); a screen that falls back to WP-native tab/table
styles is not done.

## Onboarding framework

`useServerOnboarding( '/myplugin/v1/onboarding' )` implements the SiteVitals
pattern: the server owns the state string and validates every transition;
the client renders the current state and requests transitions
(`transition( 'step2_viewing', payload )`). A "complete" state must mean the
server verified it (for tracking products: a wire-confirmed test purchase) —
never client-side click-through progress.

## Source of truth

The design language derives from the SiteVitals v2 redesign
(`colors_and_type.css` + `components.css`; the canonical mappings and audit
live with that project). Product-specific mappings (backend slugs → labels)
stay in each product; only the visual system lives here. When the v2 source
evolves, port changes here deliberately — this repo is the canonical copy
for all Carticy plugins.
