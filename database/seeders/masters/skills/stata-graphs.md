---
name: stata-graphs
description: Use when graphing data in Stata to understand it - checking distributions, comparing groups, looking for relationships, trends, or problems in a dataset. Covers choosing the graph that answers the question and quick, accurate Stata graphs.
metadata:
  title: Exploring data with Stata graphs
  summary: Quick, accurate Stata graphs for understanding data.
---

# Exploring data with Stata graphs

Exploratory graphs are for the analyst and the team. Their job is to show the
data accurately so you can understand it. Make them quickly, make many, and do
not polish them.

## Rules

- Use a clean scheme. On Stata 18 or later, `set scheme stcolor`. On Stata 17,
  use the project's scheme from `settings.do`, or `set scheme s1color`.
- Name graphs with `name(..., replace)` so you can compare several in one
  session, and `graph export` the ones you keep to `$fw_outputs_figures`.
- Do not `save` a new dataset to `inputs/` just to draw a graph. Use
  `preserve` and `restore` around `collapse` or `contract`.

## Choose the graph from the question

| Question | Graph | Stata |
|---|---|---|
| How is one numeric variable distributed? | Histogram | `histogram x, width(...)` |
| How does a numeric variable differ across groups? | Box plot | `graph box y, over(group)` |
| How many are in each category? | Bar chart, sorted | `graph hbar (count), over(cat, sort(1) descending)` |
| How do two numeric variables relate? | Scatterplot | `scatter y x`, `twoway (scatter y x) (lfit y x)` |
| How does something change over time? | Line chart | `twoway line y time, sort` |
| Does a pattern differ across subgroups? | Small multiples | `by(group)` on most graphs |

Avoid pie charts, dual y axes, and 3-D effects. They make comparisons harder to
read.

## Make it accurate

- **Histograms:** set `width()` in the units of the variable, and try more than
  one. Add `frequency` when counts matter more than density.
- **Groups in one scatterplot:** `separate y, by(group)` and graph the new
  variables, or overlay `twoway (scatter ...) if group == ...` plots.
- **Overplotting:** use smaller markers, `msize(small)`, and transparency,
  `mcolor(%40)`.
- **Bar charts:** bars start at zero. Use dots, `graph dot`, when zero is not
  meaningful.
- **Missing values:** graphs drop missing values silently. Check them with
  `misstable summarize` before you trust the graph.

## Saving and publishing

- `graph export "$fw_outputs_figures/name.svg", replace`
- To share one, `fw_publish "$fw_outputs_figures/name.svg", slug(...)`.
