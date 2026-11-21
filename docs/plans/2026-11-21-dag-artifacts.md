# DAG artifacts

Status: approved 2026-11-21. Phase 1 shipped 2026-11-21: the Cloud kind, reader, and embed contract, and framework-embed 0.7.0.

A DAG artifact is a causal diagram with the code that defines it. A blog post that embeds it shows the drawing, and a reader can copy the DAGitty model text into dagitty.net or R and get the same graph back. The code that built it (an R `dagify()` call, for example) can ride along.

The DAGitty model syntax is the portable form: `dag { smoking [exposure] cancer [outcome] age -> smoking -> cancer age -> cancer }`. R's dagitty and ggdag, and dagitty.net, all read and write it.

## Scope

| Phase | Work | Repository |
|---|---|---|
| 1 | `dag` kind, DAGitty parser, layout, reader page, embed contract, framework-embed renderers; `.dagitty` files publish as DAGs | framework-cloud, framework-embed |
| 2 | R: `publish_dag()` for dagitty and ggdag objects, with adjustment sets and implications from dagitty | framework |
| 3 | Python and Stata: publish DAGitty text and the code that built it | framework-py, framework-stata |

Out of scope: graph types other than `dag` (pdag, mag, pag), computing adjustment sets on the server, and editing a DAG on the Cloud.

## Format

A `.dagitty` file publishes as a DAG as is. A DAG with labels, code, or analysis is a `framework.artifact/v1` JSON document:

```json
{
  "schema": "framework.artifact/v1",
  "kind": "dag",
  "source": {"language": "r"},
  "dagitty": "dag {\nage [pos=\"1,0\"]\ncancer [outcome,pos=\"2,1\"]\nsmoking [exposure,pos=\"0,1\"]\nage -> cancer\nage -> smoking\nsmoking -> cancer\n}",
  "labels": {"smoking": "Smoking status"},
  "code": {"language": "r", "text": "dag <- dagify(cancer ~ smoking + age, smoking ~ age, exposure = \"smoking\", outcome = \"cancer\")"},
  "analysis": {
    "effect": "total",
    "adjustment_sets": [["age"]],
    "implications": [{"x": "a", "y": "b", "given": ["c"]}]
  }
}
```

- `dagitty`: DAGitty model text. Required. Node attributes `exposure`, `outcome`, `latent`, `adjusted`, and `selected` set roles. `pos="x,y"` places a node, with y pointing down as in DAGitty. Edges are `->`, `<-`, and `<->`, chained (`a -> b -> c`) or grouped (`{a b} -> c`). Edge attributes and `bb` are read and ignored.
- `labels`: display labels by node name. Optional.
- `code`: the code that built the graph, in `r`, `python`, or `stata`. Optional.
- `analysis`: what the producer computed. Optional. `effect` is `total` or `direct`. `adjustment_sets` lists the minimal sufficient sets: `[[]]` means no adjustment is needed, and `[]` means no set of observed variables works. `implications` lists testable conditional independencies. Names that aren't nodes are dropped.

Limits: 100 nodes, 400 edges, 64 KB of DAGitty text, 400 lines of code, 50 adjustment sets, 200 implications. A graph with a directed cycle, an undirected edge, or another graph type is rejected with a message that names the problem.

## Layout

Cloud lays the graph out once, when it normalizes it, so renderers draw from data alone and the JS and PHP renderers stay identical.

- With `pos` on every node, each axis scales on its own. Rows: the two closest sit 64 px apart, center to center, and boxes stacked in one column clear each other. Columns: boxes that share a row keep 40 px between them. With no shared row, x scales at least half as much as y. A tight row therefore no longer stretches the drawing's height. `DagLayout::VERSION` is part of a DAG preview's cache key, so a new layout redraws published DAGs.
- Otherwise the layout is layered left to right: a node's column is its longest directed path from a source, and rows are ordered by barycenter sweeps.
- Node boxes fit their label in a monospace font (0.6em per character at 14px), capped at 32 characters with an ellipsis.
- Edges run between box edges. A straight edge that would cross another box bends. Bidirected edges always bend, dashed, with heads at both ends. Each edge is an SVG path string and its heads are closed paths.

## Embed contract

`framework.embed/v1` gains `kind: "dag"`. `data` is the normalized DAG:

```json
{
  "schema": "framework.artifact/v1",
  "kind": "dag",
  "dag": {
    "width": 412, "height": 118,
    "nodes": [{"name": "smoking", "label": "Smoking status", "roles": ["exposure"], "x": 8, "y": 44, "width": 142, "height": 30}],
    "edges": [{"from": "smoking", "to": "cancer", "type": "->", "path": "M 152 59 L 236.5 59", "heads": ["M 246.5 59 L 236.5 63.5 L 236.5 54.5 Z"]}]
  },
  "dagitty": {"text": "dag { … }", "lines": [[["kw", "dag"], ["", " {"]]]},
  "code": {"language": "r", "text": "…", "lines": […]},
  "analysis": {"effect": "total", "exposures": ["smoking"], "outcomes": ["cancer"], "adjustment_sets": [["age"]], "implications": []}
}
```

Renderers draw an inline SVG, a legend of the roles present, an `Analysis` disclosure with the adjustment sets and implications, and a `Code` disclosure with the DAGitty text and the source code, each with a copy button. Older renderers show the link card. `EmbedAlt` reads "Causal diagram of 3 variables and 3 arrows. Exposure: smoking. Outcome: cancer."

## Cloud

- `app/Support/Dags/`: `DagittyParser` (text to nodes and edges), `DagLayout` (positions and edge paths), and `ArtifactDag` (validation, normalization, highlighting, alt text).
- `ArtifactPublisher` accepts `kind: "dag"`, validates DAG JSON and `.dagitty` files strictly, and rejects a broken one with a message on `file`.
- The reader is `resources/views/components/artifact-dag.blade.php`.
- Tests: `tests/Feature/DagArtifactTest.php`.
