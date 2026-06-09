<?php ob_start(); ?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-pie-chart"></i> Ngân sách & Dự toán</h4>
    <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'ngan-sach')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
    <button class="btn btn-primary btn-sm" onclick="createScenario()"><i class="bi bi-plus"></i> Kịch bản</button>
  </div>

  <div class="row mb-3">
    <div class="col-md-3">
      <select id="yearSelect" class="form-select form-select-sm" onchange="loadDashboard()">
        <?php for($y=date('Y')-2;$y<=date('Y')+1;$y++): ?>
        <option value="<?= $y ?>" <?= $y==date('Y')?'selected':'' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
    </div>
  </div>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#scenarios">Kịch bản</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#variance">So sánh</a></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane active" id="scenarios">
      <div id="scenarioList"></div>
    </div>
    <div class="tab-pane" id="variance">
      <div id="varianceContent"><p class="text-muted">Chọn kịch bản để xem so sánh.</p></div>
    </div>
  </div>
</div>

<div class="modal fade" id="budgetModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Dự toán chi tiết</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="budgetBody"></div></div></div></div>

<script>
// Uses global fmt() from layout

function loadDashboard(){
  const year=$('#yearSelect').val();
  $.getJSON('/api/ngan-sach?year='+year,function(r){
    const list=(r.data||r||[]);
    let html='<div class="card"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Tên</th><th>Năm</th><th>Loại</th><th>Trạng thái</th><th></th></tr></thead><tbody>';
    list.forEach(function(s){
      html+='<tr><td>'+s.name+'</td><td>'+s.year+'</td><td>'+s.type+'</td><td>'+statusBadge(s.status)+'</td>'+
        '<td><div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">...</button><ul class="dropdown-menu">'+
        '<li><a class="dropdown-item" href="#" onclick="showBudget(\''+s.id+'\');return false"><i class="bi bi-eye"></i> Dự toán</a></li>'+
        '<li><a class="dropdown-item" href="#" onclick="showVariance(\''+s.id+'\');return false"><i class="bi bi-bar-chart"></i> So sánh</a></li>'+
        '<li><a class="dropdown-item" href="#" onclick="addBudgetLine(\''+s.id+'\');return false"><i class="bi bi-plus"></i> Thêm dòng</a></li>'+
        (s.status==='draft'?'<li><a class="dropdown-item" href="#" onclick="activate(\''+s.id+'\');return false"><i class="bi bi-check"></i> Kích hoạt</a></li>':'')+
        '<li><a class="dropdown-item" href="/api/ngan-sach/'+s.id+'/export" target="_blank"><i class="bi bi-download"></i> Xuất</a></li></ul></div></td></tr>';
    });
    html+='</tbody></table></div>';
    $('#scenarioList').html(html);
  });
}

