---
name: stata-graphs
description: Use when making a Stata graph, from a quick look at the data to a graph for a paper, report, poster, or slides. Covers choosing the graph, accurate defaults, titles and labels, accessible color, honest scales, schemes, and saving.
metadata:
  title: Graphs in Stata
  summary: Accurate Stata graphs, from exploring data to publication.
---

# Graphs in Stata

## Graph choice

Values on a shared axis are the easiest to compare.

| Question | Graph | Stata |
|---|---|---|
| Distribution of one numeric variable | Histogram | `histogram x, width(...)` |
| A numeric variable across groups | Box plot | `graph box y, over(group)` |
| Counts by category | Bar chart, sorted | `graph hbar (count), over(cat, sort(1) descending)` |
| Two numeric variables | Scatterplot | `scatter y x`, `twoway (scatter y x) (lfit y x)` |
| Change over time or another ordered variable | Line chart | `twoway line y time, sort` |
| A pattern across subgroups | Small multiples | `by(group)` on most graphs |

Avoid dual y axes and 3-D effects. In a stacked bar, only the bottom segment
sits on a shared baseline.

## Accuracy

- **Histograms:** set `width()` in the units of the variable, and try more
  than one. Add `frequency` when counts matter more than density.
- **Groups in one scatterplot:** `separate y, by(group)` and graph the new
  variables, or overlay one `scatter` per group.
- **Overplotting:** use smaller markers, `msize(small)`, and transparency,
  `mcolor(%40)`.
- **Skewed variables:** use `xscale(log)` or `yscale(log)` for strongly
  right-skewed values. Say so in the axis title.
- **Bars:** bars start at zero. Use `graph dot` when zero is not meaningful.
- **Order:** sort categories by value unless they have a natural order.
- **Missing-value codes:** check the codebook for codes such as -99 before
  plotting, and recode them to missing.
- **Missing values:** graphs drop missing values without a note. Check them
  with `misstable summarize` before you trust the graph.
- **Panels:** panels that readers compare share axis scales, the `by()`
  default. Note any change to that in the caption.

## Titles and labels

- **Axes:** use variable labels with units. Replace raw variable names.
- **Title:** a title that states the finding can help a graph communicate its
  result. Make sure it suits the venue. Some journals and readers prefer a
  matter-of-fact title that names the variables. Follow the venue's style, and
  ask the user when it is unclear. Do not invent a finding.
- **Subtitle and note:** say what is measured, for whom, and when. Give the
  data source, the sample size, and caveats.
- **Legends:** place them near the data, and keep categories in the same order
  across graphs.
- **Uncertainty:** compute predictions and contrasts from the fitted model,
  and state the interval level in the note. Label a confidence interval as
  uncertainty in the estimate.

## Color

- Use colors readable with color vision deficiency.
- Pair color with marker symbols or line patterns, so the graph still works in
  grayscale.

## Schemes

A shared scheme is a good tactic when a project makes many graphs. It keeps
them consistent. If the project already sets one, use it.

## Saving

- Name graphs with `name(..., replace)` to compare or combine them later.
- Set the size before tuning text, and check the exported file for clipped
  labels and unreadable text.
- Use `preserve` and `restore` around `collapse` or `contract`, so a graph
  needs no extra dataset.
- In a Framework project, prefer `fw_graph "name"`. It exports the current
  graph to the figures folder. Otherwise use `graph export` with explicit
  dimensions for raster formats.
