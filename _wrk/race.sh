#!/bin/bash

curl -s -o /tmp/resp1.json -w "resp1: %{http_code}\n" -X POST http://localhost:8000/api/bookings \
  -H "Authorization: Bearer anna-token" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"room_id": 1, "title": "Race test A", "starts_at": "2026-11-10T10:00:00+00:00", "ends_at": "2026-11-10T11:00:00+00:00"}' &

curl -s -o /tmp/resp2.json -w "resp2: %{http_code}\n" -X POST http://localhost:8000/api/bookings \
  -H "Authorization: Bearer boris-token" -H "Accept: application/json" -H "Content-Type: application/json" \
  -d '{"room_id": 1, "title": "Race test B", "starts_at": "2026-11-10T10:00:00+00:00", "ends_at": "2026-11-10T11:00:00+00:00"}' &

wait
cat /tmp/resp1.json | jq . ; echo
cat /tmp/resp2.json | jq . ; echo