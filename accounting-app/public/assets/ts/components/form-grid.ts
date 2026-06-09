// FormGrid — Lưới nhập liệu nhiều dòng dùng chung
// Column types: text, number, currency, select, date, drcr
// Formulas: qty*price, custom function
// Enter/Tab navigation, totals row, add/remove rows

import { FormToast } from './form-toast';

type ColumnType = 'text' | 'number' | 'currency' | 'select' | 'date' | 'drcr';

interface GridColumn {
  key: string;
  label?: string;
  type: ColumnType;
  width?: number | 'auto';
  align?: 'left' | 'right' | 'center';
  formula?: string | ((row: Record<string, unknown>) => number);
  options?: Array<string | { value: string; label: string }>;
}

interface GridOptions {
  columns: GridColumn[];
  totals?: string[];
  data?: Array<Record<string, unknown>>;
  onChange?: (data: Array<Record<string, unknown>>) => void;
  addRowText?: string;
  rowClass?: string;
  allowAdd?: boolean;
  allowRemove?: boolean;
  tabPaste?: boolean;
}

interface GridApi {
  getData: () => Array<Record<string, unknown>>;
  addRow: (rowData?: Record<string, unknown>) => void;
  removeRow: (btn: HTMLElement) => void;
  recalc: () => void;
  clear: () => void;
  setData: (rows: Array<Record<string, unknown>>) => void;
  $grid: JQuery;
  $tbody: JQuery;
}

let gridCounter = 0;

