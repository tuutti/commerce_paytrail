#!/bin/bash

set -e $DRUPAL_TI_DEBUG

composer why phpunit/phpunit
# Ensure the right Drupal version is installed.
# Note: This function is re-entrant.
drupal_ti_ensure_drupal

# Turn on chromdriver for functional Javascript tests
chromedriver > /dev/null 2>&1 &
