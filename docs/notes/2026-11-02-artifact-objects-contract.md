# Published objects: what R and Python send, and what Cloud shows

`publish(object)` in the R package and framework-py turns common objects into
files that Framework Cloud can read well. This note is the contract between
the three. Everything here is additive to `framework.artifact/v1`: the schema
string stays the same, older documents stay valid, and Cloud ignores fields it
doesn't know.

Two rules hold for every object:

- **No observations from models.** Model exports carry coefficients, fit
  statistics, and other aggregates. They never carry the rows the model was
  fit on, residuals per row, or a serialized model.
- **Tables are the data the person chose to publish.** A published data frame
  carries its rows, as it does today.

## Which file each object becomes

| Object | R | Python | File |
|---|---|---|---|
| Data frame | `data.frame`, tibble, `data.table` | pandas `DataFrame`, polars `DataFrame` | `table.json` (kind `table`) |
| One column | atomic vector is not accepted | pandas `Series`, polars `Series` | `table.json`, one column named after the series (or `value`) |
| Matrix or array | `matrix` (numeric, character, or logical) | numpy `ndarray` with 1 or 2 dimensions | `table.json`; row names become a first column `row` |
| Contingency table | `table`, `xtabs` | `pandas.crosstab` output is a DataFrame | `table.json`; one-way tables become `value`, `count`; two-way tables are wide, with the row variable as the first column |
| Hypothesis test | `htest` (`t.test`, `chisq.test`, `wilcox.test`, `cor.test`, `prop.test`, `fisher.test`, `shapiro.test`, `ks.test`, ...) | scipy.stats results (`ttest_*`, `pearsonr`, `spearmanr`, `mannwhitneyu`, `wilcoxon`, `kruskal`, `f_oneway`, `chi2_contingency`, `shapiro`, `ks_2samp`, ...) | `test.json` (kind `test`) |
| Regression model | `lm`, `glm`, `aov`, `MASS::glm.nb`, `MASS::polr`, `nnet::multinom`, `lme4` `lmerMod`/`glmerMod`, `survival::coxph`, `survival::survreg`, `fixest` (`feols`, `feglm`, `fepois`), `mgcv::gam` | statsmodels results (OLS, WLS, GLM, Logit, Probit, Poisson, NegativeBinomial, MixedLM, QuantReg, ...) | `model.json` (kind `model`) |
| ANOVA table | `anova()` output, `summary(aov)` | `statsmodels.stats.anova.anova_lm` output (a DataFrame) | `table.json` with the row names kept |
| Fitted machine learning model | not in scope | scikit-learn linear models (`coef_`), tree ensembles (`feature_importances_`), xgboost (`XGBModel`, `Booster`), lightgbm (`LGBMModel`, `Booster`) | `model.json` (kind `model`) |
| Static plot | `ggplot` (and patchwork), base graphics from `recordPlot()`, lattice `trellis` | matplotlib `Figure` or `Axes`, seaborn `FacetGrid`/`JointGrid`/`PairGrid` or `Axes` | `figure.svg` when an SVG device is available (svglite in R, matplotlib always), else `figure.png` at 2x resolution (7 x 5 inches) |
| Interactive plot | plotly and other htmlwidgets | plotly `Figure` | `figure.html`, self-contained; plotly may load plotly.js from its CDN |
| Vega-Lite chart | `vegawidget` if installed | altair `Chart` (any top-level chart) | `figure.vl.json`, the Vega-Lite specification with its data inlined |
| Formatted table | `gt_tbl`, `gtsummary`, `flextable` (unchanged) | pandas `Styler` | `table.html` |

A ggplot that fails to save, or any object not listed, stops with the same
"publish() accepts ..." message, updated to name the new types.

## `table` additions

Each entry in `tables[].columns` may carry two optional fields.

```json
{
  "name": "bill_length_mm",
  "type": "number",
  "label": "Bill length (mm)",
  "summary": {
    "count": 344, "missing": 2, "distinct": 164,
    "mean": 43.92, "sd": 5.46, "min": 32.1, "q1": 39.23, "median": 44.45, "q3": 48.5, "max": 59.6,
    "histogram": {"edges": [32, 34, 36, 38], "counts": [9, 21, 17]}
  }
}
```

- `label` is a variable label (R `attr(x, "label")`, haven labels). Optional.
- `summary` is computed by the client over **all** rows, not only the rows
  sent, so it stays right when Cloud shows a preview.
  - Every type: `count` (rows), `missing`, `distinct`.
  - `number`: `mean`, `sd`, `min`, `q1`, `median`, `q3`, `max`, and
    `histogram` with up to 20 equal-width bins (`edges` has one more entry
    than `counts`). Leave out statistics that can't be computed (all missing).
  - `string` and `boolean`: `top`, a list of up to 8 `{"value": ..., "count": n}`
    pairs, most frequent first. Values longer than 80 characters are cut.
  - `date` and `datetime`: `min` and `max` as ISO strings.
- Clients send summaries for up to 200 columns and skip them beyond that.

`tables[]` entries may carry an optional `role`, used by Cloud to choose how
to draw them:

