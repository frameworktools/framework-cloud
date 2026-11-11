---
name: analysis-writing
description: Use when writing prose about a data analysis - results paragraphs, methods sections, notebook narrative, figure and table captions, report summaries, abstracts, or messages that explain findings. Covers reporting estimates with their uncertainty, matching claims to the design, naming variables and samples plainly, and the banned patterns that make analytic writing sound performed.
metadata:
  title: Writing up analyses
  summary: Plain, exact prose for results, methods, captions, and notebook narrative.
---

# Writing up analyses

Analytic writing tells a reader what was done, what was found, and how sure
the analysis can be. Write plain declarative sentences and let the numbers
carry the argument. Do not manage the reader with drama, metaphors,
overselling, or hedging.

When a sentence is in doubt, ask whether it reports something the reader can
check. Keep only sentences that do.

## Source of every number

- Take every estimate, count, and p-value from the analysis output: a model
  object, a saved table, or a published artifact. Never type a number from
  memory or round one you have not seen.
- If the output does not contain a number the text needs, compute it in the
  notebook or say that it is missing. Do not estimate it.
- When the analysis changes, rerun it and update the text from the new
  output. Prefer inline code (`` `r ` `` in Quarto, f-strings in Python) so
  numbers in the prose come from the same run as the tables.

## Results

Report each result as the comparison, the estimate, its uncertainty, and the
sample it describes.

- Give the estimate with its interval: "Mean systolic pressure was 4.2 mmHg
  higher in the treatment group (95% CI 1.8 to 6.6; n = 412)."
- Report effect sizes in the outcome's units, or say which standardized
  measure you used. A p-value alone is not a result.
- Write "statistically significant" when you mean the test, and avoid it
  where an interval says more. Never use "significant" to mean large or
  important.
- Give counts with their denominators: "38 of 412 participants (9%)".
- Round to the precision the data supports. Keep the same number of decimals
  for the same quantity across text, tables, and figures.
- State units, the time period, and the population every time they could be
  unclear.
- Report null and unexpected results in the same plain register as the
  expected ones.

## Claims and design

Match each verb to what the design can support.

- Use causal verbs ("increased", "reduced", "caused", "led to") only for
  randomized or explicitly causal designs whose assumptions you state.
- For observational associations, use "was associated with", "was higher
  among", or "differed by". Do not slip back into causal verbs in the
  summary or the title.
- Label exploratory analyses as exploratory. Say which analyses were planned
  in advance when that is known.
- Say what a model adjusted for in plain words, then name the variables.
- State limitations once, specifically, in the place they apply: "Income was
  self-reported and missing for 14% of households." Do not stack hedges such
  as "may potentially suggest".

## Methods

Write methods so another analyst could repeat the analysis.

- Name the data source, the sample, the inclusion rules, and the counts
  removed at each step.
- Name the model, the outcome, the predictors, and the software with its
  version: "logistic regression in R 4.4 (stats::glm)".
- Say how missing values were handled and how many there were.
- Describe what was done in the past tense, in the order it was done.

## Names

- Call variables by what they measure ("household income"), not their column
  names (`hh_inc_2`). Put a column name in code formatting only when the
  reader needs to find it in the data.
- Name groups the same way every time. Do not alternate "treatment",
  "intervention", and "exposed" for one group.
- Define an abbreviation at first use, and only when it appears again.
- Use the field's actual terms for statistical concepts. Do not explain a
  confidence interval or a regression with a metaphor.

## Figures and tables

- A figure or table title states its content or its finding as a sentence of
  fact. Do not state a finding the analysis has not established.
- A caption says what is shown, for whom, and when, then gives the data
  source, the sample size, and any caveat a reader needs to read it alone.
- Every axis and column header has a plain name and units.
- Refer to each figure or table in the text and say what to look at in it.

## Notebook narrative

- Open a notebook with its question and the data it uses, in two or three
  sentences.
- Before a code chunk, say what it does and why in one sentence. After an
  output that matters, say what it shows.
- Delete narration of the obvious, such as "Now we load the data."
- Keep scratch work out of a notebook that others will read, or move it to
  a clearly labeled section.

## Register

- Prefer a clear, measured, lightly academic register.
- Use short sentences with one claim each. Give a count its own sentence.
- Use bullets for parallel lists and paragraphs for arguments.
- Use headings that name the content, such as "Sample", "Primary outcome",
  and "Sensitivity analyses". Never start a heading with How, What, Why,
  Where, or When.
- Use American spellings and the Oxford comma.

## Banned patterns

Treat each of these as a firm ban.

- **Overselling.** "Striking", "remarkable", "powerful", "robust" (unless it
  names a robustness check), "clearly shows", and "proves".
- **`X, not Y` antithesis and dramatic reversals.** State the result once.
- **Throat-clearing.** "It is worth noting that", "Interestingly", "The key
  takeaway is", and "Let's dive into".
- **Self-commentary.** Do not tell the reader a result is important or that
  the analysis is careful. Show the check that makes it so.
- **Metaphors for statistical ideas.** No "signal in the noise", "the data
  tell a story", or "peeling back the layers".
- **Sloganized closers.** End when the information ends.
- **Em dash overuse.** Prefer a second sentence or parentheses.

## Final check

Before returning a draft:

1. Trace every number to the output it came from.
2. Check that each estimate has its uncertainty, units, and sample.
3. Check that verbs match the design, in the title and summary too.
4. Check that variables and groups have one plain name each.
5. Check that every figure and table has a caption a reader can use alone.
6. Search for the banned patterns, then check spelling and the Oxford
   comma.