function esc(s: unknown): string {
  const str = typeof s === 'string' ? s : String(s);
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function create(container: string, opts: GridOptions): GridApi | null {
  opts = opts || {};
  const $container = $(container);
  if (!$container.length) return null;

  const id = 'fg' + (++gridCounter);
  const columns = opts.columns || [];
  const totals = opts.totals || [];
  const data = opts.data || [];
  const onChange = opts.onChange || null;
  const addRowText = opts.addRowText || 'Thêm dòng';
  const rowClass = opts.rowClass || 'line-row';
  const allowAdd = opts.allowAdd !== false;
  const allowRemove = opts.allowRemove !== false;

  // Build thead
  let theadHtml = '<thead><tr><th style="width:30px"></th>';
  columns.forEach((col) => {
    const w = col.width === 'auto' ? '' : 'width:' + (col.width || 120) + 'px';
    const align = col.align || 'left';
    theadHtml +=
      '<th style="' + w + ';text-align:' + align + ';padding:6px 8px;font-size:12px;white-space:nowrap;">' +
      (col.label || col.key) +
      '</th>';
  });
  theadHtml += '<th style="width:30px"></th></tr></thead>';

  const html =
    '<div class="form-grid" id="' + id + '">' +
    '<table class="table table-sm table-bordered mb-0" style="font-size:13px;min-width:100%;">' +
    theadHtml +
    '<tbody></tbody>' +
    '</table>' +
    '<div class="p-1" style="background:#f9fafb;border-top:1px solid #e2e6ef;display:flex;justify-content:space-between;align-items:center;">' +
    (allowAdd
      ? '<button type="button" class="btn btn-sm btn-outline-primary add-grid-row" style="font-size:12px;"><i class="bi bi-plus-lg"></i> ' + addRowText + '</button>'
      : '') +
    '<span class="text-muted" style="font-size:11px;" id="' + id + '_count"></span>' +
    '</div>' +
    '</div>';

  $container.html(html);

  const $grid = $('#' + id);
  const $tbody = $grid.find('tbody');
  const $addBtn = $grid.find('.add-grid-row');
  const $count = $('#' + id + '_count');

  function renderRow(rowData: Record<string, unknown> | null, index: number): JQuery {
    const $tr = $('<tr class="' + rowClass + '" data-index="' + index + '">');
    const data = rowData || {};

    $tr.append(
      '<td style="padding:4px;text-align:center;vertical-align:middle;">' +
      '<span class="text-muted" style="font-size:10px;">' + (index + 1) + '</span></td>'
    );

    columns.forEach((col) => {
      const val = data[col.key] !== undefined ? data[col.key] : '';
      const align = col.align || 'left';
      let inputHtml = '';

      switch (col.type) {
        case 'select': {
          const options = col.options || [];
          let sel = '<select class="form-select form-select-sm" style="font-size:12px;border:1px solid #d0d5dd;border-radius:4px;padding:2px 6px;width:100%;box-sizing:border-box;" name="' + col.key + '">';
          options.forEach((opt) => {
            const ov = typeof opt === 'object' ? opt.value : opt;
            const ol = typeof opt === 'object' ? opt.label : opt;
            sel += '<option value="' + esc(ov) + '"' + (String(ov) === String(val) ? ' selected' : '') + '>' + esc(ol) + '</option>';
          });
          sel += '</select>';
          inputHtml = sel;
          break;
        }
        case 'drcr':
          inputHtml =
            '<select class="form-select form-select-sm dr-cr" style="font-size:12px;border:1px solid #d0d5dd;border-radius:4px;padding:2px 6px;width:100%;box-sizing:border-box;" name="' + col.key + '">' +
            '<option value="">--</option>' +
            '<option value="debit"' + (val === 'debit' ? ' selected' : '') + '>Nợ</option>' +
            '<option value="credit"' + (val === 'credit' ? ' selected' : '') + '>Có</option>' +
            '</select>';
          break;
        case 'number':
          inputHtml =
            '<input type="text" class="form-control form-control-sm grid-input grid-number" style="font-size:12px;border:1px solid #d0d5dd;border-radius:4px;padding:2px 6px;width:100%;box-sizing:border-box;text-align:' + align + ';" name="' + col.key + '" value="' + esc(val) + '">';
          break;
        case 'currency': {
          const fmt = val !== '' ? Number(val).toLocaleString('vi-VN') : '';
          inputHtml =
            '<input type="text" class="form-control form-control-sm grid-input grid-currency" style="font-size:12px;border:1px solid #d0d5dd;border-radius:4px;padding:2px 6px;width:100%;box-sizing:border-box;text-align:right;" name="' + col.key + '" value="' + fmt + '">';
          break;
        }
        case 'date':
          inputHtml =
            '<input type="date" class="form-control form-control-sm grid-input grid-date" style="font-size:12px;border:1px solid #d0d5dd;border-radius:4px;padding:2px 6px;width:100%;box-sizing:border-box;" name="' + col.key + '" value="' + esc(val) + '">';
          break;
        default: // text
          inputHtml =
            '<input type="text" class="form-control form-control-sm grid-input" style="font-size:12px;border:1px solid #d0d5dd;border-radius:4px;padding:2px 6px;width:100%;box-sizing:border-box;text-align:' + align + ';" name="' + col.key + '" value="' + esc(val) + '">';
          break;
      }

      $tr.append('<td style="padding:4px;vertical-align:middle;">' + inputHtml + '</td>');
    });

    let removeBtnHtml = '';
    if (allowRemove) {
      removeBtnHtml =
        '<button type="button" class="btn btn-sm btn-outline-danger border-0 grid-remove-btn" style="padding:0 4px;font-size:14px;line-height:1;" onclick="FormGrid.removeRow(this)" title="Xóa dòng">×</button>';
    }
    $tr.append(
      '<td style="padding:4px;text-align:center;vertical-align:middle;">' + removeBtnHtml + '</td>'
    );

    return $tr;
  }

  function getRowData($tr: JQuery): Record<string, unknown> {
    const row: Record<string, unknown> = {};
    columns.forEach((col) => {
      const $input = $tr.find('[name="' + col.key + '"]');
      if ($input.length) {
        if ($input.is('select')) {
          row[col.key] = $input.val() as string;
        } else {
          let raw = $input.val() as string;
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

  function calcFormulas(): void {
    $tbody.find('tr.' + rowClass).each((_i: number, el: Element) => {
      const $tr = $(el as HTMLElement);
      const row = getRowData($tr);
      columns.forEach((col) => {
        if (col.formula) {
          try {
            const qty = parseFloat(String(row['qty'])) || 0;
            const price = parseFloat(String(row['price'])) || 0;
            let amount = 0;
            if (col.formula === 'qty*price' || col.formula === 'qty*unit_price') {
              amount = qty * price;
            } else if (col.formula === 'price/qty' && qty !== 0) {
              amount = price / qty;
            } else if (typeof col.formula === 'function') {
              amount = col.formula(row);
            }
            if (col.type === 'currency') {
              $tr.find('[name="' + col.key + '"]').val(Number(amount).toLocaleString('vi-VN'));
            } else if (col.type === 'number') {
              $tr.find('[name="' + col.key + '"]').val(String(amount));
            }
            row.amount = amount;
          } catch (_e) { /* silent — formula calc errors shouldn't break UI */ }
        }
      });
    });
  }

  function calcTotals(): void {
    const sums: Record<string, number> = {};
    totals.forEach((key) => { sums[key] = 0; });
    $tbody.find('tr.' + rowClass).each((_i: number, el: Element) => {
      const row = getRowData($(el as HTMLElement));
      totals.forEach((key) => {
        sums[key]! += parseFloat(String(row[key])) || 0;
      });
    });

    $grid.find('.grid-total-row').remove();
    if ($tbody.find('tr.' + rowClass).length > 0 && totals.length > 0) {
      const $tfoot = $('<tr class="grid-total-row fw-bold" style="background:#f8f9fc;">');
      $tfoot.append('<td style="padding:4px;text-align:center;">Σ</td>');
      columns.forEach((col) => {
        let val = '';
        if (totals.indexOf(col.key) !== -1) {
          val = Number(sums[col.key]).toLocaleString('vi-VN');
        }
        $tfoot.append(
          '<td style="padding:4px 8px;text-align:right;font-size:13px;border-top:2px solid #1a2a3a;">' + val + '</td>'
        );
      });
      $tfoot.append('<td style="padding:4px;"></td>');
      $tbody.append($tfoot);
    }
  }

  function updateCount(): void {
    const n = $tbody.find('tr.' + rowClass).length;
    $count.text(n + ' dòng');
  }

  function getData(): Array<Record<string, unknown>> {
    const result: Array<Record<string, unknown>> = [];
    $tbody.find('tr.' + rowClass).each((_i: number, el: Element) => {
      result.push(getRowData($(el as HTMLElement)));
    });
    return result;
  }

  function removeRow(btn: HTMLElement): void {
    const $tr = $(btn).closest('tr.' + rowClass);
    if ($tbody.find('tr.' + rowClass).length <= 1) {
      FormToast.warning('Phải có ít nhất một dòng.');
      return;
    }
    $tr.remove();
    recalc();
  }

  function addRow(rowData?: Record<string, unknown>): void {
    const idx = $tbody.find('tr.' + rowClass).length;
    const $tr = renderRow(rowData || {}, idx);
    $tbody.append($tr);
    recalc();
    $tr.find('input,select').first().focus();
  }

  function recalc(): void {
    calcFormulas();
    calcTotals();
    updateCount();
    if (onChange) onChange(getData());
  }

  // Event binding
  $tbody.on('keydown', 'input,select', function (this: HTMLElement, e: JQuery.KeyDownEvent) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      const $current = $(this);
      const $tr = $current.closest('tr.' + rowClass);
      const $inputs = $tr.find('input,select');
      const idx = $inputs.index($current);

      if (idx < $inputs.length - 1) {
        $inputs.eq(idx + 1).focus().trigger('select');
      } else {
        const $nextTr = $tr.next('tr.' + rowClass);
        if ($nextTr.length) {
          $nextTr.find('input,select').first().focus().trigger('select');
        } else if (allowAdd) {
          addRow();
        }
      }
    }

    if (e.key === 'Tab' && !e.shiftKey) {
      const $current = $(this);
      const $tr = $current.closest('tr.' + rowClass);
      const $inputs = $tr.find('input,select');
      const idx = $inputs.index($current);
      if (idx < $inputs.length - 1) {
        e.preventDefault();
        $inputs.eq(idx + 1).focus().trigger('select');
      } else {
        e.preventDefault();
        const $nextTr = $tr.next('tr.' + rowClass);
        if ($nextTr.length) {
          $nextTr.find('input,select').first().focus().trigger('select');
        } else if (allowAdd) {
          addRow();
        }
      }
    }
  });

  $tbody.on('blur', '.grid-currency', function (this: HTMLElement) {
    const raw = $(this).val()?.toString().replace(/\./g, '').replace(/,/g, '').trim() || '';
    const num = parseFloat(raw) || 0;
    $(this).val(num.toLocaleString('vi-VN'));
    recalc();
  });

  $tbody.on('blur', '.grid-number', () => { recalc(); });
  $tbody.on('change', 'select', () => { recalc(); });

  let recalcTimer: ReturnType<typeof setTimeout>;
  $tbody.on('input', '.grid-input', () => {
    clearTimeout(recalcTimer);
    recalcTimer = setTimeout(recalc, 200);
  });

  $addBtn.on('click', () => { addRow(); });

  // Initial data load
  if (data.length > 0) {
    data.forEach((row) => {
      const idx = $tbody.find('tr.' + rowClass).length;
      $tbody.append(renderRow(row, idx));
    });
    recalc();
  } else if (allowAdd) {
    addRow({});
  }

  return {
    getData,
    addRow,
    removeRow,
    recalc,
    clear: () => {
      $tbody.empty();
      if (allowAdd) addRow({});
      recalc();
    },
    setData: (rows) => {
      $tbody.empty();
      if (rows && rows.length) {
        rows.forEach((row) => {
          const idx = $tbody.find('tr.' + rowClass).length;
          $tbody.append(renderRow(row, idx));
        });
      } else if (allowAdd) {
        addRow({});
      }
      recalc();
    },
    $grid,
    $tbody,
  };
}

export const FormGrid = {
  create,
  removeRow: (btn: HTMLElement) => {
    const $gridEl = $(btn).closest('.form-grid');
    const $tr = $(btn).closest('tr.line-row');
    if ($gridEl.find('tbody tr.line-row').length <= 1) {
      FormToast.warning('Phải có ít nhất một dòng.');
      return;
    }
    $tr.remove();
    $gridEl.find('input,select').first().trigger('input');
  },
};
export type FormGridApi = typeof FormGrid;
