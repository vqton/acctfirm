/**
 * PartnerPicker — typeahead search for customers/suppliers/employees
 *
 * Nghiệp vụ: Chọn đối tượng (khách hàng, nhà cung cấp, nhân viên)
 *            cho bút toán kế toán. Hỗ trợ tra cứu nhanh bằng tên hoặc mã.
 *
 * API: GET /api/payers/search?q=... -> [{ id, code, name, type }]
 *
 * Usage:
 *   PartnerPicker.create('#partnerInput', {
 *     placeholder: 'Gõ tên hoặc mã...',
 *     onSelect: function(payer) { ... },
 *     typeFilter: 'customer'        // optional: 'customer'|'supplier'|'employee'
 *   });
 *
 *   // Or auto-enhance elements with .partner-picker class
 *   PartnerPicker.enhance('.partner-picker');
 *
 * Rủi ro: API search require >=1 character; empty query returns empty results
 */
var PartnerPicker = (function () {
    function create($input, opts) {
        opts = opts || {};
        var placeholder = opts.placeholder || 'Gõ tên hoặc mã...';
        var onSelect = opts.onSelect || null;
        var typeFilter = opts.typeFilter || null;
        var minChars = opts.minChars || 1;
        var $wrapper = $('<div class="partner-picker-wrapper" style="position:relative;">');
        var $dropdown = $('<div class="partner-picker-dropdown" style="position:absolute;top:100%;left:0;right:0;z-index:1060;background:#fff;border:1px solid #d0d5dd;border-radius:4px;max-height:240px;overflow-y:auto;display:none;box-shadow:0 4px 12px rgba(0,0,0,.1);">');
        var currentFocus = -1;
        var selectedPayer = null;

        // Wrap input
        $input.wrap('<div class="partner-picker-wrapper" style="position:relative;">');
        $wrapper = $input.closest('.partner-picker-wrapper');
        $input.after($dropdown);
        $input.attr('autocomplete', 'off');

        function search(q, callback) {
            if (q.length < minChars) { callback([]); return; }
            $.get('/api/payers/search?q=' + encodeURIComponent(q), function (data) {
                var results = data || [];
                if (typeFilter) {
                    results = results.filter(function (p) { return p.type === typeFilter; });
                }
                callback(results);
            }).fail(function () { callback([]); });
        }

        function renderDropdown(results) {
            $dropdown.empty();
            currentFocus = -1;
            if (!results.length) {
                $dropdown.append('<div class="p-2 text-muted" style="font-size:12px;">Không tìm thấy</div>');
                $dropdown.show();
                return;
            }
            results.forEach(function (p, i) {
                var typeLabel = { customer: 'KH', supplier: 'NCC', employee: 'NV' }[p.type] || p.type;
                var $item = $('<div class="partner-picker-item" data-value="' + esc(p.code) + '" data-id="' + (p.id || '') + '" style="padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f1f3;display:flex;justify-content:space-between;align-items:center;">' +
                    '<span><strong>' + esc(p.code || '') + '</strong> ' + esc(p.name) + '</span>' +
                    '<span class="badge bg-light text-muted" style="font-size:10px;">' + typeLabel + '</span>' +
                    '</div>');
                $item.on('mouseenter', function () { currentFocus = i; $dropdown.find('.partner-picker-item').removeClass('active').eq(i).addClass('active').css('background', '#eef2ff'); });
                $item.on('click', function () { selectItem(p); });
                $dropdown.append($item);
            });
            $dropdown.show();
        }

        function selectItem(payer) {
            selectedPayer = payer;
            $input.val(payer.name + (payer.code ? ' (' + payer.code + ')' : ''));
            $dropdown.hide();
            if (onSelect) onSelect(payer);
        }

        $input.on('input', function () {
            var q = $(this).val();
            if (q.length < minChars) { $dropdown.hide(); return; }
            search(q, function (results) { renderDropdown(results); });
        });

        $input.on('focus', function () {
            var q = $(this).val();
            if (q.length >= minChars) {
                search(q, function (results) { renderDropdown(results); });
            }
        });

        $input.on('keydown', function (e) {
            var $items = $dropdown.find('.partner-picker-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                currentFocus = Math.min(currentFocus + 1, $items.length - 1);
                $items.removeClass('active').css('background', '');
                $items.eq(currentFocus).addClass('active').css('background', '#eef2ff');
                var el = $items[currentFocus];
                if (el) el.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                currentFocus = Math.max(currentFocus - 1, -1);
                $items.removeClass('active').css('background', '');
                if (currentFocus >= 0) {
                    $items.eq(currentFocus).addClass('active').css('background', '#eef2ff');
                    var el = $items[currentFocus];
                    if (el) el.scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                if ($dropdown.is(':visible') && currentFocus >= 0 && currentFocus < $items.length) {
                    e.preventDefault();
                    var code = $items.eq(currentFocus).data('value');
                    // Find original data via re-search
                    var q = $input.val();
                    search(q, function (results) {
                        var match = results.find(function (p) { return p.code === code; });
                        if (match) selectItem(match);
                    });
                }
            } else if (e.key === 'Escape') {
                $dropdown.hide();
            }
        });

        $(document).on('mousedown', function (e) {
            if (!$wrapper[0].contains(e.target)) {
                $dropdown.hide();
            }
        });

        return {
            val: function () { return selectedPayer; },
            clear: function () { $input.val(''); selectedPayer = null; $dropdown.hide(); },
            destroy: function () { $dropdown.remove(); $input.unwrap(); }
        };
    }

    function enhance(selector) {
        $(selector).each(function () {
            var $el = $(this);
            if ($el.data('partner-picker-initialized')) return;
            $el.data('partner-picker-initialized', true);
            create($el);
        });
    }

    return {
        create: create,
        enhance: enhance
    };
})();
