<?php $title = 'Bảng lương'; $activeMenu = 'payroll_entries'; ob_start(); ?>
<div class="toolbar">
  <div><h5>Bảng lương</h5></div>
  <div>
    <button class="btn btn-sm btn-primary" onclick="showCreatePeriod()"><i class="bi bi-plus"></i> Tạo kỳ lương</button>
    <button class="btn btn-sm btn-success" onclick="showProcess()"><i class="bi bi-calculator"></i> Tính lương</button>
  </div>
</div>
<div class="card-table mb-3">
  <div class="card-header-x"><i class="bi bi-calendar"></i> Kỳ lương <span class="stats" id="periodCount">0</span>
    <button class="btn btn-sm btn-outline-primary ms-auto" onclick="loadPeriods()"><i class="bi bi-arrow-clockwise"></i></button>
  </div>
  <table class="table"><thead><tr><th>Mã kỳ</th><th>Tên</th><th>Ngày</th><th>Trạng thái</th><th></th></tr></thead>
    <tbody id="periodBody"><tr><td colspan="5" class="text-muted text-center py-3">Đang tải...</td></tr></tbody>
  </table>
</div>
<div class="card-table">
  <div class="card-header-x"><i class="bi bi-file-text"></i> Bảng lương <span class="stats" id="entryCount">0</span>
    <button class="btn btn-sm btn-outline-primary ms-auto" onclick="loadEntries()"><i class="bi bi-arrow-clockwise"></i></button>
  </div>
  <table class="table"><thead><tr><th>Kỳ</th><th>NV</th><th>Tổng lương</th><th>BH DN</th><th>Thuế</th><th>Thực nhận</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
    <tbody id="entryBody"><tr><td colspan="8" class="text-muted text-center py-3">Đang tải...</td></tr></tbody>
  </table>
</div>
<div class="modal fade" id="periodModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="periodForm"><div class="modal-header"><h5 class="modal-title">Tạo kỳ lương</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label">Kỳ (NămTháng, VD: 202605)</label><input class="form-control" name="period_code" value="<?= date('Ym') ?>" required></div>
  </div>
  <div class="modal-footer"><button type="submit" class="btn btn-primary">Tạo</button></div></form>
</div></div></div>
<div class="modal fade" id="processModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
  <form id="processForm"><div class="modal-header"><h5 class="modal-title">Tính lương</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label">Kỳ lương</label><select class="form-select" name="period_id" id="processPeriodSelect" required></select></div>
    <div class="alert alert-info">Hệ thống sẽ tính lương cho tất cả nhân viên đang hoạt động.</div>
  </div>
  <div class="modal-footer"><button type="submit" class="btn btn-success">Tính lương</button></div></form>
