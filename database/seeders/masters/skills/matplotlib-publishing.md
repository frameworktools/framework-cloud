---
name: matplotlib-publishing
description: Use when preparing matplotlib figures for a paper, report, or presentation, including titles, layout, typography, accessible colors, and export.
metadata:
  title: Publication figures with matplotlib
  summary: Readable matplotlib figures at their final publication size.
---

# Publication figures with matplotlib

## Figure preparation

A title that states the finding can help a figure communicate its result. Make sure it suits the venue. Some journals and readers prefer a matter-of-fact title that names the variables. Follow the venue's style, and ask the user when it is unclear. Do not invent a finding; if the analysis has not established a result, use a descriptive title. A subtitle or caption can say what is measured, for whom, and when, with the data source and sample size.

Set the figure dimensions from the destination before tuning text or layout. Label axes with units and replace internal variable names with readable labels. Use a shared scale across comparable panels unless a different scale is explicit and justified.

Use direct labels when they reduce legend lookup. Pair color with shape, line style, or annotation where possible. Check that essential distinctions remain visible in grayscale and at the final size. Represent uncertainty using the same method and interval level as the analysis.

Use the object interface (`fig, ax = plt.subplots(...)`) and pass axes explicitly to plotting helpers. Check legends, tick labels, and annotations for clipping after layout. Export vector output for line and text figures when the destination supports it; use sufficient raster resolution for images. Inspect the exported file. Close completed figures in loops to avoid accumulating state.

A shared style is a good tactic when a project makes many figures. Define it once, as a style sheet or a function that sets `rcParams`, and apply it to each figure. If the project already has a style, use it. A one-off figure can set its style directly.
