#!/bin/sh

if [ ! -f meilisearch ]; then
    curl -L https://install.meilisearch.com | sh
fi

./meilisearch --master-key="aSampleMasterKey"
