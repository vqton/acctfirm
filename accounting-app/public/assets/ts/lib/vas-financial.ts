// VAS Financial UI Toolkit — Vietnamese Accounting Standards
// Intl.NumberFormat('vi-VN') wrapper for all financial display.
// Zero → hyphen, negatives → parentheses + Crimson, credits → Emerald.

import type { VndAmount, CurrencyCode } from '../types/index';

interface FmtOpts {
  currency?: CurrencyCode;
  decimals?: number;
  zeroDash?: boolean;
  colorize?: boolean;
}

interface DrCrResult {
  debit: string;
  credit: string;
}

interface FsLine {
  name?: string;
  ma_so?: string;
  notes?: string;
  this_period?: number;
  thisYear?: number;
  current?: number;
  last_period?: number;
  lastYear?: number;
  previous?: number;
  is_bold?: boolean;
  is_sub?: boolean;
}

interface FsLineFormatted {
  name: string;
  code: string;
  note: string;
  thisPeriod: string;
  lastPeriod: string;
}

// Formatting cache — Intl constructors are heavy
const _cache: Record<string, Intl.NumberFormat> = {};

function _formatter(decimals: number, currency?: string): Intl.NumberFormat {
  const key = (currency || 'VND') + '_' + decimals;
  if (_cache[key]) return _cache[key];
  const opts: Intl.NumberFormatOptions = {};
  if (currency) {
    opts.style = 'currency';
    opts.currency = currency;
    opts.minimumFractionDigits = decimals;
    opts.maximumFractionDigits = decimals;
  } else {
    opts.style = 'decimal';
    opts.minimumFractionDigits = decimals;
    opts.maximumFractionDigits = decimals;
  }
  _cache[key] = new Intl.NumberFormat('vi-VN', opts);
  return _cache[key];
}

function esc(s: unknown): string {
  const str = typeof s === 'string' ? s : String(s);
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

export const VAS = {
  fmt(this: void, n: unknown, opts?: FmtOpts): string {
    opts = opts || {};
    const num = Number(n);
    const zeroDash = opts.zeroDash !== false;

    if (!Number.isFinite(num) || (num === 0 && zeroDash)) {
      return '<span class="vas-zero">–</span>';
    }

    const isNegative = num < 0;
    const absVal = Math.abs(num);
    let decimals = opts.decimals;
    if (decimals === undefined) {
      decimals = opts.currency ? 2 : 0;
    }

    let formatted: string;
    if (opts.currency) {
      formatted = _formatter(decimals, opts.currency).format(absVal);
    } else {
      formatted = _formatter(decimals).format(absVal);
    }
    formatted = formatted.replace(/\s+/g, '\u00A0');

    if (isNegative) {
      formatted = '(' + formatted + ')';
      if (opts.colorize) {
        return '<span class="vas-debit">' + esc(formatted) + '</span>';
      }
      return formatted;
    }

    if (opts.colorize) {
      return '<span class="vas-credit">' + esc(formatted) + '</span>';
    }
    return formatted;
  },

  fmtDrCr(this: void, debit: number, credit: number, opts?: FmtOpts): DrCrResult {
    opts = opts || {};
    const dOpts: FmtOpts = { ...opts, colorize: true };
    return {
      debit: VAS.fmt(debit, { ...dOpts, zeroDash: true }),
      credit: VAS.fmt(credit, { ...dOpts, zeroDash: true }),
    };
  },

  fmtLine(this: void, line: FsLine, opts?: FmtOpts): FsLineFormatted {
    opts = opts || {};
    return {
      name: esc(line.name || ''),
      code: line.ma_so || '',
      note: line.notes || '',
      thisPeriod: VAS.fmt(line.this_period ?? line.thisYear ?? line.current, opts),
      lastPeriod: VAS.fmt(line.last_period ?? line.lastYear ?? line.previous, opts),
    };
  },

  renderRow(this: void, line: FsLine, opts?: FmtOpts): string {
    const f = VAS.fmtLine(line, opts);
    const cls = line.is_bold ? 'class="fw-bold"' : '';
    const indent = line.is_sub ? 'style="padding-left:24px"' : '';
    return '<tr ' + cls + '>' +
      '<td ' + indent + '>' + f.name + '</td>' +
      '<td class="text-center vas-maso">' + f.code + '</td>' +
      '<td class="text-center">' + f.note + '</td>' +
      '<td class="text-end vas-number">' + f.thisPeriod + '</td>' +
      '<td class="text-end vas-number">' + f.lastPeriod + '</td>' +
      '</tr>';
  },
};

export type VasApi = typeof VAS;
