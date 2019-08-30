#!/bin/bash

set -e $DRUPAL_TI_DEBUG

# Ensure the right Drupal version is installed.
# Note: This function is re-entrant.
drupal_ti_ensure_drupal

if [ ${DRUPAL_TI_CORE_BRANCH} != "8.8.x" ]; then
  cd "$DRUPAL_TI_DRUPAL_BASE/drupal"
  composer update phpunit/phpunit --with-dependencies --no-progress
fi

# Turn on chromdriver for functional Javascript tests
chromedriver > /dev/null 2>&1 &
