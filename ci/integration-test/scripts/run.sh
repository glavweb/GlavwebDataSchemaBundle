#!/bin/bash
set -x
set -e

composer require -n $PACKAGE_NAME:dev-main \
&& php bin/phpunit \
|| echo 'FAILED'

../scripts/copy.sh
