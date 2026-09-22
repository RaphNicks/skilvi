#!/bin/sh
cd "$(dirname "$0")/.."
PHP="${PHP:-./bin/php}"
exec "$PHP" -S 0.0.0.0:8080 -t public public/index.php
