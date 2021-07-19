#!/bin/bash
set -x

cd `dirname "$0"`

imageName='glavweb-data-schema-bundle-test'

docker build -t $imageName .

docker run --tty --rm \
        -v `pwd`/../../:/usr/src/bundle \
        -v `pwd`/../../build/test:/usr/src/build \
        $imageName \
        ../scripts/run.sh