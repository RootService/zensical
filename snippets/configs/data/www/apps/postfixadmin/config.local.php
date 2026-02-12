<?php
$CONF['database_type'] = 'pgsql';
$CONF['database_host'] = 'localhost';
$CONF['database_user'] = 'postfix';
$CONF['database_password'] = '__PASSWORD_POSTFIX__';
$CONF['database_name'] = 'postfixadmin';
$CONF['encrypt'] = 'ARGON2ID';
$CONF['configured'] = true;
$CONF['default_language'] = 'de';
$CONF['setup_password'] = '__SETUP_HASH__';
$CONF['smtp_server'] = 'localhost';
$CONF['smtp_port'] = '465';
$CONF['smtp_type'] = 'tls';
$CONF['smtp_client'] = 'mail.example.com';
$CONF['dovecotpw'] = "/usr/local/sbin/doveadm pw";
if(@file_exists('/usr/local/bin/doveadm')) { // @ to silence openbase_dir stuff; see https://github.com/postfixadmin/postfixadmin/issues/171
    $CONF['dovecotpw'] = "/usr/local/bin/doveadm pw"; # debian
}
$CONF['password_validation'] = array(
    '/.{5}/'                                         => 'password_too_short 5',      # minimum length 5 characters
    '/([a-zA-Z].*){3}/'                              => 'password_no_characters 3',  # must contain at least 3 characters
    '/([0-9].*){2}/'                                 => 'password_no_digits 2',      # must contain at least 2 digits
    '/([!\".,*&^%$£)(_+=\-`\'#@~\[\]\\<>\/].*){1,}/' => 'password_no_special 1', # must contain at least 1 special character
    /*  support a 'callable' value which if it returns a non-empty string will be assumed to have failed, non-empty string should be a PALANG key */
    // 'length_check'                                   => function($password) { if (strlen(trim($password)) < 3) { return 'password_too_short'; } },
);
$CONF['show_password'] = 'YES';
$CONF['page_size'] = '100';
$CONF['default_aliases'] = array (
    'abuse'       => 'abuse@example.com',
    'security'    => 'security@example.com',
    'hostmaster'  => 'hostmaster@example.com',
    'postmaster'  => 'postmaster@example.com',
    'webmaster'   => 'webmaster@example.com',
    'dmarc'       => 'dmarc@example.com'
    'virusalert'  => 'virusalert@example.com',
);
$CONF['aliases'] = '1000';
$CONF['mailboxes'] = '100';
$CONF['maxquota'] = '2048';
$CONF['domain_quota_default'] = '204800';
$CONF['quota'] = 'YES';
$CONF['new_quota_table'] = 'YES'
$CONF['transport'] = 'YES';
$CONF['transport_options'] = array (
    'virtual',  // for virtual accounts
    'local',    // for system accounts
    'relay',    // for backup mx
    'vacation'  // for vacation
);
$CONF['transport_default'] = 'virtual';
$CONF['vacation'] = 'YES';
$CONF['vacation_domain'] = 'autoreply.example.com';
$CONF['special_alias_control'] = 'YES';
$CONF['backup'] = 'YES';
$CONF['sendmail_all_admins'] = 'YES';
$CONF['show_header_text'] = 'YES';
$CONF['header_text'] = ':: Postfix Admin on mail.example.com ::';
$CONF['footer_text'] = 'Return to mail.example.com';
$CONF['footer_link'] = 'https://mail.example.com';
$CONF['emailcheck_localaliasonly'] ='YES';
$CONF['dkim'] = 'YES';
$CONF['dkim_all_admins'] = 'YES';
$CONF['recipient_delimiter'] = "+";
$CONF['used_quotas'] = 'YES';
$CONF['smtp_active_flag'] = 'YES';
?>
