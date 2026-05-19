<?php

declare(strict_types=1);

/**
 * default-endpoint.php
 * CSP violation report handler for PHP 8.4
 *
 * Features:
 * - Strict typing
 * - Hardened input handling
 * - Rate limiting
 * - CSP Reporting API + legacy CSP support
 * - Safe mail header handling
 * - 204 responses to prevent retry storms
 */

// -----------------------------------------------------------------------------
// Configuration
// -----------------------------------------------------------------------------

const RECIPIENT = 'abuse@rootservice.org';
const FROM      = 'csp-reports@rootservice.org';
const SUBJECT   = 'CSP violation report';

const MAX_BYTES = 65536;

const RL_WINDOW_SECONDS = 60;
const RL_MAX_PER_WINDOW = 30;

const MAX_FIELD_CHARS = 4096;

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------

function header_status(int $statusCode): void
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');
}

function safe_header_text(string $value): string
{
    return str_replace(["\r", "\n"], ' ', $value);
}

function read_request_body(): string
{
    $body = file_get_contents(
        'php://input',
        false,
        null,
        0,
        MAX_BYTES + 1
    );

    if ($body === false) {
        return '';
    }

    if (strlen($body) > MAX_BYTES) {
        return '';
    }

    return $body;
}

function truncate_text(string $text, int $max = MAX_FIELD_CHARS): string
{
    if (mb_strlen($text) <= $max) {
        return $text;
    }

    return mb_substr($text, 0, $max) . '…';
}

function fmt(mixed $value): string
{
    if ($value === null) {
        return '—';
    }

    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }

    if (is_scalar($value)) {
        return truncate_text((string) $value);
    }

    $json = json_encode(
        $value,
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_PRETTY_PRINT
    );

    return truncate_text($json === false ? '' : $json);
}

/**
 * Simple file-based rate limiter.
 */
function rate_limit_allow(string $ip): bool
{
    $safeIp = preg_replace('/[^0-9a-fA-F:\\.]/', '_', $ip);

    if ($safeIp === null || $safeIp === '') {
        $safeIp = 'unknown';
    }

    $filename = sprintf('csp_rl_%s', $safeIp);

    $path = sys_get_temp_dir()
        . DIRECTORY_SEPARATOR
        . $filename;

    $now = time();

    $fp = @fopen($path, 'c+');

    if ($fp === false) {
        // Fail open to avoid breaking CSP reporting
        return true;
    }

    @flock($fp, LOCK_EX);

    $raw = stream_get_contents($fp);

    $data = is_string($raw)
        ? json_decode($raw, true)
        : null;

    if (!is_array($data)) {
        $data = [
            'start' => $now,
            'count' => 0,
        ];
    }

    $start = (int) ($data['start'] ?? $now);
    $count = (int) ($data['count'] ?? 0);

    if (($now - $start) >= RL_WINDOW_SECONDS) {
        $start = $now;
        $count = 0;
    }

    $count++;

    ftruncate($fp, 0);
    rewind($fp);

    fwrite(
        $fp,
        json_encode(
            [
                'start' => $start,
                'count' => $count,
            ],
            JSON_THROW_ON_ERROR
        )
    );

    @flock($fp, LOCK_UN);
    fclose($fp);

    return $count <= RL_MAX_PER_WINDOW;
}

/**
 * Extract CSP reports from supported formats.
 */
function extract_reports(mixed $payload): array
{
    $reports = [];

    if (is_array($payload)) {

        // Reporting API array
        foreach ($payload as $item) {

            if (
                is_array($item)
                && isset($item['body'])
                && is_array($item['body'])
            ) {
                $reports[] = $item['body'];
            }
        }

        return $reports;
    }

    if (!is_object($payload) && !is_array($payload)) {
        return [];
    }

    $data = (array) $payload;

    // Legacy format
    if (isset($data['csp-report'])) {

        if (is_array($data['csp-report'])) {
            $reports[] = $data['csp-report'];
        } elseif (is_object($data['csp-report'])) {
            $reports[] = (array) $data['csp-report'];
        }
    }

    // Chromium wrapper
    if (
        isset($data['reports'])
        && is_array($data['reports'])
    ) {
        foreach ($data['reports'] as $report) {

            if (
                is_array($report)
                && isset($report['body'])
                && is_array($report['body'])
            ) {
                $reports[] = $report['body'];
            }
        }
    }

    return $reports;
}

