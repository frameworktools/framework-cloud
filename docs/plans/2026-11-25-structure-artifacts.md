# Project structure artifacts

Status: approved 2026-11-25.

A project structure artifact is a folder tree, with optional notes on its paths. A tutorial or course page embeds it to explain how a project's code is laid out. Ignored paths show the `.gitignore` rule that hides them, so the tree explains the `.gitignore` too.

## Scope

| Work | Repository |
|---|---|
| `tree` kind, validation, reader page, embed contract, display settings, alt text | framework-cloud |
| Renderers (JS and PHP) for `kind: "tree"` | framework-embed |
| `fw_structure()` and `publish_structure()` | framework (R) |
| `fw.structure()` and `fw.publish_structure()` | framework-py |
| `fw_structure` | framework-stata |
| `fw structure` and `fw publish --structure` | framework-cli |

Out of scope: file contents, file sizes, building a tree on the server from a blueprint or a GitHub repository (later), and notes kept in a project file (later).

## Privacy rules

Clients build the tree, so every client applies these rules before anything is uploaded.

1. `.git/` is always left out.
2. Ignored paths never publish their names. An ignored folder publishes as a stub with its rule and a file count. Ignored files in a folder that isn't ignored publish as one group per rule, with the rule and a count.
3. A private folder publishes as a stub with a file count, whether or not it is ignored. A private folder is the package's existing private-path rule: a folder named `private` or ending in `_private`, or a settings folder marked private.
4. A folder below `depth` publishes as a stub with a file count.
5. `exclude` globs leave paths out entirely, with no stub.
6. Symbolic links publish as files and are never followed.
7. A rule from a file outside the project root (the global excludes file, `.git/info/exclude`) publishes with `source: "global"` or `source: "info/exclude"`, never an absolute path.

File counts stop at 10,000. A count of 10,000 reads as "10,000+ files".

## Ignore matching

With git available and the root inside a work tree, git decides:

- `git ls-files --others --ignored --exclude-standard --directory -z` lists ignored paths, folders with a trailing slash.
- `git check-ignore -v -z --stdin` gives each one's rule, source file, and line.

Run both from the root being published so paths come back relative to it. `method` is `"git"`.

Without git, or outside a work tree, read only the root `.gitignore` with a small matcher: blank lines and `#` comments skipped, `!` negation, a trailing `/` for folders only, a leading `/` or a middle `/` anchors to the root, `*`, `?`, `**`. `method` is `"gitignore"`. With no `.gitignore`, `method` is `"none"`.

## Upload format

```json
{
  "schema": "framework.artifact/v1",
  "kind": "tree",
  "source": {"language": "r"},
  "root": "my-project",
  "base": "",
  "method": "git",
  "nodes": [
    {"path": "R", "type": "dir", "note": "Reusable functions"},
    {"path": "R/clean.R", "type": "file"},
    {"path": "data", "type": "dir"},
    {"path": "data/raw", "type": "dir", "ignored": {"rule": "data/raw/", "source": ".gitignore", "line": 4}, "hidden": 12},
    {"path": "data/private", "type": "dir", "private": true, "hidden": 3},
    {"path": "outputs", "type": "dir"},
    {"path": "outputs", "type": "ignored", "ignored": {"rule": "*.rds", "source": ".gitignore", "line": 7}, "hidden": 4},
    {"path": "renv", "type": "dir", "depth": true, "hidden": 1520},
    {"path": "README.md", "type": "file", "note": "Start here"}
  ],
  "open": ["R"],
  "highlight": ["README.md"]
}
```

- `root`: the name shown at the top, the published folder's base name. Required.
- `base`: the published folder relative to the project root, `""` for the root. Shown as a breadcrumb. Optional.
- `method`: `git`, `gitignore`, or `none`. Optional.
- `source.language`: `r`, `python`, `stata`, or `shell`. Optional.
- `nodes`: paths relative to `root`, with `/` separators, in any order. Cloud adds missing parent folders and sorts: folders first, then files, then ignored groups, each by name, ignoring case.
  - `type: "dir"` or `"file"`. A folder with `hidden` is a stub: its contents aren't listed. `ignored`, `private`, and `depth: true` say why.
  - `type: "ignored"`: a group of ignored files in the folder `path` (`""` for the root), named by its rule.
  - `note`: up to 500 characters of plain text.
  - `ignored`: `rule` (required), `source` (path relative to the root, `global`, or `info/exclude`), and `line`.
- `open`: folders that start open in the embed. Default: the top level is open, deeper folders are closed. A folder that holds a highlighted path opens too.
- `highlight`: paths drawn as highlighted.

Several folders publish as one tree: `root` is the project's name, `base` is `""`, and only the chosen folders' nodes are sent.

Limits: 5,000 nodes, 1 MB, paths up to 1,024 characters and 32 levels. A path with `..`, a leading `/`, or a backslash is rejected. Notes, `open`, or `highlight` naming a path not in the tree are rejected.

