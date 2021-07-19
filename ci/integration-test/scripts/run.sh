#!/bin/bash
set -x
set -e

composer require glavweb/data-schema-bundle

php bin/phpunit

. ../scripts/copy.sh
