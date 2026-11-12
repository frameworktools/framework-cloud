---
name: writing
description: Use before writing or editing any prose in a data analysis project - text about results, notebook text, captions, READMEs, summaries, or messages that explain findings. Covers a plain register, stating numbers with their uncertainty, claims that match the design, consistent names, headings, and banned patterns. It governs how sentences read; for building and publishing a report, use framework-reports.
metadata:
  title: Writing
  summary: Plain, exact prose about data and findings.
---

# Writing

Write plain declarative sentences and let the numbers carry the argument. Do
not manage the reader with drama, metaphors, overselling, or hedging.

When a sentence is in doubt, ask whether it tells the reader something they
can check. Keep only sentences that do.

These rules cover how prose reads. They do not set the structure of a report,
a notebook, or a paper. A project's own skills can add rules for its
deliverables.

## Register

- Prefer a clear, measured, lightly academic register.
- Use short sentences with one claim each.
- Give a count its own short sentence instead of a trailing aside.
- Use bullets for parallel lists and paragraphs for arguments.
- End a sentence when its information ends.
- Prefer American spelling when the author works in the United States, such as
  `color`, `gray`, `catalog`, and `analyze`. Prefer British spelling when the
  author works where British spelling is standard, such as `colour`, `grey`,
  `catalogue`, and `analyse`.

## Numbers

- Use only numbers that come from the analysis output. Never write a number
  from memory, and never round one you have not seen.
- State an estimate with its uncertainty, its units, and the sample it
  describes: "Mean systolic pressure was 4.2 mmHg higher in the treatment
  group (95% CI 1.8 to 6.6; n = 412)."
- Give counts with their denominators: "38 of 412 participants (9%)".
- Round to the precision the data supports. Use the same number of decimals
  for the same quantity everywhere.
- Write "statistically significant" only about a test. Never use
  "significant" to mean large or important.
- Report null and unexpected results in the same plain register as expected
  ones.

## Claims

Match each verb to what the design can support.

- Use causal verbs ("increased", "reduced", "caused", "led to") only for
  randomized or explicitly causal designs.
- For observational associations, use "was associated with", "was higher
  among", or "differed by". Keep the same verbs in titles and summaries.
- Call an exploratory analysis exploratory.
- State a limitation once, specifically, where it applies: "Income was
  self-reported and missing for 14% of households." Do not stack hedges such
  as "may potentially suggest".

## Names

- Call variables by what they measure ("household income"), not their column
  names (`hh_inc_2`). Use code formatting for a column name only when the
  reader needs to find it in the data.
- Give each group one name and keep it. Do not alternate "treatment",
  "intervention", and "exposed" for one group.
- Define an abbreviation at first use, and only when it appears again.
- Use the field's actual terms for statistical concepts. Do not explain them
  with metaphors.

## Headings and labels

- Use descriptive nouns or short noun phrases, such as "Sample", "Primary
  outcome", and "Sensitivity analyses".
- Never start a heading with How, What, Why, Where, or When.
- No eyebrow labels above headings, no decorative numbering, and no clever or
  metaphorical headings.
- A figure or table title states its content, or a finding the analysis has
  established.

## Oxford comma

Put a comma before the final `and` or `or` in a list of three or more: "age,
sex, and income". Two items take no comma. Do not add commas that grammar does
not require.

## Banned patterns

Treat each of these as a firm ban.

- **Overselling.** "Striking", "remarkable", "powerful", "robust" (unless it
  names a robustness check), "clearly shows", and "proves".
- **`X, not Y` antithesis and dramatic reversals.** State the result once.
- **Throat-clearing.** "It is worth noting that", "Interestingly", "The key
  takeaway is", and "Let's dive into".
- **Self-commentary.** Do not tell the reader that a result matters or that
  the analysis is careful. Name the check that makes it so.
- **Narrating the obvious.** "Now we load the data." Say what a step is for,
  or say nothing.
- **Metaphors for statistical ideas.** No "signal in the noise", "the data
  tell a story", or "peeling back the layers".
- **Sloganized closers.** End when the information ends.
- **Fragment-style drama.** Write complete sentences.
- **Em dash overuse.** Prefer a second sentence or parentheses.

## Final check

Before returning a draft:

1. Trace every number to the output it came from.
2. Check that each estimate has its uncertainty, units, and sample.
3. Check that verbs match the design, in titles and summaries too.
4. Check that each variable and group has one plain name.
5. Check headings for nouns, not questions or wordplay.
6. Search for the banned patterns, then check spelling and the Oxford comma.
