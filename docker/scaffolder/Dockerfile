# syntax=docker/dockerfile:1
#
# The scaffolder: builds a project from its layers with the real tools, so a
# downloaded zip matches what GitHub and a person's own computer would make.
# fw runs the framework R package for R, framework-py for Python, and builds
# Stata projects itself. Framework Cloud posts layers to it and gets a zip.
#
#   docker build -t framework-scaffolder docker/scaffolder
#
# Each tool is pinned to a commit. Change the pins here and rebuild to move
# them; all three are public repositories under github.com/frameworktools.

ARG FW_REF=baca79d982531279a3b89590e3dc4b98294149d7
# The release FW_REF is tagged as, so fw --version reports it
ARG FW_VERSION=0.6.5
ARG FRAMEWORK_REF=0f12d4155e03966a019b7edb24ab4dd49ed6573c
ARG FRAMEWORK_PY_REF=e0d707fc394ed5c3d2959669b930357ced438c27

# 1. fw, built from source at its pin
FROM golang:1.27 AS fw
ARG FW_REF
ARG FW_VERSION
RUN git clone --filter=blob:none https://github.com/frameworktools/framework-cli /src \
    && cd /src && git checkout --quiet "$FW_REF" \
    && CGO_ENABLED=0 go build -trimpath \
       -ldflags "-s -w -X github.com/frameworktools/framework-cli/internal/cli.version=${FW_VERSION} -X github.com/frameworktools/framework-cli/internal/cli.commit=$(git rev-parse --short=12 HEAD)" \
       -o /fw ./cmd/fw

# 2. R with the framework package and renv, plus Python with framework-py
FROM rocker/r-ver:4.5
ARG FW_REF
ARG FRAMEWORK_REF
ARG FRAMEWORK_PY_REF

LABEL org.opencontainers.image.title="Framework scaffolder" \
      org.opencontainers.image.source="https://github.com/frameworktools/framework-cloud" \
      org.opencontainers.image.licenses="MIT" \
      io.framework.fw="${FW_REF}" \
      io.framework.r="${FRAMEWORK_REF}" \
      io.framework.py="${FRAMEWORK_PY_REF}"

RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 python3-venv git ca-certificates zip \
    && rm -rf /var/lib/apt/lists/*

RUN Rscript -e 'install.packages("pak")' \
    && Rscript -e "pak::pak(c('frameworktools/framework@${FRAMEWORK_REF}', 'renv'))" \
    && Rscript -e 'stopifnot(exists("project_create_from_layers", envir = asNamespace("framework")))'

RUN python3 -m venv /opt/framework-py \
    && /opt/framework-py/bin/pip install --no-cache-dir "framework-py @ git+https://github.com/frameworktools/framework-py@${FRAMEWORK_PY_REF}"

COPY --from=fw /fw /usr/local/bin/fw
COPY server.py /srv/server.py

# fw finds R on the PATH; this pins it to the venv's framework-py
ENV FW_PYTHON=/opt/framework-py/bin/python \
    SCAFFOLDER_PORT=8000 \
    PYTHONUNBUFFERED=1 \
    FW_REF=${FW_REF} \
    FRAMEWORK_REF=${FRAMEWORK_REF} \
    FRAMEWORK_PY_REF=${FRAMEWORK_PY_REF}

RUN useradd --create-home --uid 10001 scaffolder
USER scaffolder
WORKDIR /home/scaffolder

EXPOSE 8000
HEALTHCHECK --interval=30s --timeout=3s CMD python3 -c "import urllib.request; urllib.request.urlopen('http://127.0.0.1:8000/health')"
CMD ["python3", "/srv/server.py"]
