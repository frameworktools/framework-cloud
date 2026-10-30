# Publishing notebooks

Publishing renders a Quarto notebook and posts it at a stable link. Anyone
with the link can read it.

## Publish from R

Run this from inside a project that was set up from Framework Cloud:

```r
framework::publish("notebooks/analysis.qmd")
```

Framework renders the notebook, uploads it, and prints its link. Publishing
the same file again updates the page at the same link.

`publish()` also accepts a rendered `.html` file, an `.html` file with its
`_files` folder, or a folder of rendered output.

If the project has its own storage bucket configured in Framework, `publish()`
uploads there instead.

## Manage published notebooks

The **Published** page lists your notebooks with their links. You can open
each one or delete it. Each page also has a **View raw** link to the HTML
itself.

## framework.pub and self-hosted servers

On framework.pub, hosting published notebooks is an optional, low-cost paid
add-on. Everything else on framework.pub is free.

On your own server, everyone can publish. Notebooks are stored on the
server's disk unless an admin adds a storage bucket (see
[Server setup](server-setup.md#published-notebooks)).
