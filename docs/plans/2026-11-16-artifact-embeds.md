# Artifact embeds

Framework artifacts appear in other sites' pages without frames from framework.pub. A server sends data. The site renders it with an open renderer: a CSS and JS bundle, a PHP library, or a CMS plugin built on them. academic.page uses the same public contract as WordPress, Drupal, Statamic, and any other site. Nothing in this contract is specific to one consumer, and no account or key is needed to embed a public or unlisted artifact.

## Pieces

| Piece | Where | Purpose |
|---|---|---|
| Embed API | Framework Cloud | Version-pinned embed JSON, images, oEmbed, discovery |
| `framework-embed` repository | `~/Projects/framework-embed` | Specification, fixtures, renderers, CMS plugins |
| JS and CSS bundle | `framework-embed/js` | Renderer, `<framework-artifact>` web component, stylesheet |
| PHP core | `framework-embed/php` | Client and server-side renderer for PHP sites |
| Plugins | `framework-embed/wordpress`, `drupal`, `statamic` | Paste a URL or insert a block; renders through the PHP core |
| academic.page | its own repository | A generic `embed` Tiptap node that renders through the same contract |

## Embed API (Framework Cloud)

All routes are public, cookie-free, and stateless. They answer with `Access-Control-Allow-Origin: *` and never `Access-Control-Allow-Credentials`. An artifact is embeddable when it is a cloud document whose visibility is `public` or `unlisted` and has no password. Every other case (private, password-protected, missing, deleted, external driver) gets the same `404`, so the endpoints never reveal that a private key exists.

| Route | Answer |
|---|---|
| `GET /api/embed/v1/resolve?url=<artifact URL>` | The embed document for a pasted URL: `/d/{key}`, `/d/{key}/versions/{n}`, `/@{handle}/{project}/{slug}`, and its `/versions/{n}` form. The URL's host must be this server. |
| `GET /api/embed/v1/artifacts/{key}` | The embed document for the newest version. |
| `GET /api/embed/v1/artifacts/{key}/versions/{n}` | The embed document for version `n`. |
| `GET /api/embed/v1/artifacts/{key}/versions/{n}/image` | The figure file (SVG, PNG, JPEG, GIF, WebP) for image artifacts. |
| `GET /api/oembed?url=…&format=json&maxwidth=&maxheight=` | oEmbed 1.0. `photo` for images, `link` for everything else. Never `rich` or `video`: there is no iframe. `format=xml` gets `501`. |
| `GET /.well-known/framework.json` | Discovery: `{"framework": {"embed": {"version": 1, "resolve": "<abs URL>", "artifact": "<abs URL template>", "oembed": "<abs URL>"}}}` |

Document pages that are embeddable carry discovery links in `<head>`:

```html
<link rel="alternate" type="application/json+oembed" href="https://framework.pub/api/oembed?url=…" title="…">
<link rel="alternate" type="application/vnd.framework.embed+json" href="https://framework.pub/api/embed/v1/artifacts/{key}/versions/{n}">
```

Caching: version URLs send `Cache-Control: public, max-age=3600`. The newest-version and resolve URLs send `public, max-age=300`. A takedown (delete, make private, add a password) reaches live consumers and shared caches within an hour. Stored copies are covered under Consumer tiers. Rate limit: 300 requests a minute per IP across the embed routes.

Stability: version URLs (`embed_url`, `/versions/{n}`) and `image.url` stay the same for the life of the artifact. `framework.embed/v1` stays renderable indefinitely. Fields are only added.

Images are sent with `Cross-Origin-Resource-Policy: cross-origin`, `X-Content-Type-Options: nosniff`, and `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox`, so an SVG opened directly runs nothing. Renderers show SVG only through `<img>`.

Where the content Worker serves files (framework.pub), the image URL answers `302` with `Cache-Control: public, max-age=300` to a signed Worker link in embed mode (claim `c: "embed"`, `ContentWorker::embedImage`). The app never relays the bytes, and a takedown reaches the redirect within five minutes. Embed links expire at the end of the next whole hour, one to two hours away, so every redirect within an hour names the same link and browsers reuse it. In embed mode the Worker sends `Access-Control-Allow-Origin: *`, `Cross-Origin-Resource-Policy: cross-origin`, `X-Content-Type-Options: nosniff`, `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox`, `Referrer-Policy: no-referrer`, and `Cache-Control: public` with a `max-age` no longer than the link's remaining life. It sends no `Content-Disposition` and no `frame-ancestors`. The image URL in the embed document stays the stable `/api/embed/...` URL.

Implementation notes:

- The `404` body is `{"message": "Not found."}` with `Cache-Control: no-store`, the same for every case above. A path that matches no route (a malformed key or version) gets the framework's usual `404`.
- `url` and `attribution.url` always name the version's page, also on the newest-version and resolve answers: the readable link when the artifact is public and its owner has a handle, else `/d/{key}/versions/{n}`.
- The discovery `artifact` template is `https://…/api/embed/v1/artifacts/{key}`. Append `/versions/{n}` for a version.
- oEmbed: any `format` other than `json` gets `501`. `photo` needs a known width and height. A figure of unknown size answers `link` without a thumbnail, since oEmbed requires `thumbnail_width` and `thumbnail_height` with `thumbnail_url`. `maxwidth` and `maxheight` scale the reported `width` and `height`, keeping the proportions. `author_name` and `author_url` are left out when the owner's profile isn't public. `framework_embed_url` carries the version's embed URL.
- The page's oEmbed discovery link names the version's page URL, so it resolves to that version.
- `alt` for a model counts the rows of the coefficient table, intercept included, as terms.
- The rate limit is the named limiter `embed` (`throttle:embed`), keyed by IP, shared by every embed route and `/.well-known/framework.json`.

