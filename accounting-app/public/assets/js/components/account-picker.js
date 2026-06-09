/**
 * AccountPicker — typeahead search for account codes
 *
 * Nghiệp vụ: Chọn tài khoản kế toán từ danh sách 1000+ tài khoản
 *            Tra cứu nhanh bằng mã hoặc tên, hỗ trợ phím tắt
 *
 * API: GET /api/coa/flat -> [{ id, code, name, type, is_control, balance }]
 *
 * Usage:
 *   AccountPicker.create('#mySelect', {
 *     placeholder: 'Gõ mã hoặc tên TK...',
 *     onSelect: function(account) { ... }
 *   });
 *
 *   // Or transform existing <select>
 *   AccountPicker.enhance('.acc-picker');
 *
 * Rủi ro: Nếu API fail, fallback về <select> gốc
 */
var AccountPicker = (function () {
    var cache = null;

    function loadAccounts(callback) {
        if (cache) { callback(cache); return; }
        $.get('/api/coa/flat', function (data) {
            cache = data || [];
            callback(cache);
        }).fail(function () {
            callback([]);
        });
    }

    function create($select, opts) {
        opts = opts || {};
        var placeholder = opts.placeholder || 'Gõ mã hoặc tên TK...';
        var onSelect = opts.onSelect || null;
        var $wrapper = $('<div class="acc-picker-wrapper" style="position:relative;">');
        var $input = $('<input type="text" class="form-control form-control-sm acc-picker-input" autocomplete="off" placeholder="' + placeholder + '">');
        var $dropdown = $('<div class="acc-picker-dropdown" style="position:absolute;top:100%;left:0;right:0;z-index:1060;background:#fff;border:1px solid #d0d5dd;border-radius:4px;max-height:260px;overflow-y:auto;display:none;box-shadow:0 4px 12px rgba(0,0,0,.1);">');
        var selectedVal = $select.val();
        var selectedLabel = $select.find('option:selected').text();

        $select.hide().after($wrapper);
        $wrapper.append($input).append($dropdown);

        // If <select> had a value, show it
        if (selectedVal && selectedLabel && selectedLabel !== '-- TK --') {
            $input.val(selectedLabel);
        }

        var allAccounts = [];
        loadAccounts(function (accounts) {
            allAccounts = accounts;
            // If we had a selected value, try to restore display
            if (selectedVal) {
                var match = accounts.find(function (a) { return a.code === selectedVal; });
                if (match) $input.val(match.code + ' - ' + match.name);
            }
        });

        var currentFocus = -1;

        function filterAccounts(q) {
            if (!q) return allAccounts.slice(0, 50);
            var lower = q.toLowerCase();
            return allAccounts.filter(function (a) {
                return a.code.toLowerCase().indexOf(lower) !== -1 ||
                       a.name.toLowerCase().indexOf(lower) !== -1;
            }).slice(0, 50);
        }

        function renderDropdown(results) {
            $dropdown.empty();
            currentFocus = -1;
            if (!results.length) {
                $dropdown.append('<div class="p-2 text-muted" style="font-size:12px;">Không tìm thấy tài khoản</div>');
                $dropdown.show();
                return;
            }
            results.forEach(function (a, i) {
                var isControl = a.is_control ? ' <span class="text-warning" title="TK tổng hợp">[Tổng hợp]</span>' : '';
                var balance = a.balance ? ' <span class="text-muted" style="font-size:11px;">' + VAS.fmt(a.balance) + '</span>' : '';
                var $item = $('<div class="acc-picker-item" data-value="' + a.code + '" style="padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f1f3;display:flex;justify-content:space-between;align-items:center;">' +
                    '<span><strong>' + esc(a.code) + '</strong> - ' + esc(a.name) + isControl + '</span>' +
                    balance +
                    '</div>');
                $item.on('mouseenter', function () { currentFocus = i; $dropdown.find('.acc-picker-item').removeClass('active').eq(i).addClass('active').css('background', '#eef2ff'); });
                $item.on('click', function () { selectItem(a); });
                $dropdown.append($item);
            });
            $dropdown.show();
        }

        function selectItem(account) {
            $input.val(account.code + ' - ' + account.name);
            $select.val(account.code);
            $dropdown.hide();
            if (onSelect) onSelect(account);
        }

        $input.on('input', function () {
            var q = $(this).val();
            renderDropdown(filterAccounts(q));
        });

        $input.on('focus', function () {
            if (!$dropdown.is(':visible')) {
                renderDropdown(filterAccounts(''));
            }
        });

        $input.on('keydown', function (e) {
            var $items = $dropdown.find('.acc-picker-item');
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
            } else if (e.key === 'Enter' || e.key === 'Tab') {
                if ($dropdown.is(':visible') && currentFocus >= 0 && currentFocus < $items.length) {
                    e.preventDefault();
                    var $active = $items.eq(currentFocus);
                    var code = $active.data('value');
                    var account = allAccounts.find(function (a) { return a.code === code; });
                    if (account) selectItem(account);
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    // Try to match whatever was typed
                    var q = $input.val();
                    var results = filterAccounts(q);
                    if (results.length === 1) {
                        selectItem(results[0]);
                    }
                }
            } else if (e.key === 'Escape') {
                $dropdown.hide();
            }
        });

        // Close dropdown on outside click
        $(document).on('mousedown', function (e) {
            if (!$wrapper[0].contains(e.target)) {
                $dropdown.hide();
            }
        });

        // Return control methods
        return {
            val: function (v) {
                if (v === undefined) return $select.val();
                var match = allAccounts.find(function (a) { return a.code === v; });
                if (match) selectItem(match);
                else $select.val(v);
            },
            clear: function () {
                $input.val('');
                $select.val('');
                $dropdown.hide();
            },
            destroy: function () {
                $input.remove();
                $dropdown.remove();
                $select.show();
                $wrapper.remove();
            }
        };
    }

    // Enhance all .acc-picker selects on the page
    function enhance(selector) {
        $(selector).each(function () {
            var $sel = $(this);
            if ($sel.data('acc-picker-initialized')) return;
            $sel.data('acc-picker-initialized', true);
            create($sel);
        });
    }

    return {
        create: create,
        enhance: enhance,
        refreshCache: function () { cache = null; }
    };
})();
