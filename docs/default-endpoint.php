<?php
/**
 * csp-report-handler.php
 * Processes CSP violation reports and emails a formatted summary.
 * Target: abuse@example.com
 *
 * Place behind HTTPS. Point your CSP "report-uri" or "report-to" endpoint here.
 */

// ---- Configuration ---------------------------------------------------------
const RECIPIENT = 'abuse@rootservice.org';
const FROM      = 'csp-reports@rootservice.org';
const SUBJECT   = 'CSP violation report';
const MAX_BYTES = 65536; // hard cap to avoid abuse

// ---- Helper ---------------------------------------------------------------
function header_status(int $code): void {
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
}

// Basic newline header injection guard
function safe_header_text(string $s): string {
    return str_replace(["\r", "\n"], ' ', $s);
}

function read_request_body(): string {
    $body = file_get_contents('php://input', false, null, 0, MAX_BYTES + 1);
    if ($body === false) return '';
    if (strlen($body) > MAX_BYTES) return '';
    return $body;
}

/**
 * Normalize incoming payloads to an array of CSP report bodies.
 * Supports:
 *  - Legacy: {"csp-report": {...}}
 *  - Reporting API: [{"type":"csp-violation", "body": {...}}, ...]
 *  - Chromium variants: {"reports":[{"body": {...}}, ...]}
 */
function extract_reports(mixed $json): array {
    $out = [];

    if (is_array($json)) {
        // Possibly an array of report objects (Reporting API)
        foreach ($json as $item) {
            if (is_array($item) && isset($item['body']) && is_array($item['body'])) {
                $out[] = $item['body'];
            } elseif (is_object($item) && isset($item->body) && is_object($item->body)) {
                $out[] = (array)$item->body;
            }
        }
    } elseif (is_object($json)) {
        $arr = (array)$json;

        // Legacy single report
        if (isset($arr['csp-report']) && is_array($arr['csp-report'])) {
            $out[] = $arr['csp-report'];
        } elseif (isset($arr['csp-report']) && is_object($arr['csp-report'])) {
            $out[] = (array)$arr['csp-report'];
        }

        // Chromium "reports" wrapper
        if (isset($arr['reports']) && is_array($arr['reports'])) {
            foreach ($arr['reports'] as $r) {
                if (is_array($r) && isset($r['body']) && is_array($r['body'])) {
                    $out[] = $r['body'];
                } elseif (is_object($r) && isset($r->body) && is_object($r->body)) {
                    $out[] = (array)$r->body;
                }
            }
        }
    }

    return $out;
}

function fmt(mixed $v): string {
    if ($v === null) return '—';
    if (is_bool($v)) return $v ? 'true' : 'false';
    if (is_scalar($v)) return (string)$v;
    return json_encode($v, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

function build_email(array $reports): string {
    $lines = [];
    $meta = [
        'Received at' => gmdate('Y-m-d\TH:i:s\Z'),
        'Remote IP'   => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'User-Agent'  => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'Referer'     => $_SERVER['HTTP_REFERER'] ?? 'none',
        'Content-Type'=> $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? 'unknown'),
        'Report count'=> count($reports),
    ];

    $lines[] = '== Meta ==';
    foreach ($meta as $k => $v) {
        $lines[] = sprintf('%-14s %s', $k.':', fmt($v));
    }
    $lines[] = '';

    foreach ($reports as $i => $r) {
        $lines[] = '== Report #'.($i+1).' ==';

        // Common legacy keys
        $map = [
            'document-uri'           => 'Document URI',
            'referrer'               => 'Referrer',
            'violated-directive'     => 'Violated Directive',
            'effective-directive'    => 'Effective Directive',
            'original-policy'        => 'Original Policy',
            'blocked-uri'            => 'Blocked URI',
            'disposition'            => 'Disposition',
            'status-code'            => 'Status Code',
            'line-number'            => 'Line',
            'column-number'          => 'Column',
            'source-file'            => 'Source File',
            'script-sample'          => 'Script Sample',
            'policy'                 => 'Policy', // some browsers
            'operation'              => 'Operation',
            'violatedDirective'      => 'Violated Directive (camel)',
            'blockedURL'             => 'Blocked URL',
        ];

        // Reporting API body keys often mirror legacy with snake/camel
        foreach ($map as $key => $label) {
            if (array_key_exists($key, $r)) {
                $lines[] = sprintf('%-22s %s', $label.':', fmt($r[$key]));
            }
        }

        // Add anything else not mapped
        $known = array_keys($map);
        foreach ($r as $k => $v) {
            if (!in_array($k, $known, true)) {
                $lines[] = sprintf('%-22s %s', $k.':', fmt($v));
            }
        }

        $lines[] = '';
    }

    return implode("\n", $lines);
}

// ---- Main -----------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    header_status(405);
    echo "Use POST.\n";
    exit;
}

$body = read_request_body();
if ($body === '') {
    header_status(400);
    echo "Invalid or oversized body.\n";
    exit;
}

$payload = json_decode($body, true);
if ($payload === null && json_last_error() !== JSON_ERROR_NONE) {
    header_status(400);
    echo "Malformed JSON.\n";
    exit;
}

$reports = extract_reports($payload);
if (!$reports) {
    // Some user agents send the legacy object directly without wrappers
    if (is_array($payload)) $reports = [$payload];
}

if (!$reports) {
    header_status(422);
    echo "No CSP reports found.\n";
    exit;
}

$emailText = build_email($reports);

// Prepare headers
$from   = safe_header_text(FROM);
$subj   = safe_header_text(SUBJECT);
$rcpt   = safe_header_text(RECIPIENT);
$hdrs   = [];
$hdrs[] = "From: {$from}";
$hdrs[] = "Reply-To: {$from}";
$hdrs[] = "MIME-Version: 1.0";
$hdrs[] = "Content-Type: text/plain; charset=utf-8";
$hdrs[] = "X-CSP-Handler: v1";
$headers = implode("\r\n", $hdrs);

// Send
$ok = @mail($rcpt, $subj, $emailText, $headers);

// Respond with no content so browsers do not retry
header_status($ok ? 204 : 500);
if (!$ok) {
    echo "Mail delivery failed.\n";
}
