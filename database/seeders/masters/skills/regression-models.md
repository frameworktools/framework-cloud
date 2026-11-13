---
name: regression-models
description: Use when specifying, fitting, or diagnosing regression models for an analysis, including outcome families, adjustment, repeated observations, and uncertainty.
metadata:
  title: Fitting regression models
  summary: Model specification and diagnostics matched to the research question.
---

# Fitting regression models

## Specification

State the estimand, outcome, exposure or predictors, population, and observation unit before fitting. Distinguish explanation, prediction, and causal inference. A fitted association alone does not establish a causal effect.

Choose the outcome family and link from the measurement scale and sampling design. Specify reference levels, transformations, interactions, weights, and the handling of missing values. For clustered or repeated observations, choose an appropriate model or uncertainty estimator and document the clustering unit.

Record the analysis sample and compare it across specifications. Inspect convergence, separation, residual patterns, influential observations, and model-specific assumptions. For predictive work, split or resample at the independent sampling unit and fit preprocessing inside each training fold.

Do not choose an adjustment set or a final model solely from p-values. Explain specification changes using the research design and diagnostics.

## Language conventions

Keep the model formula, family, weights, and sample definition in code. Choose diagnostics and covariance estimators for the fitted model class.
