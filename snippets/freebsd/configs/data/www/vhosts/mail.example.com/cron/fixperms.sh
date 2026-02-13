#!/bin/tcsh

chown -R root:wheel /data/www/vhosts/mail.example.com/{conf,logs}
chown -R www:www    /data/www/vhosts/mail.example.com/{cron,data}

find /data/www/vhosts/mail.example.com/{conf,logs}/ -type d -print0 | xargs -0 chmod 0755
find /data/www/vhosts/mail.example.com/{conf,logs}/ -type f -print0 | xargs -0 chmod 0664

find /data/www/vhosts/mail.example.com/cron/ -type d -print0 | xargs -0 chmod 0755
find /data/www/vhosts/mail.example.com/cron/ -type f -print0 | xargs -0 chmod 0755

find /data/www/vhosts/mail.example.com/data/ -type d -print0 | xargs -0 chmod 6750
find /data/www/vhosts/mail.example.com/data/ -type f -print0 | xargs -0 chmod 0640
