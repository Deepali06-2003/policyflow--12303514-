#!/usr/bin/env bash
set -euo pipefail
BASE="${BASE:-http://127.0.0.1:8000/api}"
curl -sS -X POST "$BASE/policy-requests" -H 'Content-Type: application/json' --data @examples/policy-request.json
echo
