---
id: course-teaching
title: Course and teaching
section: project_types
position: 13
description: 'Project layout for lecture slides, assignments, readings, and course documents'
---
## Overview

The **Course/Teaching** project type organizes academic course materials, including Quarto RevealJS slides, student assignments, shared datasets, and syllabi.

## Directory structure

```
my-course/
├── assignments/         # Student assignment notebooks
├── cache/               # Cached computations
├── course_docs/         # Syllabi and schedules
├── data/                # Shared course datasets
├── functions/           # Shared R functions
├── modules/             # Default make_notebook() directory
├── readings/            # Reading lists and notes
├── scripts/             # Standalone scripts
├── slides/              # Lecture slide decks
├── framework.db         # Local metadata database
└── settings.yml         # Course configuration
```

## Course materials

### Slides

Create lecture decks with `make_notebook()`:

```r
make_notebook("intro-to-r", dir = "slides/week-01")
```

### Assignments

Create assignment documents in `assignments/`:

```r
make_notebook("assignment-01", dir = "assignments")
```

### Course documents

Create syllabi and schedules in `course_docs/`:

```r
make_notebook("syllabus", dir = "course_docs")
```

## Configuration

Example `settings.yml`:

```yaml
default:
  project_type: course

  directories:
    data: data
    slides: slides
    assignments: assignments
    course_docs: course_docs
    readings: readings

  packages:
    - name: dplyr
      auto_attach: true
    - name: ggplot2
      auto_attach: true
    - name: palmerpenguins
      auto_attach: false
```

## Content organization

### Weekly directories

```
slides/
├── week-01-introduction/
├── week-02-data-types/
├── week-03-data-wrangling/
└── week-04-visualization/
```

### Topic modules

```
slides/
├── foundations/
├── data-manipulation/
└── visualization/
```

## Course workflow

### Lecture preparation

Create lecture `.qmd` files under `slides/`:

```r
make_notebook("data-wrangling", dir = "slides/week-03")
```

### Assignment folders

Separate student instructions from instructor answer keys:

```
assignments/assignment-01/
├── instructions.qmd  # Distributed to students
└── solution.qmd      # Instructor solution key
```

### Quarto rendering

Slides render to `rendered/slides/` and assignments render to `rendered/assignments/`:

```bash
quarto render slides/week-01/lecture.qmd
```

## Conventions

### Prefix naming

Use zero-padded numeric prefixes so weeks and assignments sort chronologically:

```
week-01-*, week-02-*, ...
assignment-01, assignment-02, ...
```

### Shared R helpers

Use `functions/` for shared course utilities:

```r
# functions/grading.R
calculate_grade <- function(scores, weights) {
  sum(scores * weights) / sum(weights)
}
```
