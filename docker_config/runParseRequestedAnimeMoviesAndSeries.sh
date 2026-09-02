#!/bin/bash
cd /usr/src/app 2>/dev/null || cd "$(dirname "$0")"
python3 parseRequestedAnimeMoviesAndSeries.py
