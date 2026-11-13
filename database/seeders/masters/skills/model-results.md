---
name: model-results
description: Use when presenting fitted models in tables or figures, including coefficients, intervals, predictions, and marginal effects.
metadata:
  title: Presenting model results
  summary: Estimates, uncertainty, and sample information in model tables and figures.
---

# Presenting model results

## Estimates

- Name the outcome, the model, and the estimation sample.
- Give the sample size and how uncertainty was estimated, including
  clustering or weights.
- Label units, reference groups, and the scale of each estimate: link,
  response, or exponentiated.
- Present estimates with intervals, and state the interval level.
- For an exponentiated estimate, exponentiate the interval limits.
- For nonlinear models and interactions, predictions or marginal contrasts
  can be easier to read than coefficients. State the covariate values used.
- Note changes in the sample between models.
- In R, check whether a tidying function such as `broom::tidy()` exponentiates the estimates and the interval limits together.
