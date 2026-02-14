<?php
/**
 * default-endpoint.php
 * CSP violation report handler.
 *
 * Notes:
 * - Keep responses 204 on success *and* most errors to avoid browser retry storms.
 * - Protect against abuse (rate limiting) to avoid becoming a mail-bomb endpoint.
 * - Designed to be placed behind HTTPS.
 */

// ---- Configuration ---------------------------------------------------------
const RECIPIENT = "abuse@rootservice.org";
const FROM      = "csp-reports@rootservice.org";
const SUBJECT   = "CSP violation report";

// Hard cap to avoid abuse
const MAX_BYTES = 65536;

// Simple per-IP rate limit (best-effort). Keep low: CSP can be noisy.
const RL_WINDOW_SECONDS = 60;
const RL_MAX_PER_WINDOW = 30;

// Truncate potentially large fields to keep email size sane
const MAX_FIELD_CHARS = 4096;

// ---- Helpers --------------------------------------------------------------
function header_status(int ): void {
    http_response_code();
    header("Content-Type: text/plain; charset=utf-8");
}

function safe_header_text(string ): string {
    // Basic newline header injection guard
    return str_replace(["\r", "\n"], " ", );
}

function read_request_body(): string {
     = file_get_contents("php://input", false, null, 0, MAX_BYTES + 1);
    if ( === false) return "";
    if (strlen() > MAX_BYTES) return "";
    return ;
}

function truncate_text(string , int  = MAX_FIELD_CHARS): string {
    if (strlen() <= ) return ;
    return substr(, 0, ) . "…";
}

function fmt(mixed ): string {
    if ( === null) return "—";
    if (is_bool()) return  ? "true" : "false";
    if (is_scalar()) {
        return truncate_text((string));
    }
     = json_encode(, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return truncate_text( === false ? "" : );
}

/**
 * Simple file-based rate limiting keyed by IP.
 * Returns true if request is allowed; false if it should be dropped.
 */
function rate_limit_allow(string ): bool {
     = preg_replace('/[^0-9a-fA-F:\\.]/', '_', );
    if ( === "")  = "unknown";

      = sprintf("csp_rl_%s", );
     = sys_get_temp_dir() . DIRECTORY_SEPARATOR . ;

     = time();

    // Best-effort lock
     = @fopen(, 'c+');
    if (!) {
        // If we can't rate limit, still accept to avoid breaking reporting.
        return true;
    }

    @flock(, LOCK_EX);

      = stream_get_contents();
     =  ? json_decode(, true) : null;
    if (!is_array())  = ["start" => , "count" => 0];

     = (int)(["start"] ?? );
     = (int)(["count"] ?? 0);

    if ( -  >= RL_WINDOW_SECONDS) {
         = ;
         = 0;
    }

    ++;

    // rewind+truncate
    ftruncate(, 0);
    rewind();
    fwrite(, json_encode(["start" => , "count" => ]));

    @flock(, LOCK_UN);
    fclose();

    return  <= RL_MAX_PER_WINDOW;
}

/**
 * Normalize incoming payloads to an array of CSP report bodies.
 * Supports:
 *  - Legacy: {"csp-report": {...}}
 *  - Reporting API: [{"type":"csp-violation", "body": {...}}, ...]
 *  - Chromium variants: {"reports":[{"body": {...}}, ...]}
 */
function extract_reports(mixed ): array {
     = [];

    if (is_array()) {
        // Possibly an array of report objects (Reporting API)
        foreach ( as ) {
            if (is_array() && isset(['body']) && is_array(['body'])) {
                [] = ['body'];
            } elseif (is_object() && isset(->body) && is_object(->body)) {
                [] = (array)->body;
            }
        }
    } elseif (is_object()) {
         = (array);

        // Legacy single report
        if (isset(['csp-report']) && is_array(['csp-report'])) {
            [] = ['csp-report'];
        } elseif (isset(['csp-report']) && is_object(['csp-report'])) {
            [] = (array)['csp-report'];
        }

        // Chromium "reports" wrapper
        if (isset(['reports']) && is_array(['reports'])) {
            foreach (['reports'] as ) {
                if (is_array() && isset(['body']) && is_array(['body'])) {
                    [] = ['body'];
                } elseif (is_object() && isset(->body) && is_object(->body)) {
                    [] = (array)->body;
                }
            }
        }
    }

    return ;
}

function build_email(array ): string {
     = [];
     = [
        'Received at'  => gmdate('Y-m-d\\TH:i:s\\Z'),
        'Remote IP'    => ['REMOTE_ADDR'] ?? 'unknown',
        'User-Agent'   => ['HTTP_USER_AGENT'] ?? 'unknown',
        'Referer'      => ['HTTP_REFERER'] ?? 'none',
        'Content-Type' => ['CONTENT_TYPE'] ?? (['HTTP_CONTENT_TYPE'] ?? 'unknown'),
        'Report count' => count(),
    ];

    [] = '== Meta ==';
    foreach ( as  => ) {
        [] = sprintf('%-14s %s',  . ':', fmt());
    }
    [] = '';

    foreach ( as  => ) {
        [] = '== Report #' . ( + 1) . ' ==';

         = [
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
            // some browsers
            'violatedDirective'   => 'Violated Directive (camel)',
            'blockedURL'          => 'Blocked URL',
        ];

        foreach ( as  => ) {
            if (array_key_exists(, )) {
                [] = sprintf('%-22s %s',  . ':', fmt([]));
            }
        }

         = array_keys();
        foreach ( as  => ) {
            if (!in_array(, , true)) {
                [] = sprintf('%-22s %s',  . ':', fmt());
            }
        }

        [] = '';
    }

    return implode("\n", );
}

function log_note(string ): void {
    // Best-effort logging into PHP/webserver logs
    error_log('[csp-endpoint] ' . );
}

// ---- Main ----------------------------------------------------------------
 = ['REQUEST_METHOD'] ?? 'GET';

if ( === 'GET' ||  === 'HEAD') {
    header_status(200);
    echo "ok\n";
    exit;
}

if ( !== 'POST') {
    header('Allow: POST, GET, HEAD');
    header_status(405);
    echo "Use POST.\n";
    exit;
}

 = ['REMOTE_ADDR'] ?? 'unknown';
if (!rate_limit_allow()) {
    // Drop silently to avoid being used as a mail-bomb and avoid retries.
    log_note("rate-limited ip={}");
    http_response_code(204);
    exit;
}

 = read_request_body();
if ( === '') {
    // Keep 204 to avoid retries; log for debugging.
    log_note("empty/oversized body ip={}");
    http_response_code(204);
    exit;
}

 = json_decode(, true);
if ( === null && json_last_error() !== JSON_ERROR_NONE) {
    log_note("malformed json ip={} err=" . json_last_error_msg());
    http_response_code(204);
    exit;
}

 = extract_reports();
if (!) {
    // Some user agents send the legacy object directly without wrappers
    if (is_array())  = [];
}

if (!) {
    log_note("no reports found ip={}");
    http_response_code(204);
    exit;
}

 = build_email();

 = safe_header_text(FROM);
 = safe_header_text(SUBJECT . " (rootservice.org)");
 = safe_header_text(RECIPIENT);

 = [];
[] = "From: {}";
[] = "Reply-To: {}";
[] = "MIME-Version: 1.0";
[] = "Content-Type: text/plain; charset=utf-8";
[] = "X-CSP-Handler: v2";
 = implode("\r\n", );

 = @mail(, , , );
if (!) {
    log_note("mail delivery failed ip={}");
}

// Always respond 204 to avoid retry storms
http_response_code(204);
