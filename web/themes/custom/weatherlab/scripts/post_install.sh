#!/bin/bash
# Install Pattern Lab depedencies only

cd ./pattern-lab
# Prevent existing config being overriden by install scripts
composer install --no-scripts
# Add plugin-data-transform
composer require 'aleksip/plugin-data-transform:^1.0.0'

