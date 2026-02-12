<?php

date_default_timezone_set('UTC');

// This script will process incoming Content Security Policy violation reports
// and send them, nicely formatted, to the email address listed below.
//
// Included in the script is a large list of false-positives that are generated
// by browser addons etc.
//
// To activate, emit the proper CSP headers via PHP in all your page-generating scripts,
// e.g.: header("Content-Security-Policy-Report-Only: default-src https: wss: data: 'unsafe-eval' 'unsafe-inline'; report-uri /csp-report.php");
// Which we use to track HTTPS mixed-content warnings.

$emailTo='admin@example.com';

http_response_code(204);

// Only run if proper input data received
if ($data=json_decode(file_get_contents('php://input'), true))
    ksort($data);

    // Prettify the JSON-formatted data
    $email=json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\r\n\r\n";

    $email.=sprintf("%s (%s)\r\n", $_SERVER['REMOTE_ADDR'], gethostbyaddr($_SERVER['REMOTE_ADDR']));
    $email.=sprintf("%s\r\n\r\n", $_SERVER['HTTP_USER_AGENT']);

    // Mail the CSP violation report
    mail($emailTo, 'CSP Violation', $email, 'Content-Type: text/plain;charset=utf-8');
?>