function createScenario(){
  var today=new Date().getFullYear();
  FormModal.create({
    id:'scenarioModal',title:'Kịch bản mới',size:'sm',
    body:'<div class="mb-2"><label>Tên kịch bản</label><input class="form-control" id="scName" data-v-required="Tên kịch bản"></div>'+
      '<div class="mb-2"><label>Năm</label><input type="number" class="form-control" id="scYear" value="'+today+'"></div>'+
      '<div class="mb-2"><label>Loại</label><select class="form-select" id="scType"><option value="operating">Operating</option><option value="capital">Capital</option></select></div>',
    onSave:function(){
      var v=FormValidation.validate('#scenarioModal');if(!v.valid)return false;
      var name=$('#scName').val();if(!name)return false;
      var year=parseInt($('#scYear').val())||today;
      var type=$('#scType').val();
      return $.post('/api/ngan-sach',JSON.stringify({name:name,year:year,type:type}),
        function(){FormModal.close('scenarioModal');loadDashboard();FormToast.success('Đã tạo kịch bản');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
    }
  });
  setTimeout(function(){FormValidation.setup('#scenarioModal');},100);
}

function activate(id){
  FormConfirm.confirm('Kích hoạt','Kích hoạt kịch bản này?',function(ok){
    if(!ok)return;
    $.post('/api/ngan-sach/'+id+'/activate','{}',function(){loadDashboard();FormToast.success('Đã kích hoạt');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
  });
}

function showBudget(id){
  $.getJSON('/api/ngan-sach/'+id+'/lines',function(r){
    const lines=r.data||r||[];
    let html='<table class="table table-sm"><tr><th>Kỳ</th><th>TK</th><th class="text-end">Dự toán</th><th>Ghi chú</th></tr>';
    lines.forEach(function(l){html+='<tr><td>'+l.period_code+'</td><td>'+l.account_code+'</td><td class="text-end">'+fmt(l.budget_amount)+'</td><td>'+(l.notes||'')+'</td></tr>';});
    html+='</table><button class="btn btn-sm btn-outline-primary" onclick="addBudgetLine(\''+id+'\')"><i class="bi bi-plus"></i> Thêm</button>';
    $('#budgetBody').html(html);$('#budgetModal').modal('show');
  });
}

function showVariance(id){
  $.getJSON('/api/ngan-sach/'+id+'/variance',function(r){
    const d=r.data||r;
    let sum=d.summary||{};
    let html='<div class="row mb-2"><div class="col"><div class="card bg-primary text-white p-2 text-center"><small>Tổng DT</small><strong>'+fmt(sum.total_budget)+'</strong></div></div>'+
      '<div class="col"><div class="card bg-success text-white p-2 text-center"><small>DT Doanh thu</small><strong>'+fmt(sum.total_revenue_budget)+'</strong></div></div>'+
      '<div class="col"><div class="card bg-danger text-white p-2 text-center"><small>DT Chi phí</small><strong>'+fmt(sum.total_expense_budget)+'</strong></div></div></div>';
    html+='<table class="table table-sm"><tr><th>Kỳ</th><th>TK</th><th>Diễn giải</th><th class="text-end">DT</th><th class="text-end">Nợ</th><th class="text-end">Có</th><th class="text-end">CL</th></tr>';
    (d.lines||[]).forEach(function(l){
      const cl=parseFloat(l.variance);
      html+='<tr><td>'+l.period_code+'</td><td>'+l.account_code+'</td><td>'+l.account_name+'</td><td class="text-end">'+fmt(l.budget_amount)+'</td><td class="text-end">'+fmt(l.actual_debit)+'</td><td class="text-end">'+fmt(l.actual_credit)+'</td><td class="text-end '+(cl<0?'text-danger':'text-success')+'">'+fmt(cl)+'</td></tr>';
    });
    html+='</table>';
    $('#varianceContent').html(html);
    $('#variance').tab('show');
  });
}

function addBudgetLine(id){
  FormModal.create({
    id:'budgetLineModal',title:'Thêm dòng dự toán',size:'sm',
    body:'<div class="mb-2"><label>Kỳ (YYYY-MM)</label><input class="form-control" id="blPeriod" placeholder="VD: 2025-06" data-v-required="Kỳ"></div>'+
      '<div class="mb-2"><label>Tài khoản</label><input class="form-control" id="blAccount" data-v-required="TK" data-v-account="TK"></div>'+
      '<div class="mb-2"><label>Số tiền</label><input type="number" class="form-control" id="blAmount" step="100000" data-v-required="Số tiền" data-v-number="Số tiền"></div>'+
      '<div class="mb-2"><label>Ghi chú</label><input class="form-control" id="blNotes"></div>',
    onSave:function(){
      var v=FormValidation.validate('#budgetLineModal');if(!v.valid)return false;
      var period=$('#blPeriod').val();if(!period)return false;
      var acc=$('#blAccount').val();if(!acc)return false;
      var amt=parseFloat($('#blAmount').val())||0;
      var notes=$('#blNotes').val()||'';
      return $.post('/api/ngan-sach/'+id+'/lines',JSON.stringify({period_code:period,account_code:acc,amount:amt,notes:notes}),
        function(){FormModal.close('budgetLineModal');loadDashboard();FormToast.success('Đã thêm dòng dự toán');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
    }
  });
  setTimeout(function(){FormValidation.setup('#budgetLineModal');},100);
}

$(loadDashboard);
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
