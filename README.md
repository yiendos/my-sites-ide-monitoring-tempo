# Tempo

[Tempo](https://grafana.com/oss/tempo/) for [my-sites-ide](https://github.com/yiendos/my-sites-ide):
distributed tracing. It takes OpenTelemetry traces - usually through the
[alloy plugin](https://github.com/yiendos/my-sites-ide-monitoring-alloy) at `alloy:4318` - and,
when the [prometheus plugin](https://github.com/yiendos/my-sites-ide-monitoring-prometheus) is
installed, turns them into service graphs and span metrics. Read them in the
[grafana plugin](https://github.com/yiendos/my-sites-ide-monitoring-grafana).

Written for: developers running sites in my-sites-ide who want to follow a request through their
app, its queries and the services it calls.

## Contents

- [Installation](#installation)
- [Sending traces](#sending-traces)
- [Service graphs and span metrics](#service-graphs-and-span-metrics)
- [Command reference](#command-reference)
- [Configuration](#configuration)
- [What it uses from the IDE](#what-it-uses-from-the-ide)
- [Troubleshooting](#troubleshooting)
- [Known gaps](#known-gaps)

## Installation

A [my-sites-ide](https://github.com/yiendos/my-sites-ide) plugin. Add it to the `require` section
of the IDE's `composer.local.json` - or add
[`yiendos/my-sites-ide-preset-monitoring`](https://github.com/yiendos/my-sites-ide-preset-monitoring)
instead, for the whole monitoring stack:

```json
"yiendos/my-sites-ide-monitoring-tempo": "@dev"
```

Then, from the IDE root:

```
composer update
php my-sites-ide monitoring:tempo-start
```

Tempo is opt-in: it doesn't autostart. Start it with `monitoring:tempo-start`, which writes its
config first. Then run `monitoring:alloy-start` and `monitoring:grafana-start` (when those plugins
are installed) so Alloy forwards traces and Grafana gets a Tempo data source.

## Sending traces

Point your app's OpenTelemetry exporter at Alloy, which forwards traces here:

```
OTEL_EXPORTER_OTLP_ENDPOINT=http://alloy:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
```

Without Alloy, send straight to Tempo on `tempo:4318` (HTTP) or `tempo:4317` (gRPC). Either way,
these are only reachable inside the IDE.

To try it without an app:

```
docker run --rm --network <project>_my-sites-ide \
    ghcr.io/open-telemetry/opentelemetry-collector-contrib/telemetrygen:latest \
    traces --otlp-http --otlp-endpoint alloy:4318 --otlp-insecure --traces 5
```

## Service graphs and span metrics

With the prometheus plugin installed, `monitoring:tempo-start` switches on Tempo's
metrics-generator, which remote-writes `traces_service_graph_*` and `traces_spanmetrics_*` to
Prometheus (with exemplars). That's what Grafana's service map and span-to-metrics links run on.
Without Prometheus it's left off; run `monitoring:tempo-start` again after installing or removing
it.

## Command reference

| Command | What it does |
|---|---|
| `monitoring:tempo-start` | Writes `storage/plugins/tempo/conf/tempo.yaml` from `stubs/tempo.yaml` (adding `stubs/metrics-generator.yaml` when Prometheus is installed), then `docker compose up -d --build tempo`. Restarts a running Tempo when the config changed |
| `monitoring:tempo-stop` | `docker compose stop tempo`, leaving the rest of the IDE running |

## Configuration

| Variable | Default | What it does |
|---|---|---|
| `TEMPO_RETENTION` | `48h` (this plugin's `.env`) | How long traces are kept - in hours, since Tempo takes a Go duration (no `d`) |

Set it in the IDE's root `.env`, which wins over the plugin's default, then run
`monitoring:tempo-start`. `php my-sites-ide ide:plugin-env yiendos/my-sites-ide-monitoring-tempo`
copies it in, commented out.

## What it uses from the IDE

| From the IDE | Used for |
|---|---|
| `NAMESPACE` (root `.env`) | the image name, `${NAMESPACE}_tempo` |
| `IDE_ROOT` (set by the CLI and `_dev/cache/ide.env`) | finding the plugin list and storage |
| `_dev/cache/plugins.php` | whether the prometheus plugin is installed |
| `storage/plugins/tempo/` (`"storage": true`) | the config, recent traces (live-store WAL), blocks and the metrics-generator's WAL |
| the `my-sites-ide` network | receiving traces, being queried by Grafana, writing to Prometheus |

The container carries `prometheus.io/scrape` labels, so the prometheus plugin scrapes Tempo's own
metrics.

## Troubleshooting

**A trace you just sent isn't found.** Search takes a few seconds to see new traces - wait and
search again.

**`error calling scheduler ... no jobs found` every 15 seconds.** Tempo 3's single binary polls its
own compaction scheduler and logs this whenever there's nothing to compact. It's harmless.

**No service map in Grafana.** Check the prometheus plugin is installed, and that `monitoring:tempo-start`
said "Service graphs and span metrics go to Prometheus". Service graphs need spans from two
services calling each other.

## Known gaps

- Runs in monolithic mode (`-target=all`), which needs no Kafka - fine for one developer, not a
  production layout.
- No host ports, so apps on the host can't send traces - only containers in the IDE.
- On Linux hosts, `storage/plugins/tempo/` is created by your user while Tempo runs as uid 10001, so
  it may not be able to write there. Docker Desktop on macOS maps ownership, so it isn't affected.
