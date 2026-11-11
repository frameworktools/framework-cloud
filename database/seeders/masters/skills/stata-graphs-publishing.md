---
name: stata-graphs-publishing
description: Use when preparing Stata graphs for a paper, report, or presentation, including consistent scales, labels, uncertainty, and export.
metadata:
  title: Publication graphs in Stata
  summary: Consistent Stata graphs for papers, reports, and slides.
---

# Publication graphs in Stata

## Graph preparation

Set the intended dimensions and text sizes before polishing the graph. Use readable variable labels and explicit units. Keep scales comparable across related graphs. Explain any axis restriction or transformed scale.

Use a consistent scheme and accessible colors. Combine colors with symbols or line patterns when distinctions must survive grayscale. Place legends near the data they describe and keep categories in a consistent order.

For model graphs, calculate predictions or contrasts using the fitted model and its uncertainty estimator. State the interval level and prediction conditions in the caption. Do not treat a confidence interval as the range of individual outcomes.

Name graphs that need later editing or combination. Keep the graph commands in the analysis do-file. Export with `graph export` in the format the destination accepts, with explicit dimensions for raster output. Inspect the exported file for clipped labels, unreadable text, and shifted legends. Preserve an editable graph when useful.
