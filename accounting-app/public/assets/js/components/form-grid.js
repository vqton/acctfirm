/**
 * FormGrid — Lưới nhập liệu nhiều dòng dùng chung
 * ===
 * Hỗ trợ: thêm/xóa dòng, tính toán tự động, tab giữa các ô
 * Kiểu cột: text, number, currency, select, date, drcr (Nợ/Có)
 * Tính toán: qty × price = amount, tổng cộng cuối
 * Tab: Enter để xuống dòng mới, Tab để sang ô kế
 * Backward-compatible: hỗ trợ mọi form hiện tại
 *
 * Cách dùng:
 *   var grid = FormGrid.create('#linesContainer', {
 *       columns: [
 *           { key: 'account', label: 'TK', type: 'text', width: 100 },
 *           { key: 'description', label: 'Diễn giải', type: 'text', width: 'auto' },
 *           { key: 'qty', label: 'SL', type: 'number', width: 80 },
 *           { key: 'price', label: 'Đơn giá', type: 'currency', width: 120 },
 *           { key: 'amount', label: 'Thành tiền', type: 'currency', width: 140, formula: 'qty*price' },
 *           { key: 'drcr', label: 'Nợ/Có', type: 'drcr', width: 80 }
 *       ],
 *       totals: ['amount'],
 *       onChange: function(data) { ... },
 *       addRowText: 'Thêm dòng'
 *   });
 */

