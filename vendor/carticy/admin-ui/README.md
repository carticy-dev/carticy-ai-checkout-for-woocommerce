# Carticy Admin UI (`carticy/admin-ui`)

The unified Carticy admin design system: mount-scoped `--cv-*` design tokens,
`.cv-*` component CSS, React 18 primitives (JSX source, compiled by the
consuming plugin), and a server-validated onboarding framework. Calm, dense,
near-black with a sparing violet accent.

License: GPL-2.0-or-later — compiled output may ship inside WordPress.org
plugin artifacts.

**This package is the standard admin foundation for every current and future
Carticy plugin.** Do not create per-plugin design systems — extend this one
instead. Nothing here is product-specific: product mappings, copy, and PHP
page shells live in each plugin.

> This README is **stateless** — it describes the package and how to consume
> it on *any* Carticy plugin. Wherever you see `myplugin`, substitute your
> plugin's slug. Nothing here assumes a particular product.

---

## What's inside

```
css/tokens.css        Design tokens (--cv-*), scoped to .cv-root
css/components.css    All .cv-* components, global resets scoped to .cv-root
css/chrome.css        wp-admin "takeover" layer, gated by the body.cv-takeover class
css/admin-ui.css      @import entry point (tokens + components) for bundlers
js/src/index.js       React exports (JSX source, compiled by the consumer)
js/src/mount.js       adminUiRoot() mount-scoping helper
js/src/date.js        formatDate(): the site's timezone and date formats
                      (its own entry point, @carticy/admin-ui/date; see Dates)
js/src/components/    Screen shell: AppHeader, HeaderUtilities, NoticeSlot,
                      LoadingPanel, SectionLayout, SaveBar
                      Button, Spinner, Skeleton, Card, StatCard, StatusPill, Chip, Banner,
                      Tabs, SectionHeader, TextField/TextareaField/SelectField,
                      Toggle/ToggleRow, Modal, EmptyState, Table, SearchBar,
                      BulkActionBar, Pagination, Drawer, ToastProvider/useToast,
                      FormSection, Progress, Segmented, Dropzone, Combobox
js/src/onboarding/    Stepper + useServerOnboarding (server-validated state
                      machine client)
bin/check-copy.mjs    Plain-language check for the plugin's translation template
```

The CSS in `css/` is prebuilt and ready to enqueue. The JS in `js/src/` is
**uncompiled JSX** — it compiles inside your plugin's webpack/babel build (see
Build setup). React is never bundled here; it resolves to the WordPress
`wp.element` global at runtime.

---

## Screen layout

Every Carticy screen uses the same frame, so a store owner who knows one
plugin knows them all:

```jsx
<AppHeader
    title={ __( 'My Plugin', 'myplugin' ) }
    icon={ <svg viewBox="0 0 24 24">…</svg> }
    navLabel={ __( 'My Plugin sections', 'myplugin' ) }
    items={ [ { id: 'overview', label: __( 'Overview', 'myplugin' ), href: '#overview' }, … ] }
    current={ section }
    actions={ <Button size="sm" onClick={ openTester }>{ __( 'Test a search', 'myplugin' ) }</Button> }
    links={ [
        { href: config.contactUrl, label: __( 'Contact us', 'myplugin' ), icon: 'contact' },
        { href: config.accountUrl, label: __( 'Account', 'myplugin' ), icon: 'account' },
    ] }
/>
<div className="cv-page">
    <NoticeSlot areaId={ config.noticeArea } />
    <h2 className="cv-h2">{ sectionTitle }</h2>
    { loading ? <LoadingPanel label={ __( 'Loading settings…', 'myplugin' ) } /> : <SectionLayout … /> }
    { dirty && <SaveBar label={ __( 'Unsaved settings', 'myplugin' ) } message={ __( 'You have unsaved changes.', 'myplugin' ) }>…</SaveBar> }
</div>
```