## Embed document: `framework.embed/v1`

```json
{
  "schema": "framework.embed/v1",
  "key": "aB3dE5fG7hJ9kL1m",
  "version": 3,
  "latest_version": 3,
  "kind": "model",
  "title": "Admission model",
  "description": null,
  "alt": "Logistic regression of admitted with 4 terms. Odds ratios available.",
  "url": "https://framework.pub/d/aB3dE5fG7hJ9kL1m/versions/3",
  "embed_url": "https://framework.pub/api/embed/v1/artifacts/aB3dE5fG7hJ9kL1m/versions/3",
  "published_at": "2026-11-16T12:00:00Z",
  "author": {"name": "Ada Example", "url": "https://framework.pub/@ada"},
  "provider": {"name": "Framework", "url": "https://framework.pub"},
  "attribution": {"text": "Framework", "url": "https://framework.pub/d/aB3dE5fG7hJ9kL1m/versions/3"},
  "data": { "...": "the framework.artifact/v1 representation, as Cloud normalizes it" },
  "spec": null,
  "image": null
}
```

- `kind` is one of `model`, `table`, `test`, `image`, `vega-lite`, `page`. Renderers treat an unknown kind as `page`.
- `data` is set for `model`, `table`, and `test`: the normalized representation from `ArtifactRepresentation::parse` (tables capped at 10, columns at 50, with `truncated`, `row_count`, `column_count`, `precision_note`). Each table carries its first 25 rows, the number renderers show. `row_count`, `column_count`, and the column summaries describe the whole table. A table cut to 25 rows has `truncated: true`, and so does the representation. An embed document for a typical artifact stays well under 200 KB.
- `spec` is set for `vega-lite`: the Vega-Lite specification, data inlined. Only self-contained specs embed. A spec that loads data by URL (`url` under `data`, a `lookup` source, or a `datasets` entry), uses an image mark's `url` channel, or sets `href` embeds as `page`.
- `image` is `{"url", "content_type", "width", "height"}`. It is set for `image`, and it may be set for other kinds later as a fallback. `width` and `height` may be null.
- `page` covers HTML documents, Quarto sites, PDFs, data files, and anything else. Renderers show a link card: title, description, author, and the attribution link.
- `author` and `description` may be null. `author` is set only when the owner's profile is public. Fields may be added within `v1`; renderers ignore fields they don't know.
- `alt` is a one-sentence text description for screen readers and image alt text.

## Renderer markup

The JS and PHP renderers produce the same HTML for the same embed document; the shared fixtures in `framework-embed/spec/fixtures` hold the expected output for both. All artifact text is inserted as text, never as HTML. Every class starts with `fw-embed`.

```html
<figure class="fw-embed fw-embed--model" data-fw-embed-kind="model" data-fw-embed-key="…" data-fw-embed-version="3">
  <div class="fw-embed__body">…kind-specific content…</div>
  <figcaption class="fw-embed__caption">
    <span class="fw-embed__title">Admission model</span>
    <a class="fw-embed__credit" href="…attribution.url…" target="_blank" rel="noopener">Framework</a>
  </figcaption>
</figure>
```

- `model`: a coefficient table per coefficient-like table (estimate, standard error, confidence interval, p-value when present), with a switch between the ratio scale and the model scale when `model.exponentiate` is set, then fit statistics as a `<dl>`.
- `table`: a static `<table>` with the first 25 rows and a note giving the total and a link to the artifact for the rest.
- `test`: method, statistic, parameters, p-value, estimates, and confidence interval.
- `image`: `<img src alt loading="lazy">`.
- `vega-lite`: a `<div class="fw-embed__chart" data-fw-embed-chart>` holding the spec in `<script type="application/json">`, and a link to the artifact as the fallback. The JS bundle draws it with vega-embed; without JavaScript the link stays.
- `page`: a link card.

The stylesheet themes through the custom properties listed under "Theming" in framework-embed's `SPEC.md`. It follows `prefers-color-scheme` and a `data-theme="dark"` or `.dark` ancestor.

## Consumer tiers

Consumers prefer the stored tier. The live tier is the fallback.

| Tier | Behavior |
|---|---|
| Stored | Store the embed document with the content at insert. Render from storage. Keep the copy indefinitely. Fetch again only when the author asks ("Fetch again"). Pin the version URL. Show images by hotlinking `image.url`, never by copying the file. An optional background check may drop the stored copy, only on a definite `404`. |
| Live | Fetch the embed document and keep it as long as its `Cache-Control` allows. Fetch asynchronously and never block page render. Drop the copy when the server answers `404`. |

Errors other than `404` (timeouts, `429`, `5xx`) never drop a copy in either tier.

## Security rules for consumers

- Fetch only from hosts the site trusts: framework.pub by default, plus servers an administrator adds. Never fetch a host taken from a pasted URL without that check.
- Insert artifact text as text. Show SVG only through `<img>`.
- Load vega-embed from the site itself or a pinned CDN version.

## Later

- Fallback images for models, tables, and tests (needs server-side rendering).
- Plotly figure JSON in the R and Python packages, then a `plotly` kind.
- Reports (`/r/`) and collections (`/c/`) as `page` cards.
- A picker in plugins through Framework OAuth.
