#!/usr/bin/env bash
# Spustí lokálny WooCommerce na http://localhost:8090 a vypíše hodnoty do .env.
set -euo pipefail

cd "$(dirname "$0")"
wp() { docker compose run --rm -T cli wp "$@"; }

docker compose up -d --wait

until docker compose exec -T wordpress test -f /var/www/html/wp-config.php; do sleep 2; done

if ! wp core is-installed >/dev/null 2>&1; then
    wp core install --url=http://localhost:8090 --title="Demo e-shop" \
        --admin_user=admin --admin_password=admin --admin_email=admin@example.test --skip-email
fi

wp plugin is-active woocommerce >/dev/null 2>&1 || wp plugin install woocommerce --activate
wp option update woocommerce_currency EUR >/dev/null
wp rewrite structure '/%postname%/' --hard >/dev/null

wp user application-password delete admin --all >/dev/null 2>&1 || true
password=$(wp user application-password create admin ai-feed-enricher --porcelain | tr -d '\r')

cat <<EOF

WooCommerce beží na http://localhost:8090 (admin / admin).
Doplň do .env:

WOO_URL=http://localhost:8090
WOO_USER=admin
WOO_APP_PASSWORD="${password}"
EOF
