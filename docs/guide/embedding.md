# Embedding artifacts

Public and unlisted artifacts can appear on blogs, personal websites, newsletters, and slides. Open an artifact and select **Embed**, or open **Share** from an artifact menu and select **Embed**, to preview the output and copy a link, PNG image, or HTML snippet.

Models, tables, hypothesis tests, figures, and Vega-Lite charts render directly in the host page's typography and colors rather than inside an iframe. HTML documents, Quarto sites, PDFs, and downloadable files render as link cards. Embedding requires no account or API key on the receiving site.

Each embed opens with the artifact's title and a link back to it, followed by the result, then the description and author. A model shows its coefficients first; its type, response, formula, and fit statistics are under **Model details**. When a model was published with its variables, each coefficient term explains itself on hover or tap: the variable's label, the level it compares against, and a summary of the data the model used. Table columns with labels or summaries do the same. Bayesian models show their posterior mean, credible interval, R-hat, and effective sample sizes, with a warning when the chains may not have converged.

An artifact can be embedded when it is stored on Framework Cloud, its sharing is **Public, with a readable link** or **Anyone with the link**, and it has no password. Private artifacts, password-protected artifacts, deleted artifacts, and external publications cannot be embedded. Making an artifact private, adding a password, or deleting it stops live embeds within an hour, and figure images stop loading within five minutes on framework.pub or within an hour on a self-hosted server. Sites that store a copy of the artifact keep showing that copy until a takedown check removes it or the author refreshes it.

Every artifact link form works when embedding:

- `https://framework.pub/d/{key}` for the newest version
- `https://framework.pub/d/{key}/versions/{n}` for a specific version
- `https://framework.pub/@{handle}/{project}/{slug}` for the readable URL of a public artifact
- `https://framework.pub/@{handle}/{project}/{slug}/versions/{n}` for a specific version at a readable URL

Turn on **Pin to version** in the **Embed** tab to copy a versioned link or HTML snippet that stays on the current version when you publish updates. When version pinning is off, live embeds and HTML snippets follow the newest version. Editors and blocks that save a copy of the artifact with the page always pin the version that was current when inserted.

## Academic.page

