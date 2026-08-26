#!/usr/bin/env sh

set -eu

vite_pid=''

cleanup() {
    rm -f public/hot

    if [ -n "$vite_pid" ]; then
        kill "$vite_pid" 2>/dev/null || true
    fi
}

trap cleanup EXIT INT TERM HUP

rm -f public/hot
npm run dev -- --host 0.0.0.0 --port "${VITE_DEV_PORT:-5174}" &
vite_pid=$!
wait "$vite_pid"
