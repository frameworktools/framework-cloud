---
name: matplotlib-exploring
description: Use when plotting data in Python to understand it - checking distributions, comparing groups, looking for relationships, trends, or problems in a dataset. Covers choosing the chart that answers the question and quick, accurate matplotlib and seaborn figures.
metadata:
  title: Exploring data with matplotlib
  summary: Quick, accurate matplotlib and seaborn figures for understanding data.
---

# Exploring data with matplotlib

Exploratory figures are for the analyst and the team. Their job is to show the
data accurately so you can understand it. Make them quickly, make many, and do
not polish them.

## Rules

- Set the style once, right after `fw.scaffold()`, with
  `sns.set_theme(style="whitegrid")`. If the project defines its own style in
  `functions/`, use that instead.
- Create figures with `fig, ax = plt.subplots()` and draw on `ax`. Pass `ax=ax`
  to seaborn so every figure has a handle you can save or publish.
- Compute the counts and summaries a figure needs in memory. Do not write a new
  dataset to `inputs/` just to draw figures.
- Close figures you are done with, `plt.close(fig)`, in loops and scripts.

## Choose the chart from the question

| Question | Chart | seaborn / matplotlib |
|---|---|---|
| How is one numeric variable distributed? | Histogram | `sns.histplot()` |
| How does a numeric variable differ across groups? | Box or strip plot | `sns.boxplot()`, `sns.stripplot()` |
| How many are in each category? | Bar chart, sorted | `sns.countplot()`, `ax.barh()` |
| How do two numeric variables relate? | Scatterplot | `sns.scatterplot()`, `sns.regplot()` |
| How does something change over time? | Line chart | `sns.lineplot()` |
| Does a pattern differ across subgroups? | Small multiples | `sns.relplot(col=...)`, `sns.catplot(col=...)` |

Avoid pie charts, dual y axes, and 3-D effects. They make comparisons harder to
read.

## Make it accurate

- **Histograms:** try more than one bin width. Set `binwidth` in the units of
  the variable.
- **Group summaries:** with a few hundred points or fewer, overlay the data
  with `sns.stripplot(..., alpha=0.3)` on a box plot.
- **Overplotting:** lower `alpha`, use smaller markers, or use `sns.histplot()`
  with both `x` and `y` for a 2-D histogram.
- **Bar charts:** the axis must start at zero. Use points when zero is not
  meaningful.
- **Missing values:** seaborn drops rows with `NaN` silently. Count them with
  `df[cols].isna().sum()` before you trust the figure.

## Saving and publishing

- Save exploratory figures to `outputs/figures/` with `fig.savefig(path)`.
- To share one, publish the figure object: `fw.publish(fig, slug="...")`.
