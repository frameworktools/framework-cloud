---
name: writing
description: Use before writing or editing prose in a data analysis project, such as text about results, notebook text, captions, READMEs, and summaries of findings. Covers a plain register, numbers with their uncertainty, claims that match the design, and consistent names.
metadata:
  title: Writing
  summary: Plain, exact prose about data and findings.
---

# Writing

## Register

- Prefer plain declarative sentences with one claim each.
- Use bullets for parallel lists and paragraphs for arguments.
- Report null and unexpected results in the same register as expected ones.

## Numbers

- Use only numbers that come from the analysis output.
- State an estimate with its uncertainty, its units, and the sample it
  describes: "Mean systolic pressure was 4.2 mmHg higher in the treatment
  group (95% CI 1.8 to 6.6; n = 412)."
- Give counts with their denominators: "38 of 412 participants (9%)".
- Round to the precision the data supports, and use the same number of
  decimals for the same quantity throughout.
- Use "statistically significant" only about a test.

## Claims

- Use causal verbs such as "increased" and "caused" for randomized or
  explicitly causal designs.
- For observational associations, use "was associated with", "was higher
  among", or "differed by". Keep the same verbs in titles and summaries.
- Call an exploratory analysis exploratory.
- State a limitation once, specifically, where it applies: "Income was
  self-reported and missing for 14% of households."

## Names

- Call variables by what they measure, such as "household income". Use a
  column name such as `hh_inc_2` only when the reader needs to find it in the
  data.
- Give each group one name and keep it.
- Define an abbreviation at first use.
- Use the field's terms for statistical concepts.

## Wording

Prefer plain wording to these:

- Overselling, such as "striking", "remarkable", "clearly shows", and
  "proves".
- Openers such as "It is worth noting that" and "Interestingly".
- Narration of each step, such as "Now we load the data". Say what a step is
  for.
- Stacked hedges such as "may potentially suggest".
