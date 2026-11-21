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
- `https://framework.pub/go/{code}`, a public artifact's short link, for the newest version

Turn on **Pin to version** in the **Embed** tab to copy a versioned link or HTML snippet that stays on the current version when you publish updates. When version pinning is off, live embeds and HTML snippets follow the newest version. Editors and blocks that save a copy of the artifact with the page always pin the version that was current when inserted.

## Academic.page

Paste an artifact link into the [Academic.page](https://academic.page) editor to insert the model, table, hypothesis test, figure, chart, or link card. Academic.page saves a copy of the artifact with the page and pins its version.

## CMS plugins

Plugins for WordPress, Drupal, and Statamic are available in the open-source [framework-embed](https://github.com/frameworktools/framework-embed) repository. Each plugin renders pasted artifact links, blocks, or fields as formatted models, tables, hypothesis tests, figures, Vega-Lite charts, or link cards using a shared PHP renderer and stylesheet.

| CMS | Installation |
|---|---|
| WordPress | Download the `framework-embed` `.zip` archive from the [latest release](https://github.com/frameworktools/framework-embed/releases/latest) and upload it under **Plugins > Add New > Upload Plugin**. |
| Drupal | The module is not on drupal.org. Clone the repository at a release tag and install `drupal/` with Composer path repositories. See [`drupal/README.md`](https://github.com/frameworktools/framework-embed/blob/main/drupal/README.md). |
| Statamic | The addon is not on Packagist. Clone the repository at a release tag and install `statamic/` with Composer path repositories. See [`statamic/README.md`](https://github.com/frameworktools/framework-embed/blob/main/statamic/README.md). |

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

Paste an artifact link into any editor that supports oEmbed. Figures with known dimensions embed as `photo` responses, and other artifacts embed as `link` responses. Embeddable artifact reader pages include oEmbed and `framework.embed/v1` discovery `<link>` tags in `<head>`. See [Discovery](embed-integrations.md#discovery).

## Custom integrations

Sites can build their own integration from the public embed API, which serves each artifact as a `framework.embed/v1` JSON document. [Embed integrations](embed-integrations.md) covers the API, the JavaScript and PHP renderers, page setup, HTML sanitizers, Content Security Policy, caching, and conformance tests. [Artifact representation](artifact-representation.md) describes the model, table, and test data inside each document. Self-hosted Framework Cloud servers serve the same API at their own domain.
