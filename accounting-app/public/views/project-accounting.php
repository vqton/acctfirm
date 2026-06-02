<?php ob_start(); ?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-diagram-3"></i> Quản lý Dự án</h4>
    <div>
      <a href="/danh-muc/du-an" class="btn btn-outline-primary btn-sm"><i class="bi bi-gear"></i> Danh mục</a>
    </div>
  </div>

  <div class="row mb-3" id="projectStats">
    <div class="col-md-2"><div class="card bg-primary text-white p-2 text-center"><small>Tổng</small><strong id="statTotal">0</strong></div></div>
    <div class="col-md-2"><div class="card bg-success text-white p-2 text-center"><small>Đang thực hiện</small><strong id="statActive">0</strong></div></div>
    <div class="col-md-2"><div class="card bg-info text-white p-2 text-center"><small>Hoàn thành</small><strong id="statCompleted">0</strong></div></div>
    <div class="col-md-2"><div class="card bg-warning text-white p-2 text-center"><small>Ngân sách</small><strong id="statBudget">0</strong></div></div>
    <div class="col-md-2"><div class="card bg-danger text-white p-2 text-center"><small>Chi phí</small><strong id="statCost">0</strong></div></div>
    <div class="col-md-2"><div class="card bg-secondary text-white p-2 text-center"><small>Đã xuất HĐ</small><strong id="statBilled">0</strong></div></div>
  </div>

  <div class="card">
    <div class="table-responsive"><table class="table table-sm table-hover mb-0" id="projectTable"><thead class="table-light"><tr>
      <th>Mã</th><th>Tên</th><th>Khách hàng</th><th>Ngân sách</th><th>Chi phí</th><th>%</th><th>Trạng thái</th><th></th>
    </tr></thead><tbody></tbody></table></div>
  </div>
</div>

<div class="modal fade" id="reportModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">BC Dự án</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="reportBody"></div></div></div></div>

<script>
function fmt(n){return new Intl.NumberFormat('vi-VN').format(n||0)}
function loadProjects(){
  $.getJSON('/api/projects/dashboard',function(r){
    const d=r.data||r;
    if(d.stats){
      $('#statTotal').text(d.stats.total||0);$('#statActive').text(d.stats.active||0);
      $('#statCompleted').text(d.stats.completed||0);$('#statBudget').text(fmt(d.stats.total_budget));
      $('#statCost').text(fmt(d.stats.total_cost));$('#statBilled').text(fmt(d.stats.total_billed));
    }
    const tbody=$('#projectTable tbody').empty();
    (d.projects||[]).forEach(function(p){
      const pct=p.budget>0?(p.actual_cost/p.budget*100).toFixed(1):'0.0';
      tbody.append($('<tr>').append(
        $('<td>').html('<a href="#" onclick="showReport(\''+p.id+'\');return false">'+p.code+'</a>'),
        $('<td>').text(p.name),$('<td>').text(p.customer_name||''),
        $('<td class="text-end">').text(fmt(p.budget)),
        $('<td class="text-end">').text(fmt(p.actual_cost)),
        $('<td><div class="progress" style="height:6px"><div class="progress-bar" style="width:'+pct+'%"></div></div><small class="text-muted">'+pct+'%</small></td>'),
        $('<td>').append($('<span class="badge bg-'+(p.status==='active'?'success':p.status==='completed'?'info':'secondary')+'">').text(p.status)),
        $('<td>').append(
          '<div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">...</button><ul class="dropdown-menu">'+
          '<li><a class="dropdown-item" href="#" onclick="showReport(\''+p.id+'\');return false"><i class="bi bi-eye"></i> Chi tiết</a></li>'+
          '<li><a class="dropdown-item" href="#" onclick="allocateCost(\''+p.id+'\');return false"><i class="bi bi-link"></i> Phân bổ CP</a></li>'+
          '<li><a class="dropdown-item" href="#" onclick="createBilling(\''+p.id+'\');return false"><i class="bi bi-file-text"></i> Yêu cầu TT</a></li>'+
          '<li><a class="dropdown-item" href="#" onclick="recognizeRevenue(\''+p.id+'\');return false"><i class="bi bi-currency-dollar"></i> Ghi nhận DT</a></li>'+
          '<li><a class="dropdown-item" href="#" onclick="setBudget(\''+p.id+'\');return false"><i class="bi bi-pie-chart"></i> Ngân sách</a></li>'+
          '<li><hr class="dropdown-divider"></li>'+
          '<li><a class="dropdown-item text-danger" href="#" onclick="finalize(\''+p.id+'\');return false"><i class="bi bi-check-circle"></i> Kết thúc</a></li></ul></div>'
        )
      ));
    });
  });
}

