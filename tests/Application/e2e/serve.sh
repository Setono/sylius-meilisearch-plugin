#!/bin/sh
set -eu

# Starts the test application for the Playwright end-to-end suite.
#
# The autocomplete widget queries Meilisearch directly from the browser using
# MEILISEARCH_SEARCH_KEY, which is not set in .env. We resolve the instance's
# auto-generated search-only key here and export it before starting the server.

# Always run from tests/Application, regardless of the caller's working directory
cd "$(dirname "$0")/.."

MEILISEARCH_URL="${MEILISEARCH_URL:-http://localhost:7700}"
MEILISEARCH_MASTER_KEY="${MEILISEARCH_MASTER_KEY:-aSampleMasterKey}"
PORT="${E2E_PORT:-8080}"

# Wait for Meilisearch to be reachable (max ~30s)
tries=0
until curl -fsS "$MEILISEARCH_URL/health" >/dev/null 2>&1; do
    tries=$((tries + 1))
    if [ "$tries" -gt 30 ]; then
        echo "Meilisearch is not reachable at $MEILISEARCH_URL." >&2
        echo "Start it with: docker compose up -d --wait   (from tests/Application)" >&2
        exit 1
    fi
    sleep 1
done

# Resolve the "Default Search API Key" (the key whose actions are exactly ["search"]).
# Uses PHP rather than jq so we don't add a tooling dependency.
MEILISEARCH_SEARCH_KEY="$(MEILISEARCH_URL="$MEILISEARCH_URL" MEILISEARCH_MASTER_KEY="$MEILISEARCH_MASTER_KEY" php -r '
    $response = file_get_contents(getenv("MEILISEARCH_URL") . "/keys", false, stream_context_create([
        "http" => ["header" => "Authorization: Bearer " . getenv("MEILISEARCH_MASTER_KEY")],
    ]));
    if (false === $response) {
        fwrite(STDERR, "Could not read the API keys from Meilisearch" . PHP_EOL);
        exit(1);
    }
    foreach (json_decode($response, true)["results"] as $key) {
        if (["search"] === $key["actions"]) {
            echo $key["key"];
            exit(0);
        }
    }
    fwrite(STDERR, "No search-only API key found on the Meilisearch instance" . PHP_EOL);
    exit(1);
')"

export MEILISEARCH_SEARCH_KEY
export APP_ENV="${APP_ENV:-test}"

echo "Serving tests/Application at http://127.0.0.1:$PORT (APP_ENV=$APP_ENV)"
exec symfony serve --no-tls --port="$PORT"
