---
name: model-results
description: Use when converting fitted models into coefficient tables, marginal effects, predictions, or model plots for a report.
metadata:
  title: Reporting model results
  summary: Estimates, uncertainty, and sample information for model tables and plots.
---

# Reporting model results

## Results

Identify the fitted model and its estimation sample. Label the outcome, model family, scale, reference groups, and units. Report the sample size and the uncertainty method, including clustering or weighting when used.

Present estimates with intervals. State whether an estimate is on the link scale, response scale, or exponentiated scale. For nonlinear models and interactions, use predictions or marginal contrasts when they answer the question more directly than coefficients. Specify the covariate distribution or values used in those calculations.

Match uncertainty calculations to the fitted model. Preserve covariance when calculating contrasts. Do not construct intervals for a transformed estimate by transforming its standard error.

Keep comparable specifications in aligned columns with a stable row order. Explain sample changes and omitted terms. Use sensible precision and explicit interval levels. Separate evidence of association from causal claims and substantive importance.

## Language conventions

Keep parameter names aligned with estimates and covariance matrices. Use the estimator's prediction or contrast methods when available and inspect their output scale.
