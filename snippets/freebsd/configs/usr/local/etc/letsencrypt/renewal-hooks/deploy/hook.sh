#!/bin/sh
set -eu
install -d -m 0755 /usr/local/etc/letsencrypt/certs

cat /usr/local/etc/letsencrypt/live/devnull.example.com/fullchain.pem \
    /usr/local/etc/letsencrypt/live/devnull.example.com/privkey.pem \
    > /usr/local/etc/letsencrypt/certs/devnull.example.com.pem
cat /usr/local/etc/letsencrypt/live/www.example.com/fullchain.pem \
    /usr/local/etc/letsencrypt/live/www.example.com/privkey.pem \
    > /usr/local/etc/letsencrypt/certs/www.example.com.pem
cat /usr/local/etc/letsencrypt/live/mail.example.com/fullchain.pem \
    /usr/local/etc/letsencrypt/live/mail.example.com/privkey.pem \
    > /usr/local/etc/letsencrypt/certs/mail.example.com.pem
chmod 0640 /usr/local/etc/letsencrypt/certs/*.pem
service haproxy reload
service nginx reload
service apache24 reload
service dovecot reload
service postfix reload