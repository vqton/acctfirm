/**
 * VAS Financial UI Toolkit — Vietnamese Accounting Standards
 *
 * Intl.NumberFormat('vi-VN') wrapper for all financial display.
 * Zero → hyphen, negatives → parentheses + Crimson, credits → Emerald.
 *
 * Usage:
 *   VAS.fmt(1500000)        // "1.500.000"
 *   VAS.fmt(0)              // "–" (light gray hyphen)
 *   VAS.fmt(-500000)        // "(500.000)" with Crimson text
 *   VAS.fmt(1250.75, {currency:'USD', decimals:2})  // "1.250,75 USD"
 *   VAS.fmtDrCr(100000, 0)  // Dr in Crimson, Cr in Emerald
 */
(function () {
  'use strict';

  // Formatting cache — Intl constructors are heavy
  var _cache = {};

  function _formatter(decimals, currency) {
    var key = (currency || 'VND') + '_' + (decimals ?? -1);
    if (_cache[key]) return _cache[key];
    var opts = {};
    if (currency) {
      opts.style = 'currency';
      opts.currency = currency;
      if (decimals !== undefined && decimals !== null) {
        opts.minimumFractionDigits = decimals;
        opts.maximumFractionDigits = decimals;
      }
    } else {
      opts.style = 'decimal';
      opts.minimumFractionDigits = decimals ?? 0;
      opts.maximumFractionDigits = decimals ?? 2;
    }
    _cache[key] = new Intl.NumberFormat('vi-VN', opts);
    return _cache[key];
  }

  var VAS = {

    /**
     * Format a number for VAS financial display.
     *
     * @param {number|null|undefined} n  Value to format
     * @param {Object} [opts]
     * @param {string} [opts.currency]   Currency code (default: none — bare number)
     * @param {number} [opts.decimals]   Fraction digits (default: 0 for VND)
     * @param {boolean} [opts.zeroDash]  Show hyphen for zero (default: true)
     * @param {boolean} [opts.colorize]  Wrap in coloured <span> (default: false)
     * @return {string} Formatted string (HTML-safe if colorize)
     */
    fmt: function (n, opts) {
      opts = opts || {};
      var num = parseFloat(n);
      var zeroDash = opts.zeroDash !== false;

      // Zero → light gray hyphen
      if (isNaN(num) || (num === 0 && zeroDash)) {
        return '<span class="vas-zero">–</span>';
      }

      // Negative → Crimson with parentheses (no minus sign)
      var isNegative = num < 0;
      var absVal = Math.abs(num);
      var decimals = opts.decimals;
      if (decimals === undefined || decimals === null) {
        decimals = opts.currency ? 2 : 0;
      }

      var formatted;
      if (opts.currency) {
        formatted = _formatter(decimals, opts.currency).format(absVal);
      } else {
        formatted = _formatter(decimals).format(absVal);
      }
      // Remove the trailing space before currency symbol Intl adds
      formatted = formatted.replace(/\s+/g, '\u00A0');

      if (isNegative) {
        formatted = '(' + formatted + ')';
        if (opts.colorize) {
          return '<span class="vas-debit">' + escaped(formatted) + '</span>';
        }
        return formatted;
      }

      // Positive → Emerald only if explicitly opted in
      if (opts.colorize) {
        return '<span class="vas-credit">' + escaped(formatted) + '</span>';
      }
      return formatted;
    },

    /**
     * Format a periodic Dr/Cr pair.
     * Dr rendered in Crimson (debit), Cr in Emerald (credit).
     *
     * @param {number} debit
     * @param {number} credit
     * @param {Object} [opts]
     * @return {{debit: string, credit: string}}
     */
    fmtDrCr: function (debit, credit, opts) {
      opts = opts || {};
      var dOpts = Object.assign({}, opts, { colorize: true });
      return {
        debit: VAS.fmt(debit, Object.assign({}, dOpts, { zeroDash: true })),
        credit: VAS.fmt(credit, Object.assign({}, dOpts, { zeroDash: true })),
      };
    },

    /**
     * Format a BC02/BC03 line (name, code, note, thisPeriod, lastPeriod).
     * @param {Object} line
     * @param {number} [opts.decimals]
     * @return {{name, code, note, thisPeriod, lastPeriod}}
     */
    fmtLine: function (line, opts) {
      opts = opts || {};
      return {
        name: escaped(line.name || ''),
        code: line.ma_so || '',
        note: line.notes || '',
        thisPeriod: VAS.fmt(line.this_period ?? line.thisYear ?? line.current, opts),
        lastPeriod: VAS.fmt(line.last_period ?? line.lastYear ?? line.previous, opts),
      };
    },

    /**
     * Render a complete BC02-style table row.
     * @return {string} <tr> HTML
     */
    renderRow: function (line, opts) {
      var f = VAS.fmtLine(line, opts);
      var cls = line.is_bold ? 'class="fw-bold"' : '';
      var indent = line.is_sub ? 'style="padding-left:24px"' : '';
      return '<tr ' + cls + '>' +
        '<td ' + indent + '>' + f.name + '</td>' +
        '<td class="text-center vas-maso">' + f.code + '</td>' +
        '<td class="text-center">' + f.note + '</td>' +
        '<td class="text-end vas-number">' + f.thisPeriod + '</td>' +
        '<td class="text-end vas-number">' + f.lastPeriod + '</td>' +
        '</tr>';
    },
  };

  // Minimal escape for HTML safety
  function escaped(s) {
    if (typeof s !== 'string') s = String(s);
    return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }

  // Export
  window.VAS = VAS;
})();
