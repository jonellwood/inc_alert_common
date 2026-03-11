# ASAP ECC Ingest Stub (PHP)

This is a minimal PHP endpoint for ASAP ECC ingestion. It exposes:

- `POST /asap/ecc` — Accepts `application/xml` ASAP payloads with `Authorization: Bearer <token>` (mock token), validates basic namespaces, logs raw XML plus extracted fields, and returns a correlation ID.
- `GET /health` — Simple health check.

Samples:
- `samples/sample-asap.xml` — mock ASAP payload.
- `samples/sample-asap.json` — expected transformed JSON for the mock payload.
- `reference/scenario1_new_alarm.xml` — vendor-provided sample ASAP payload.
- `samples/scenario1_new_alarm.json` — transformed JSON from that sample using `src/Transformer.php`.
- `samples/scenario1_new_alarm.cad.json` — CAD payload mapped from the vendor sample using `src/CadMapper.php`.

## Configuration

Defaults are in `config/config.php`. Override with environment variables:

- `MOCK_BEARER_TOKEN` (default `change-me-token`)
- `LOG_DIR` (default `<project>/logs`)
- `OUTPUT_DIR` (default `<project>/logs/events`)
- `MAX_BODY_BYTES` (default `1048576`)
- `TIMEZONE` (default `UTC`)

Logs are written as JSON lines to `LOG_DIR/asap-ecc-YYYY-MM-DD.log`.
Each accepted request also writes a per-event JSON file to `OUTPUT_DIR` named `<correlation-id>-<yyyymmdd_hhmmss>.json`.

## Running under Apache

Point the Apache document root to `public/` (or create a VirtualHost) so `public/index.php` handles requests. Ensure PHP is enabled and the log directory is writable by the web server user.

## Running locally with PHP built-in server

From the project root:

```sh
php -S localhost:8888 -t public public/router.php
```

Then POST to `http://localhost:8888/asap/ecc` with the headers/body noted above.

## Notes

- The transformer (`src/Transformer.php`) extracts common ASAP fields defensively. Extend it as you learn the payload shape.
- Namespace checks enforce the APCO/TMA prefixes (`apco-alarm`, `em`, `j`, `s`, `nc`) to match the reference guide. If incoming payloads omit prefixes, adjust in `public/index.php`.
