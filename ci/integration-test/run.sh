#!/bin/bash
set -x
set -e

cd `dirname "$0"`

export IMAGE_NAME='glavweb-data-schema-bundle-test'
export BUNDLE_VERSION=$(git describe --tags `git rev-list --tags --max-count=1`)

docker build -t $IMAGE_NAME --build-arg BUNDLE_VERSION=$BUNDLE_VERSION .

docker run --tty --rm \
        -v `pwd`/../../:/usr/src/bundle \
        -v `pwd`/../../build/test:/usr/src/build \
        $IMAGE_NAME \
        ../scripts/run.sh