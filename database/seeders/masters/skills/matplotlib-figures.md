---
name: matplotlib-figures
description: Use when making a matplotlib or seaborn figure in Python, from a quick look at the data to a figure for a paper, report, poster, or slides. Covers choosing the chart, accurate defaults, titles and labels, accessible color, honest scales, styles, and saving.
metadata:
  title: Figures with matplotlib
  summary: Accurate matplotlib and seaborn figures, from exploring data to publication.
---

# Figures with matplotlib

## Chart choice

Values on a shared axis are the easiest to compare.

| Question | Chart | seaborn or matplotlib |
|---|---|---|
| Distribution of one numeric variable | Histogram | `sns.histplot()` |
| A numeric variable across groups | Box plot with the points | `sns.boxplot()`, `sns.stripplot()` |
| Counts by category | Bar chart, sorted | `sns.countplot()`, `ax.barh()` |
| Two numeric variables | Scatterplot | `sns.scatterplot()`, `sns.regplot()` |
| Change over time or another ordered variable | Line chart | `sns.lineplot()` |
| A pattern across subgroups | Small multiples | `sns.relplot(col=...)`, `sns.catplot(col=...)` |

Avoid dual y axes and 3-D effects. In a stacked bar, only the bottom segment
sits on a shared baseline.

## Accuracy

- **Histograms:** try more than one bin width. Set `binwidth` in the units of
  the variable.
- **Group summaries:** a box plot hides group size and multiple peaks. With a
  few hundred points or fewer, overlay them with
  `sns.stripplot(..., alpha=0.3)`.
- **Overplotting:** lower `alpha`, use smaller markers, or pass both `x` and
  `y` to `sns.histplot()` for a 2-D histogram.
- **Skewed variables:** use `ax.set_xscale("log")` or `ax.set_yscale("log")`
  for strongly right-skewed values. Say so in the axis label.
- **Bars:** a bar's length encodes its value, so the axis starts at zero. Use
  points when zero is not meaningful.
- **Order:** sort categories by value unless they have a natural order.
- **Missing-value codes:** check the codebook for codes such as -99 before
  plotting, and recode them to missing.
- **Missing values:** seaborn drops rows with missing values without a
  warning. Count them with `df[cols].isna().sum()` before you trust the
  figure.
- **Panels:** panels that readers compare share axis limits, with
  `sharex=True` and `sharey=True`. Free scales suit panels not meant to be
  compared. Say so in the caption.

## Titles and labels

- **Axes:** use plain names with units, such as "Income per person (US
  dollars, log scale)". Replace column names such as `sbp_mean`.
- **Title:** a title that states the finding, such as "Blood pressure rises
  with age in every group", can help a figure communicate its result. Make
  sure it suits the venue. Some journals and readers prefer a matter-of-fact
  title that names the variables, such as "Systolic blood pressure by age".
  Follow the venue's style, and ask the user when it is unclear. Do not
  invent a finding.
- **Subtitle and caption:** say what is measured, for whom, and when. Give
  the data source, the sample size, and caveats such as the handling of
  missing values.
- **Direct labels:** with a few series, label lines or points directly with
  `ax.annotate()` or `ax.text()` and remove the legend.
- **Uncertainty:** show it with the same method and interval level as the
  analysis. Label a confidence interval as uncertainty in the estimate.

## Color

- For categories, use a palette readable with color vision deficiency, such
  as `sns.color_palette("colorblind")` or viridis. For ordered values, use
  viridis or a single-hue sequential palette.
- Pair color with labels, position, marker shape, or line style, so the figure
  still works in grayscale.

## Styles

A shared style is a good tactic when a project makes many figures. It keeps
them consistent and saves repeating the same settings. Define it once, as a
style sheet or a function that sets `rcParams`, and apply it to each figure.
If the project already has a style, use it. A one-off figure can set its style
directly.

## Saving

- Create figures with `fig, ax = plt.subplots(figsize=(...))` and pass
  `ax=ax` to seaborn, so each figure has a handle to save.
- Set the size before tuning text. Text that reads well on screen can be too
  small at column width.
- Prefer vector output, such as PDF or SVG, for line and text figures when the
  destination accepts it. Check the saved file for clipped labels.
- Close finished figures with `plt.close(fig)` in loops and scripts.
- In a Framework project, prefer `fw.save_figure(fig, "name")`.
