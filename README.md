# RootService Documentation (Zensical)

This repository contains the RootService documentation site, built with **Zensical**.

[![Documentation](https://github.com/RootService/zensical/actions/workflows/docs.yml/badge.svg)](https://github.com/RootService/zensical/actions/workflows/docs.yml)
[![License](https://img.shields.io/github/license/RootService/zensical)](LICENSE)
[![Last Commit](https://img.shields.io/github/last-commit/RootService/zensical.svg)](https://github.com/RootService/zensical/commits/main)

## Quickstart

Create a virtualenv and install Zensical:

```bash
python3 -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip
pip install zensical
```

Serve locally:

```bash
zensical serve
```

Build once:

```bash
zensical build --clean
```

## Repository structure

- `docs/` – documentation content
- `zensical.toml` – Zensical configuration
- `zensical_overrides/` – minimal Zensical theme overrides (e.g. additional meta tags)
- `overrides/` – legacy MkDocs-Material overrides (kept for reference; **not enabled**)
- `site/` – build output (generated)
- `.github/workflows/docs.yml` – GitHub Pages build/deploy workflow

## CI / Deploy

GitHub Actions workflow:

- **Documentation** (`.github/workflows/docs.yml`)
  - Trigger: `push` to `main` (and `master`)
  - Builds: `zensical build --clean`
  - Deploys: `site/` to GitHub Pages

## Maintainer note

If you change configuration, navigation, or workflows, update this README in the same PR/commit.