Paste an artifact link into the [Academic.page](https://academic.page) editor to insert the model, table, hypothesis test, figure, chart, or link card. Academic.page saves a copy of the artifact with the page and pins its version.

## CMS plugins

Plugins for WordPress, Drupal, and Statamic are available in the open-source [framework-embed](https://github.com/frameworktools/framework-embed) repository. Each plugin renders pasted artifact links, blocks, or fields as formatted models, tables, hypothesis tests, figures, Vega-Lite charts, or link cards using a shared PHP renderer and stylesheet.

| CMS | Installation |
|---|---|
| WordPress | Download the `framework-embed` `.zip` archive from the [latest release](https://github.com/frameworktools/framework-embed/releases/latest) and upload it under **Plugins > Add New > Upload Plugin**. |
| Drupal | Install the `drupal/` module with Composer from the repository. See `drupal/README.md` for path repository setup. |
| Statamic | Install the `statamic/` addon with Composer from the repository. See `statamic/README.md` for path repository setup. |

Inserting a block or field saves a copy of the artifact with the post or entry and renders from that copy. Pasting a link on its own line queues a background fetch and displays a link until the copy arrives.

## Images

Select **Copy image** or **Download PNG** in the **Embed** tab to export a high-resolution PNG for Substack, email newsletters, and slide decks. The exported image includes the artifact title, formatted result, description, and web address on a light background.

## Web component

The `framework-embed` JavaScript and CSS bundle defines the `<framework-artifact>` custom element for Quarto websites, static site generators, and custom HTML pages. Copy the **HTML** snippet from the **Embed** tab, or host `framework-embed.css` and `framework-embed.iife.js` from the [latest release](https://github.com/frameworktools/framework-embed/releases/latest):

```html
<link rel="stylesheet" href="framework-embed.css">
<script src="framework-embed.iife.js" defer></script>
<framework-artifact src="https://framework.pub/d/KEY">
  <a href="https://framework.pub/d/KEY">View artifact</a>
</framework-artifact>
```

The element fetches the embed document and renders it into the page. Vega-Lite charts load `vega-embed` when a chart enters the viewport. When JavaScript is unavailable, the fallback link inside `<framework-artifact>` remains visible.

## oEmbed

Paste an artifact link into any editor that supports oEmbed. Figures with known dimensions embed as `photo` responses, and other artifacts embed as `link` responses. Embeddable artifact reader pages include oEmbed and `framework.embed/v1` discovery `<link>` tags in `<head>`.

## Embed API

Sites that build a custom renderer can call the embed API directly. All routes are public and stateless, set no cookies, and respond with `Access-Control-Allow-Origin: *`.

| Route | Response |
|---|---|
| `GET /api/embed/v1/resolve?url={artifact link}` | Embed document for any artifact URL on this server |
| `GET /api/embed/v1/artifacts/{key}` | Embed document for the newest version |
| `GET /api/embed/v1/artifacts/{key}/versions/{n}` | Embed document for version `n` |
| `GET /api/embed/v1/artifacts/{key}/versions/{n}/image` | Figure file for an image artifact |
| `GET /api/oembed?url={artifact link}&format=json` | oEmbed 1.0 `photo` or `link` response |
| `GET /.well-known/framework.json` | Discovery document listing the server's embed endpoints |

Artifacts that cannot be embedded and artifacts that do not exist return the same `404` response with `Cache-Control: no-store`. Requests that name a version send `Cache-Control: public, max-age=3600`. Requests for the newest version send `Cache-Control: public, max-age=300`. Resolve and oEmbed requests follow the link they are given. Each IP address can make up to 300 requests per minute across all embed routes.

Version URLs and `image.url` remain stable for the life of the artifact. On framework.pub, `image.url` redirects to a short-lived signed URL on `content.framework.pub`. Consumers must reference `image.url` directly rather than storing the redirect target.

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
| `kind` | `model`, `table`, `test`, `image`, `vega-lite`, or `page`. Renderers treat unknown values as `page`. |
| `data` | Normalized `framework.artifact/v1` representation for `model`, `table`, and `test`. Includes up to 10 tables, 50 columns per table, and the first 25 rows per table. `row_count`, `column_count`, and column summaries describe the full table, and shortened tables set `truncated: true`. Models may include `variables` and `terms`, which describe the data the model used and map coefficient terms to variables and levels. |
| `spec` | Vega-Lite specification for `vega-lite`. Only charts with inline data embed as `vega-lite`. Charts that load external URLs for data, images, or links embed as `page`. |
| `image` | Object with `url`, `content_type`, `width`, and `height` for `image`. `width` and `height` may be `null`. |
| `alt` | One-sentence text description for screen readers and image alt text. |
| `url` | Reader page URL for this version. |
| `author`, `description` | Optional metadata, or `null`. `author` appears only when the owner's profile is public. |
| `provider`, `attribution` | The server's share name and address. The name is set in **Server administration > Sharing** or with `FW_SHARE_NAME`, and is the server's host by default. |

The `page` kind covers HTML documents, Quarto sites, PDFs, data files, and other outputs. Renderers display `page` documents as link cards showing the title, description, author, and attribution link.

Existing `framework.embed/v1` fields remain compatible across updates, and new optional fields may appear within `v1`. Renderers should ignore unknown fields.

## Consumer tiers

Consumers either store embed documents with their content or fetch them through a short-lived cache.

| Tier | Behavior |
|---|---|
| Stored | Save the embed document with the page when the author inserts it, pinned to the version URL, and render from that saved copy. Refresh the copy only when the author requests it. Render figures from `image.url` without copying image files. An optional background check may remove the saved copy when the server returns `404` or `410`. |
| Live | Fetch the embed document in the background so network requests do not block page rendering. Cache the document up to its `Cache-Control` lifetime, and drop the cached copy when the server returns `404` or `410`. |

Keep existing copies when a request times out or returns `429` or `5xx`.

## Consumer security

- Fetch embed documents only from allowed hosts: framework.pub by default, plus any self-hosted Framework Cloud servers configured by an administrator. Validate the host of every pasted URL before sending a request.
- Escape all artifact text before inserting it into HTML.
- Render SVG figures inside `<img>` elements.
- Load `vega-embed` from self-hosted assets or a pinned CDN version with subresource integrity.
