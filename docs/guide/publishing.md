# Publishing notebooks

Publishing renders a Quarto notebook and posts it as a web page. Every
notebook gets a private link that only people you share it with can find. You
can also give it a readable link.

## Publish from R or Python

Run this from inside a project that was set up from Framework Cloud:

```r
framework::publish("notebooks/analysis.qmd")
```

```python
import framework

framework.publish("notebooks/analysis.qmd")
```

Framework renders the notebook, uploads it, and prints the version number and link. `publish()`
also accepts a rendered `.html` file, an `.html` file with its `_files`
folder, or a folder of rendered output.

Notebooks go to the server the project was set up from, which is recorded in
the project's `.env` file. If the project has its own storage bucket
configured in Framework, `publish()` uploads there instead.

`publish_notebook()` works the same way. If the project has
neither a bucket nor a Framework Cloud project token, it stops with an error
that explains how to add one.

## Private and readable links

The private link looks like `https://framework.pub/d/q7w2k9m4x8c1v5b3n6z0p2rt`.
It can't be guessed, so only people you give it to can open the notebook.

A readable link uses your handle, the project, and a slug you choose:

```text
https://framework.pub/@ada/nhanes-study/main-results
```

Anyone who knows or guesses it can open the notebook. To get one, give the
notebook a slug when you publish it:

```r
framework::publish("notebooks/analysis.qmd", slug = "main-results")

# Or use the file name as the slug
framework::publish("notebooks/analysis.qmd", slug = TRUE)
```

```python
import framework

framework.publish("notebooks/analysis.qmd", slug="main-results")

# Or use the file name as the slug
framework.publish("notebooks/analysis.qmd", slug=True)
```

Publishing without `slug` keeps a notebook's current setting. To turn its
readable link off, use `slug = FALSE` in R or `slug=False` in Python.

On a storage bucket, `slug` is ignored, and `dest` sets the file's path.

Readable links need a handle, which you set in **Settings > Account**. You can
also turn a notebook's readable link on or off on the **Published** page. The
private link keeps working either way.

## Versions

Each publish adds a version. The links always show the newest version, and
earlier versions stay available:

```text
https://framework.pub/d/q7w2k9m4x8c1v5b3n6z0p2rt/versions/2
https://framework.pub/@ada/nhanes-study/main-results/versions/2
```

Readers can switch between versions from the bar at the top of the page.
Versions are kept until you delete them. On the **Published** page you can
delete one version or take down the whole notebook. Deleting the newest
version makes the one before it current.

## Manage published notebooks

The **Published** page lists your notebooks with their links, versions, views,
and sizes. Each notebook page also has a **View raw** link to the HTML itself.

## Framework Public Cloud and self-hosted servers

On Framework Public Cloud, hosting published notebooks is an optional,
low-cost paid add-on. Everything else is free.

On your own server, everyone can publish. Notebooks are stored on the
server's disk unless an admin adds a storage bucket (see
[Server setup](server-setup.md#published-notebooks)).
