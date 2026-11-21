# Embed integrations

An embed integration displays Framework artifacts on another platform. It can be a CMS plugin for a system such as Ghost, an extension for a static site generator such as Hugo, Jekyll, or Quarto, or a custom renderer. [Embedding artifacts](embedding.md) covers the existing plugins and snippets.

The open-source [framework-embed](https://github.com/frameworktools/framework-embed) repository holds the reference renderers and the full contract:

| File | Contents |
|---|---|
| [`SPEC.md`](https://github.com/frameworktools/framework-embed/blob/main/SPEC.md) | The `framework.embed/v1` specification: API, embed document, markup for each kind, number formatting, chart safety, allowed hosts, artifact URLs, and theming |
| [`spec/embed.schema.json`](https://github.com/frameworktools/framework-embed/blob/main/spec/embed.schema.json) | JSON Schema for embed documents |
| [`spec/fixtures/`](https://github.com/frameworktools/framework-embed/tree/main/spec/fixtures) | Embed documents and the exact HTML each must render to |
| [`js/README.md`](https://github.com/frameworktools/framework-embed/blob/main/js/README.md) and [`js/index.d.ts`](https://github.com/frameworktools/framework-embed/blob/main/js/index.d.ts) | JavaScript API |
| `wordpress/`, `drupal/`, `statamic/` | Reference integrations built on the PHP renderer |

[Artifact representation](artifact-representation.md) describes the `data` field of model, table, test, and code snippet documents.

## Integration paths

| Path | Renders | Suited to |
|---|---|---|
| `<framework-artifact>` web component | In the browser, from a stored document or a live fetch | Static sites, Quarto websites, and hand-written HTML |
| JavaScript API: `fetchEmbed`, `render`, `mount` | In the browser or in Node during a build | Single-page apps, editors, and static site build steps |
| PHP `Client` and `Renderer` | On the server | PHP content systems |
| Custom renderer | Anywhere | Other languages, or output other than HTML |

The JavaScript and PHP renderers return identical HTML for the same embed document. A static site build can call `render()` in Node, write the HTML into the page, and load the stylesheet and script on the published site.

A custom renderer reads the embed document and produces its own output. It must follow the escaping, URL, and chart rules in `SPEC.md`. It may produce different markup, but the stylesheet and the browser script apply only to the documented markup.

## Package sources

The packages are not yet on npm, Packagist, or drupal.org. Each [release](https://github.com/frameworktools/framework-embed/releases) attaches these files:

| File | Contents |
|---|---|
| `framework-embed.css` | Stylesheet |
| `framework-embed.iife.js` | Script-tag build. It sets `window.FrameworkEmbed`, applies `window.FrameworkEmbedConfig`, registers `<framework-artifact>`, prepares disclosures for printing, and sets up charts, variable tips, and code snippet controls on load. |
| `framework-embed.js` | ES module with the same exports. Importing it registers no custom element and adds no event listeners. |
| `framework-embed-<version>.zip` | WordPress plugin |

The repository also commits these three built files under `php/assets/`. jsDelivr serves them at a pinned tag, such as `https://cdn.jsdelivr.net/gh/frameworktools/framework-embed@v0.8.4/php/assets/framework-embed.js`. GitHub Pages serves the latest release under unversioned names at `https://frameworktools.github.io/framework-embed/`. The **HTML** snippet in the **Embed** tab loads its files from there.

PHP projects can install `framework-cloud/embed` from a release archive with a Composer package repository:

```json title="composer.json"
{
    "repositories": [
        {
            "type": "package",
            "package": {
                "name": "framework-cloud/embed",
                "version": "0.8.4",
                "dist": {"type": "zip", "url": "https://github.com/frameworktools/framework-embed/archive/refs/tags/v0.8.4.zip"},
                "autoload": {"psr-4": {"Framework\\Embed\\": "php/src/"}}
            }
        }
    ],
    "require": {"framework-cloud/embed": "0.8.4"}
}
```

A clone used as a Composer path repository also works, as the Drupal and Statamic READMEs show. Without Composer, copy the `php/src` and `php/assets` directories side by side and autoload the `Framework\Embed` namespace from `src`. `Assets::css()`, `Assets::script()`, and `Assets::module()` return the paths of the bundled files in `assets`. `Assets::version()` returns a hash of their contents for cache busting.

## Discovery

`GET /.well-known/framework.json` lists a server's embed endpoints:

```json title="Discovery document"
{"framework": {"embed": {
  "version": 1,
  "resolve": "https://framework.pub/api/embed/v1/resolve",
  "artifact": "https://framework.pub/api/embed/v1/artifacts/{key}",
  "oembed": "https://framework.pub/api/oembed"
}}}
```

Reader pages of embeddable artifacts link to the embed document and the oEmbed response for their version:

```html title="Reader page head"
<link rel="alternate" type="application/json+oembed" href="https://framework.pub/api/oembed?url=…" title="…">
<link rel="alternate" type="application/vnd.framework.embed+json" href="https://framework.pub/api/embed/v1/artifacts/{key}/versions/{n}">
```

oEmbed responses also carry `framework_embed_url`, the version's embed document URL. A consumer can recognize pasted artifact links by their paths and call `resolve` directly. `matchArtifactUrl` and `embedRequestUrl` in JavaScript and `UrlMatcher` in PHP implement the path rules in "Artifact URLs" in `SPEC.md`.

## Embed API

All routes are public and stateless, set no cookies, and respond with `Access-Control-Allow-Origin: *`.

| Route | Response |
|---|---|
| `GET /api/embed/v1/resolve?url={artifact link}` | Embed document for any artifact URL on this server |
| `GET /api/embed/v1/artifacts/{key}` | Embed document for the newest version |
| `GET /api/embed/v1/artifacts/{key}/versions/{n}` | Embed document for version `n` |
| `GET /api/embed/v1/artifacts/{key}/versions/{n}/image` | Figure file for an image artifact |
| `GET /api/oembed?url={artifact link}&format=json` | oEmbed 1.0 `photo` or `link` response |
| `GET /.well-known/framework.json` | Discovery document |

Artifacts that cannot be embedded and artifacts that do not exist return the same `404` response with `Cache-Control: no-store`. Embed documents for a named version send `Cache-Control: public, max-age=3600`. Embed documents for the newest version send `Cache-Control: public, max-age=300`. Resolve and oEmbed responses use `max-age=3600` when the `url` parameter names a version and `max-age=300` otherwise. On framework.pub, the image route answers with a redirect that sends `max-age=300`, so a takedown reaches cached redirects within five minutes. Each IP address can make up to 300 requests per minute across all embed routes.

Version URLs and `image.url` remain stable for the life of the artifact. On framework.pub, `image.url` redirects to a short-lived signed URL on `content.framework.pub`. Store and reference `image.url` itself. The signed redirect target expires.

For a code snippet, `?output=none` on a pasted link or an artifact route sets `data.display.output` to `false`, so the output starts hidden. The embed document's `url` and `embed_url` keep the option, as does `framework_embed_url` in oEmbed responses. Other kinds ignore it.

Self-hosted Framework Cloud servers serve the same API at their own domain.

## Embed document

An embed document uses the `framework.embed/v1` schema:

```json
{
  "schema": "framework.embed/v1",
  "key": "rglmlogit0000000",
  "version": 1,
  "latest_version": 1,
  "kind": "model",
  "title": "Transmission model",
  "description": null,
  "alt": "Logistic regression of am with 3 terms and odds ratios.",
  "url": "https://framework.pub/@ada/admissions/transmission/versions/1",
  "embed_url": "https://framework.pub/api/embed/v1/artifacts/rglmlogit0000000/versions/1",
  "published_at": "2026-11-16T12:00:00Z",
  "author": {"name": "Ada Example", "url": "https://framework.pub/@ada"},
  "provider": {"name": "Framework.pub", "url": "https://framework.pub"},
  "attribution": {"text": "Framework.pub", "url": "https://framework.pub/@ada/admissions/transmission/versions/1"},
  "data": {"schema": "framework.artifact/v1", "kind": "model", "...": "..."},
  "spec": null,
  "image": null
}
```

| Field | Contents |
|---|---|
| `kind` | `model`, `table`, `test`, `code_snippet`, `dag`, `image`, `vega-lite`, or `page`. Renderers treat unknown values as `page`. |
| `data` | The `framework.artifact/v1` representation for `model`, `table`, `test`, `code_snippet`, and `dag`. See [Artifact representation](artifact-representation.md). |
| `spec` | Vega-Lite specification for `vega-lite`. Only charts with inline data embed as `vega-lite`. Charts that load external URLs for data, images, or links embed as `page`. A figure published with a phiz plot description embeds its compiled chart, without a background, colors, or fonts for the page's text and axes. |
| `image` | Object with `url`, `content_type`, `width`, and `height` for `image`. A `vega-lite` document of a figure published with a plot description also has `image`, the figure itself, as its fallback. Otherwise `image` is `null` for `vega-lite`. `width` and `height` may be `null`. |
| `key`, `version`, `latest_version` | The artifact's key, this document's version number, and the newest version number. A stored integration can compare `version` with `latest_version` to offer an update. |
| `alt` | One-sentence text description for screen readers and image alt text. |
| `url` | Reader page URL for this version. |
| `embed_url` | This document's own URL, pinned to its version. |
| `author`, `description` | Optional metadata, or `null`. `author` appears only when the owner's profile is public. |
| `provider` | `name` and `url` of the server. The name is set in **Server administration > Sharing** or with `FW_SHARE_NAME`, and is the server's host by default. |
| `attribution` | `text` and `url` for the credit link. `text` is the server's share name, and `url` is the version's reader page. |

The `page` kind covers HTML documents, Quarto sites, PDFs, data files, and other outputs. Renderers display `page` documents as link cards showing the title, description, author, and attribution link.

Existing `framework.embed/v1` fields remain compatible across updates, and new optional fields may appear within `v1`. Renderers ignore unknown fields.

## Markup

Every renderer output is one `<figure class="fw-embed fw-embed--{kind}">`. "Markup by kind" in `SPEC.md` specifies the rest. Three parts need the browser script for full behavior.

**Variable tips.** A coefficient term mapped in `data.terms`, or a data table column header with a label or summary, renders as a button followed immediately by its popover:

```html title="Variable tip"
<th scope="row"><button type="button" class="fw-embed__term" popovertarget="fw-embed-tip-{key}-{version}-{n}">speciesChinstrap</button><span class="fw-embed__tip" id="fw-embed-tip-{key}-{version}-{n}" popover=""><span class="fw-embed__tip-block"><span class="fw-embed__tip-name">species = Chinstrap</span><span class="fw-embed__tip-label">Penguin species</span><span class="fw-embed__tip-detail">Compared with Adelie. 68 of 342 rows.</span></span></span></th>
```

The `popover` attribute opens the tip on click or tap without JavaScript. `enhanceTips` adds hover and placement, and finds each tip as the button's next element sibling, so keep the two adjacent. "Variable tips" in `SPEC.md` gives the tip text rules.

**Charts.** A `vega-lite` document renders as `<div class="fw-embed__chart" data-fw-embed-chart>` holding the specification in `<script type="application/json">` and a fallback paragraph with a link. `enhanceCharts` replaces the fallback with the chart.

**Code snippets.** A `code_snippet` document renders as `<div class="fw-embed__snippet">`: a header, code in `<pre class="fw-embed__code">`, and each block of output, messages, warnings, or errors in `<div class="fw-embed__block">`. Framework Cloud sends styled text as `[classes, text]` segments, and the renderers wrap styled segments of code and output in `<span>` elements with `fw-t-` classes. Snippets with non-code blocks include an output toggle. The **Copy** button and the toggle ship with the `hidden` attribute. `enhanceCopy` and `enhanceOutput` show them and handle clicks. When the output starts hidden, each `.fw-embed__block` also carries `hidden`, and only the toggle shows it.

**DAGs.** A `dag` document renders as `<div class="fw-embed__dag">` holding an inline SVG drawn from the layout Framework Cloud sends, and a legend of the roles present. An **Analysis** disclosure lists adjustment sets and testable implications, and a **Code** disclosure holds the DAGitty text and the code that built the graph, each in a `.fw-embed__snippet` panel with a **Copy** button that `enhanceCopy` shows.

**Stored documents.** `<framework-artifact>` renders a document from a direct child `<script type="application/json">` without a network request:

```html title="Stored document"
<framework-artifact src="https://framework.pub/d/KEY/versions/1">
  <script type="application/json">{"schema":"framework.embed/v1", …}</script>
</framework-artifact>
```

Write the JSON with `<`, `>`, `&`, U+2028, and U+2029 escaped as `\u003c`, `\u003e`, `\u0026`, `\u2028`, and `\u2029`, so artifact text cannot close the `<script>` element. `scriptJson()` in the JavaScript package does this.

## Page setup

The script-tag build sets up a page by itself. Set `window.FrameworkEmbedConfig` before `framework-embed.iife.js` runs. The script applies that configuration, registers `<framework-artifact>`, prepares disclosures for printing, and calls `enhanceCharts`, `enhanceTips`, `enhanceCopy`, and `enhanceOutput` on `document` once the document is parsed. Server-rendered markup that is in the page at load needs nothing more.

A server-rendered integration can load the script only when the rendered HTML contains `data-fw-embed-chart`, `fw-embed__term`, or `fw-embed__snippet`. Without the script, browsers that support `::details-content` still open disclosures for printing.

Markup inserted later needs four calls on the element that contains it, after the element is in the document. Examples are a single-page app's route change, an editor preview, and a block rendered in a CMS editor.

```js
FrameworkEmbed.enhanceCharts(container);
FrameworkEmbed.enhanceTips(container);
FrameworkEmbed.enhanceCopy(container);
FrameworkEmbed.enhanceOutput(container);
```

`mount(element, doc)` inserts the markup and makes all four calls. `<framework-artifact>` uses `mount`.

A page that imports the ES module calls the setup functions directly:

```js title="ES module"
import { configure, defineElement, enhanceCharts, enhanceCopy, enhanceOutput, enhanceTips, expandDetailsForPrint } from './framework-embed.js';

configure({ hosts: ['framework.pub', 'framework.example.org'] });
defineElement();          // only if the page uses <framework-artifact>
expandDetailsForPrint();
enhanceCharts(document);
enhanceTips(document);
enhanceCopy(document);
enhanceOutput(document);
```

| Function | Effect |
|---|---|
| `configure({hosts, vegaScripts, vegaEmbed, vegaInterpreter, vegaOptions})` | Sets allowed hosts and chart loading for later calls. `hosts` replaces the default `["framework.pub"]`. `vegaScripts` lists the `vega`, `vega-lite`, and `vega-embed` scripts as URLs or `{src, integrity}` objects. `vegaEmbed` supplies the `vega-embed` function directly. `vegaInterpreter` replaces the Vega expression interpreter. `vegaOptions` adds `vega-embed` options, and the chart safety options override them. |
| `enhanceCharts(root, {lazy})` | Draws each chart under `root` that has not been drawn, as it comes within 200 pixels of the viewport. `{lazy: false}` draws them at once. The first chart loads the Vega scripts. A chart that fails keeps its fallback link. |
| `enhanceTips(root)` | Renumbers tip ids under `root` that repeat an id elsewhere in the document, which happens when one artifact is embedded more than once. The first call adds delegated listeners on `document` and `window` for mouse hover, click to keep a tip open, placement beside the button, scrolling, and resizing. Call it after the markup is in the document. |
| `enhanceCopy(root)` | Shows the **Copy** button on each code snippet under `root`. A click copies the snippet's code blocks, joined with newlines, without output. The Clipboard API requires a secure context: HTTPS or `localhost`. |
| `enhanceOutput(root)` | Shows the output toggle on each code snippet under `root` that has non-code blocks. A click hides or shows its output, messages, warnings, and errors, and switches the label between **Hide output** and **Show output**. |
| `expandDetailsForPrint()` | Opens closed **Model details** and **Test details** disclosures and folded snippet output before printing and closes them afterward. Later calls do nothing. |
| `defineElement(name)` | Registers `<framework-artifact>`, or another tag name. |

`<framework-artifact>` sets `data-fw-embed-state` to `loading`, `ready`, `gone`, or `error`. It dispatches `framework-embed:load` with `detail.doc` and `framework-embed:error` with `detail.error`.

Without JavaScript, embeds still show their results. Tips open on click or tap through the `popover` attribute, the scale switch works through CSS, and disclosures open on click. Charts show their fallback link, and tips do not open on hover. Code snippets show no copy button or output toggle, and output that starts hidden stays hidden.

## HTML sanitizers

The renderers escape all artifact text, and their output contains no event handlers or `javascript:` URLs. The simplest approach keeps renderer output out of the host's user-content sanitizer. The host sanitizes the author's own content, stores the embed document or artifact URL separately, and inserts the rendered HTML at display time. The WordPress, Drupal, and Statamic plugins work this way.

A host that must pass the HTML through a sanitizer needs to allow these elements and attributes:

| Element | Attributes |
|---|---|
| `figure` | `class`, `data-fw-embed-kind`, `data-fw-embed-key`, `data-fw-embed-version` |
| `figcaption`, `p`, `dl`, `details`, `table`, `code`, `pre` | `class` |
| `div` | `class`, `role`, `aria-label`, `tabindex`, `data-fw-embed-chart`, `hidden` |
| `span` | `class`, `id`, `popover` |
| `a` | `class`, `href`, `target`, `rel` |
| `button` | `type`, `class`, `popovertarget`, `data-fw-copy`, `data-fw-output`, `hidden` |
| `form` | `class` |
| `label`, `summary`, `caption`, `thead`, `tbody`, `tr`, `dt` | none |
| `input` | `type="radio"`, `name`, `value`, `checked` |
| `th` | `scope`, `class` |
| `td`, `dd` | `class`, `title` |
| `img` | `class`, `src`, `alt`, `width`, `height`, `loading`, `decoding`, `style` |
| `script` | `type="application/json"`, only inside `div[data-fw-embed-chart]` |

A sanitizer that filters values can rely on these patterns. Every class name starts with `fw-embed` or, for styled code and output in code snippets, `fw-t-`. Every tip `id` and `popovertarget` starts with `fw-embed-tip-`. `href` and `src` use `http` or `https`. The `style` attribute on `img` sets only `max-width`.

Removing an element or attribute changes the embed:

| Removed | Effect |
|---|---|
| `popover` on `span.fw-embed__tip` | The tip text appears inline after the term instead of in a popup. Remove the whole tip span or keep the attribute. |
| `popovertarget` or `id` | Tips no longer open. |
| `form` while keeping `input` | Every embed on the page shares one radio group, so switching the scale in one embed clears the switch in the others. |
| `input` | The scale switch disappears, and only the ratio scale table shows. |
| `script[type=application/json]` or `data-fw-embed-chart` | The chart never draws, and its fallback link remains. |
| `title` | Rounded values lose their full value on hover. |
| `data-fw-copy` or `data-fw-output` | The copy button or output toggle stays hidden. |
| `hidden` on a snippet button | The button shows before the script runs, and does nothing without it. |
| `hidden` on a snippet output block | Output requested hidden with `?output=none` shows. |
| `style` on `img` | Tall SVG figures fill the width instead of narrowing to fit 80% of the viewport height. |
| `class` | Embeds lose all styling, both scale tables show at once, and tips, snippet buttons, and print expansion stop working. |
| `role`, `aria-label`, `tabindex` | Scrolling tables lose keyboard focus and their labels, and the scale switch loses its group label. |

## Content Security Policy

The renderers and the browser script need no `unsafe-eval` and no inline event handlers. Charts evaluate Vega expressions with Vega's interpreter, which the pinned `vega-embed` 7.1.0 build includes.

| Directive | Sources |
|---|---|
| `script-src` | The origin that serves `framework-embed.iife.js` or `framework-embed.js`. On pages with charts, `https://cdn.jsdelivr.net` unless `configure({vegaScripts})` points at self-hosted copies. |
| `connect-src` | Each allowed Framework Cloud host, for pages that fetch documents in the browser. |
| `img-src` | Each allowed host. On framework.pub, also `https://content.framework.pub`, where figure URLs redirect. |
| `font-src` | `https://cdn.jsdelivr.net`, unless the page sets `--fw-embed-mono`. Then the font is not downloaded. |
| `style-src` | The origin that serves `framework-embed.css`. |

`window.FrameworkEmbedConfig` is usually set in an inline script. Under a policy without `unsafe-inline` scripts, call `configure()` from an external script or module instead. Stored documents and chart specifications in `<script type="application/json">` are data blocks, which CSP does not block.

Under a `style-src` without `unsafe-inline`, the browser ignores the `style` attribute on SVG figures. `vega-embed` also adds a `<style>` element for its action menu, which embeds turn off. Pass `configure({vegaOptions: {defaultStyle: false}})` to skip that element. Tip placement sets styles through the DOM, which CSP allows.

## Theming

Embeds take the host page's font and text color. Custom properties on `.fw-embed` or any ancestor adjust the rest:

```css
.post-body {
  --fw-embed-mono: ui-monospace, Menlo, monospace;
  --fw-embed-accent: #9b2c2c;
  --fw-embed-tip-bg: #2b2118;
  --fw-embed-tip-fg: #fff8ef;
  --fw-embed-warning: #a16207;
}
```

| Property | Used for |
|---|---|
| `--fw-embed-font` | Labels, headers, prose, and tips. Unset by default, so the page's font applies. |
| `--fw-embed-mono` | Numbers, formulas, the outcome variable, and tip headings. JetBrains Mono by default. |
| `--fw-embed-code` | Code and output in code snippets. System monospace fonts by default, so box-drawing characters in Stata output line up. |
| `--fw-embed-fg` | Embed text. Unset by default, so the page's text color applies. |
| `--fw-embed-muted` | Table headers, notes, statistic labels, and chart axis labels. |
| `--fw-embed-border` | Header rules, the scale switch, code snippet panels, link cards, and chart gridlines. |
| `--fw-embed-rule` | Rules between table rows and between statistics. |
| `--fw-embed-bg` | Background of the `<figure class="fw-embed">` wrapper and the scale switch. Transparent by default. Image artifacts keep a white panel. |
| `--fw-embed-accent` | Links and focus outlines. |
| `--fw-embed-warning` | Sampling warnings under a Bayesian model's coefficients. |
| `--fw-embed-tip-bg`, `--fw-embed-tip-fg` | Variable tip background and text. Dark in light and dark themes by default. |

The stylesheet switches the accent and warning colors to dark mode under `prefers-color-scheme: dark`, a `data-theme="dark"` ancestor, or a `.dark` ancestor. `data-theme="light"` or `.light` keeps light colors. Charts detect the theme from the same markers and redraw when the theme changes. "Theming" and "Chart display" in `SPEC.md` give the defaults and rules.

## Caching and takedown

Consumers either store embed documents with their content or fetch them through a short-lived cache.

| Tier | Behavior |
|---|---|
| Stored | Save the embed document with the page when the author inserts it, and render from that saved copy. The document's `embed_url` pins its version. Refresh the copy only when the author requests it. Render figures from `image.url` without copying image files. |
| Live | Fetch the embed document in the background so network requests do not block page rendering. Show a plain link until the first copy arrives. Cache the document up to its `Cache-Control` lifetime, and keep rendering an expired copy while it refreshes. |

Drop a copy when the server returns `404` or `410`, and render a plain link in its place. Keep existing copies when a request times out or returns `429` or `5xx`. A stored copy can also be checked on a schedule. The CMS plugins check each stored copy once a day and remove it only on `404` or `410`.

A static site build is a stored consumer. It fetches each document at build time, writes it into the page in `<framework-artifact>` or as rendered HTML, and refreshes only on a deliberate rebuild.

The PHP `Client` fetches synchronously. It caches documents for the `max-age` of the response, capped by its `maxTtl` argument. It returns `null` and deletes the cached copy on `404` or `410`, and throws `EmbedException` for hosts that are not allowed, other HTTP errors, and invalid documents. Live integrations call it from a queue or a post-response callback. It accepts any cache that implements `Framework\Embed\Cache`, and `Psr16Cache` adapts a PSR-16 store.

## Allowed hosts

A consumer fetches embed documents only from allowed hosts: framework.pub by default, plus self-hosted Framework Cloud servers an administrator adds. Check the host of every pasted URL before sending a request.

An entry is a bare host name, such as `framework.example.org`, which allows HTTPS on the default port. An origin, such as `http://localhost:8000`, allows exactly that scheme, host, and port. IP addresses, user info, and percent-encoded hosts never match. Embed requests send no cookies and follow no redirects. "Allowed hosts" in `SPEC.md` gives the exact matching rules.

In JavaScript, `configure({hosts})` and `window.FrameworkEmbedConfig.hosts` replace the default list. In PHP, the `hosts` argument of `Client` replaces it. Include `framework.pub` in either list to keep it. A CMS integration should offer a settings field for additional hosts, as the reference plugins do.

## Security

The reference renderers follow these rules. A custom renderer must follow them too.

- Escape all artifact text before inserting it into HTML.
- Use a URL in `href` or `src` only when it passes the "Safe URLs" rule in `SPEC.md`.
- Render SVG figures inside `<img>` elements.
- Escape JSON written into `<script type="application/json">` as described under [Markup](#markup).
- Load `vega-embed` from self-hosted files or a pinned CDN version with subresource integrity.
- Follow "Chart safety" in `SPEC.md`: skip specifications that reference URLs, block Vega's loader, evaluate expressions with the interpreter, and strip `usermeta`.

## Conformance tests

A renderer that produces the documented markup can be checked against the shared test data in the framework-embed repository:

| File | Check |
|---|---|
| `spec/fixtures/{name}.json` and `{name}.html` | Rendering the JSON document produces the HTML file byte for byte, including the final newline. |
| `spec/fixtures/hostile-*.json` | Script, markup, and URL injection attempts render as escaped text or are left out. |
| `spec/format-cases.json` | Each case formats `value` as `expected` under its `format`: `number` and `decimal` for data table cells, `result` and `result-decimal` for model and test results, `p` for p-values, and `percent` for confidence levels. The `decimal` forms apply to columns that contain any non-whole number. |
| `spec/model-names.json` | Each `model` object produces the `meta` line. |
| `spec/embed.schema.json` | Every fixture document except `hostile-image.json` validates. |

`js/test/fixtures.test.js` and `php/tests/FixturesTest.php` run the fixture and number formatting checks. `js/test/security.test.js` and `php/tests/SecurityTest.php` run the hostile fixtures. `js/test/views.test.js` and `php/tests/FixturesTest.php` run the model names, and `js/test/schema.test.js` validates the fixtures. A custom renderer that produces different markup can still use the fixtures as input and `format-cases.json` for its numbers.
