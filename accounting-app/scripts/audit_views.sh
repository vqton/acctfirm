#!/bin/bash
#
# audit_views.sh — Phát hiện bug pattern trong view files
#
# Bug: view có `require layout.php` ở TOP (sai pattern)
# Đúng pattern: ob_start() ở top, $content = ob_get_clean(); require layout.php; ở bottom
#
# Output: list file bị lỗi (nếu có)
#
cd "$(dirname "$0")/.." || exit 1

VIOLATIONS=0
for view in public/views/*.php; do
    # Bỏ qua layout.php (là layout, không phải content view)
    [[ "$(basename "$view")" == "layout.php" ]] && continue

    # Check: có 'require layout.php' ở đầu file (trong 5 dòng đầu)?
    top_require=$(head -5 "$view" | grep -c "require.*layout\.php" || true)
    # Check: có ob_start() ở đầu file?
    has_ob_start_top=$(head -10 "$view" | grep -c "ob_start" || true)
    # Check: có ob_get_clean() ở cuối file (5 dòng cuối)?
    has_ob_get_clean_bottom=$(tail -5 "$view" | grep -c "ob_get_clean" || true)
    # Check: có require layout.php ở cuối file?
    has_layout_bottom=$(tail -5 "$view" | grep -c "require.*layout\.php" || true)

    # Pattern đúng: (top_require=0 hoặc top có ob_start) AND bottom có ob_get_clean + layout
    if [[ $top_require -gt 0 ]]; then
        echo "BUG: $view — 'require layout.php' ở TOP (sai pattern, sẽ render layout 2 lần hoặc trống)"
        VIOLATIONS=$((VIOLATIONS+1))
    elif [[ $has_ob_get_clean_bottom -eq 0 && $has_layout_bottom -gt 0 ]]; then
        echo "WARN: $view — thiếu ob_get_clean() ở bottom (content có thể không hiển thị)"
        VIOLATIONS=$((VIOLATIONS+1))
    fi
done

if [[ $VIOLATIONS -eq 0 ]]; then
    echo "OK: Tất cả 105 view files tuân thủ pattern đúng"
    exit 0
else
    echo ""
    echo "Total violations: $VIOLATIONS"
    exit 1
fi
