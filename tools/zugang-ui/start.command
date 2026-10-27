#!/bin/bash
# Doppelklick (macOS) oder ./start.command – startet die Zugang-UI lokal und öffnet den Browser.
cd "$(dirname "$0")" && exec python3 server.py "$@"