| Role | Meaning |
|---|---|
| `coefficients` | One row per term: `term`, `estimate`, and optionally `std_error`, `statistic`, `p_value`, `conf_low`, `conf_high`. A model's first table when no role is given. |
| `random_effects` | Variance components of a mixed model: `group`, `term`, `variance`, `std_dev`, and optionally `correlation`. |
| `importance` | Feature importance: `feature`, `importance`, and optionally `importance_type` as a metric. Sorted by importance, largest first. |
| `smooth_terms` | `mgcv` smooth terms: `term`, `edf`, `ref_df`, `statistic`, `p_value`. |
| `anova` | An ANOVA table. |
| `data` | Plain data (the default for `table` documents). |

Row names: when a data frame has row names that aren't the default 1..n (R)
or an index that isn't a default `RangeIndex` (pandas), the export adds them
as the first column, named `row` in R and the index name (or `index`) in
pandas.

## `model` additions

An optional `model` object describes the fit.

```json
"model": {
  "type": "glm",
  "family": "binomial",
  "link": "logit",
  "response": "admitted",
  "exponentiate": "Odds ratio"
}
```

- `type`: one of `linear`, `glm`, `mixed`, `survival`, `ordinal`,
  `multinomial`, `additive`, `fixed_effects`, `quantile`, `linear_ml`,
  `tree_ensemble`, or `other`.
- `family` and `link`: as the fitting library names them, lower case
  (`gaussian`, `binomial`, `poisson`, `negative binomial`, `gamma`;
  `identity`, `logit`, `probit`, `log`, `inverse`).
- `response`: the outcome variable's name, when known.
- `exponentiate`: the label for exponentiated estimates when they have a
  standard reading: `Odds ratio` (logit link), `Rate ratio` (log link on
  counts), `Hazard ratio` (Cox models), `Time ratio` (accelerated failure
  time with a log link). Leave it out otherwise. Estimates in the
  coefficient table stay on the model's scale; Cloud computes the
  exponentiated values.

Multi-equation models (`multinom`, `polr` thresholds) keep one row per term,
with the equation in the term name: `"setosa: Sepal.Width"`, or a
`component` column such as `"threshold"`.

Machine learning models send what they have. scikit-learn linear models send
a `coefficients` table with `term` and `estimate` only (no standard errors),
plus an `(Intercept)` row. Tree ensembles and boosting send an `importance`
table and metrics such as `n_estimators`, `max_depth`, `learning_rate`, and
`n_features`. Hyperparameters go in `metrics`; skip any that aren't scalar.

Fit statistics keep their current names (`nobs`, `aic`, `bic`, `r.squared`,
`rsquared`, `log_likelihood`, `llf`, `deviance`, ...). New common names:
`n_groups` (mixed models, one metric per grouping factor as
`n_groups.<factor>`), `concordance` (Cox), `theta` (negative binomial),
`pseudo_rsquared`.

## `test` kind

```json
{
  "schema": "framework.artifact/v1",
  "kind": "test",
  "source": {"language": "r", "class": ["htest"]},
  "test": {
    "method": "Welch Two Sample t-test",
    "alternative": "two.sided",
    "statistic": {"name": "t", "value": -2.71},
    "parameters": [{"name": "df", "value": 31.2}],
    "p_value": 0.0108,
    "estimates": [{"name": "mean in group A", "value": 5.0}, {"name": "mean in group B", "value": 6.2}],
    "null_value": [{"name": "difference in means", "value": 0}],
    "conf_int": {"level": 0.95, "low": -2.1, "high": -0.3}
  },
  "tables": [],
  "metrics": []
}
```

- `method` is required. Everything else is optional; leave out what the test
  doesn't report.
- `alternative`: `two.sided`, `less`, or `greater`.
- R: from the `htest` fields of the same names (`data.name` is left out,
  because it can contain expressions with data).
- scipy: `method` is the function's readable name ("Welch's t-test" when
  `equal_var=False` is detectable, otherwise "t-test"), the statistic and
  p-value from the result, `df` when the result has it, and `conf_int` from
  `result.confidence_interval()` when the result has that method.
- Tests with a table of results (`chisq.test` expected counts, pairwise
  tests) may add `tables`; Cloud shows them after the summary.

## Plots

- SVG is preferred: it is sharp at any size and small for most charts. Fall
  back to PNG at 2x (300 dpi at 7 x 5 inches) when the SVG would be larger
  than 5 MB (for example, a scatter plot of many points) or no SVG device is
  available.
- The background is white, as most plots assume. Cloud shows figures on a
  white card in both themes.
- Vega-Lite: `figure.vl.json` with `$schema` pointing at Vega-Lite. Cloud
  draws it with vega-embed and the visitor's theme.

## Cloud

- Accepts `kind: test`, `model`, column `label` and `summary`, table `role`,
  and Vega-Lite files.
- Tables: a column profile above the rows (type, missing share, summary
  statistics, and a small histogram or top values per column).
- Models: the forest plot from the `coefficients` table, with a switch to the
  exponentiated scale when `model.exponentiate` is set; a bar chart for an
  `importance` table; other role tables after the coefficients.
- Tests: a summary card with the method, statistic, parameters, p-value,
  estimates, and interval, with an interval chart when there's an estimate
  and a confidence interval.
- Figures: a centered white card with the figure, and download links.

## Fixtures

Each client writes one file per supported object to a catalog folder by
running its exporter on real objects (`/tmp/fwtest/artifacts/r/` and
`/tmp/fwtest/artifacts/python/`), so Cloud can be checked against real output.
