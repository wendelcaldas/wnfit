#!/bin/sh
set -eu
umask 077
mkdir -p /var/lib/grafana/provisioning/datasources /var/lib/grafana/provisioning/dashboards /var/lib/grafana/dashboards
printf '%s' "$DATASOURCE_B64" | base64 -d > /var/lib/grafana/provisioning/datasources/wnfit.yml
printf '%s' "$PROVIDER_B64" | base64 -d > /var/lib/grafana/provisioning/dashboards/wnfit.yml
printf '%s' "$DASHBOARD_B64" | base64 -d > /var/lib/grafana/dashboards/clientes.json
if [ "$(id -u)" = "0" ]; then
    chown -R 472:0 /var/lib/grafana
    exec su -s /bin/sh grafana -c 'exec /run.sh'
fi
exec /run.sh