## Client output

`fw_structure()` (each language's equivalent) prints the full local tree and uploads nothing. Ignored files are listed by name with their rule, up to 20 per folder and rule, so a person can see what their `.gitignore` does:

```
my-project/
├── R/                  Reusable functions
│   └── clean.R
├── data/
│   ├── private/        private · 3 files
│   └── raw/            ignored by data/raw/ (.gitignore:4) · 12 files
├── outputs/
│   ├── fit.rds         ignored by *.rds (.gitignore:7)
│   └── draws.rds       ignored by *.rds (.gitignore:7)
├── renv/               1,520 files
└── README.md           Start here
```

`publish_structure()` prints the tree as it will publish, with stubs and groups in place of ignored names, then uploads, then prints `Published version N.`, `Share:`, and `Manage:` like every other publish.

Arguments, named for each language's conventions:

- `path`: one folder or several, relative to the project root. Default: the project root.
- `depth`: levels to list. Default: no limit.
- `exclude`: globs to leave out.
- `notes`: path to note. A trailing `/` on a folder is allowed. A path not in the tree is an error.
- `open`, `highlight`: paths, as above.
- `ignored`: `"rules"` (default, the stubs and groups) or `"hide"` (ignored paths left out). Private stubs show either way, unless the private folder is also ignored and `ignored` is `"hide"`.
- `slug` (default `structure`, or `structure-<base>` for a subfolder), `title` (default `<root> structure`), `description`, `visibility`, `display`, as for other publishes.

The upload is a JSON file named `<slug>.json` with `kind=tree`.

## Embed contract

`framework.embed/v1` gains `kind: "tree"`. `data` is the normalized tree, nested:

```json
{
  "schema": "framework.artifact/v1",
  "kind": "tree",
  "tree": {
    "root": "my-project",
    "base": "",
    "method": "git",
    "counts": {"dirs": 6, "files": 3, "hidden": 1539},
    "children": [
      {"name": "R", "path": "R", "type": "dir", "note": "Reusable functions", "open": true, "children": [
        {"name": "clean.R", "path": "R/clean.R", "type": "file"}
      ]},
      {"name": "raw", "path": "data/raw", "type": "dir", "open": false, "ignored": {"rule": "data/raw/", "source": ".gitignore", "line": 4}, "hidden": 12, "children": []},
      {"name": "*.rds", "path": "outputs", "type": "ignored", "ignored": {"rule": "*.rds", "source": ".gitignore", "line": 7}, "hidden": 4},
      {"name": "README.md", "path": "README.md", "type": "file", "note": "Start here", "highlight": true}
    ]
  }
}
```

Every folder has `open` and `children`. `highlight`, `note`, `ignored`, `private`, `depth`, and `hidden` appear only when set. An ignored group's `name` is its rule without a trailing `/`.

Renderers draw a monospace tree of nested `<details>` (folders) and rows (files), with notes in a muted column. Ignored and private stubs are dimmed, with a badge: `ignored · *.rds · 4 files`, `private · 3 files`, `12 files`. A highlighted row has a tinted background. Rule sources show in the badge's title: `.gitignore line 7`. Older renderers show the link card.

`EmbedAlt` reads "Folder tree of my-project: 6 folders and 3 files, 2 ignored."

## Display settings

| Setting | Label | Default |
|---|---|---|
| `title` | Title | on |
| `description` | Description | off |
| `notes` | Notes | on |
| `ignored` | Ignored paths | on |

With `ignored` off, ignored stubs and groups are dropped from the embed. Private stubs stay.

## Cloud

- `app/Support/Trees/ArtifactTree.php`: validation, normalization, alt text.
- `ArtifactPublisher` accepts `kind: "tree"` and rejects a broken tree with a message on `file`.
- Reader: `resources/views/components/artifact-tree.blade.php`.
- Tests: `tests/Feature/TreeArtifactTest.php`.

## Shared fixture

Each client tests against the same project, built in a temporary folder with `git init`:

```
.gitignore           "data/raw/\n*.rds\n.env\n"
.env
README.md
R/clean.R
data/raw/a.csv
data/raw/b.csv
data/private/ids.csv
outputs/fit.rds
outputs/draws.rds
outputs/table.csv
```

With default arguments the published nodes are, in any order:

```
.gitignore           file
.env                 ignored group at "" (rule .env, .gitignore:3, 1 file)
README.md            file
R                    dir
R/clean.R            file
data                 dir
data/raw             dir, ignored (data/raw/, .gitignore:1), hidden 2
data/private         dir, private, hidden 1
outputs              dir
outputs              ignored group (rule *.rds, .gitignore:2, 2 files)
outputs/table.csv    file
```

No published node may contain `a.csv`, `b.csv`, `ids.csv`, `fit.rds`, or `draws.rds`.
