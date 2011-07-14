#!/bin/bash
path=releases/${2}
third_party_path=${path}/system/expressionengine/third_party/${1}
mkdir -p ${third_party_path}
cp config.php ${third_party_path}
cp ext.* ${third_party_path}
cp -R language ${third_party_path}
cp README.textile ${path}