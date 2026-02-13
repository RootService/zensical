#!/bin/tcsh

chown -R root:wheel /data/www/vhosts/0localhost0/{conf,logs}
chown -R www:www    /data/www/vhosts/0localhost0/{cron,data}

find /data/www/vhosts/0localhost0/{conf,logs}/ -type d -print0 | xargs -0 chmod 0755
find /data/www/vhosts/0localhost0/{conf,logs}/ -type f -print0 | xargs -0 chmod 0664

find /data/www/vhosts/0localhost0/cron/ -type d -print0 | xargs -0 chmod 0755
find /data/www/vhosts/0localhost0/cron/ -type f -print0 | xargs -0 chmod 0755

find /data/www/vhosts/0localhost0/data/ -type d -print0 | xargs -0 chmod 6750
find /data/www/vhosts/0localhost0/data/ -type f -print0 | xargs -0 chmod 0640
