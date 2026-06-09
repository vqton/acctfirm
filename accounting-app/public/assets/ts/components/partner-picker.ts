// PartnerPicker — typeahead search for customers/suppliers/employees
// API: GET /api/payers/search?q=... -> [{ id, code, name, type }]

import type { Partner } from '../types/index';

interface PartnerPickerOpts {
  placeholder?: string;
  onSelect?: (payer: Partner) => void;
  typeFilter?: 'customer' | 'supplier' | 'employee';
  minChars?: number;
}

interface PartnerPickerInstance {
  val: () => Partner | null;
  clear: () => void;
  destroy: () => void;
}

function create($input: JQuery, opts?: PartnerPickerOpts): PartnerPickerInstance {
  opts = opts || {};
  const placeholder = opts.placeholder || 'Gõ tên hoặc mã...';
  const onSelect = opts.onSelect || null;
  const typeFilter = opts.typeFilter || null;
  const minChars = opts.minChars || 1;
  let currentFocus = -1;
  let selectedPayer: Partner | null = null;

  $input.wrap('<div class="partner-picker-wrapper" style="position:relative;">');
  const $wrapper = $input.closest('.partner-picker-wrapper');
  const $dropdown = $('<div class="partner-picker-dropdown" style="position:absolute;top:100%;left:0;right:0;z-index:1060;background:#fff;border:1px solid #d0d5dd;border-radius:4px;max-height:240px;overflow-y:auto;display:none;box-shadow:0 4px 12px rgba(0,0,0,.1);">');
  $input.after($dropdown);
  $input.attr('autocomplete', 'off');

  function search(q: string, callback: (results: Partner[]) => void): void {
    if (q.length < minChars) { callback([]); return; }
    $.get('/api/payers/search?q=' + encodeURIComponent(q), (data: Partner[]) => {
      let results = data || [];
      if (typeFilter) {
        results = results.filter((p) => p.type === typeFilter);
      }
      callback(results);
    }).fail(() => { callback([]); });
  }

  function renderDropdown(results: Partner[]): void {
    $dropdown.empty();
    currentFocus = -1;
    if (!results.length) {
      $dropdown.append('<div class="p-2 text-muted" style="font-size:12px;">Không tìm thấy</div>');
      $dropdown.show();
      return;
    }
    results.forEach((p, i) => {
      const typeLabel = ({ customer: 'KH', supplier: 'NCC', employee: 'NV' } as Record<string, string>)[p.type] || p.type;
      const $item = $(
        '<div class="partner-picker-item" data-value="' + esc(p.code || '') + '" data-id="' + (p.id || '') + '" style="padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f1f3;display:flex;justify-content:space-between;align-items:center;">' +
        '<span><strong>' + esc(p.code || '') + '</strong> ' + esc(p.name) + '</span>' +
        '<span class="badge bg-light text-muted" style="font-size:10px;">' + esc(typeLabel) + '</span>' +
        '</div>'
      );
      $item.on('mouseenter', () => {
        currentFocus = i;
        $dropdown.find('.partner-picker-item').removeClass('active').eq(i).addClass('active').css('background', '#eef2ff');
      });
      $item.on('click', () => { selectItem(p); });
      $dropdown.append($item);
    });
    $dropdown.show();
  }

  function selectItem(payer: Partner): void {
    selectedPayer = payer;
    $input.val(payer.name + (payer.code ? ' (' + payer.code + ')' : ''));
    $dropdown.hide();
    if (onSelect) onSelect(payer);
  }

  $input.on('input', function (this: HTMLElement) {
    const q = $(this).val() as string;
    if (q.length < minChars) { $dropdown.hide(); return; }
    search(q, (results) => { renderDropdown(results); });
  });

  $input.on('focus', function (this: HTMLElement) {
    const q = $(this).val() as string;
    if (q.length >= minChars) {
      search(q, (results) => { renderDropdown(results); });
    }
  });

  $input.on('keydown', function (this: HTMLElement, e: JQuery.KeyDownEvent) {
    const $items = $dropdown.find('.partner-picker-item');
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      currentFocus = Math.min(currentFocus + 1, $items.length - 1);
      $items.removeClass('active').css('background', '');
      $items.eq(currentFocus).addClass('active').css('background', '#eef2ff');
      const el = $items[currentFocus];
      if (el) (el as HTMLElement).scrollIntoView({ block: 'nearest' });
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      currentFocus = Math.max(currentFocus - 1, -1);
      $items.removeClass('active').css('background', '');
      if (currentFocus >= 0) {
        $items.eq(currentFocus).addClass('active').css('background', '#eef2ff');
        const el = $items[currentFocus];
        if (el) (el as HTMLElement).scrollIntoView({ block: 'nearest' });
      }
    } else if (e.key === 'Enter') {
      if ($dropdown.is(':visible') && currentFocus >= 0 && currentFocus < $items.length) {
        e.preventDefault();
        const code = $items.eq(currentFocus).data('value') as string;
        const q = $input.val() as string;
        search(q, (results) => {
          const match = results.find((p) => p.code === code);
          if (match) selectItem(match);
        });
      }
    } else if (e.key === 'Escape') {
      $dropdown.hide();
    }
  });

  $(document).on('mousedown', (e: JQuery.MouseDownEvent) => {
    if (!$wrapper[0]!.contains(e.target as Node)) {
      $dropdown.hide();
    }
  });

  return {
    val: () => selectedPayer,
    clear: () => { $input.val(''); selectedPayer = null; $dropdown.hide(); },
    destroy: () => { $dropdown.remove(); $input.unwrap(); },
  };
}

function enhance(selector: string): void {
  $(selector).each((_i: number, el: Element) => {
    const $el = $(el as HTMLElement);
    if ($el.data('partner-picker-initialized')) return;
    $el.data('partner-picker-initialized', true);
    create($el);
  });
}

function esc(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

export const PartnerPicker = { create, enhance };
export type PartnerPickerApi = typeof PartnerPicker;
