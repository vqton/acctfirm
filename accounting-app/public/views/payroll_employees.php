<?php $title = 'Nhân viên (Tiền lương)'; $activeMenu = 'payroll'; ob_start(); ?>
<div class="toolbar"><div><h5>Danh sách nhân viên <span class="stats" id="empCount"></span></h5></div>
  <div><button class="btn btn-sm btn-outline-primary" onclick="location.href='/danh-muc/nhan-vien'"><i class="bi bi-gear"></i> Quản lý nhân viên</button></div>
</div>
<div class="card-table">
  <div class="card-header-x"><i class="bi bi-search text-muted"></i><input type="text" id="searchInput" placeholder="Tìm kiếm..."></div>
  <div style="overflow-x:auto;">
  <table class="table">
    <thead><tr><th>Mã</th><th>Họ tên</th><th>Phòng ban</th><th>Chức vụ</th><th>HĐ</th><th>Vùng</th><th>Lương BH</th><th>TK NH</th><th>Mã số thuế</th><th>NPT</th><th>Trạng thái</th></tr></thead>
    <tbody id="empBody"></tbody>
  </table>
  </div>
</div>
<script>
function fmt(n){return new Intl.NumberFormat('vi-VN').format(n||0)}
var deptMap={};
$.get('/api/departments').done(function(deps){deps.forEach(function(d){deptMap[d.id]=d;});loadEmps();}).fail(function(){loadEmps();});
function loadEmps(){
  $.get('/api/employees').done(function(r){
    var q=($('#searchInput').val()||'').toLowerCase();
    var f=r.data?r.data.filter(function(i){return!q||i.name.toLowerCase().includes(q)||i.code.toLowerCase().includes(q);}):[];
    $('#empCount').text('('+f.length+' NV)');
    var h='';f.forEach(function(i){
      var ct={'indefinite':'KXĐ','definite':'XĐ','probation':'TV','parttime':'BT'}[i.contract_type]||i.contract_type||'';
      var st=i.status?'badge-active':'badge-inactive';var stt=i.status?'Hoạt động':'Ngừng';
      var dept=i.department_id&&deptMap[i.department_id]?esc(deptMap[i.department_id].name):'';
      h+='<tr><td>'+esc(i.code)+'</td><td>'+esc(i.name)+'</td><td>'+dept+'</td><td>'+esc(i.position||'')+'</td><td>'+ct+'</td><td>'+(i.region||'')+'</td>'
        +'<td class="text-end">'+(i.insurance_salary?fmt(i.insurance_salary):'-')+'</td><td>'+(i.bank_account?esc(i.bank_account):'-')+'</td>'
        +'<td>'+esc(i.tax_code||'-')+'</td><td class="text-center">'+(i.dependent_count||0)+'</td>'
        +'<td><span class="badge-status '+st+'">'+stt+'</span></td></tr>';
    });$('#empBody').html(h||'<tr><td colspan="11" class="empty-state"><i class="bi bi-inbox"></i>Không có nhân viên</td></tr>');
  }).fail(function(){});
}
$('#searchInput').on('keyup',loadEmps);
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
