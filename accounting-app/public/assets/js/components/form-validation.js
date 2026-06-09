/**
 * FormValidation — Kiểm tra dữ liệu biểu mẫu dùng chung
 * ===
 * Kiểm tra: required, min, max, pattern, độ dài, số >0
 * Hiển thị lỗi inline dưới mỗi trường
 * Tự động xóa lỗi khi sửa
 * Hỗ trợ kiểm tra Dr = Cr cho bút toán kế toán
 * Hỗ trợ kiểm tra ngày tháng (ngày không quá hiện tại)
 *
 * Cách dùng:
 *   data-v-required="Tên khách hàng"  → bắt buộc nhập
 *   data-v-number="Số tiền"            → phải là số > 0
 *   data-v-min="1000"                  → tối thiểu 1000
 *   data-v-max="1000000000"            → tối đa 1 tỷ
 *   data-v-length="10"                 → độ dài tối đa 10
 *   data-v-pattern="[0-9]{10}"         → regex
 *   data-v-date="Ngày chứng từ"        → phải là ngày hợp lệ, không quá hiện tại
 */

var FormValidation = (function () {
    var defaults = {
        errorClass: 'form-validation-error',
        errorStyle:
            'color:#dc2626;font-size:11px;margin-top:2px;display:block;line-height:1.3;',
        validClass: 'is-valid',
        invalidClass: 'is-invalid',
        debounceMs: 300
    };

    var timers = {};

    // Xóa lỗi của một field
    function clearField($el) {
        $el.closest('.mb-3, .col, td, .position-relative').find('.' + defaults.errorClass).remove();
        $el.removeClass(defaults.invalidClass).removeClass(defaults.validClass);
    }

    // Hiển thị lỗi cho một field
    function showError($el, msg) {
        clearField($el);
        $el.addClass(defaults.invalidClass);
        var $parent = $el.closest('.mb-3, .col, td, .position-relative');
        var $err = $('<div class="' + defaults.errorClass + '" style="' + defaults.errorStyle + '">' + msg + '</div>');
        if ($parent.find('.' + defaults.errorClass).length === 0) {
            $parent.append($err);
        }
    }

    // Hiển thị thành công
    function showValid($el) {
        $el.removeClass(defaults.invalidClass).addClass(defaults.validClass);
    }

    // Kiểm tra một field
    function validateField($el) {
        clearField($el);
        var val = $el.val() ? $el.val().toString().trim() : '';
        var name = $el.attr('name') || '';

        // Data attributes rules
        var required = $el.data('v-required');
        if (required) {
            if (!val) { showError($el, required + ' không được để trống.'); return false; }
        }

        var numberLabel = $el.data('v-number');
        if (numberLabel) {
            if (val && (isNaN(parseFloat(val)) || parseFloat(val) <= 0)) {
                showError($el, numberLabel + ' phải là số lớn hơn 0.');
                return false;
            }
        }

        if (val) {
            var numVal = parseFloat(val);

            var min = $el.data('v-min');
            if (min !== undefined && !isNaN(numVal) && numVal < parseFloat(min)) {
                showError($el, 'Giá trị tối thiểu là ' + min + '.');
                return false;
            }

            var max = $el.data('v-max');
            if (max !== undefined && !isNaN(numVal) && numVal > parseFloat(max)) {
                showError($el, 'Giá trị tối đa là ' + max + '.');
                return false;
            }

            var maxLen = $el.data('v-length');
            if (maxLen !== undefined && val.length > parseInt(maxLen)) {
                showError($el, 'Độ dài tối đa ' + maxLen + ' ký tự.');
                return false;
            }

            var pattern = $el.data('v-pattern');
            if (pattern) {
                var re = new RegExp('^' + pattern + '$');
                if (!re.test(val)) {
                    showError($el, 'Định dạng không hợp lệ.');
                    return false;
                }
            }

            var dateLabel = $el.data('v-date');
            if (dateLabel) {
                var d = new Date(val);
                if (isNaN(d.getTime())) {
                    showError($el, dateLabel + ' không hợp lệ.');
                    return false;
                }
            }

            var accountLabel = $el.data('v-account');
            if (accountLabel && val) {
                if (val.length < 3 || val.length > 7) {
                    showError($el, accountLabel + ' phải từ 3-7 ký tự.');
                    return false;
                }
            }
        }

        showValid($el);
        return true;
    }

    // Kiểm tra tất cả field trong form
    function validate(formSelector) {
        var $form = $(formSelector);
        if (!$form.length) return { valid: true, errors: {} };

        var errors = {};
        var valid = true;

        $form.find('[data-v-required],[data-v-number],[data-v-min],[data-v-max],[data-v-length],[data-v-pattern],[data-v-date],[data-v-account]').each(function () {
            var $el = $(this);
            if ($el.is(':disabled') || $el.closest('.d-none, [style*="display:none"]').length) return;
            if (!validateField($el)) {
                valid = false;
                var name = $el.attr('name') || $el.data('v-required') || 'field';
                errors[name] = $el.closest('.mb-3, .col, td').find('.' + defaults.errorClass).text();
            }
        });

        return { valid: valid, errors: errors };
    }

    // Kiểm tra Dr = Cr
    function checkDrCr(totalDr, totalCr, labelDr, labelCr) {
        var diff = Math.abs(totalDr - totalCr);
        if (diff > 10) {
            var msg =
                'Tổng Nợ (' +
                Number(totalDr).toLocaleString('vi-VN') +
                ') không khớp tổng Có (' +
                Number(totalCr).toLocaleString('vi-VN') +
                '). Chênh lệch: ' +
                Number(diff).toLocaleString('vi-VN') +
                ' VND.';
            return { valid: false, message: msg };
        }
        return { valid: true, message: '' };
    }

    // Xóa lỗi trong form
    function clear(formSelector) {
        $(formSelector).find('.' + defaults.errorClass).remove();
        $(formSelector).find('.' + defaults.invalidClass).removeClass(defaults.invalidClass);
        $(formSelector).find('.' + defaults.validClass).removeClass(defaults.validClass);
    }

    // Tự động kiểm tra khi blur và input
    function setup(formSelector) {
        $(formSelector).on('blur change',
            '[data-v-required],[data-v-number],[data-v-min],[data-v-max],[data-v-length],[data-v-pattern],[data-v-date],[data-v-account]',
            function () {
                var $el = $(this);
                var key = formSelector + '_' + ($el.attr('name') || $el.index());
                clearTimeout(timers[key]);
                timers[key] = setTimeout(function () { validateField($el); }, defaults.debounceMs);
            }
        );
        // Xóa lỗi ngay khi gõ
        $(formSelector).on('input',
            '[data-v-required],[data-v-number],[data-v-min],[data-v-max],[data-v-length],[data-v-pattern]',
            function () {
                var $el = $(this);
                $el.removeClass(defaults.invalidClass).removeClass(defaults.validClass);
                $el.closest('.mb-3, .col, td').find('.' + defaults.errorClass).remove();
            }
        );
    }

    return { validate: validate, validateField: validateField, checkDrCr: checkDrCr, clear: clear, setup: setup };
})();
