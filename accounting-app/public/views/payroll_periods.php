<?php $title = 'Quản lý kỳ lương'; $activeMenu = 'payroll'; ob_start(); ?>
<div class="toolbar"><div><h5>Kỳ lương <span class="stats" id="periodCount">0</span></h5></div>
  <div><button class="btn btn-sm btn-primary" onclick="showCreatePeriod()"><i class="bi bi-plus"></i> Tạo kỳ lương</button></div>
</div>
<div class="card-table">
  <table class="table"><thead><tr><th>Mã kỳ</th><th>Tên kỳ</th><th>Ngày bắt đầu</th><th>Ngày kết thúc</th><th>Trạng thái</th><th>Người tạo</th><th style="width:120px"></th></tr></thead>
    <tbody id="periodBody"><tr><td colspan="7" class="text-muted text-center py-3">Đang tải...</td></tr></tbody>
  </table>
</div>
<div class="modal fade" id="periodModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
  <form id="periodForm"><div class="modal-header"><h5 class="modal-title">Tạo kỳ lương</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="mb-3"><label class="form-label">Kỳ (NămTháng, VD: 202605)</label><input class="form-control" name="period_code" value="<?= date('Ym') ?>" required></div>
  </div>
  <div class="modal-footer"><button type="submit" class="btn btn-primary">Tạo</button></div></form>
</div></div></div>
<script>
function fmt(n){return new Intl.NumberFormat('vi-VN').format(n||0)}
function periodBadge(s){return statusBadge(s);}
function loadPeriods(){$.get('/api/payroll/periods').done(function(r){var h='';if(r.data){r.data.forEach(function(p){h+='<tr><td>'+esc(p.period_code)+'</td><td>'+esc(p.name)+'</td><td>'+p.start_date+'</td><td>'+p.end_date+'</td><td>'+periodBadge(p.status)+'</td><td>'+esc(p.created_by||'')+'</td>';h+='<td>';if(p.status==='open')h+='<button class="btn-action btn-action-danger" onclick="closePeriod(\''+p.id+'\')">Đóng</button>';h+='</td></tr>';});}$('#periodBody').html(h||'<tr><td colspan="7" class="text-muted text-center">Không có kỳ lương</td></tr>');$('#periodCount').text(r.data?r.data.length:0);}).fail(function(){});}
function showCreatePeriod(){$('#periodModal').modal('show');}
$('#periodForm').submit(function(e){e.preventDefault();$.post('/api/payroll/periods',JSON.stringify({period_code:$('#periodForm [name=period_code]').val()})).done(function(){showToast('Đã tạo kỳ lương thành công.','success');$('#periodModal').modal('hide');loadPeriods();}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi tạo kỳ lương','error');});});
function closePeriod(id){if(!confirm('Bạn có chắc chắn muốn đóng kỳ lương này? Sau khi đóng sẽ không thể thay đổi dữ liệu kỳ lương.'))return;$.post('/api/payroll/periods/'+id+'/close').done(function(){showToast('Đã đóng kỳ lương thành công.','success');loadPeriods();}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}
loadPeriods();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
