#!/usr/bin/env bash

here=$(readlink -f $(dirname "$0"))
cd "$here"
/usr/bin/php cron.php >> cronnit.log 2>> cronnit.log.err
