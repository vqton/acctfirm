// AccountPicker — typeahead search for account codes
// API: GET /api/coa/flat -> [{ id, code, name, type, is_control, balance }]

import type { Account } from '../types/index';
import { VAS } from '../lib/vas-financial';

interface AccountPickerOpts {
  placeholder?: string;
  onSelect?: (account: Account) => void;
}

interface AccountPickerInstance {
  val: (v?: string) => string | undefined;
  clear: () => void;
  destroy: () => void;
}

interface FlatAccount {
  id: string;
  code: string;
  name: string;
  type: string;
  is_control?: boolean;
  balance?: number;
}

let cache: FlatAccount[] | null = null;

function loadAccounts(callback: (accounts: FlatAccount[]) => void): void {
  if (cache) { callback(cache); return; }
  $.get('/api/coa/flat', (data: FlatAccount[]) => {
    cache = data || [];
    callback(cache);
  }).fail(() => { callback([]); });
}

function create($select: JQuery<HTMLElement>, opts?: AccountPickerOpts): AccountPickerInstance | undefined {
  opts = opts || {};
  const placeholder = opts.placeholder || 'Gõ mã hoặc tên TK...';
  const onSelect = opts.onSelect || null;
  const $wrapper = $('<div class="acc-picker-wrapper" style="position:relative;">');
  const $input = $('<input type="text" class="form-control form-control-sm acc-picker-input" autocomplete="off" placeholder="' + placeholder + '">');
  const $dropdown = $('<div class="acc-picker-dropdown" style="position:absolute;top:100%;left:0;right:0;z-index:1060;background:#fff;border:1px solid #d0d5dd;border-radius:4px;max-height:260px;overflow-y:auto;display:none;box-shadow:0 4px 12px rgba(0,0,0,.1);">');
  const selectedVal = $select.val() as string;
  const selectedLabel = $select.find('option:selected').text();

  $select.hide().after($wrapper);
  $wrapper.append($input).append($dropdown);

  if (selectedVal && selectedLabel && selectedLabel !== '-- TK --') {
    $input.val(selectedLabel);
  }

  let allAccounts: FlatAccount[] = [];
  loadAccounts((accounts) => {
    allAccounts = accounts;
    if (selectedVal) {
      const match = accounts.find((a) => a.code === selectedVal);
      if (match) $input.val(match.code + ' - ' + match.name);
    }
  });

  let currentFocus = -1;

  function filterAccounts(q: string): FlatAccount[] {
    if (!q) return allAccounts.slice(0, 50);
    const lower = q.toLowerCase();
    return allAccounts.filter((a) =>
      a.code.toLowerCase().indexOf(lower) !== -1 ||
      a.name.toLowerCase().indexOf(lower) !== -1
    ).slice(0, 50);
  }

  function renderDropdown(results: FlatAccount[]): void {
    $dropdown.empty();
    currentFocus = -1;
    if (!results.length) {
      $dropdown.append('<div class="p-2 text-muted" style="font-size:12px;">Không tìm thấy tài khoản</div>');
      $dropdown.show();
      return;
    }
    results.forEach((a, i) => {
      const isControl = a.is_control ? ' <span class="text-warning" title="TK tổng hợp">[Tổng hợp]</span>' : '';
      const balanceStr = a.balance
        ? ' <span class="text-muted" style="font-size:11px;">' + VAS.fmt(a.balance) + '</span>'
        : '';
      const $item = $(
        '<div class="acc-picker-item" data-value="' + a.code + '" style="padding:6px 10px;cursor:pointer;font-size:13px;border-bottom:1px solid #f0f1f3;display:flex;justify-content:space-between;align-items:center;">' +
        '<span><strong>' + esc(a.code) + '</strong> - ' + esc(a.name) + isControl + '</span>' +
        balanceStr +
        '</div>'
      );
      $item.on('mouseenter', () => {
        currentFocus = i;
        $dropdown.find('.acc-picker-item').removeClass('active').eq(i).addClass('active').css('background', '#eef2ff');
      });
      $item.on('click', () => { selectItem(a); });
      $dropdown.append($item);
    });
    $dropdown.show();
  }

  function selectItem(account: FlatAccount): void {
    $input.val(account.code + ' - ' + account.name);
    $select.val(account.code);
    $dropdown.hide();
    if (onSelect) onSelect(account as Account);
  }

  $input.on('input', function (this: HTMLElement) {
    const q = $(this).val() as string;
    renderDropdown(filterAccounts(q));
  });

  $input.on('focus', function () {
    if (!$dropdown.is(':visible')) {
      renderDropdown(filterAccounts(''));
    }
  });

  $input.on('keydown', function (this: HTMLElement, e: JQuery.KeyDownEvent) {
    const $items = $dropdown.find('.acc-picker-item');
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
    } else if (e.key === 'Enter' || e.key === 'Tab') {
      if ($dropdown.is(':visible') && currentFocus >= 0 && currentFocus < $items.length) {
        e.preventDefault();
        const $active = $items.eq(currentFocus);
        const code = $active.data('value') as string;
        const account = allAccounts.find((a) => a.code === code);
        if (account) selectItem(account);
      } else if (e.key === 'Enter') {
        e.preventDefault();
        const q = $input.val() as string;
        const results = filterAccounts(q);
        if (results.length === 1) {
          selectItem(results[0]!);
        }
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
    val: (v?: string) => {
      if (v === undefined) return $select.val() as string;
      const match = allAccounts.find((a) => a.code === v);
      if (match) selectItem(match);
      else $select.val(v);
      return undefined;
    },
    clear: () => { $input.val(''); $select.val(''); $dropdown.hide(); },
    destroy: () => { $input.remove(); $dropdown.remove(); $select.show(); $wrapper.remove(); },
  };
}

function enhance(selector: string): void {
  $(selector).each((_i: number, el: Element) => {
    const $sel = $(el as HTMLElement);
    if ($sel.data('acc-picker-initialized')) return;
    $sel.data('acc-picker-initialized', true);
    create($sel);
  });
}

function refreshCache(): void { cache = null; }

function esc(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

export const AccountPicker = { create, enhance, refreshCache };
export type AccountPickerApi = typeof AccountPicker;