var FormGrid = (function () {
    var gridCounter = 0;

    function create(container, opts) {
        opts = opts || {};
        var $container = $(container);
        if (!$container.length) return null;

        var id = 'fg' + (++gridCounter);
        var columns = opts.columns || [];
        var totals = opts.totals || [];
        var data = opts.data || [];
        var onChange = opts.onChange || null;
        var addRowText = opts.addRowText || 'Thêm dòng';
        var rowClass = opts.rowClass || 'line-row';
        var allowAdd = opts.allowAdd !== false;
        var allowRemove = opts.allowRemove !== false;
        var tabPaste = opts.tabPaste !== false;

        // Tạo table
        var theadHtml = '<thead><tr><th style="width:30px"></th>';
        columns.forEach(function (col) {
            var w = col.width === 'auto' ? '' : 'width:' + (col.width || 120) + 'px';
            var align = col.align || 'left';
            theadHtml +=
                '<th style="' +
                w +
                ';text-align:' +
                align +
                ';padding:6px 8px;font-size:12px;white-space:nowrap;">' +
                (col.label || col.key) +
                '</th>';
        });
        theadHtml += '<th style="width:30px"></th></tr></thead>';

        var html =
            '<div class="form-grid" id="' +
            id +
            '">' +
            '<table class="table table-sm table-bordered mb-0" style="font-size:13px;min-width:100%;">' +
            theadHtml +
            '<tbody></tbody>' +
            '</table>' +
            '<div class="p-1" style="background:#f9fafb;border-top:1px solid #e2e6ef;display:flex;justify-content:space-between;align-items:center;">' +
            (allowAdd
                ? '<button type="button" class="btn btn-sm btn-outline-primary add-grid-row" style="font-size:12px;"><i class="bi bi-plus-lg"></i> ' +
                  addRowText +
                  '</button>'
                : '') +
            '<span class="text-muted" style="font-size:11px;" id="' +
            id +
            '_count"></span>' +
            '</div>' +
            '</div>';

        $container.html(html);

        var $grid = $('#' + id);
        var $tbody = $grid.find('tbody');
        var $addBtn = $grid.find('.add-grid-row');
        var $count = $('#' + id + '_count');

        // Render một dòng
        function renderRow(rowData, index) {
            var $tr = $('<tr class="' + rowClass + '" data-index="' + index + '">');

            // Drag handle / index
            $tr.append(
                '<td style="padding:4px;text-align:center;vertical-align:middle;">' +
                    '<span class="text-muted" style="font-size:10px;">' +
                        (index + 1) +
                    '</span>' +
                '</td>'
            );

            columns.forEach(function (col) {
                var val = rowData[col.key] !== undefined ? rowData[col.key] : '';
                var align = col.align || 'left';
                var inputHtml = '';

                switch (col.type) {
                    case 'select':
                        var options = col.options || [];
                        var sel = '<select class="form-select form-select-sm" style="font-size:12px;border:1px solid #d0d5dd;border-radius:4px;padding:2px 6px;width:100%;box-sizing:border-box;" name="' + col.key + '">';
                        options.forEach(function (opt) {
                            var ov = typeof opt === 'object' ? opt.value : opt;
                            var ol = typeof opt === 'object' ? opt.label : opt;
                            sel += '<option value="' + ov + '"' + (ov == val ? ' selected' : '') + '>' + ol + '</option>';
                        });
                        sel += '</select>';
                        inputHtml = sel;
                        break;

                    case 'drcr':
                        inputHtml =
                            '<select class="form-select form-select-sm dr-cr" style="font-size:12px;border:1px solid #d0d5dd;border-radius:4px;padding:2px 6px;width:100%;box-sizing:border-box;" name="' +
                            col.key +
                            '">' +
                            '<option value="">--</option>' +
                            '<option value="debit"' +
                            (val === 'debit' ? ' selected' : '') +
                            '>Nợ</option>' +
                            '<option value="credit"' +
                            (val === 'credit' ? ' selected' : '') +
                            '>Có</option>' +
                            '</select>';
                        break;

                    case 'number':
                        inputHtml =
                            '<input type="text" class="form-control form-control-sm grid-input grid-number" style="font-size:12px;border:1px solid #d0d5dd;border-radius:4px;padding:2px 6px;width:100%;box-sizing:border-box;text-align:' +
                            align +
                            ';" name="' +
                            col.key +
                            '" value="' +
                            esc(val) +
                            '">';
                        break;

                    case 'currency':
                        var fmt = val !== '' ? Number(val).toLocaleString('vi-VN') : '';
                        inputHtml =
                            '<input type="text" class="form-control form-control-sm grid-input grid-currency" style="font-size:12px;border:1px solid #d0d5dd;border-radius:4px;padding:2px 6px;width:100%;box-sizing:border-box;text-align:right;" name="' +
                            col.key +
                            '" value="' +
                            fmt +
                            '">';
                        break;

                    case 'date':
                        inputHtml =
                            '<input type="date" class="form-control form-control-sm grid-input grid-date" style="font-size:12px;border:1px solid #d0d5dd;border-radius:4px;padding:2px 6px;width:100%;box-sizing:border-box;" name="' +
                            col.key +
                            '" value="' +
                            esc(val) +
                            '">';
                        break;

                    default: // text
                        inputHtml =
                            '<input type="text" class="form-control form-control-sm grid-input" style="font-size:12px;border:1px solid #d0d5dd;border-radius:4px;padding:2px 6px;width:100%;box-sizing:border-box;text-align:' +
                            align +
                            ';" name="' +
                            col.key +
                            '" value="' +
                            esc(val) +
                            '">';
                        break;
                }

                $tr.append(
                    '<td style="padding:4px;vertical-align:middle;">' + inputHtml + '</td>'
                );
            });

            // Remove btn
            var removeBtnHtml = '';
            if (allowRemove) {
                removeBtnHtml =
                    '<button type="button" class="btn btn-sm btn-outline-danger border-0 grid-remove-btn" style="padding:0 4px;font-size:14px;line-height:1;" onclick="FormGrid.removeRow(this)" title="Xóa dòng">' +
                    '×</button>';
            }
            $tr.append(
                '<td style="padding:4px;text-align:center;vertical-align:middle;">' +
                    removeBtnHtml +
                '</td>'
            );

            return $tr;
        }

        // Lấy dữ liệu từ dòng
        function getRowData($tr) {
            var row = {};
            columns.forEach(function (col) {
                var $input = $tr.find('[name="' + col.key + '"]');
                if ($input.length) {
                    if ($input.is('select')) {
                        row[col.key] = $input.val();
                    } else {
                        var raw = $input.val();
                        if (col.type === 'currency' || col.type === 'number') {
                            raw = raw.replace(/\./g, '').replace(/,/g, '').trim();
                            row[col.key] = raw === '' ? '' : parseFloat(raw) || 0;
                        } else {
                            row[col.key] = raw;
                        }
                    }
                }
            });
            return row;
        }

        // Tính toán các cột formula
        function calcFormulas() {
            $tbody.find('tr.' + rowClass).each(function () {
                var $tr = $(this);
                var row = getRowData($tr);
                columns.forEach(function (col) {
                    if (col.formula) {
                        try {
                            var qty = parseFloat(row['qty']) || 0;
                            var price = parseFloat(row['price']) || 0;
                            var amount = 0;
                            if (col.formula === 'qty*price') amount = qty * price;
                            if (col.formula === 'qty*unit_price') amount = qty * price;
                            // Có thể mở rộng thêm formula
                            if (col.formula === 'price/qty' && qty !== 0) amount = price / qty;
                            // Formula tùy chỉnh qua callback
                            if (typeof col.formula === 'function') amount = col.formula(row);
                            if (col.type === 'currency') {
                                $tr.find('[name="' + col.key + '"]').val(Number(amount).toLocaleString('vi-VN'));
                            } else if (col.type === 'number') {
                                $tr.find('[name="' + col.key + '"]').val(amount);
                            }
                            row.amount = amount;
                        } catch (e) {}
                    }
                });
            });
        }

        // Tính tổng
        function calcTotals() {
            var sums = {};
            totals.forEach(function (key) {
                sums[key] = 0;
            });
            $tbody.find('tr.' + rowClass).each(function () {
                var row = getRowData($(this));
                totals.forEach(function (key) {
                    sums[key] += parseFloat(row[key]) || 0;
                });
            });

            // Xóa hoặc cập nhật total row
            $grid.find('.grid-total-row').remove();
            if ($tbody.find('tr.' + rowClass).length > 0 && totals.length > 0) {
                var $tfoot = $('<tr class="grid-total-row fw-bold" style="background:#f8f9fc;">');
                $tfoot.append('<td style="padding:4px;text-align:center;">Σ</td>');
                columns.forEach(function (col) {
                    var val = '';
                    if (totals.indexOf(col.key) !== -1) {
                        val = Number(sums[col.key]).toLocaleString('vi-VN');
                    }
                    $tfoot.append(
                        '<td style="padding:4px 8px;text-align:right;font-size:13px;border-top:2px solid #1a2a3a;">' +
                            val +
                        '</td>'
                    );
                });
                $tfoot.append('<td style="padding:4px;"></td>');
                $tbody.append($tfoot);
            }
        }

        // Cập nhật số dòng
        function updateCount() {
            var n = $tbody.find('tr.' + rowClass).length;
            $count.text(n + ' dòng');
        }

        // Lấy tất cả dữ liệu grid
        function getData() {
            var result = [];
            $tbody.find('tr.' + rowClass).each(function () {
                result.push(getRowData($(this)));
            });
            return result;
        }

        // Xóa dòng
        function removeRow(btn) {
            var $tr = $(btn).closest('tr.' + rowClass);
            if ($tbody.find('tr.' + rowClass).length <= 1) {
                FormToast.warning('Phải có ít nhất một dòng.');
                return;
            }
            $tr.remove();
            recalc();
        }

        // Thêm dòng (có thể truyền dữ liệu)
        function addRow(rowData) {
            var idx = $tbody.find('tr.' + rowClass).length;
            var $tr = renderRow(rowData || {}, idx);
            $tbody.append($tr);
            recalc();
            // Focus vào ô đầu tiên của dòng mới
            $tr.find('input,select').first().focus();
        }

        // Tính toán lại
        function recalc() {
            calcFormulas();
            calcTotals();
            updateCount();
            if (onChange) onChange(getData());
        }

        // Tab navigation
        $tbody.on('keydown', 'input,select', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                var $current = $(this);
                var $tr = $current.closest('tr.' + rowClass);
                var $inputs = $tr.find('input,select');
                var idx = $inputs.index($current);

                if (idx < $inputs.length - 1) {
                    // Sang ô kế trong cùng dòng
                    $inputs.eq(idx + 1).focus().select();
                } else {
                    // Xuống dòng mới
                    var $nextTr = $tr.next('tr.' + rowClass);
                    if ($nextTr.length) {
                        $nextTr.find('input,select').first().focus().select();
                    } else if (allowAdd) {
                        addRow();
                    }
                }
            }

            if (e.key === 'Tab') {
                // Cho phép tab mặc định nếu Shift+Tab (lùi) hoặc Tab ở ô cuối cuối
                // Tab trên cột tính toán → nhảy đến cột kế
                if (!e.shiftKey) {
                    var $current = $(this);
                    var $tr = $current.closest('tr.' + rowClass);
                    var $inputs = $tr.find('input,select');
                    var idx = $inputs.index($current);
                    if (idx < $inputs.length - 1) {
                        e.preventDefault();
                        $inputs.eq(idx + 1).focus().select();
                    } else {
                        e.preventDefault();
                        var $nextTr = $tr.next('tr.' + rowClass);
                        if ($nextTr.length) {
                            $nextTr.find('input,select').first().focus().select();
                        } else if (allowAdd) {
                            addRow();
                        }
                    }
                }
            }
        });

        // Currency format: tự động format khi blur
        $tbody.on('blur', '.grid-currency', function () {
            var raw = $(this).val().replace(/\./g, '').replace(/,/g, '').trim();
            var num = parseFloat(raw) || 0;
            $(this).val(num.toLocaleString('vi-VN'));
            recalc();
        });

        // Number format
        $tbody.on('blur', '.grid-number', function () {
            recalc();
        });

        // Select change
        $tbody.on('change', 'select', function () {
            recalc();
        });

        // Input change (debounced)
        var recalcTimer;
        $tbody.on('input', '.grid-input', function () {
            clearTimeout(recalcTimer);
            recalcTimer = setTimeout(recalc, 200);
        });

        // Click thêm dòng
        $addBtn.on('click', function () {
            addRow();
        });

        // Load data ban đầu
        if (data.length > 0) {
            data.forEach(function (row) {
                var idx = $tbody.find('tr.' + rowClass).length;
                $tbody.append(renderRow(row, idx));
            });
            recalc();
        } else if (allowAdd) {
            addRow({});
        }

        return {
            getData: getData,
            addRow: addRow,
            removeRow: removeRow,
            recalc: recalc,
            clear: function () {
                $tbody.empty();
                if (allowAdd) addRow({});
                recalc();
            },
            setData: function (rows) {
                $tbody.empty();
                if (rows && rows.length) {
                    rows.forEach(function (row) {
                        var idx = $tbody.find('tr.' + rowClass).length;
                        $tbody.append(renderRow(row, idx));
                    });
                } else if (allowAdd) {
                    addRow({});
                }
                recalc();
            },
            $grid: $grid,
            $tbody: $tbody
        };
    }

    // Static removeRow để dùng từ inline onclick
    function removeRow(btn) {
        var $grid = $(btn).closest('.form-grid');
        var $tr = $(btn).closest('tr.' + 'line-row');
        if ($grid.find('tbody tr.' + 'line-row').length <= 1) {
            FormToast.warning('Phải có ít nhất một dòng.');
            return;
        }
        $tr.remove();
        // Tìm instance và recalc
        $grid.find('input,select').first().trigger('input');
    }

    return { create: create, removeRow: removeRow };
})();
