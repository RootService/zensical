#!/bin/sh
set -eu

dovecot_user="${1}"

localpart="${dovecot_user%@*}"
domain="${dovecot_user#*@}"
home="/var/vmail/${domain}/${localpart}"

dovecot_pass="$(openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | cut -c 3-18)"
dovecot_hash="$(doveadm pw -s ARGON2ID -p "${dovecot_pass}")"

echo "Password for ${dovecot_user} is: ${dovecot_pass}"
echo "${dovecot_user}:${dovecot_hash}:5000:5000::${home}::" >> /usr/local/etc/dovecot/passwd

exit 0