function showReport(id){
  $.getJSON('/api/projects/'+id+'/report',function(r){
    const d=r.data||r,p=d.project||{};
    let html='<div class="row"><div class="col-md-6"><dl class="row small">'+
      '<dt class="col-sm-4">Mã</dt><dd class="col-sm-8">'+p.code+'</dd>'+
      '<dt class="col-sm-4">Tên</dt><dd class="col-sm-8">'+p.name+'</dd>'+
      '<dt class="col-sm-4">Ngân sách</dt><dd class="col-sm-8">'+fmt(p.budget)+'</dd>'+
      '<dt class="col-sm-4">CP thực tế</dt><dd class="col-sm-8">'+fmt(p.actual_cost)+'</dd>'+
      '<dt class="col-sm-4">Chênh lệch</dt><dd class="col-sm-8'+(d.variance<0?' text-danger':'')+'">'+fmt(d.variance)+'</dd>'+
      '<dt class="col-sm-4">% hoàn thành</dt><dd class="col-sm-8">'+d.completion_pct+'%</dd>'+
      '<dt class="col-sm-4">Đã xuất HĐ</dt><dd class="col-sm-8">'+fmt(p.billed_amount)+'</dd>'+
      '<dt class="col-sm-4">DT ghi nhận</dt><dd class="col-sm-8">'+fmt(p.revenue_recognized)+'</dd></dl></div></div>';

    if(d.cost_summary&&d.cost_summary.length>0){
      html+='<h6 class="mt-2">Chi phí theo TK</h6><table class="table table-sm"><tr><th>TK</th><th>Diễn giải</th><th class="text-end">Nợ</th><th class="text-end">Có</th></tr>';
      d.cost_summary.forEach(function(c){html+='<tr><td>'+c.code+'</td><td>'+c.name+'</td><td class="text-end">'+fmt(c.debit)+'</td><td class="text-end">'+fmt(c.credit)+'</td></tr>';});
      html+='</table>';
    }
    if(d.billings&&d.billings.length>0){
      html+='<h6 class="mt-2">Yêu cầu thanh toán</h6><table class="table table-sm"><tr><th>Ngày</th><th class="text-end">Số tiền</th><th>%</th><th>Trạng thái</th></tr>';
      d.billings.forEach(function(b){html+='<tr><td>'+b.billing_date+'</td><td class="text-end">'+fmt(b.amount)+'</td><td>'+b.pct_complete+'%</td><td>'+b.status+'</td></tr>';});
      html+='</table>';
    }
    $('#reportBody').html(html);$('#reportModal').modal('show');
  });
}

function allocateCost(id){
  const tid=prompt('ID chứng từ:');
  if(!tid)return;
  const acc=prompt('Tài khoản (VD: 621, 622):');
  if(!acc)return;
  const amt=parseFloat(prompt('Số tiền:','0')||'0');
  const dr=confirm('Là bên Nợ? OK=Nợ, Cancel=Có')?1:0;
  $.post('/api/projects/'+id+'/allocate-cost',JSON.stringify({transaction_id:tid,account_code:acc,amount:amt,is_debit:dr}),
    function(r){loadProjects();alert('Đã phân bổ');}).fail(function(x){alert(x.responseJSON?.error||'Lỗi');});
}

function createBilling(id){
  const date=prompt('Ngày (YYYY-MM-DD):');
  if(!date)return;
  const amt=parseFloat(prompt('Số tiền:','0')||'0');
  const pct=parseFloat(prompt('% hoàn thành:','0')||'0');
  const desc=prompt('Mô tả:','');
  $.post('/api/projects/'+id+'/billing',JSON.stringify({billing_date:date,amount:amt,pct_complete:pct,description:desc}),
    function(r){loadProjects();alert('Đã tạo yêu cầu TT');}).fail(function(x){alert(x.responseJSON?.error||'Lỗi');});
}

function recognizeRevenue(id){
  if(!confirm('Ghi nhận doanh thu theo POC?'))return;
  $.post('/api/projects/'+id+'/recognize-revenue','{}',function(r){const d=r.data||r;loadProjects();alert('DT: '+fmt(d.revenue));}).fail(function(x){alert(x.responseJSON?.error||'Lỗi');});
}

function setBudget(id){
  const acc=prompt('Tài khoản:');
  if(!acc)return;
  const amt=parseFloat(prompt('Số tiền ngân sách:','0')||'0');
  const notes=prompt('Ghi chú:','');
  $.post('/api/projects/'+id+'/budget',JSON.stringify({account_code:acc,amount:amt,notes:notes}),
    function(r){loadProjects();alert('Đã thiết lập ngân sách');}).fail(function(x){alert(x.responseJSON?.error||'Lỗi');});
}

function finalize(id){
  if(!confirm('Kết thúc dự án?'))return;
  $.post('/api/projects/'+id+'/finalize','{}',function(r){loadProjects();alert('Đã kết thúc');}).fail(function(x){alert(x.responseJSON?.error||'Lỗi');});
}

$(loadProjects);
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