- **AppHeader** — brand (the screen's `h1`), one link per section, then the
  screen-wide `actions` and the utility links at the end. On narrow screens
  the section links move to a row of their own that scrolls sideways; the
  utility links stay beside the brand while both fit on one line, and a
  longer title moves them to a row under the brand instead of squeezing
  either. `actions` always take that row with the utility links, wrapping
  there. Every link and action in the header is a 44px touch target there.
  The plugin owns the routes behind `href`. Moving between sections is
  navigation: links with `aria-current="page"`, never `Tabs`.
- **HeaderUtilities** (inside AppHeader, or alone) — Contact us, Account and
  similar destinations as labelled links with an icon. Pass trusted URLs
  from the server; an empty `href` leaves a link out, so omit destinations
  the current user cannot open. These links replace the duplicate Contact
  Us / Upgrade / Account entries the licensing SDK adds to the WordPress
  menu; the plugin keeps one menu entry under WooCommerce.
- **NoticeSlot** — where the screen's own notices appear: below the header,
  inside the page. See Notices below.
- **LoadingPanel** — a whole screen or panel waiting for data whose layout
  is not known yet: a centered spinner with room around it and a
  screen-reader label. With `showLabel` the label also shows under the
  spinner: use it when the wait is a step the reader should see named
  ("Checking for a consent platform…"), typically a check against another
  site or service that can take a few seconds; a plain first load keeps the
  spinner alone. Content whose layout is known (cards, stat rows,
  table rows, a chart) keeps its shape with `Skeleton` instead (see
  Loading). Keep the inline `Spinner` (or `Button loading`) for busy
  controls. When the request settles, show the result, an `EmptyState` or a
  `Banner` with a retry.
- **SectionLayout** — a long screen split into sections: a side navigation
  on wide screens, a Drawer (from the start edge) behind an "On this page"
  button on narrow ones.
  The navigation marks the section being read; choosing one focuses its
  heading and scrolls to it without changing the route, so drafts stay.
  Pass `sections: [ { id, title, content } ]` (stable ids, visible sections
  only, display order) and `headingLevel` (2, or 3 under a screen heading),
  or pass `items: [ { id, label, element } ]` with your own children when
  the headings already exist in your markup. One section shows no
  navigation. If sections above the chosen one are still loading their
  data, the heading is kept in view until they settle or the reader
  scrolls, types or clicks.
- **SaveBar** — while a settings draft differs from the saved values: the
  message and the Save / Discard buttons, sticky at the bottom of the
  viewport so the last field stays reachable on a phone.

Headings: the AppHeader title is the `h1`, the section title `h2`, cards
and sub-sections `h3`. Components that render a heading take
`headingLevel` (2 to 6; out-of-range levels are clamped) so the outline
stays right wherever they sit.

### Notices

The screen shows only the plugin's own notices, below its header:

1. In PHP, on the plugin's screens only, hook `in_admin_header` at the last
   priority (`PHP_INT_MAX`): remove every callback from `admin_notices`,
   `all_admin_notices`, `network_admin_notices` and `user_admin_notices`,
   then add back one printer for the plugin's own notices (and its
   licensing SDK's messages for this product).
2. On the main screen that printer wraps them in
   `<div id="myplugin-notices" class="cv-notice-area"><hr class="wp-header-end">…</div>`.
   WordPress's script moves notices to the first `wp-header-end`, so they
   stay in that area instead of landing in the app's header.
3. The app renders `<NoticeSlot areaId="myplugin-notices" />` below its
   header; the area moves there and takes no space when empty.

A notice the store can dismiss gets a close icon, never a "Dismiss"
button or a form that reloads the page. Print it inside the notice, with
the `cv-dismissible` class on the notice:

```html
<div class="notice notice-info cv-dismissible">
    <p>…</p>
    <button type="button" class="cv-notice-dismiss" aria-label="Dismiss this notice"
        data-cv-dismiss="/myplugin/v1/notices/cache" data-cv-dismiss-data='{"fingerprint":"…"}'></button>
</div>
```

NoticeSlot sends a `POST` to that REST path (with the optional JSON body)
and removes the notice; if the request fails the notice stays. The route
checks the capability and records the dismissal. Print the button only on
screens that render NoticeSlot.

Elsewhere in wp-admin the plugin's notices print through `admin_notices`
as usual. Don't hide other sources' notices with CSS; the starter plugin
has a working `Notices` class.

### Loading

Pick the indicator by what the screen already knows:

- **Skeleton** — the layout is known (a card's text, a stat value, table
  rows, a chart): placeholder shapes hold it, so nothing moves when the
  data arrives.
- **LoadingPanel** — the layout is not known yet (a screen or panel on its
  first load): a centered spinner, with its label under it when the wait
  needs naming (`showLabel`).
- **Spinner** or `Button loading` — a control that is busy.

Skeletons are hidden from screen readers. The region they fill carries
`aria-busy` and names what is loading in a status line that is on the page
before loading starts, so screen readers announce it:

```jsx
<section aria-busy={ loading }>
    <p className="cv-sr-only" role="status">{ loading ? __( 'Loading orders…', 'myplugin' ) : '' }</p>
    <div className="cv-kpi-strip">
        <StatCard label={ __( 'Orders', 'myplugin' ) } value={ loading ? <Skeleton width="4ch" /> : stats.orders } />
        …
    </div>
    <Card flush>
        <Table
            columns={ loading ? columns.map( ( column ) => ( { ...column, sortable: false, render: () => <Skeleton /> } ) ) : columns }
            rows={ loading ? [ { id: 1 }, { id: 2 }, { id: 3 } ] : orders }
        />
    </Card>
</section>
```

Labels the screen already knows (the stat labels, the table headers) stay
as text; only the values wait. A request that fails replaces the skeletons
with a `Banner` and a retry.

### Screen rules

Every Carticy screen follows these, so a store owner can set a plugin up
without reading documentation:

1. **Overview** opens with one status line (all good / working on it / needs
   you, with the action that fixes it), then what needs attention, then one
   next step. Maintenance buttons appear only when something is wrong.
2. **One way to save.** Settings collect in a draft and the SaveBar saves or
   discards them. A list editor (rules, pinned products) saves an item with
   its own "Save" in the editor. No permanently visible disabled Save/Discard
   pairs and no "saved values are shown above" lines.
3. **Plain words.** Say it the way a store owner would; one sentence of help
   per control, anything longer behind "Learn more". REST errors are read by
   people too. `bin/check-copy.mjs` enforces the vocabulary (below).
4. **Advanced stays out of the way.** Command-line commands, IDs, slugs,
   meta keys and diagnostics sit in one "Advanced" section per page.
5. **Pickers** search the way the store's search does (any word of the name,
   or the SKU) and name the search in their empty state.
6. **Dependent controls** are disabled or hidden while the setting they
   depend on is off.
7. **Layout:** content sits in cards; stat rows (`.cv-kpi-strip`) hold two
   to four equal cards; licence badges show only to unlicensed sites; locked
   features show one notice per page.
8. **Dates and times** use the site's timezone and date format
   (`formatDate`, see Dates).
9. **Destructive actions** come last, styled as danger, and ask to confirm.

The plain-language check reads the plugin's translation template, which
holds every PHP and JavaScript string. Add it to the plugin's lint script:

```
node vendor/carticy/admin-ui/bin/check-copy.mjs copy-check.json
```

