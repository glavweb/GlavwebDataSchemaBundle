#!/bin/bash
set -x

composer require glavweb/data-schema-bundle

php bin/phpunit

. ../scripts/copy.sh
