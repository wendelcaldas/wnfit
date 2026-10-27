#!/bin/sh
set -eu

php artisan migrate --force

# Keep the shared catalog in sync without overwriting reviewed or private entries.
php artisan db:seed --class=ExerciseSeeder --force
php scripts/verify-exercise-catalog.php
