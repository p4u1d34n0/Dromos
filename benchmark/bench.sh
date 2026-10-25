#!/bin/sh
set -e

GATEWAY="http://gateway:9501"
TOKEN="Bearer benchmark-token"
DURATION=10
WARMUP=3

echo ""
echo "========================================================"
echo "   Dromos + OpenSwoole Microservices Benchmark           "
echo "========================================================"
echo ""

# Wait for services
echo "[*] Waiting for services..."
RETRIES=0
until curl -sf "$GATEWAY/health" > /dev/null 2>&1; do
    RETRIES=$((RETRIES + 1))
    if [ $RETRIES -gt 30 ]; then
        echo "[!] Gateway did not become ready after 30s"
        exit 1
    fi
    sleep 1
done
echo "[+] All services ready"
echo ""

# Warmup
echo "[*] Warming up ($WARMUP seconds)..."
wrk -t2 -c10 -d${WARMUP}s "$GATEWAY/health" > /dev/null 2>&1
wrk -t2 -c10 -d${WARMUP}s -H "Authorization: $TOKEN" "$GATEWAY/api/users" > /dev/null 2>&1
echo "[+] Warmup complete"
echo ""

run_wrk() {
    local threads=$1
    local conc=$2
    shift 2

    if [ $threads -gt 4 ]; then threads=4; fi
    if [ $threads -lt 1 ]; then threads=1; fi

    wrk -t$threads -c$conc -d${DURATION}s --latency "$@" 2>&1 | \
        grep -E "Latency|Req/Sec|requests in|Requests/sec|50%|99%|Socket errors|Non-2xx"
    echo ""
}

for CONC in 1 10 50 100 200; do
    echo "--------------------------------------------------------"
    echo "  Concurrency: $CONC connections | Duration: ${DURATION}s"
    echo "--------------------------------------------------------"
    echo ""

    echo "  GET /health (no middleware)"
    run_wrk $CONC $CONC "$GATEWAY/health"

    echo "  GET /api/users (auth+cors+ratelimit+proxy)"
    run_wrk $CONC $CONC -H "Authorization: $TOKEN" "$GATEWAY/api/users"

    echo "  GET /api/users/50 (single resource lookup)"
    run_wrk $CONC $CONC -H "Authorization: $TOKEN" "$GATEWAY/api/users/50"

    echo "  GET /api/products (second service)"
    run_wrk $CONC $CONC -H "Authorization: $TOKEN" "$GATEWAY/api/products"

    echo "  POST /api/users (write+validation)"
    run_wrk $CONC $CONC -s /app/benchmark/post.lua -H "Authorization: $TOKEN" -H "Content-Type: application/json" "$GATEWAY/api/users"
done

echo "========================================================"
echo "   Benchmark Complete                                    "
echo "========================================================"
