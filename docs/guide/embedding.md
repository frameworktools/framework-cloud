# Embedding artifacts

Public and unlisted artifacts can appear on other websites. The server sends the artifact's data. The other site draws it with its own markup and styles, without a frame. Embedding needs no account or key.

An artifact is embeddable when it is stored on Framework Cloud, its sharing is **Public, with a readable link** or **Anyone with the link**, and it has no password. Private artifacts, password-protected artifacts, deleted artifacts, and publications to other destinations are never embeddable. Making an artifact private, adding a password, or deleting it stops its embeds within an hour. Its `image.url` stops answering within five minutes. A site that stored an embed keeps its stored text and data until it fetches again.

## Pasted links

Paste an artifact link into an editor that supports oEmbed. Every artifact link form works:

- `https://framework.pub/d/{key}`, the newest version
- `https://framework.pub/d/{key}/versions/{n}`, one version
- `https://framework.pub/@{handle}/{project}/{slug}`, the readable link of a public artifact
- `https://framework.pub/@{handle}/{project}/{slug}/versions/{n}`

Figures with a known size embed as images. Other artifacts embed as links. Embeddable reader pages also carry oEmbed discovery links in their `<head>`.

A link to a version always shows that version. A link without a version shows the newest version.

## CMS plugins

Plugins for WordPress, Drupal, and Statamic live in the open-source [framework-embed](https://github.com/frameworktools/framework-embed) repository. Each plugin turns a pasted artifact link, a block, or a field into a drawn model, table, test, figure, chart, or link card. They render through the same PHP renderer and stylesheet.

| CMS | Install |
|---|---|
| WordPress | Upload `framework-embed-0.2.0.zip` from the [latest release](https://github.com/frameworktools/framework-embed/releases/latest) under Plugins → Add New → Upload. |
| Drupal | Install the `drupal/` module with Composer from the repository. See its README. |
| Statamic | Install the `statamic/` addon with Composer from the repository. See its README. |

A block or field stores the artifact with the page and draws it from that copy. A pasted link fetches in the background and draws once the copy arrives.

## Web component

The `framework-embed` JavaScript and CSS bundle defines the `<framework-artifact>` element. The element fetches an embed document and draws it on the page. Vega-Lite charts are drawn with vega-embed. Without JavaScript, the element shows a link to the artifact. The bundle's files are attached to each [release](https://github.com/frameworktools/framework-embed/releases/latest).

```html
<link rel="stylesheet" href="framework-embed.css">
<script src="framework-embed.iife.js" defer></script>
<framework-artifact src="https://framework.pub/d/KEY">
  <a href="https://framework.pub/d/KEY">View the artifact</a>
</framework-artifact>
```

## Embed API

Sites that build their own renderer use the embed API directly. All routes are public and stateless. They set no cookies and answer with `Access-Control-Allow-Origin: *`.

| Route | Response |
|---|---|
| `GET /api/embed/v1/resolve?url={artifact link}` | The embed document for any artifact link on this server |
| `GET /api/embed/v1/artifacts/{key}` | The embed document for the newest version |
| `GET /api/embed/v1/artifacts/{key}/versions/{n}` | The embed document for version `n` |
| `GET /api/embed/v1/artifacts/{key}/versions/{n}/image` | The figure file of an image artifact |
| `GET /api/oembed?url={artifact link}&format=json` | oEmbed 1.0: `photo` or `link` |
| `GET /.well-known/framework.json` | Discovery: the URLs above for this server |

Artifacts that are not embeddable, and artifacts that do not exist, get the same `404` response. Version responses send `Cache-Control: public, max-age=3600`. Newest-version and resolve responses send `public, max-age=300`. Each IP address may make 300 requests a minute across these routes.

Version URLs and `image.url` stay the same for the life of the artifact. On framework.pub, `image.url` redirects to a short-lived link on `content.framework.pub`. Use `image.url` itself, never the link it redirects to.

Self-hosted servers serve the same API at their own address.

## Embed document

An embed document has the schema `framework.embed/v1`:

```json
{
  "schema": "framework.embed/v1",
  "key": "rglmlogit0000000",
  "version": 1,
  "latest_version": 1,
  "kind": "model",
  "title": "Transmission model",
  "description": null,
  "alt": "Logistic regression of am with 3 terms. Odds ratios available.",
  "url": "https://framework.pub/@ada/admissions/transmission/versions/1",
  "embed_url": "https://framework.pub/api/embed/v1/artifacts/rglmlogit0000000/versions/1",
  "published_at": "2026-11-16T12:00:00Z",
  "author": {"name": "Ada Example", "url": "https://framework.pub/@ada"},
  "provider": {"name": "Framework", "url": "https://framework.pub"},
  "attribution": {"text": "Framework", "url": "https://framework.pub/@ada/admissions/transmission/versions/1"},
  "data": {"schema": "framework.artifact/v1", "kind": "model", "...": "..."},
  "spec": null,
  "image": null
}
```

| Field | Contents |
|---|---|
| `kind` | `model`, `table`, `test`, `image`, `vega-lite`, or `page`. Treat an unknown kind as `page`. |
| `data` | The artifact's `framework.artifact/v1` representation, for `model`, `table`, and `test`. Tables are capped at 10 and columns at 50. Each table carries its first 25 rows. `row_count`, `column_count`, and the column summaries describe the whole table. A shortened table has `truncated: true`. |
| `spec` | The Vega-Lite specification, for `vega-lite`. Only charts with inline data embed as charts. A chart that loads data, images, or links from another address embeds as `page`. |
| `image` | `url`, `content_type`, `width`, and `height`, for `image`. `width` and `height` may be null. |
| `alt` | A one-sentence text description for screen readers and image alt text. |
| `url` | The artifact's reader page for this version. |
| `author`, `description` | May be null. `author` is set only when the owner's profile is public. |

A `page` covers HTML documents, Quarto sites, PDFs, data files, and other outputs. Show it as a link card with the title, description, author, and attribution link.

`framework.embed/v1` stays renderable indefinitely. New fields may appear within `v1`. Ignore fields your renderer does not know.

## Consumer tiers

Sites store embeds where they can, and fetch them live otherwise.

| Tier | Behavior |
|---|---|
| Stored | Store the embed document with your content when the author inserts it. Render from storage. Keep the copy indefinitely. Fetch again only when the author asks. Pin the version URL. Show figures by linking to `image.url`, without copying the file. An optional background check may drop the stored copy, only when the server answers `404`. |
| Live | Fetch the embed document and keep it as long as its `Cache-Control` allows. Fetch in the background and never delay page rendering. Drop the copy when the server answers `404`. |

Timeouts, `429`, and `5xx` responses never drop a copy.

## Security rules for consumers

- Fetch embed documents only from trusted servers. Trust framework.pub by default, plus servers an administrator adds. Never fetch from a host taken from a pasted link without that check.
- Insert artifact text as text, never as HTML.
- Show SVG figures only through `<img>`.
- Load vega-embed from your own site or a pinned CDN version.
