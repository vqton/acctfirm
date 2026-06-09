#!/bin/bash
#
# audit_php.sh — Phát hiện bug pattern PHP toàn codebase
#
# Checks:
#   1. PHP syntax errors
#   2. Common undefined methods (e.g., Auth::user())
#   3. Hardcoded user/credentials
#   4. Missing required fields in view forms
#
cd "$(dirname "$0")/.." || exit 1

ERRORS=0

echo "=== 1. PHP syntax check ==="
SYNTAX_ERRORS=$(find src public/views config tests database -name "*.php" -exec php -l {} \; 2>&1 | grep -v "No syntax errors" | grep -v "^$")
if [[ -n "$SYNTAX_ERRORS" ]]; then
    echo "$SYNTAX_ERRORS"
    ERRORS=$((ERRORS+$(echo "$SYNTAX_ERRORS" | wc -l)))
else
    echo "OK: All PHP files have valid syntax"
fi

echo ""
echo "=== 2. Auth::user() không tồn tại — dùng Auth::currentUser() hoặc getCurrentUserId() ==="
# Sử dụng grep để tìm, vì Auth::user() không phải function
USER_CALLS=$(grep -rn "Auth::user()" src/ public/ 2>/dev/null)
if [[ -n "$USER_CALLS" ]]; then
    echo "BUG: Auth::user() không tồn tại (chỉ có currentUser/getCurrentUserId):"
    echo "$USER_CALLS"
    ERRORS=$((ERRORS+$(echo "$USER_CALLS" | wc -l)))
else
    echo "OK: Không có Auth::user() call"
fi

echo ""
echo "=== 3. Session keys sai (user_id thay vì user['username']) ==="
SUSPECT_SESSION=$(grep -rn '\$_SESSION\[.user.\]\[.id.\]\|\$_SESSION\[.user_id.\]' src/ 2>/dev/null | head -5)
if [[ -n "$SUSPECT_SESSION" ]]; then
    echo "WARN: Session key có thể sai:"
    echo "$SUSPECT_SESSION"
else
    echo "OK: Session key usage"
fi

echo ""
echo "=== 4. Hardcoded credentials ==="
HARDCODED=$(grep -rn "password.*=.*['\"]" src/ database/seed_*.php 2>/dev/null | grep -v "//" | grep -v "PASSWORD_DEFAULT\|password_hash" | head -3)
if [[ -n "$HARDCODED" ]]; then
    echo "WARN: Có thể hardcoded password (manual review):"
    echo "$HARDCODED"
else
    echo "OK: No hardcoded passwords"
fi

echo ""
echo "=== 5. eval/exit/die trong controllers (FORBIDDEN) ==="
FORBIDDEN=$(grep -rn "^\s*eval(\|^\s*exit(\|^\s*die(" src/Accounting/Interfaces/ 2>/dev/null)
if [[ -n "$FORBIDDEN" ]]; then
    echo "BUG: eval/exit/die trong controllers (FORBIDDEN):"
    echo "$FORBIDDEN"
    ERRORS=$((ERRORS+$(echo "$FORBIDDEN" | wc -l)))
else
    echo "OK: No forbidden functions in controllers"
fi

echo ""
echo "=== 6. SQL string interpolation ==="
SQL_INTERP=$(grep -rn "WHERE.*\\\${\|WHERE.*\\\$" src/ 2>/dev/null | grep -v "?" | head -3)
if [[ -n "$SQL_INTERP" ]]; then
    echo "WARN: SQL có thể có string interpolation:"
    echo "$SQL_INTERP"
else
    echo "OK: No SQL string interpolation"
fi

echo ""
echo "=== Summary ==="
if [[ $ERRORS -eq 0 ]]; then
    echo "Total errors: 0"
    exit 0
else
    echo "Total errors: $ERRORS"
    exit 1
fi