</div></div></div>
<script>
function fmt(n){return new Intl.NumberFormat('vi-VN').format(n||0)}
function badge(s){return statusBadge(s);}
function periodBadge(s){return statusBadge(s);}
function loadPeriods(){$.get('/api/payroll/periods').done(function(r){var h='';if(r.data){r.data.forEach(function(p){h+='<tr><td>'+p.period_code+'</td><td>'+esc(p.name)+'</td><td>'+p.start_date+' → '+p.end_date+'</td><td>'+periodBadge(p.status)+'</td>';h+='<td>'+(p.status==='open'?'<button class="btn-action btn-action-danger" onclick="closePeriod(\''+p.id+'\')">Đóng</button>':'')+'</td></tr>';});}$('#periodBody').html(h||'<tr><td colspan="5" class="text-muted text-center">Không có kỳ lương</td></tr>');$('#periodCount').text(r.data?r.data.length:0);});}
function loadEntries(){$.get('/api/payroll/entries').done(function(r){var h='';if(r.data){r.data.forEach(function(e){h+='<tr><td>'+esc(e.period_id.substring(0,8))+'</td><td>'+e.total_employees+'</td><td>'+fmt(e.total_gross)+'</td><td>'+fmt(e.total_insurance_er)+'</td><td>'+fmt(e.total_tax)+'</td><td>'+fmt(e.total_net)+'</td><td>'+badge(e.status)+'</td>';h+='<td>';if(e.status==='draft')h+='<button class="btn-action" onclick="approveEntry(\''+e.id+'\')">Duyệt</button> ';if(e.status==='draft'||e.status==='approved')h+='<button class="btn-action text-success" onclick="postEntry(\''+e.id+'\')">Ghi sổ</button> ';h+='<button class="btn-action" onclick="showDetails(\''+e.id+'\')">CT</button></td></tr>';});}$('#entryBody').html(h||'<tr><td colspan="8" class="text-muted text-center">Chưa có bảng lương</td></tr>');$('#entryCount').text(r.data?r.data.length:0);});}
function showCreatePeriod(){ $('#periodModal').modal('show'); }
$('#periodForm').submit(function(e){e.preventDefault();$.post('/api/payroll/periods',JSON.stringify({period_code:$('#periodForm [name=period_code]').val()})).done(function(){showToast('Đã tạo kỳ lương thành công.','success');$('#periodModal').modal('hide');loadPeriods();}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});});
function showProcess(){$.get('/api/payroll/periods/open').done(function(r){var s='';if(r.data){r.data.forEach(function(p){s+='<option value="'+p.id+'">'+p.period_code+' - '+esc(p.name)+'</option>';});}$('#processPeriodSelect').html(s||'<option>Không có kỳ mở</option>');$('#processModal').modal('show');});}
$('#processForm').submit(function(e){e.preventDefault();$.post('/api/payroll/process',JSON.stringify({period_id:$('#processPeriodSelect').val()})).done(function(r){showToast('Đã tính lương cho '+r.data?.total_employees+' nhân viên. Vui lòng kiểm tra kết quả trước khi duyệt.','success');$('#processModal').modal('hide');loadEntries();}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi tính lương','error');});});
function closePeriod(id){if(!confirm('Bạn có chắc chắn muốn đóng kỳ lương này? Sau khi đóng sẽ không thể thay đổi dữ liệu kỳ lương.'))return;$.post('/api/payroll/periods/'+id+'/close').done(function(){showToast('Đã đóng kỳ lương','success');loadPeriods();}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}
function approveEntry(id){if(!confirm('Bạn có chắc chắn duyệt bảng lương này?'))return;$.post('/api/payroll/entries/'+id+'/approve').done(function(){showToast('Đã duyệt bảng lương thành công.','success');loadEntries();}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}
function postEntry(id){if(!confirm('Bạn có chắc chắn ghi sổ bảng lương? Thao tác này sẽ tạo bút toán kế toán và không thể hoàn tác.'))return;$.post('/api/payroll/entries/'+id+'/post').done(function(){showToast('Đã ghi sổ bảng lương thành công.','success');loadEntries();}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}
function showDetails(id){$.get('/api/payroll/entries/'+id+'/details').done(function(r){var h='<table class="table table-sm"><thead><tr><th>NV</th><th>Tổng lương</th><th>BH</th><th>Thuế</th><th>Thực nhận</th><th>CP DN</th></tr></thead><tbody>';if(r.data?.details){r.data.details.forEach(function(d){h+='<tr><td>'+esc(d.employee_code)+' - '+esc(d.employee_name)+'</td><td>'+fmt(d.gross_salary)+'</td><td>'+fmt(d.insurance_ee)+'</td><td>'+fmt(d.tax_amount)+'</td><td>'+fmt(d.net_pay)+'</td><td>'+fmt(d.total_cost)+'</td></tr>';});}h+='</tbody></table>';showToast('','info');var m=$('<div class="modal fade" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5>Chi tiết bảng lương</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">'+h+'</div></div></div></div>');m.modal('show');m.on('hidden.bs.modal',function(){m.remove();});});}
loadPeriods();loadEntries();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
