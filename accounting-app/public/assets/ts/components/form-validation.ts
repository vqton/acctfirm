// FormValidation — Kiểm tra dữ liệu biểu mẫu
// data-v-required, data-v-number, data-v-min, data-v-max, data-v-pattern, etc.

interface ValidationResult {
  valid: boolean;
  errors: Record<string, string>;
}

interface DrCrResult {
  valid: boolean;
  message: string;
}

const defaults = {
  errorClass: 'form-validation-error' as string,
  errorStyle: 'color:#dc2626;font-size:11px;margin-top:2px;display:block;line-height:1.3;',
  validClass: 'is-valid' as string,
  invalidClass: 'is-invalid' as string,
  debounceMs: 300,
};

const timers: Record<string, ReturnType<typeof setTimeout>> = {};

function clearField($el: JQuery): void {
  $el.closest('.mb-3, .col, td, .position-relative').find('.' + defaults.errorClass).remove();
  $el.removeClass(defaults.invalidClass).removeClass(defaults.validClass);
}

function showError($el: JQuery, msg: string): void {
  clearField($el);
  $el.addClass(defaults.invalidClass);
  const $parent = $el.closest('.mb-3, .col, td, .position-relative');
  const $err = $('<div class="' + defaults.errorClass + '" style="' + defaults.errorStyle + '">' + esc(msg) + '</div>');
  if ($parent.find('.' + defaults.errorClass).length === 0) {
    $parent.append($err);
  }
}

function showValid($el: JQuery): void {
  $el.removeClass(defaults.invalidClass).addClass(defaults.validClass);
}

function esc(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function validateField($el: JQuery): boolean {
  clearField($el);
  const val = $el.val() ? String($el.val()).trim() : '';

  const required = $el.data('v-required') as string | undefined;
  if (required) {
    if (!val) { showError($el, required + ' không được để trống.'); return false; }
  }

  const numberLabel = $el.data('v-number') as string | undefined;
  if (numberLabel) {
    if (val && (isNaN(parseFloat(val)) || parseFloat(val) <= 0)) {
      showError($el, numberLabel + ' phải là số lớn hơn 0.');
      return false;
    }
  }

  if (val) {
    const numVal = parseFloat(val);

    const min = $el.data('v-min') as string | undefined;
    if (min !== undefined && !isNaN(numVal) && numVal < parseFloat(min)) {
      showError($el, 'Giá trị tối thiểu là ' + min + '.');
      return false;
    }

    const max = $el.data('v-max') as string | undefined;
    if (max !== undefined && !isNaN(numVal) && numVal > parseFloat(max)) {
      showError($el, 'Giá trị tối đa là ' + max + '.');
      return false;
    }

    const maxLen = $el.data('v-length') as string | undefined;
    if (maxLen !== undefined && val.length > parseInt(maxLen)) {
      showError($el, 'Độ dài tối đa ' + maxLen + ' ký tự.');
      return false;
    }

    const pattern = $el.data('v-pattern') as string | undefined;
    if (pattern) {
      const re = new RegExp('^' + pattern + '$');
      if (!re.test(val)) {
        showError($el, 'Định dạng không hợp lệ.');
        return false;
      }
    }

    const dateLabel = $el.data('v-date') as string | undefined;
    if (dateLabel) {
      const d = new Date(val);
      if (isNaN(d.getTime())) {
        showError($el, dateLabel + ' không hợp lệ.');
        return false;
      }
    }

    const accountLabel = $el.data('v-account') as string | undefined;
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

function validate(formSelector: string): ValidationResult {
  const $form = $(formSelector);
  if (!$form.length) return { valid: true, errors: {} };

  const errors: Record<string, string> = {};
  let valid = true;

  const selectors = [
    '[data-v-required]', '[data-v-number]', '[data-v-min]', '[data-v-max]',
    '[data-v-length]', '[data-v-pattern]', '[data-v-date]', '[data-v-account]',
  ].join(',');

  $form.find(selectors).each((_i: number, el: Element) => {
    const $el = $(el as HTMLElement);
    if ($el.is(':disabled') || $el.closest('.d-none, [style*="display:none"]').length) return;
    if (!validateField($el)) {
      valid = false;
      const name = $el.attr('name') || $el.data('v-required') || 'field';
      errors[name] = $el.closest('.mb-3, .col, td').find('.' + defaults.errorClass).text();
    }
  });

  return { valid, errors };
}

function checkDrCr(totalDr: number, totalCr: number): DrCrResult {
  const diff = Math.abs(totalDr - totalCr);
  if (diff > 10) {
    const msg = 'Tổng Nợ (' + Number(totalDr).toLocaleString('vi-VN') +
      ') không khớp tổng Có (' + Number(totalCr).toLocaleString('vi-VN') +
      '). Chênh lệch: ' + Number(diff).toLocaleString('vi-VN') + ' VND.';
    return { valid: false, message: msg };
  }
  return { valid: true, message: '' };
}

function clear_(formSelector: string): void {
  $(formSelector).find('.' + defaults.errorClass).remove();
  $(formSelector).find('.' + defaults.invalidClass).removeClass(defaults.invalidClass);
  $(formSelector).find('.' + defaults.validClass).removeClass(defaults.validClass);
}

function setup(formSelector: string): void {
  const selectors = [
    '[data-v-required]', '[data-v-number]', '[data-v-min]', '[data-v-max]',
    '[data-v-length]', '[data-v-pattern]', '[data-v-date]', '[data-v-account]',
  ].join(',');

  $(formSelector).on('blur change', selectors, function (this: HTMLElement) {
    const $el = $(this);
    const key = formSelector + '_' + ($el.attr('name') || $el.index());
    clearTimeout(timers[key]);
    timers[key] = setTimeout(() => { validateField($el); }, defaults.debounceMs);
  });

  $(formSelector).on('input',
    '[data-v-required],[data-v-number],[data-v-min],[data-v-max],[data-v-length],[data-v-pattern]',
    function (this: Element) {
      const $el = $(this);
      $el.removeClass(defaults.invalidClass).removeClass(defaults.validClass);
      $el.closest('.mb-3, .col, td').find('.' + defaults.errorClass).remove();
    }
  );
}

export const FormValidation = { validate, validateField, checkDrCr, clear: clear_, setup };
export type FormValidationApi = typeof FormValidation;