function build_email(array $reports): string
{
    $lines = [];

    $meta = [
        'Received at'  => gmdate('Y-m-d\TH:i:s\Z'),
        'Remote IP'    => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'User-Agent'   => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'Referer'      => $_SERVER['HTTP_REFERER'] ?? 'none',
        'Content-Type' => $_SERVER['CONTENT_TYPE']
            ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? 'unknown'),
        'Report count' => count($reports),
    ];

    $lines[] = '== Meta ==';

    foreach ($meta as $key => $value) {
        $lines[] = sprintf(
            '%-14s %s',
            $key . ':',
            fmt($value)
        );
    }

    $lines[] = '';

    foreach ($reports as $index => $report) {

        $lines[] = '== Report #' . ($index + 1) . ' ==';

        $fields = [
            'document-uri'        => 'Document URI',
            'referrer'            => 'Referrer',
            'violated-directive'  => 'Violated Directive',
            'effective-directive' => 'Effective Directive',
            'original-policy'     => 'Original Policy',
            'blocked-uri'         => 'Blocked URI',
            'disposition'         => 'Disposition',
            'status-code'         => 'Status Code',
            'line-number'         => 'Line',
            'column-number'       => 'Column',
            'source-file'         => 'Source File',
            'script-sample'       => 'Script Sample',
            'policy'              => 'Policy',
            'operation'           => 'Operation',

            // Browser variants
            'violatedDirective'   => 'Violated Directive (camel)',
            'blockedURL'          => 'Blocked URL',
        ];

        foreach ($fields as $field => $label) {

            if (array_key_exists($field, $report)) {

                $lines[] = sprintf(
                    '%-22s %s',
                    $label . ':',
                    fmt($report[$field])
                );
            }
        }

        // Unknown fields
        foreach ($report as $field => $value) {

            if (!array_key_exists($field, $fields)) {

                $lines[] = sprintf(
                    '%-22s %s',
                    $field . ':',
                    fmt($value)
                );
            }
        }

        $lines[] = '';
    }

    return implode("\n", $lines);
}

function log_note(string $message): void
{
    error_log('[csp-endpoint] ' . $message);
}

// -----------------------------------------------------------------------------
// Main
// -----------------------------------------------------------------------------

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET' || $method === 'HEAD') {

    header_status(200);

    echo "ok\n";

    exit;
}

if ($method !== 'POST') {

    header('Allow: POST, GET, HEAD');

    header_status(405);

    echo "Use POST.\n";

    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!rate_limit_allow($ip)) {

    log_note("rate-limited ip={$ip}");

    http_response_code(204);

    exit;
}

$raw = read_request_body();

if ($raw === '') {

    log_note("empty/oversized body ip={$ip}");

    http_response_code(204);

    exit;
}

try {

    $payload = json_decode(
        $raw,
        true,
        512,
        JSON_THROW_ON_ERROR
    );

} catch (JsonException $e) {

    log_note(
        "malformed json ip={$ip} err=" . $e->getMessage()
    );

    http_response_code(204);

    exit;
}

$reports = extract_reports($payload);

if (
    !$reports
    && is_array($payload)
) {
    // Some browsers send the report directly
    $reports = [$payload];
}

if (!$reports) {

    log_note("no reports found ip={$ip}");

    http_response_code(204);

    exit;
}

$body = build_email($reports);

$from = safe_header_text(FROM);
$subject = safe_header_text(SUBJECT . ' (rootservice.org)');
$to = safe_header_text(RECIPIENT);

$headers = [
    "From: {$from}",
    "Reply-To: {$from}",
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=utf-8',
    'X-CSP-Handler: v3',
];

$mailHeaders = implode("\r\n", $headers);

$sent = @mail(
    $to,
    $subject,
    $body,
    $mailHeaders
);

if (!$sent) {
    log_note("mail delivery failed ip={$ip}");
}

// Always 204 to prevent browser retries
http_response_code(204);
exit;