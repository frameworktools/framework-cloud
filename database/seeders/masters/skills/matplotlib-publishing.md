---
name: matplotlib-publishing
description: Use when preparing matplotlib figures for a paper, report, or presentation, including layout, typography, accessible colors, and export.
metadata:
  title: Publication figures with matplotlib
  summary: Readable matplotlib figures at their final publication size.
---

# Publication figures with matplotlib

## Figure preparation

Set the figure dimensions from the destination before tuning text or layout. Label axes with units and replace internal variable names with readable labels. Use a shared scale across comparable panels unless a different scale is explicit and justified.

Use direct labels when they reduce legend lookup. Pair color with shape, line style, or annotation where possible. Check that essential distinctions remain visible in grayscale and at the final size. Represent uncertainty using the same method and interval level as the analysis.

Use the object interface (`fig, ax = plt.subplots(...)`) and pass axes explicitly to plotting helpers. Check legends, tick labels, and annotations for clipping after layout. Export vector output for line and text figures when the destination supports it; use sufficient raster resolution for images. Inspect the exported file. Close completed figures in loops to avoid accumulating state.
