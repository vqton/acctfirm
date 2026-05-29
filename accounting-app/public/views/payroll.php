<?php $title = 'Tiền lương'; $activeMenu = 'payroll'; ob_start(); ?>
<div class="toolbar"><div><h5>Tổng quan tiền lương</h5></div></div>
<div class="row g-3 mb-3">
  <div class="col-md-3"><div class="card-table p-3 text-center"><div class="text-muted small">Tổng nhân viên</div><div class="fs-4 fw-bold" id="kpiEmployees">0</div></div></div>
  <div class="col-md-3"><div class="card-table p-3 text-center"><div class="text-muted small">Kỳ lương đang mở</div><div class="fs-4 fw-bold" id="kpiOpenPeriods">0</div></div></div>
  <div class="col-md-3"><div class="card-table p-3 text-center"><div class="text-muted small">Bảng lương nháp</div><div class="fs-4 fw-bold" id="kpiDraft">0</div></div></div>
  <div class="col-md-3"><div class="card-table p-3 text-center"><div class="text-muted small">Đã ghi sổ tháng này</div><div class="fs-4 fw-bold" id="kpiPosted">0</div></div></div>
</div>
<div class="row g-3 mb-3">
  <div class="col-md-6"><div class="card-table p-3"><h6>Kỳ lương gần đây</h6><div id="recentPeriods" class="text-muted small">Đang tải...</div></div></div>
  <div class="col-md-6"><div class="card-table p-3"><h6>Bảng lương gần đây</h6><div id="recentEntries" class="text-muted small">Đang tải...</div></div></div>
</div>
<script>
function loadDashboard(){
  $.get('/api/employees').done(function(r){$('#kpiEmployees').text(r.data?r.data.length:0);}).fail(function(){});
  $.get('/api/payroll/periods').done(function(r){
    var open=0,closed=0;if(r.data){r.data.forEach(function(p){if(p.status==='open')open++;if(p.status==='closed')closed++;});}
    $('#kpiOpenPeriods').text(open);
    var h='';if(r.data){r.data.slice(0,5).forEach(function(p){h+='<div class="d-flex justify-content-between py-1 border-bottom"><span>'+esc(p.period_code)+' - '+esc(p.name)+'</span><span class="badge-status '+(p.status==='open'?'badge-active':p.status==='closed'?'badge-danger':'badge-warning')+'">'+(p.status==='open'?'Đang mở':p.status==='closed'?'Đã đóng':'Đang XL')+'</span></div>';});}$('#recentPeriods').html(h||'<div class="text-muted">Chưa có kỳ lương</div>');
  }).fail(function(){});
  $.get('/api/payroll/entries').done(function(r){
    var draft=0,posted=0,totalGross=0,totalNet=0,recent=r.data?r.data.slice(0,5):[];
    if(r.data){r.data.forEach(function(e){if(e.status==='draft')draft++;if(e.status==='posted')posted++;totalGross+=e.total_gross||0;totalNet+=e.total_net||0;});}
    $('#kpiDraft').text(draft);$('#kpiPosted').text(posted);
    var h='';recent.forEach(function(e){h+='<div class="d-flex justify-content-between py-1 border-bottom"><span>'+esc(e.period_id.substring(0,8))+' - '+e.total_employees+' NV</span><span>'+fmt(totalGross)+'</span></div>';});$('#recentEntries').html(h||'<div class="text-muted">Chưa có bảng lương</div>');
  }).fail(function(){});
}
loadDashboard();
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
