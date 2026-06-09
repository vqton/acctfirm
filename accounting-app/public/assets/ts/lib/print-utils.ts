// Print utilities — xuất CSV, in chứng từ từ API hoặc dữ liệu có sẵn
// Định dạng A4 theo TT 99/2025/TT-BTC

function s(s: unknown): string {
  const str = typeof s === 'string' ? s : String(s);
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

export function exportCSV(tableSelector: string | HTMLTableElement, filename: string): void {
  const tbl = typeof tableSelector === 'string' ? document.querySelector(tableSelector) : tableSelector;
  if (!tbl) { window.showToast('Không tìm thấy dữ liệu để xuất', 'error'); return; }
  const rows = tbl.querySelectorAll('tr');
  if (!rows.length) { window.showToast('Bảng không có dữ liệu', 'error'); return; }
  let csv = '\uFEFF';
  rows.forEach(function(tr) {
    const cells = tr.querySelectorAll('th,td');
    const vals: string[] = [];
    cells.forEach(function(td) {
      let txt = td.textContent!.trim().replace(/"/g, '""');
      txt = txt.replace(/\s+/g, ' ');
      vals.push('"' + txt + '"');
    });
    csv += vals.join(',') + '\n';
  });
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.download = filename + '.csv';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  URL.revokeObjectURL(link.href);
  window.showToast('Đã xuất file: ' + filename + '.csv', 'success');
}

export function printForm(title: string, bodyHtml: string): Window | null {
  const w = window.open('', '_blank', 'width=900,height=700');
  if (!w) { window.showToast('Trình duyệt đã chặn cửa sổ pop-up. Vui lòng cho phép pop-up.', 'error'); return null; }
  w.document.write('<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8">');
  w.document.write('<title>' + s(title) + '</title>');
  w.document.write('<style>');
  w.document.write('@page { size:A4; margin:15mm 20mm; }');
  w.document.write('body { font-family:"Times New Roman",serif; font-size:12pt; line-height:1.6; color:#000; padding:20px; }');
  w.document.write('.print-header { text-align:center; margin-bottom:20px; }');
  w.document.write('.print-header h3 { margin:0 0 4px; font-size:14pt; font-weight:700; }');
  w.document.write('.print-header .sub { font-size:10pt; color:#555; }');
  w.document.write('table { width:100%; border-collapse:collapse; font-size:11pt; }');
  w.document.write('table th, table td { border:1px solid #333; padding:4px 8px; text-align:left; vertical-align:top; }');
  w.document.write('table th { background:#f0f0f0; font-weight:600; }');
  w.document.write('.text-end { text-align:right; }');
  w.document.write('.text-center { text-align:center; }');
  w.document.write('.signature { display:flex; justify-content:space-between; margin-top:40px; }');
  w.document.write('.signature div { text-align:center; width:30%; }');
  w.document.write('.signature div p { margin:0; }');
  w.document.write('.signature .name { margin-top:40px; }');
  w.document.write('@media print { .no-print { display:none !important; } }');
  w.document.write('.no-print { text-align:center; margin-bottom:12px; }');
  w.document.write('<button onclick="window.print()" style="padding:6px 24px;font-size:14px;cursor:pointer;">In</button>');
  w.document.write(' <button onclick="window.close()" style="padding:6px 24px;font-size:14px;cursor:pointer;">Đóng</button></div>');
  w.document.write(bodyHtml);
  w.document.write('</body></html>');
  w.document.close();
  return w;
}

export function printTransaction(
  title: string,
  apiUrl: string,
  fieldMap: Record<string, string>,
  linesField?: string,
  lineFields?: Record<string, string>,
  partnerField?: string
): void {
  window.showToast('Đang tải dữ liệu in...', 'info');
  fetch(apiUrl).then(function(r) {
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return r.json();
  }).then(function(d) {
    const data = d.data || d;
    let h = '<div class="print-header"><h3>' + s(title) + '</h3>';
    h += '<div class="sub">Mẫu số: ' + s(title) + '</div>';
    h += '<div class="sub">Ban hành theo TT 99/2025/TT-BTC</div></div>';

    h += '<table><tbody>';
    for (const key in fieldMap) {
      let val: unknown = data[key];
      if (val === null || val === undefined) val = '';
      if (typeof val === 'number') val = (val as number).toLocaleString();
      h += '<tr><td style="width:140px;font-weight:600;">' + s(fieldMap[key]) + '</td><td>' + s(String(val)) + '</td></tr>';
    }
    h += '</tbody></table>';

    if (partnerField && data[partnerField]) {
      h += '<h4 style="margin-top:16px;font-size:11pt;">Thông tin đối tác</h4><table><tbody>';
      const partner = data[partnerField];
      for (const pk in partner) {
        if (typeof partner[pk] === 'object') continue;
        h += '<tr><td style="width:140px;font-weight:600;">' + s(pk) + '</td><td>' + s(String(partner[pk])) + '</td></tr>';
      }
      h += '</tbody></table>';
    }

    if (linesField && data[linesField] && data[linesField].length) {
      h += '<h4 style="margin-top:16px;font-size:11pt;">Chi tiết</h4><table><thead><tr>';
      const colKeys: string[] = [];
      for (const lk in lineFields) {
        colKeys.push(lk);
        h += '<th>' + s(lineFields![lk]) + '</th>';
      }
      h += '</tr></thead><tbody>';
      data[linesField].forEach(function(line: Record<string, unknown>) {
        h += '<tr>';
        colKeys.forEach(function(k) {
          let v = line[k];
          if (v === null || v === undefined) v = '';
          if (typeof v === 'number') v = (v as number).toLocaleString();
          h += '<td>' + s(String(v)) + '</td>';
        });
        h += '</tr>';
      });
      h += '</tbody></table>';
    }

    if (data.description) {
      h += '<div style="margin-top:12px;"><strong>Diễn giải:</strong> ' + s(data.description) + '</div>';
    }
    if (data.amount) {
      h += '<div style="margin-top:8px;text-align:right;font-size:13pt;font-weight:700;">Số tiền: ' + s(Number(data.amount).toLocaleString()) + ' VNĐ</div>';
    }

    h += '<div class="signature"><div><p><strong>Người lập</strong></p><p class="name">(Ký, họ tên)</p></div>';
    h += '<div><p><strong>Kế toán trưởng</strong></p><p class="name">(Ký, họ tên)</p></div>';
    h += '<div><p><strong>Thủ quỹ/Giám đốc</strong></p><p class="name">(Ký, họ tên)</p></div></div>';

    printForm(title, h);
  }).catch(function(e: Error) {
    window.showToast('Lỗi tải dữ liệu in: ' + e.message, 'error');
  });
}

export function printFromData(
  title: string,
  data: Record<string, unknown>,
  fieldMap: Record<string, string>,
  linesField?: string,
  lineFields?: Record<string, string>
): void {
  let h = '<div class="print-header"><h3>' + s(title) + '</h3>';
  h += '<div class="sub">Ban hành theo TT 99/2025/TT-BTC</div></div>';
  h += '<table><tbody>';
  for (const key in fieldMap) {
    let val = data[key];
    if (val === null || val === undefined) val = '';
    if (typeof val === 'number') val = (val as number).toLocaleString();
    h += '<tr><td style="width:140px;font-weight:600;">' + s(fieldMap[key]) + '</td><td>' + s(String(val)) + '</td></tr>';
  }
  h += '</tbody></table>';

  if (linesField && data[linesField] && Array.isArray(data[linesField])) {
    h += '<h4 style="margin-top:16px;font-size:11pt;">Chi tiết</h4><table><thead><tr>';
    const colKeys: string[] = [];
    for (const lk in lineFields) {
      colKeys.push(lk);
      h += '<th>' + s(lineFields![lk]) + '</th>';
    }
    h += '</tr></thead><tbody>';
    (data[linesField] as Array<Record<string, unknown>>).forEach(function(line) {
      h += '<tr>';
      colKeys.forEach(function(k) {
        let v = line[k];
        if (v === null || v === undefined) v = '';
        if (typeof v === 'number') v = (v as number).toLocaleString();
        h += '<td>' + s(String(v)) + '</td>';
      });
      h += '</tr>';
    });
    h += '</tbody></table>';
  }

  if (data.description) h += '<div style="margin-top:12px;"><strong>Diễn giải:</strong> ' + s(data.description) + '</div>';
  if (data.amount) h += '<div style="margin-top:8px;text-align:right;font-size:13pt;font-weight:700;">Số tiền: ' + s(Number(data.amount).toLocaleString()) + ' VNĐ</div>';

  h += '<div class="signature"><div><p><strong>Người lập</strong></p><p class="name">(Ký, họ tên)</p></div>';
  h += '<div><p><strong>Kế toán trưởng</strong></p><p class="name">(Ký, họ tên)</p></div>';
  h += '<div><p><strong>Thủ quỹ/Giám đốc</strong></p><p class="name">(Ký, họ tên)</p></div></div>';

  printForm(title, h);
}
