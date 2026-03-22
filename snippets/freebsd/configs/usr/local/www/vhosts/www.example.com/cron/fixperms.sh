#!/bin/sh

DIR="$(dirname $0)"

chown -R root:wheel $DIR/../conf
chown -R root:wheel $DIR/../logs

chown -R www:www    $DIR/../cron
chown -R www:www    $DIR/../data

find $DIR/../conf/ -type d -print0 | xargs -0 chmod 0755
find $DIR/../conf/ -type f -print0 | xargs -0 chmod 0664

find $DIR/../logs/ -type d -print0 | xargs -0 chmod 0755
find $DIR/../logs/ -type f -print0 | xargs -0 chmod 0664

find $DIR/../cron/ -type d -print0 | xargs -0 chmod 0755
find $DIR/../cron/ -type f -print0 | xargs -0 chmod 0755

find $DIR/../data/ -type d -print0 | xargs -0 chmod 6750
find $DIR/../data/ -type f -print0 | xargs -0 chmod 0640

exit 0