```json
{
    "pot": "languages/myplugin.pot",
    "terms": [ "generation" ],
    "allow": [ "Privacy policy" ]
}
```

`terms` adds the plugin's own internal words to the shared list; `allow`
lists exact strings that may keep a listed word.

## Quick start (copy-paste)

Four pieces, all required. Skipping any one is the usual cause of "nothing
renders" or "invalid hook call".

### 1. composer.json

```json
{
    "repositories": [ { "type": "vcs", "url": "https://github.com/carticy-dev/admin-ui" } ],
    "require-dev": { "carticy/admin-ui": "dev-master" }
}
```

Then `composer require --dev carticy/admin-ui:dev-master`. Pull updates with
`composer update carticy/admin-ui`. (Use the GitHub repo — never a local
`path` repository — so CI and other machines can install it.)

### 2. Build setup (webpack + babel) — the part that bites people

The JSX compiles in *your* build, and React/i18n/api-fetch must resolve to the
WordPress script globals via **externals** (otherwise webpack bundles a second
React and every hook throws). preset-react must use the **classic runtime with
the `wp.element.createElement` pragma** (the automatic runtime would `import
'react'`, which isn't installed).

```js
// webpack.config.js
const path = require( 'path' );

module.exports = {
    // entry / output as usual, e.g. entry: { admin: './assets/js/src/admin/index.jsx' }

    externals: {
        '@wordpress/element': 'wp.element',
        '@wordpress/api-fetch': 'wp.apiFetch',
        '@wordpress/i18n': 'wp.i18n',
    },

    resolve: {
        extensions: [ '.js', '.jsx' ],
        alias: {
            '@carticy/admin-ui': path.resolve( __dirname, 'vendor/carticy/admin-ui/js/src' ),
        },
    },

    module: {
        rules: [ {
            test: /\.jsx?$/,
            exclude: /node_modules/,
            use: { loader: 'babel-loader', options: { presets: [
                [ '@babel/preset-env', { targets: 'defaults' } ],
                [ '@babel/preset-react', {
                    runtime: 'classic',
                    pragma: 'wp.element.createElement',
                    pragmaFrag: 'wp.element.Fragment',
                } ],
            ] } },
        } ],
    },
};
```

devDependencies: `@babel/core`, `@babel/preset-env`, `@babel/preset-react`,
`babel-loader`, `webpack`, `webpack-cli`. Import in your app:

```js
import { adminUiRoot, Button, Banner, Table, useServerOnboarding } from '@carticy/admin-ui';
```

### 3. Enqueue (PHP)

Register the script with the WordPress globals as **dependencies** (so they
exist before your bundle runs), and enqueue the three CSS files in order under
**plugin-unique handles** with your plugin's version:

```php
// React bundle — wp-element/api-fetch/i18n are the script deps for the externals.
wp_register_script(
    'myplugin-admin',
    plugins_url( 'assets/js/dist/admin.js', __FILE__ ),
    array( 'wp-element', 'wp-api-fetch', 'wp-i18n' ),
    MYPLUGIN_VERSION,
    true
);

// Design-system CSS, in order: tokens -> components -> chrome. Plugin-unique
// handles + your own version so multiple Carticy plugins/versions coexist.
$ui = plugins_url( 'vendor/carticy/admin-ui/css/', __FILE__ );
wp_enqueue_style( 'myplugin-admin-ui-tokens', $ui . 'tokens.css', array(), MYPLUGIN_VERSION );
wp_enqueue_style( 'myplugin-admin-ui', $ui . 'components.css', array( 'myplugin-admin-ui-tokens' ), MYPLUGIN_VERSION );
wp_enqueue_style( 'myplugin-admin-ui-chrome', $ui . 'chrome.css', array( 'myplugin-admin-ui' ), MYPLUGIN_VERSION );
wp_enqueue_script( 'myplugin-admin' );

// Pass server data to the app (REST namespace, nonce is handled by api-fetch).
wp_add_inline_script( 'myplugin-admin',
    'window.mypluginAdmin = ' . wp_json_encode( array(
        'restNamespace' => 'myplugin/v1',
        'mountId'       => 'myplugin-admin-root',
    ) ) . ';', 'before' );
```

For the WordPress.org artifact, **bundle the compiled CSS** into your plugin's
`assets/css/dist/` (via `postcss-import` on `css/admin-ui.css`, plus
`chrome.css`) and enqueue *that* — do **not** ship `vendor/carticy/admin-ui`
inside the zip.

### 4. Mount (JS)

```js
import { render } from '@wordpress/element';
import { adminUiRoot } from '@carticy/admin-ui';

const boot = window.mypluginAdmin || {};
const root = document.getElementById( boot.mountId || 'myplugin-admin-root' );
if ( root ) {
    adminUiRoot( root, { page: 'myplugin' } ); // adds .cv-root (+ data-page)
    root.classList.add( 'cv-root--app' );       // full-bleed app surface
    render( <App />, root );
}
```

The PHP outputs only `<div id="myplugin-admin-root"></div>` for the screen.

---

## Taking over the wp-admin page (recommended for full screens)

A Carticy screen should own the whole inner page, not float as a boxed island
on wp-admin's gray. Three pieces:

1. **Mount markup:** the wrapper gets `cv-root cv-root--app` (full-bleed
   surface) and your app renders a `cv-page` (centered, max-width) inside it.
2. **Body class + chrome.css:** add `cv-takeover` via `admin_body_class` on
   your screens only, and enqueue `css/chrome.css`. It removes wp-admin's
   content gutters, paints the page floor with the app surface (so a tall
   admin menu never reveals gray below a short screen), hides `#wpfooter`,
   and keeps notices printed straight into the page hidden until WordPress
   moves them into the notice area (see Notices) — all gated by the body
   class, so the rest of wp-admin is untouched.
3. **Notices (PHP + NoticeSlot):** isolate the screen's notices as described
   in Screen layout > Notices. Feedback from the app's own actions is a toast
   when it confirms what the reader just did ("Store saved"), and a `Banner`
   next to the thing that failed when the reader must act on it (see Toasts).

The admin sidebar and admin bar stay visible — the takeover is the inner page
only. Inside it, use cv-* components for EVERYTHING (tabs, tables, buttons,
fields, badges); a screen that falls back to WP-native tab/table styles is not
done.

---

## Component reference

All components are functional, accept `className` (merged) and pass unknown
props through to their root element, except where a component names another
element: the fields pass them to their control, `Toggle` and `ToggleRow` to
the checkbox, `Table` to the `<table>`, `Tabs` to the tab list, `Modal` and
`Drawer` to the dialog. `ToastProvider` renders no element of its own.
Import from `@carticy/admin-ui` (dates from `@carticy/admin-ui/date`).

`ref` reaches the same element as the other props on the components a
screen focuses or measures: `Button`, `Card`, `StatCard` (the card),
`Table` (the `<table>`; its scroll container is the parent element),
`SectionLayout`, `Skeleton`, `Toggle` and `ToggleRow` (the checkbox) and the
fields (the control).

When a component moves focus itself (SectionLayout to a heading, Modal and
Drawer into the dialog and back to its opener, Tabs and Segmented to the
chosen item, a dismissed toast to its neighbour), the focus ring shows only
if the reader last pressed a key. Safari focuses nothing on a tap or click,
so without this the ring would follow every tap there.

**Mount**
- `adminUiRoot( element, { page } )` — adds `cv-root` (and `data-page`) to the
  mount element. Call before `render`.

**Screen shell** (see Screen layout)
- `AppHeader({ title, icon?, items?: [{ id, label, href }], current?, navLabel?, actions?, links? })` — `actions` are controls for the whole screen (a `Button size="sm"`), shown before the utility links.
- `HeaderUtilities({ links: [{ href, label, icon?: 'contact'|'account'|'help'|'docs' }] })` — links are told apart by their place in `links`, so two may share an address (Help and Upgrade both on the plugin's page).
- `NoticeSlot({ areaId })` — an empty `display: contents` slot the notice area moves into.
- `LoadingPanel({ label, showLabel? = false })` — `role="status"`; `label` is read by screen readers, and with `showLabel` it also shows under the spinner (once, so it is not announced twice).
- `SectionLayout({ label, closeLabel, sections?: [{ id, title, icon?, content }], headingLevel? = 2, items?: [{ id, label, element }], children? })`: an `h2` heading takes the section-title size, deeper levels the card-title size; a section's `icon` (inline SVG) sits in a small tile before its heading; the navigation shows the title only. On narrow screens the navigation opens in a `Drawer` from the start edge; `closeLabel` names its close button.
- `SaveBar({ label, message?, children })`

**Actions & feedback**
- `Button({ variant: 'primary'|'secondary'|'tertiary'|'ghost'|'destructive'|'link' = 'secondary', size?: 'sm'|'lg', block?, loading?, icon?, type? = 'button', ...rest })` — primary = near-black; violet is never a button background. `ghost` has no fill or border until hovered (toolbar and row actions); `destructive` is for actions that delete or cannot be undone; `link` reads as a text link. An icon-only button needs an `aria-label`. While `loading` the button shows a spinner and stays focusable: it carries `aria-disabled` and `aria-busy`, looks disabled and ignores clicks, Enter and Space (a submit button does not submit) until `loading` is false. Use `disabled` for an action that is not available at all. A link drawn as a button (`<a className="cv-btn cv-btn--primary">`) keeps its variant's colours in every state inside `.cv-root`, whatever wp-admin applies to links.
- `Spinner({ ...rest })` — inline spinner; used inside `Button` when `loading`.
- `Skeleton({ variant?: 'text'|'block' = 'text', lines? = 1, width?, height?, ...rest })` — placeholder shapes for content whose layout is known while its data loads (see Loading). `text` draws `lines` bars, each the height of a line of the surrounding text (inside a `.cv-stat__value` it takes the value's size), the last of several shorter; `block` is one rectangle `height` tall (120px by default), for a chart or an image. `width` and `height` take a CSS length or a number of pixels. Hidden from screen readers; static when the reader reduces motion.
- `Banner({ tone: 'info'|'success'|'warning'|'critical' = 'info', title?, icon?, actions?, children })` — in-app notice; `critical` gets `role="alert"`. `icon` (inline SVG) is drawn before the text in the tone's colour, in a `.cv-banner__icon` slot.
- `ToastProvider({ duration? = 5000, max? = 4, dismissLabel? = 'Dismiss', container?, children })` + `useToast()` → `{ show({ id?, tone?, title, description?, duration? }), dismiss(id), success(title, options?), info(…), warning(…), critical(…) }` — short confirmations stacked in the bottom corner on the end side of the reading direction, above a SaveBar or an open Drawer's footer. See Toasts below.
- `Progress({ value?, max? = 100, label, valueText?, showValue? = true, meta?, tone?: 'warning'|'critical', ...rest })` — `role="progressbar"` with `aria-valuenow/min/max`; leave `value` out for the indeterminate bar (static when the reader reduces motion). `valueText` replaces the shown and announced percentage ("12 of 40 stores").

**Containers & metrics**
- `Card({ variant?: 'critical'|'active'|'locked', size?: 'lg', flush?, clickable?, ...rest })` — `flush` removes inner padding (e.g. to host a `Table`); `size="lg"` rounds the corners further.
- `StatCard({ value, label, sublabel?, icon?, tone?: 'neutral'|'accent'|'success'|'warning'|'critical' = 'neutral', accent?, onClick?, ...rest })` — a single metric tile; `accent` tints the value violet; `onClick` makes the whole card a button; `icon` sits in a small tile in the top corner, coloured by `tone`.
- `.cv-kpi-strip` (CSS) — the row for metric tiles, `StatCard`s or compact `.cv-kpi-tile`s (`<div class="cv-kpi-tile"><span class="cv-kpi-tile__num">…</span><span class="cv-kpi-tile__label">…</span></div>`), for any number of them: as many equal tiles per row as fit (150px or wider), two per row at 600px and below, where a last tile on its own takes the whole row.
- `SectionHeader({ title, headingLevel? = 2, actions? })` — section title with optional `actions` on the end side (a filter control, a Button…). An `h2` takes the section-title size, deeper levels the card-title size. For a line of meta text beside a title, use a `<span className="cv-section__meta">`.
- `StatusPill({ status, children })` — small status dot+label; `status` maps to `cv-status--{status}` (e.g. `connected`, `expired`). For states that are not about a connection, use the tones by meaning: `success`, `info`, `neutral`, `warning`, `critical`.
- `Chip({ tone: 'neutral'|'success'|'warning'|'critical' = 'neutral', children })` — compact tabular tag, coloured by meaning like the other tones.

**Navigation**
- `Tabs({ tabs: [{ id, label, panel?, count?, disabled?, severity? }], activeId, onChange(id), label })` — views of one screen behind underline tabs (WAI-ARIA tabs). One tab is in the Tab order; Left / Right move to the next or previous enabled tab and show it (following the reading direction), Home / End jump to the ends. Each tab's `panel` renders below the row as its `tabpanel` (`aria-controls`, labelled by its tab); inactive panels stay mounted but hidden, so drafts stay. `label` names the tab list. Page-to-page navigation is not tabs: use links with `aria-current="page"`, as AppHeader does.
- `Segmented({ options: [{ value, label, disabled?, count? }], value, onChange(value), label, size?: 'sm'|'lg', block?, disabled?, ...rest })` — one choice among a few (List / Map), drawn as `.cv-segmented` and announced as a radio group: one option in the Tab order, arrow keys move and choose (skipping disabled options, following the reading direction), Home / End jump to the ends. `label` names the group for screen readers. Options that do not fit wrap onto a second line.
- `Pagination({ page, total, onChange(page), summary?, siblings? = 1, label? = 'Pagination', previousLabel? = 'Previous', nextLabel? = 'Next', pageLabel?(n) = 'Page n', ...rest })` — `page` is 1-based and `total` is the number of pages. First, last and the pages around the current one, with an ellipsis per gap; the number of buttons stays the same from page to page. The current page carries `aria-current="page"`; Previous / Next are disabled at the ends and their arrows follow the reading direction. At 782px and below the buttons are 44px touch targets, Previous / Next show arrows only and `siblings` drops to 0, so the row fits a phone. A single page renders only the `summary`. Under a table, put it in a `<div className="cv-table__pagination">`.

**Forms**
- `TextField({ label?, help?, error?, icon?, ...inputProps })` / `TextareaField({ ... })` — `label` is optional; omit it for a bare control (e.g. an inline filter). TextField's `icon` (inline SVG) is drawn inside the input's border before the text, for fields found faster by their symbol (phone, email, link).
- `SelectField({ label?, help?, error?, options: [{ value, label, disabled? }], ...selectProps })`.
- All three forward `ref` to the control itself (the `input`, `textarea` or `select`), e.g. to focus it or to pass it as a Drawer's `initialFocusRef`.
- A `required` field shows `requiredLabel` (default `'Required'`; pass a translated string) beside its label; screen readers get the control's own required state instead.
- The help or error line is tied to the control with `aria-describedby` (an `aria-describedby` you pass is kept and read first), and the control gets `aria-invalid` with an error.
- Inside `.cv-root`, inputs, selects and date or time fields are one height (38px, a default Button's height) whatever wp-admin applies to their type, and selects take the full width of their column.
- `FormSection({ title, icon?, description?, actions?, variant?: 'plain'|'card' = 'plain', tone?: 'neutral'|'accent'|'success'|'warning'|'critical' = 'neutral', headingLevel? = 3, children })` — one titled group in a long form: an icon tile, the title, an optional one-line description and actions, then the fields with even spacing. It is a labelled group (`role="group"`, named by the title). Plain sections are divided by a hairline; `card` sections are white cards (`.cv-card`), for a grey surface such as `Drawer surface="subtle"`. Keep `tone` neutral unless the colour says something, such as the state of what the section edits.
- `Combobox({ label, options: [{ value, label, description?, disabled? }], value, onChange, multiple?, placeholder?, help?, error?, required?, requiredLabel? = 'Required', disabled?, filter? = true, onInputChange?(query), loading?, loadingLabel? = 'Loading…', noResultsLabel? = 'No matches', removeLabel?(label), clearLabel? = 'Clear selection', 'aria-describedby'?, ...rest })` — a searchable picker (WAI-ARIA 1.2 combobox with a listbox popup). `value` is one value or `null`; with `multiple` it is an array and the chosen options show as chips in the field. Label, help, error and `required` work as in the fields above (`required` sets `aria-required`, so the empty text field never blocks a form). Other props go to the `.cv-field` root. See Combobox below.
- `Dropzone({ onFiles(files), onReject?(rejections), accept?, maxSize?, multiple?, disabled?, label?, help?, buttonLabel? = 'Choose file', messages?, ...rest })` — a drop area plus a "Choose file" button. `help` is a line under the label (accepted types, size limit), read with the button. It checks the files and hands the accepted ones to `onFiles`; it never reads them. See Dropzone below.
- `Toggle({ size?, indeterminate?, ...checkboxProps })` — pass `checked` + `onChange`. The checkbox covers the whole switch, so a press anywhere on the track or thumb changes it; the focus ring is drawn on the track. `disabled` draws on and off in muted colours (and dims a ToggleRow's label) whatever wp-admin applies to checkboxes, with or without the takeover.
- `ToggleRow({ label, help?, ...toggleProps })` — a labelled full-width toggle row; `help` is a line under the label, outside it, so the label names the switch and the help is read once as its description (`aria-describedby`).
- `.cv-check` / `.cv-radio` (CSS) — a checkbox or radio in the package look: checked, mixed, focus and disabled states drawn by the package whatever wp-admin applies, disabled in the toggle's muted colours (grey off, light violet on). Under the takeover, bare checkboxes and radios in the mount take the same look, without the mixed mark; give a mixed checkbox the class.

**Overlays & empty**
- `Modal({ open, onClose, title?, size?: 'sm'|'md'|'lg'|'xl' = 'md', footer?, dismissible? = true, closeLabel? = 'Close', initialFocusRef?, container?, ...rest, children })` — a centred dialog for a short decision. It behaves like a Drawer (focus moves in and stays inside, the page does not scroll, focus returns on close, portal into the closest `.cv-root` above the wp-admin toolbar), so a Modal opened from a Drawer sits above it and Escape closes the Modal only. Closes on Escape and a scrim click when `dismissible`. The title names the dialog (`aria-labelledby`); without a title pass an `aria-label`. Other props go to the dialog element.
- `Drawer({ open, onClose, title, description?, size?: 'xs'|'sm'|'md'|'lg' = 'md', side?: 'end'|'start' = 'end', surface?: 'default'|'subtle' = 'default', footer?, closeLabel? = 'Close', initialFocusRef?, container?, ...rest, children })` — a side panel for editing or inspecting one item without leaving the list. See Drawer below. Keep `Modal` for short confirmations.
- `EmptyState({ icon?, title?, headingLevel? = 3, size?: 'compact', actions?, meta?, children })` — `children` is the description paragraph; `size="compact"` fits it inside a card.

**Tables & bulk (data screens)**
- `Table({ columns: [{ key, header, render?(row), className?, headerClassName?, sortable?, defaultSortDirection? }], rows, getRowId? = r => r.id, selectable?, selectedIds?, onToggleSelect?(id), selectLabel? = 'Select', onToggleSelectAll?(checked, ids), selectAllLabel? = 'Select all', sort?: { key, direction: 'asc'|'desc' }, onSortChange?(key, direction), renderRowExtra?(row, colSpan), emptyLabel?, ...rest })` — `render` returns the cell node; `renderRowExtra` adds a full-width row below (detail panels / inline forms). Selection and sort state are owned by the consumer. Headers show as written, in sentence case, in the muted header style (WP_List_Table headers under chrome.css too). The table sits in a `.cv-table-wrap` that scrolls sideways on narrow screens; while it scrolls and holds no control, Chrome and Firefox make it a Tab stop (the arrow keys scroll it), drawn with the focus ring.
  - **Sorting:** with `sort` and `onSortChange`, columns marked `sortable` get a button in their header (`.cv-sort`) and the sorted column carries `aria-sort`. A click on the sorted column flips the direction; another column starts at its `defaultSortDirection` (`'asc'`). The consumer sorts the rows or re-queries.
  - **Select all:** with `selectable` and `onToggleSelectAll`, the selection column's header holds a checkbox: checked when every row in `rows` is selected, mixed (indeterminate) when some are, disabled when there are no rows. `onToggleSelectAll( checked, ids )` gets what the reader asked for and the ids of the rows shown.
  - The selection checkboxes are `.cv-check` (checked, mixed, disabled and focus states drawn by the package, whatever wp-admin applies).
- `SearchBar({ value, onChange(value), onSubmit(value), onClear?, placeholder?, submitLabel?, clearLabel?, 'aria-label'?, ...rest })` — keep the typed value separate from the applied query (submit re-queries). The field shows a search icon, takes the room beside its buttons and is named by `aria-label` (or the placeholder).
- `BulkActionBar({ label, children, ...rest })` — the strip above a table when rows are selected; render it conditionally, pass action Buttons as children.

**Onboarding**
- `Stepper({ steps: [{ id, label }], activeId, doneLabel? = 'Completed', progressLabel?(number, total) = 'Step n of total', ...rest })` — numbered step rail with completed/active states. The active step carries `aria-current="step"`; a completed step shows a check icon and is announced with `doneLabel` (pass a translated string). Labels wrap before the rail outgrows its column. At 782px and below it becomes a progress track, one segment per step (done, current and upcoming in their colours), under the current step's label and `progressLabel` (pass a translated string, e.g. `( n, total ) => sprintf( __( 'Step %1$d of %2$d', 'myplugin' ), n, total )`); screen readers read the steps as on wide screens.
- `useServerOnboarding( path, { autoFetch? } )` — see below.

### Drawer

```jsx
const [ editing, setEditing ] = useState( null );
const nameRef = useRef( null );

<Drawer
    open={ !! editing }
    onClose={ () => setEditing( null ) }
    title={ __( 'Edit store', 'myplugin' ) }
    closeLabel={ __( 'Close', 'myplugin' ) }
    initialFocusRef={ nameRef }
    footer={ <>
        <Button variant="tertiary" onClick={ () => setEditing( null ) }>{ __( 'Cancel', 'myplugin' ) }</Button>
        <Button variant="primary" onClick={ save }>{ __( 'Save store', 'myplugin' ) }</Button>
    </> }
>
    <TextField ref={ nameRef } label={ __( 'Name', 'myplugin' ) } value={ draft.name } onChange={ … } />
</Drawer>
```

- Controlled: `onClose` runs on the close button, Escape and a click on the
  scrim; the consumer sets `open` to false.
- A modal dialog (`role="dialog"`, `aria-modal="true"`, labelled by the
  title). On open, focus moves to `initialFocusRef` or the close button; Tab
  and Shift+Tab stay inside, in the browser's own order (a radio group is one
  stop, and a scroll container with no control inside, such as a wide
  table's, is one too), wrapping round at the ends; the page behind does not
  scroll. On close, focus returns to the element that opened it.
- Renders through a portal into the closest `.cv-root` (or `container`), so
  tokens apply and no card or table clips it. The layer sits above the
  wp-admin toolbar.
- Drawers and Modals can open from inside a drawer; Escape closes the top
  one only. A control inside that handles Escape itself (an open Combobox
  list) keeps the drawer open.
- Slides in and out; without motion when the reader reduces motion.
- `side` is the edge it opens from: `'end'` (the right in a left-to-right
  page, the left in a right-to-left one) or `'start'`. `size="xs"` (320px)
  suits a navigation list.
- `description` is one line of context under the title (it also describes the dialog). `surface="subtle"` paints the body grey, for a body built from `FormSection variant="card"` cards. Header, body and footer share the same side gutter (16px at 782px and below); footer actions sit at the end. Other props go to the panel (`role="dialog"`).

### Toasts

```jsx
// Once, inside the mount:
render( <ToastProvider><App /></ToastProvider>, root );

// Where an action settles:
const toast = useToast();
toast.success( __( 'Store saved', 'myplugin' ) );
toast.critical( __( 'The import failed', 'myplugin' ), { description: __( 'Row 12 has no address.', 'myplugin' ) } );
const id = toast.show( { tone: 'info', title: __( 'Geocoding stores…', 'myplugin' ), duration: 0 } );
toast.show( { id, tone: 'success', title: __( 'Geocoded 40 stores', 'myplugin' ) } ); // replaces it
```

- Success, info and warning toasts are announced politely and leave after
  `duration` ms; the timer pauses while the pointer is over a toast or focus
  is inside it. Critical toasts are announced at once and stay until
  dismissed. `duration: 0` keeps any toast until dismissed.
- Both live regions are on the page before the first toast, so screen
  readers announce it. Past `max`, the oldest non-critical toast goes.
- `description` is an optional line under the title.
- The stack sits above drawers and modals, in the bottom corner on the end
  side of the reading direction (full width at 782px and below, with a 44px
  dismiss button). While a SaveBar or an open Drawer's footer is on screen
  the stack moves up by its height, so their buttons stay reachable.
  Toasts confirm what the reader
  just did; an error they must act on belongs in a `Banner` next to the thing
  that failed.
- `useToast()` outside a `ToastProvider` throws.

### Combobox

```jsx
<Combobox
    label={ __( 'Categories', 'myplugin' ) }
    multiple
    options={ categories.map( ( c ) => ( { value: c.id, label: c.name } ) ) }
    value={ draft.categoryIds }
    onChange={ ( ids ) => setDraft( { ...draft, categoryIds: ids } ) }
    noResultsLabel={ __( 'No categories match', 'myplugin' ) }
    removeLabel={ ( name ) => sprintf( __( 'Remove %s', 'myplugin' ), name ) }
/>
```

- Keyboard: Down / Up open the list and move through it (disabled options
  are skipped), Enter chooses (with `multiple` it toggles and keeps the list
  open), Escape closes the list and a second Escape clears the typed text,
  Backspace in an empty field removes the last chip. Focus stays in the
  field; the option under the keyboard is announced through
  `aria-activedescendant`.
- Typing filters by label. To search on the server, pass `filter={ false }`,
  `onInputChange( query )` and `loading`, and keep the chosen options in
  `options` so their chips keep their labels.
- Single: the field shows the chosen label and a clear button. "No matches"
  (or `loadingLabel`) is announced through a status region.
- At 782px and below the chip, clear and list buttons are 44px touch targets.

### Dropzone

```jsx
<Dropzone
    label={ __( 'Import stores from a CSV file', 'myplugin' ) }
    help={ __( 'CSV, up to 5 MB', 'myplugin' ) }
    buttonLabel={ __( 'Choose file', 'myplugin' ) }
    accept=".csv,text/csv"
    maxSize={ 5 * 1024 * 1024 }
    onFiles={ ( [ file ] ) => startImport( file ) }
    messages={ {
        type: ( file ) => sprintf( __( '%s is not a CSV file.', 'myplugin' ), file.name ),
        size: ( file ) => sprintf( __( '%s is larger than 5 MB.', 'myplugin' ), file.name ),
        count: () => __( 'Choose one file at a time.', 'myplugin' ),
    } }
/>
```

- `accept` uses the file input's syntax. Include the extension: browsers
  report some types inconsistently (a CSV often arrives as
  `application/vnd.ms-excel`).
- Rejected files go to `onReject( [ { file, reason: 'type'|'size'|'count', message } ] )`
  and their messages are listed under the button (`role="alert"`) until the
  next pick. More than one file without `multiple` rejects them all.
- The area highlights while a file is dragged over it (`is-active`). A drop
  anywhere on it is claimed, even when disabled, so the browser never leaves
  the screen to open the file.
- The button opens the file picker, for keyboard and pointer alike.

### Dates

`formatDate` shows a moment in the site's timezone and its date and time
formats (Settings > General) through WordPress's `wp.date`, so a screen
shows the dates the rest of wp-admin shows. It is its own entry point: it
needs the `@wordpress/date` external and the `wp-date` script, which the
rest of the package does not.

```js
// webpack.config.js, next to the other externals
'@wordpress/date': 'wp.date',
```

```php
// The bundle's script dependencies gain wp-date.
array( 'wp-element', 'wp-api-fetch', 'wp-i18n', 'wp-date' )
```

```js
import { formatDate } from '@carticy/admin-ui/date';

formatDate( order.created );             // "November 21, 2026 10:18 pm"
formatDate( order.created, 'date' );     // "November 21, 2026"
formatDate( order.created, 'time' );     // "10:18 pm"
formatDate( order.created, 'relative' ); // "2 minutes ago", "3 hours from now"
formatDate( report.day, 'date' );        // a Y-m-d calendar day, shown as that day
formatDate( order.paidAt ) || __( 'Not paid yet', 'myplugin' );
```

- The value is a `Date`, a timestamp in milliseconds (PHP's seconds times
  1000), or an ISO 8601 date and time with its zone (`2026-11-21T16:48:00Z`,
  `2026-11-21T17:48:00+01:00`; PHP's `gmdate( 'c' )`, or a REST `date_gmt`
  with `Z` appended).
- A `Y-m-d` calendar day (a report's day, already in the site's terms)
  takes the `'date'` style only and is shown without a timezone shift.
- `null`, `undefined` and `''` give `''`, so the screen words a missing date
  itself. A string without a zone, a calendar day with another style and an
  unknown style throw a `RangeError`: a date is never read in the browser's
  own zone.
- Month names, formats and the relative words come from WordPress in the
  reader's admin language; there are no strings to pass.

### Strings

Components ship neutral English defaults for their screen-reader and helper
strings (`closeLabel`, `dismissLabel`, `pageLabel`, `noResultsLabel`,
`requiredLabel`, `doneLabel`, `progressLabel`, `messages`…). Pass translated
strings from your plugin's text domain.

---

## Scoping doctrine (load-bearing)

Every token and global reset is scoped to `.cv-root` — never `:root`, never
`html`/`body` — so multiple Carticy plugins (and versions of this package) can
coexist on one wp-admin without leaking styles. Therefore:

1. Add `cv-root` to your mount element (via `adminUiRoot`).
2. Enqueue the CSS under **plugin-unique handles** with **your** version
   string, never a shared handle.

Violet (`--cv-accent`) is the brand accent, used sparingly (active tab,
toggle-on, focus ring) — never a button background. Primary actions
are near-black. Our components use inline SVG / Lucide-style icons (24 viewBox,
stroke 2, `currentColor`, `aria-hidden` when decorative); WP-native shells
(`.notice`, PHP modals) keep dashicons — that hybrid is deliberate. Never use a
text or HTML symbol as an icon (a check mark, a cross, an arrow, a bullet or an
ellipsis character): draw it as SVG.

---

## Onboarding framework

`useServerOnboarding( '/myplugin/v1/onboarding' )` is a server-validated
state machine client: the server owns the state string and validates every transition; the
client renders the current state and requests transitions (`transition(
'step2_viewing', payload )`). A "complete" state must mean the server verified
it — never client-side click-through progress.

It returns `{ state, data, loading, submitting, error, refetch(), transition( to, payload? ) }`
and fetches on mount unless `autoFetch` is false. The plugin's REST routes:

- `GET {path}` returns `{ state, …context }`.
- `POST {path}/transition` takes `{ to, …payload }` and returns
  `{ state, …context }`, or a 4xx `{ message }` for a transition it refuses;
  `transition()` then rejects and `error` holds the response.

---

## Common pitfalls

1. **No externals → broken React.** If `@wordpress/element` isn't an external,
   webpack bundles a second React and you get "invalid hook call" / a blank
   mount. The three externals in the Build setup are mandatory.
2. **Wrong preset-react runtime.** Use `runtime: 'classic'` +
   `pragma: 'wp.element.createElement'`. The automatic runtime emits
   `import 'react'`, which isn't installed.
3. **Missing script deps.** Register the bundle with
   `array( 'wp-element', 'wp-api-fetch', 'wp-i18n' )` so the globals exist
   before it runs, plus `wp-date` (and the `@wordpress/date` external) when
   it imports `@carticy/admin-ui/date`; without it `formatDate` throws.
4. **CSS order / shared handles.** Enqueue tokens → components → chrome, each
   with a plugin-unique handle and your plugin version.
5. **Forgot `cv-root`.** Nothing is styled unless the mount has `cv-root`
   (use `adminUiRoot`). Add `cv-root--app` + the `cv-takeover` body class for
   the full-bleed takeover.
6. **Shipping `vendor/` to WP.org.** Bundle the compiled CSS into
   `assets/css/dist/` and enqueue that; never ship `vendor/carticy/admin-ui`.
7. **Updating the package.** Edit the canonical source, push to
   `github.com/carticy-dev/admin-ui`, then `composer update carticy/admin-ui`
   in each plugin. Never hand-edit `vendor/carticy/admin-ui` in a plugin — the
   next `composer install` reverts it.

---

## Source of truth

This repo is the canonical copy for all Carticy plugins. Product-specific
mappings (backend slugs → labels) stay in each product; only the visual system
and the shared components live here. Change it here, push to GitHub and
update each plugin with Composer (see Common pitfalls).
