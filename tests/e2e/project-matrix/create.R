args <- commandArgs(trailingOnly = TRUE)
library(framework)
project_create_from_layers(args[[1]], args[[2]], interactive = TRUE)
