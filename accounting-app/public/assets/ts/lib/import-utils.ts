// Import utilities — nhập dữ liệu từ Excel/CSV

interface ImportApiResponse {
  error?: string;
  total_rows?: number;
  valid_rows?: number;
  error_rows?: number;
  errors?: string[];
  headers?: string[];
  inserted_rows?: number;
  batch_id?: string;
}

const importLabels: Record<string, string> = {
  items: 'vật tư, hàng hóa',
  customers: 'khách hàng',
  suppliers: 'nhà cung cấp',
  coa: 'hệ thống tài khoản',
  opening_balance: 'số dư đầu kỳ',
  employees: 'nhân viên',
  fixed_assets: 'tài sản cố định'
};

const importConfig: { entity: string } = { entity: '' };

function s(s: unknown): string {
  const str = typeof s === 'string' ? s : String(s);
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

export function importFromExcel(entityType: string): void {
  importConfig.entity = entityType;
  const lbl = importLabels[entityType] || entityType;
  $('#importModalLabel').text('Nhập dữ liệu ' + lbl);
  $('#importFileInput').val('');
  $('#importResult').addClass('d-none');
  $('#importSubmitBtn').prop('disabled', true);
  fetch('/api/import/template/' + entityType).then(function(r) {
    if (r.ok) {
      $('#importTemplateBtn').removeClass('d-none').attr('href', '/api/import/template/' + entityType);
    } else {
      $('#importTemplateBtn').addClass('d-none');
    }
  }).catch(function() { $('#importTemplateBtn').addClass('d-none'); });
}

export function importValidate(): void {
  const file = (document.getElementById('importFileInput') as HTMLInputElement).files?.[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('file', file);
  $('#importResult').removeClass('d-none').html('<div class="text-center py-3"><i class="bi bi-arrow-repeat spinner"></i> Đang kiểm tra...</div>');
  $('#importSubmitBtn').prop('disabled', true);
  fetch('/api/import/dry-run/' + importConfig.entity, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d: ImportApiResponse) {
      if (d.error) {
        $('#importResult').html('<div class="alert alert-danger mb-0">' + s(d.error) + '</div>');
        return;
      }
      let h = '<div class="mb-2">Tổng: ' + d.total_rows + ' dòng, Hợp lệ: ' + d.valid_rows + ', Lỗi: ' + d.error_rows + '</div>';
      if (d.errors && d.errors.length) {
        h += '<div style="max-height:200px;overflow-y:auto;font-size:12px;">';
        d.errors.forEach(function(e) { h += '<div class="text-danger">• ' + s(e) + '</div>'; });
        h += '</div>';
      }
      if (d.headers) h += '<div class="text-muted mt-1" style="font-size:11px;">Cột: ' + s(d.headers.join(', ')) + '</div>';
      $('#importResult').html(h);
      if (d.error_rows === 0 && d.valid_rows && d.valid_rows > 0) $('#importSubmitBtn').prop('disabled', false);
    })
    .catch(function(e: Error) {
      $('#importResult').html('<div class="alert alert-danger mb-0">Lỗi: ' + s(e.message) + '</div>');
    });
}

export function importCommit(): void {
  const file = (document.getElementById('importFileInput') as HTMLInputElement).files?.[0];
  if (!file) return;
  if (!confirm('Xác nhận nhập ' + file.name + ' vào hệ thống?')) return;
  const fd = new FormData();
  fd.append('file', file);
  $('#importResult').html('<div class="text-center py-3"><i class="bi bi-arrow-repeat spinner"></i> Đang nhập dữ liệu...</div>');
  $('#importSubmitBtn').prop('disabled', true);
  fetch('/api/import/commit/' + importConfig.entity, { method: 'POST', body: fd })
    .then(function(r) { return r.json(); })
    .then(function(d: ImportApiResponse) {
      if (d.error) {
        $('#importResult').html('<div class="alert alert-danger mb-0">' + s(d.error) + '</div>');
        $('#importSubmitBtn').prop('disabled', false);
        return;
      }
      $('#importResult').html('<div class="alert alert-success mb-0">Đã nhập ' + d.inserted_rows + ' dòng thành công. Batch: ' + s(d.batch_id || '') + '</div>');
      $('#importSubmitBtn').prop('disabled', true);
      if (typeof (window as any).loadData === 'function') setTimeout((window as any).loadData, 500);
    })
    .catch(function(e: Error) {
      $('#importResult').html('<div class="alert alert-danger mb-0">Lỗi: ' + s(e.message) + '</div>');
      $('#importSubmitBtn').prop('disabled', false);
    });
}

export function downloadTemplate(entityType: string): void {
  window.open('/api/import/template/' + entityType, '_blank');
}
