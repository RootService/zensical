#!/bin/tcsh

chown -R root:wheel /data/www/vhosts/_localhost_/{conf,logs}
chown -R www:www    /data/www/vhosts/_localhost_/{cron,data}

find /data/www/vhosts/_localhost_/{conf,logs}/ -type d -print0 | xargs -0 chmod 0755
find /data/www/vhosts/_localhost_/{conf,logs}/ -type f -print0 | xargs -0 chmod 0664

find /data/www/vhosts/_localhost_/cron/ -type d -print0 | xargs -0 chmod 0755
find /data/www/vhosts/_localhost_/cron/ -type f -print0 | xargs -0 chmod 0755

find /data/www/vhosts/_localhost_/data/ -type d -print0 | xargs -0 chmod 6750
find /data/www/vhosts/_localhost_/data/ -type f -print0 | xargs -0 chmod 0640
