#!/usr/bin/env bash
# ==============================================================================
# WebsiteTailors Load Test Automation CLI Runner
# Usage:
#   ./runner.sh [scenario] [target_url]
# Examples:
#   ./runner.sh baseline http://127.0.0.1:8088
#   ./runner.sh normal http://127.0.0.1:8088
#   ./runner.sh growth http://127.0.0.1:8088
#   ./runner.sh stress http://127.0.0.1:8088
#   ./runner.sh spike http://127.0.0.1:8088
#   ./runner.sh soak http://127.0.0.1:8088
# ==============================================================================

set -euo pipefail

SCENARIO="${1:-baseline}"
TARGET="${2:-http://127.0.0.1:8088}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$(dirname "$SCRIPT_DIR")")"
OUTPUT_DIR="${SCRIPT_DIR}/results"

mkdir -p "$OUTPUT_DIR"

echo "=========================================================="
echo "WebsiteTailors Load Testing Runner"
echo "Scenario:   $SCENARIO"
echo "Target URL: $TARGET"
echo "Timestamp:  $(date '+%Y-%m-%d %H:%M:%S')"
echo "=========================================================="

# Safety verification
if [[ "$TARGET" =~ websitetailors\.com ]] && [[ "${ALLOW_PRODUCTION:-false}" != "true" ]]; then
    echo "ERROR: Target is production domain ($TARGET) and ALLOW_PRODUCTION is not set to 'true'."
    echo "Refusing to run high-load test against production server without explicit authorization."
    exit 1
fi

K6_BIN="$(which k6 || echo "/opt/homebrew/bin/k6")"
if [ ! -x "$K6_BIN" ]; then
    echo "ERROR: k6 binary not found at $K6_BIN"
    exit 1
fi

TIMESTAMP="$(date '+%Y%m%d_%H%M%S')"
RESULT_FILE="${OUTPUT_DIR}/${SCENARIO}_${TIMESTAMP}.txt"
JSON_FILE="${OUTPUT_DIR}/${SCENARIO}_${TIMESTAMP}.json"

echo "Executing k6 test scenario [combined_load.js] under stage profile [$SCENARIO]..."

BASE_URL="$TARGET" SCENARIO="$SCENARIO" "$K6_BIN" run \
    --summary-export="$JSON_FILE" \
    "${SCRIPT_DIR}/scenarios/combined_load.js" | tee "$RESULT_FILE"

echo ""
echo "Cleaning up synthetic test records from database..."
php "${SCRIPT_DIR}/teardown_test_leads.php"

echo ""
echo "Test run completed. Results saved to:"
echo "  - Summary: $RESULT_FILE"
echo "  - Metrics JSON: $JSON_FILE"
