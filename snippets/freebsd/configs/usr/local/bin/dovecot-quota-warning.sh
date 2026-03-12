#!/usr/local/bin/bash
# Purpose: Mail to user when quota exceeds specified percentage
# Reference: https://doc.dovecot.org/configuration_manual/quota/
# Location: /usr/local/bin/dovecot-quota-warning.sh
# Permissions: chown root:vmail && chmod 750

set -euo pipefail

# ============================================================================
# CONFIGURATION
# ============================================================================
LOG_FILE="/var/log/dovecot/quota-warnings.log"
POSTMASTER_NOTIFY_THRESHOLD=95
DOVECOT_LDA="/usr/local/libexec/dovecot/dovecot-lda"
HOSTNAME_FQDN="$(hostname -f)"

# ============================================================================
# LOGGING FUNCTION
# ============================================================================
log_message() {
    local level="${1}"
    local message="${2}"
    local timestamp
    timestamp="$(date '+%Y-%m-%d %H:%M:%S')"
    echo "[${timestamp}] [${level}] ${message}" >> "${LOG_FILE}"
}

# ============================================================================
# INPUT VALIDATION
# ============================================================================
PERCENT="${1:-}"
USER="${2:-}"

# Validate inputs
if [[ -z "${PERCENT}" || -z "${USER}" ]]; then
    log_message "ERROR" "Missing arguments: PERCENT=${PERCENT:-EMPTY} USER=${USER:-EMPTY}"
    exit 1
fi

# Validate PERCENT is numeric
if ! [[ "${PERCENT}" =~ ^[0-9]+$ ]]; then
    log_message "ERROR" "Invalid PERCENT value: ${PERCENT}"
    exit 1
fi

# Validate USER contains @
if ! [[ "${USER}" =~ ^[^@]+@[^@]+$ ]]; then
    log_message "ERROR" "Invalid USER format: ${USER}"
    exit 1
fi

log_message "INFO" "Quota warning triggered: USER=${USER} PERCENT=${PERCENT}%"

# ============================================================================
# QUOTA CONFIGURATION (MUST MATCH dovecot.conf plugin.quota)
# ============================================================================
# Note: Remove :noenforcing to match main quota configuration
QUOTA_CONFIG="dict:storage=2G quota::proxy::quota"

# ============================================================================
# SEND WARNING TO USER
# ============================================================================
send_user_warning() {
    local user="${1}"
    local percent="${2}"
    
    log_message "INFO" "Sending quota warning to ${user} (${percent}%)"
    
    if ! ${DOVECOT_LDA} -d "${user}" -o "plugin/quota=${QUOTA_CONFIG}" << EOF
From: no-reply@${HOSTNAME_FQDN}
To: ${user}
Subject: Warning: Your mailbox is now ${percent}% full
Auto-Submitted: auto-generated
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8

Your mailbox is now ${percent}% full.

Current usage: ${percent}% of allocated quota
Threshold: This warning was triggered at ${percent}%

Please delete old messages or archive them to free up space.
If you need additional storage, contact your administrator.

--
Automated quota warning from ${HOSTNAME_FQDN}
EOF
    then
        log_message "ERROR" "Failed to send quota warning to ${user}"
        return 1
    fi
    
    log_message "INFO" "Quota warning sent successfully to ${user}"
    return 0
}

# ============================================================================
# SEND COPY TO POSTMASTER (High threshold only)
# ============================================================================
send_postmaster_warning() {
    local user="${1}"
    local percent="${2}"
    local domain
    
    domain="$(echo "${user}" | awk -F'@' '{print $2}')"
    local postmaster="postmaster@${domain}"
    
    log_message "INFO" "Sending postmaster notification for ${user} (${percent}%)"
    
    if ! ${DOVECOT_LDA} -d "${postmaster}" -o "plugin/quota=${QUOTA_CONFIG}" << EOF
From: no-reply@${HOSTNAME_FQDN}
To: ${postmaster}
Subject: CRITICAL: Mailbox Quota Warning - ${percent}% full (${user})
Auto-Submitted: auto-generated
MIME-Version: 1.0
Content-Type: text/plain; charset=UTF-8
X-Priority: 1 (Highest)

CRITICAL QUOTA WARNING
======================

User Account: ${user}
Domain: ${domain}
Current Usage: ${percent}% of allocated quota
Threshold Triggered: ${POSTMASTER_NOTIFY_THRESHOLD}%
Timestamp: $(date '+%Y-%m-%d %H:%M:%S %Z')

ACTION REQUIRED:
This user's mailbox is critically full. Please contact the user to:
1. Delete unnecessary messages
2. Archive old emails
3. Request quota increase if needed

Failure to act may result in bounced incoming mail.

--
Automated quota alert from ${HOSTNAME_FQDN}
Dovecot Quota Warning System
EOF
    then
        log_message "ERROR" "Failed to send postmaster notification for ${user}"
        return 1
    fi
    
    log_message "INFO" "Postmaster notification sent successfully for ${user}"
    return 0
}

# ============================================================================
# MAIN EXECUTION
# ============================================================================
main() {
    # Ensure log directory exists
    mkdir -p "$(dirname "${LOG_FILE}")"
    
    # Send warning to user (all thresholds)
    if ! send_user_warning "${USER}" "${PERCENT}"; then
        log_message "ERROR" "User warning delivery failed"
        exit 1
    fi
    
    # Send postmaster copy only for critical thresholds
    if [[ "${PERCENT}" -ge "${POSTMASTER_NOTIFY_THRESHOLD}" ]]; then
        if ! send_postmaster_warning "${USER}" "${PERCENT}"; then
            log_message "ERROR" "Postmaster notification failed"
            # Don't exit - user warning was successful
        fi
    fi
    
    log_message "INFO" "Quota warning processing completed for ${USER}"
}

main
exit 0