#!/bin/bash

set -e $DRUPAL_TI_DEBUG

# Ensure the right Drupal version is installed.
# Note: This function is re-entrant.
drupal_ti_ensure_drupal

if [ "$DRUPAL_TI_CORE_BRANCH" == "8.5.x" ]; then
  cd "$DRUPAL_TI_DRUPAL_BASE/drupal"

  composer update phpunit/phpunit --with-dependencies --no-progress
fi

# Turn on PhantomJS for functional Javascript tests
phantomjs --ssl-protocol=any --ignore-ssl-errors=true $DRUPAL_TI_DRUPAL_DIR/vendor/jcalderonzumba/gastonjs/src/Client/main.js 8510 1024 768 2>&1 >> /dev/null &
