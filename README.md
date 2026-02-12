# MKDocs Documentation

[![CI](https://github.com/RootService/MKDocs/actions/workflows/ci.yml/badge.svg)](https://github.com/RootService/MKDocs/actions/workflows/ci.yml)
[![License](https://img.shields.io/github/license/RootService/MKDocs)](LICENSE)
[![Last Commit](https://img.shields.io/github/last-commit/RootService/MKDocs.svg)](https://github.com/RootService/MKDocs/commits/main)

## Quickstart

```bash
python3 -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip
pip install --use-pep517 -r requirements.txt
mkdocs serve
```

Build once:

```bash
mkdocs build --clean
```

## Repository Structure

- `docs/` – content
- `overrides/` – MkDocs theme overrides
- `mkdocs.yml` – MkDocs configuration
- `requirements.txt` – Python dependencies
- `tools/` – helper scripts (Linux/BSD/Windows)
- `.github/workflows/ci.yml` – CI build workflow

## CI / Workflows

Only one workflow is active:

1. **CI** (`.github/workflows/ci.yml`)
   - Trigger: pull_request and push on `main`
   - Runner: `ubuntu-latest`
   - Steps:
     - checkout
     - setup Python
     - install dependencies
     - run `mkdocs build --clean`

There is currently:

- no deploy workflow
- no lint workflow
- no security/automation workflow

## Maintainer Note

If workflows or repository structure change, update this README in the same PR.
