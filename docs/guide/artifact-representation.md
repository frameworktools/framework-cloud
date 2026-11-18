# Artifact representation

Models, tables, and hypothesis tests published from the Framework packages for R, Python, and Stata carry a structured representation in the `framework.artifact/v1` schema. Framework Cloud validates and normalizes it when the artifact is published. The `data` field of an embed document holds that normalized representation for the `model`, `table`, and `test` kinds.

[`spec/embed.schema.json`](https://github.com/frameworktools/framework-embed/blob/main/spec/embed.schema.json) defines it formally, and [`SPEC.md`](https://github.com/frameworktools/framework-embed/blob/main/SPEC.md) specifies how the reference renderers display each field. This page describes the representation as embed documents deliver it, after normalization. [Embed integrations](embed-integrations.md) covers fetching and rendering documents.

## Top-level fields

```json
{
  "schema": "framework.artifact/v1",
  "kind": "model",
  "source": {"language": "r", "class": ["glm", "lm"]},
  "model": {"type": "glm", "family": "binomial", "link": "logit", "response": "am", "exponentiate": "Odds ratio"},
  "test": null,
  "tables": [{"title": "Coefficients", "role": "coefficients", "columns": ["..."], "rows": ["..."]}],
  "metrics": [{"name": "nobs", "value": 32}, {"name": "aic", "value": 16.06}],
  "formula": "am ~ wt + hp",
  "truncated": false
}
```

| Field | Contents |
|---|---|
| `schema` | Always `framework.artifact/v1`. |
| `kind` | `model`, `table`, or `test`. Matches the embed document's `kind`. |
| `source.language` | `r`, `python`, `stata`, or `file` for a data file published directly. |
| `source.class` | The object's classes in the source language, such as `["glm", "lm"]` or `["DataFrame"]`, with 0 to 20 entries. |
| `model` | Model description, or `null`. See [Models](#models). |
| `test` | Hypothesis test result for `test`, otherwise `null`. See [Hypothesis tests](#hypothesis-tests). |
| `tables` | Up to 10 tables. See [Tables](#tables). |
| `metrics` | Up to 50 named values. See [Metrics](#metrics). |
| `formula` | The model formula as text. Optional. |
| `variables`, `terms` | The data behind a model's terms. Optional. See [Model variables](#model-variables). |
| `truncated` | `true` when any table, list, or string was shortened. |

Fields marked optional may be absent. Unknown fields may appear within `v1` and should be ignored.

## Tables

```json
{
  "title": "Table",
  "role": "data",
  "columns": [
    {"name": "species", "type": "string",
     "summary": {"count": 60, "missing": 0, "distinct": 3, "top": [{"value": "Chinstrap", "count": 27}]}},
    {"name": "bill_length_mm", "type": "number", "label": "Bill length (mm)",
     "summary": {"count": 60, "missing": 2, "mean": 44.34, "sd": 4.41, "min": 35, "max": 54.9}}
  ],
  "rows": [["Adelie", 39.1], ["Gentoo", null]],
  "precision_note": null,
  "row_count": 60,
  "column_count": 2,
  "truncated": true
}
```

| Field | Contents |
|---|---|
| `title` | Table title, used as the caption. |
| `role` | `coefficients`, `random_effects`, `importance`, `smooth_terms`, `anova`, `priors`, `data`, or `null`. |
| `columns` | Up to 50 columns, each with `name` and `type`, and optionally `label` and `summary`. |
| `rows` | Rows as arrays in column order. Embed documents send the first 25 rows. |
| `row_count` | Rows in the full table. |
| `column_count` | Columns in the full table, which may exceed the columns sent. |
| `precision_note` | A note when large integers were converted to text, otherwise `null`. |
| `truncated` | `true` when rows, columns, or strings were cut. |

Column types are `number`, `string`, `boolean`, `date`, and `datetime`. Cell values are numbers, strings, booleans, or `null`. Dates are `YYYY-MM-DD` strings. Datetimes are `YYYY-MM-DDTHH:MM:SS` strings, or with a space in place of `T`, with optional fractional seconds and offset. A number column containing an integer beyond ±2^53 becomes a `string` column, with every value as text and `precision_note` set.

A column `summary` is computed over every row of the published table, including rows the embed document leaves out. In `table` documents, the reference renderers show a column's `label` and `summary` in a tip on its header. Each summary field is optional:

| Field | Contents |
|---|---|
| `count`, `missing`, `distinct` | Rows, missing values among them, and distinct values |
| `mean`, `sd`, `q1`, `median`, `q3` | For numbers |
| `min`, `max` | Numbers, or strings for dates and datetimes |
| `top` | Up to 8 of the most frequent values, as `{"value", "count"}` |
| `histogram` | `{"edges", "counts"}`, with up to 51 ascending edges and one count per bin |

### Model tables

In a model, a table with `term` and `estimate` columns is a coefficient table when its `role` is `coefficients`, or when it is the first table and has no role. Its standard columns are:

| Column | Contents |
|---|---|
| `term` | Term name as the package prints it, such as `wt`, `speciesChinstrap`, or `(Intercept)` |
| `estimate` | Estimate on the model's scale, such as log odds |
| `std_error` | Standard error |
| `statistic` | Test statistic |
| `p_value` | p-value |
| `conf_low`, `conf_high` | Interval bounds, at the level in the `confidence_level` metric |
| `df` | Degrees of freedom, when the package reports them per term |

Other columns, such as a multinomial model's outcome level, may appear and keep their own names.

A `random_effects` table lists group-level terms with `group`, `term`, `variance`, `std_dev`, and `correlation` columns as available. An `importance` table lists `feature` and `importance` for tree ensembles. `smooth_terms` and `anova` tables use the package's column names, such as `edf` and `ref_df`. A column's `label`, when present, names it for display.

## Metrics

Each metric is `{"name", "value"}`, where the value is a number, string, boolean, or `null`. Packages send the names their source objects use. Common metric names:

| Name | Meaning |
|---|---|
| `nobs` | Observations used |
| `confidence_level` | Interval level as a proportion, such as `0.95` |
| `interval_method` | `wald-t`, `wald-normal`, `profile`, `bootstrap`, or a package's own name |
| `vcov_type` | Standard error type, such as `HC1` |
| `aic`, `bic`, `log_likelihood`, `llf`, `deviance`, `null.deviance` | Fit statistics |
| `rsquared`, `r.squared`, `rsquared_adj`, `adj.r.squared`, `pseudo_rsquared` | R² variants |
| `df_resid`, `df_model`, `sigma`, `dispersion`, `converged`, `reml` | Model details |
| `n_groups.{factor}` | Group count for a random-effects factor |
| `n_events`, `concordance` | Survival models |
| `command` | The Stata command that fit the model, shown in place of a formula |

"Metrics" under the `model` section of `SPEC.md` lists every name the reference renderers label. Other names display with `_` and `.` replaced by spaces.

## Models

`model` describes how the model was fit. Every field is optional.

| Field | Contents |
|---|---|
| `type` | `linear`, `glm`, `mixed`, `survival`, `ordinal`, `multinomial`, `additive`, `fixed_effects`, `quantile`, `linear_ml`, `tree_ensemble`, or another value |
| `family`, `link` | Distribution family and link function, such as `binomial` and `logit` |
| `response` | Outcome variable name |
| `exponentiate` | The label of the ratio scale, such as `Odds ratio`, `Hazard ratio`, or `Rate ratio`, when the estimates are on a log scale and exponentiating them gives that ratio |
| `inference` | `frequentist` or `bayesian`. Absent means frequentist. |

"Model names" in `SPEC.md` maps `type`, `family`, and `link` to the name a reader sees, such as Logistic regression. `spec/model-names.json` holds test cases for it.

When `exponentiate` is set, the coefficient table still holds model-scale values. Renderers compute the ratio scale as `exp(estimate)` and `exp` of each bound, with the standard error `exp(estimate) * std_error`.

### Model variables

Packages may send `variables` and `terms` for the data the model was fit on. Both fields describe only the rows the model used.

```json
"variables": [
  {"name": "species", "role": "predictor", "type": "categorical", "label": "Penguin species",
   "levels": ["Adelie", "Chinstrap", "Gentoo"], "reference": "Adelie",
   "summary": {"count": 342, "missing": 0, "distinct": 3,
               "top": [{"value": "Adelie", "count": 151}, {"value": "Chinstrap", "count": 68}]}}
],
"terms": [
  {"term": "speciesChinstrap", "parts": [{"variable": "species", "level": "Chinstrap"}]}
]
```

`variables` lists up to 100 variables:

| Field | Contents |
|---|---|
| `name` | The variable's name in the data, before any transformation |
| `type` | `number`, `categorical`, `boolean`, `date`, `datetime`, or `string` |
| `role` | `response`, `predictor`, `group`, `weight`, or `offset`. Optional. |
| `label` | The variable label from the data. Optional. |
| `levels` | A categorical variable's levels in model order, up to 50. Optional. |
| `reference` | The level coefficients compare against. Optional. |
| `summary` | A column summary without `histogram`. Optional. |

`terms` maps up to 500 coefficient terms to variables. Each `term` matches a `term` cell exactly. `parts` lists 1 to 10 entries: one for a main effect, or one per factor of an interaction, in order. Each entry names a variable, with `level` for a categorical level. The intercept and terms a package cannot map are left out. Framework Cloud drops `terms` entries that name a variable missing from `variables`, and sends `terms` only when `variables` is present.

The reference renderers turn these fields into variable tips on coefficient terms. "Variable tips" in `SPEC.md` gives the text of each tip.

### Bayesian models

A model with `inference: "bayesian"` summarizes posterior draws. Its coefficient and random effects tables use these columns:

| Column | Contents |
|---|---|
| `estimate` | Posterior mean |
| `std_error` | Posterior standard deviation |
| `conf_low`, `conf_high` | Credible interval bounds, equal-tailed unless `interval_method` is `HDI` |
| `rhat` | R-hat |
| `ess_bulk`, `ess_tail` | Bulk and tail effective sample sizes |
| `ratio_estimate`, `ratio_std_error`, `ratio_conf_low`, `ratio_conf_high` | With `exponentiate`, the same summaries of the exponentiated draws |

Bayesian tables have no `statistic` or `p_value`. Renderers use the `ratio_` columns for the ratio scale and never exponentiate posterior summaries themselves. Without `ratio_` columns, a Bayesian model shows only the model scale.

Bayesian metrics may include `chains`, `draws`, `warmup`, `sampler`, `divergent_transitions`, `max_treedepth_hits`, `bayes_r2`, `elpd_loo`, `elpd_loo_se`, `looic`, `p_loo`, and `waic`. Stata models may also send `rhat_max`, `acceptance_rate`, `efficiency_min`, `efficiency_avg`, `efficiency_max`, `dic`, and `log_marginal_likelihood`.

A `priors` table lists `parameter`, `prior` (the distribution as text, such as `student_t(3, 0, 2.5)`), and `source` (`default` or `user`) when known.

"Bayesian models" in `SPEC.md` specifies the column labels and the convergence warnings renderers show.

## Hypothesis tests

```json
"test": {
  "method": "Welch Two Sample t-test",
  "alternative": "two.sided",
  "statistic": {"name": "t", "value": 1.915},
  "parameters": [{"name": "df", "value": 55.31}],
  "estimates": [{"name": "mean in group OJ", "value": 20.66}, {"name": "mean in group VC", "value": 16.96}],
  "null_value": [{"name": "difference in means between group OJ and group VC", "value": 0}],
  "p_value": 0.0606,
  "conf_int": {"level": 0.95, "low": -0.171, "high": 7.571}
}
```

| Field | Contents |
|---|---|
| `method` | Test name. The only required field. |
| `alternative` | `two.sided`, `less`, or `greater` |
| `statistic` | Test statistic as `{"name", "value"}` |
| `parameters` | Up to 20 named values, such as degrees of freedom |
| `estimates` | Up to 20 named estimates |
| `null_value` | Up to 20 named null hypothesis values |
| `p_value` | p-value between 0 and 1 |
| `conf_int` | `level` between 0 and 1, with `low`, `high`, or both. A one-sided interval omits one bound. |

A test may also carry `tables` and `metrics` with further details.

## Limits

Framework Cloud enforces these limits when it normalizes a representation. Entries past a limit are dropped. For tables, rows, columns, metrics, source classes, variables, levels, terms, and strings, `truncated` is also set.

| Item | Limit |
|---|---|
| Tables | 10 |
| Columns per table | 50 |
| Rows per table in an embed document | 25 |
| Metrics | 50 |
| Source classes | 20 |
| Variables | 100 |
| Levels per variable | 50 |
| Terms | 500 |
| Names, labels, and titles | 200 characters |
| String cell, metric, and `formula` values | 10,000 characters |
| `top` values | 8 entries of up to 80 characters each |
| Parts per term | 10 |
| Test `parameters`, `estimates`, and `null_value` | 20 entries each |
