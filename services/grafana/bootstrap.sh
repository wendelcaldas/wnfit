#!/bin/sh
set -eu
case "$APP_DB_NAME" in ''|*[!A-Za-z0-9_]*) echo 'Invalid database identifier'; exit 1;; esac
case "$METRICS_PASSWORD" in ''|*[!0-9a-f]*) echo 'Invalid reader credential'; exit 1;; esac
printf '%s' "$SETUP_SQL_B64" | base64 -d | sed "s/__APP_DB__/$APP_DB_NAME/g;s/__READER_PASSWORD__/$METRICS_PASSWORD/g" > /tmp/wnfit-analytics.sql
chmod 600 /tmp/wnfit-analytics.sql
export MYSQL_PWD="$SETUP_DB_PASSWORD"
mysql --protocol=TCP --host="$APP_DB_HOST" --port="$APP_DB_PORT" --user="$SETUP_DB_USER" --ssl-mode=REQUIRED < /tmp/wnfit-analytics.sql
rm /tmp/wnfit-analytics.sql
export MYSQL_PWD="$METRICS_PASSWORD"
mysql --protocol=TCP --host="$APP_DB_HOST" --port="$APP_DB_PORT" --user=wnfit_grafana_reader --ssl-mode=REQUIRED --database=wnfit_analytics --execute="SELECT 'READ_ONLY_METRICS_READY' AS setup, COUNT(*) AS clients, COALESCE(SUM(students),0) AS students FROM clients;"
if mysql --protocol=TCP --host="$APP_DB_HOST" --port="$APP_DB_PORT" --user=wnfit_grafana_reader --ssl-mode=REQUIRED --execute="SELECT COUNT(*) FROM $APP_DB_NAME.users" >/dev/null 2>&1; then
 echo 'Reader unexpectedly has access to application users'; exit 1
fi
echo 'READER_RAW_TABLE_ACCESS_DENIED_OK'
