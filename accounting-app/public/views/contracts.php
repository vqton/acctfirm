<?php ob_start(); ?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-file-earmark-text"></i> Quản lý Hợp đồng</h4>
    <div>
      <a href="/contracts/form" class="btn btn-primary btn-sm"><i class="bi bi-plus"></i> Thêm hợp đồng</a>
      <button class="btn btn-outline-secondary btn-sm" onclick="location.href='/api/contracts/export?format=csv'"><i class="bi bi-download"></i> Xuất CSV</button>
    </div>
  </div>

  <div class="row mb-3" id="contractStats">
    <div class="col-md-2"><div class="card bg-primary text-white p-2 text-center"><small>Tổng</small><strong id="statTotal">0</strong></div></div>
    <div class="col-md-2"><div class="card bg-success text-white p-2 text-center"><small>Đang thực hiện</small><strong id="statActive">0</strong></div></div>
    <div class="col-md-2"><div class="card bg-warning text-white p-2 text-center"><small>Giá trị</small><strong id="statValue">0</strong></div></div>
    <div class="col-md-2"><div class="card bg-info text-white p-2 text-center"><small>Đã thực hiện</small><strong id="statFulfilled">0</strong></div></div>
    <div class="col-md-2"><div class="card bg-secondary text-white p-2 text-center"><small>Đã thanh toán</small><strong id="statPaid">0</strong></div></div>
    <div class="col-md-2"><div class="card bg-danger text-white p-2 text-center"><small>Sắp hết hạn</small><strong id="statExpiring">0</strong></div></div>
  </div>

  <div class="card">
    <div class="card-body p-2">
      <div class="row g-1">
        <div class="col-md-2"><select id="filterType" class="form-select form-select-sm"><option value="">Tất cả loại</option><option value="sales">Bán hàng</option><option value="purchase">Mua hàng</option><option value="service">Dịch vụ</option><option value="construction">Xây dựng</option></select></div>
        <div class="col-md-2"><select id="filterStatus" class="form-select form-select-sm"><option value="">Tất cả trạng thái</option><option value="draft">Nháp</option><option value="active">Đang thực hiện</option><option value="completed">Hoàn thành</option><option value="liquidated">Thanh lý</option><option value="cancelled">Hủy</option></select></div>
        <div class="col-md-2"><input type="text" id="filterSearch" class="form-control form-control-sm" placeholder="Tìm số/khách hàng"></div>
        <div class="col-md-2"><button class="btn btn-sm btn-outline-primary" onclick="loadContracts()"><i class="bi bi-search"></i> Lọc</button></div>
      </div>
    </div>
  </div>

  <div class="card mt-2">
    <div class="table-responsive"><table class="table table-sm table-hover mb-0" id="contractTable"><thead class="table-light"><tr>
      <th>Số HĐ</th><th>Loại</th><th>Đối tác</th><th>Ngày ký</th><th class="text-end">Giá trị</th><th class="text-end">Đã thực hiện</th><th class="text-end">%</th><th>Trạng thái</th><th>Ngày KT</th><th></th>
    </tr></thead><tbody></tbody></table></div>
  </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Chi tiết Hợp đồng</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="detailBody"></div></div></div></div>

<script>
// Uses global fmt() from layout
function pct(a,b){return b>0?(a/b*100).toFixed(1):'0.0'}

function loadContracts(){
  const type=$('#filterType').val(), status=$('#filterStatus').val(), search=$('#filterSearch').val();
  $.getJSON('/api/contracts',{type,status,search},function(r){
    const tbody=$('#contractTable tbody').empty();
    (r.data||r||[]).forEach(function(c){
      const row=$('<tr>').append(
        $('<td>').html('<a href="#" onclick="showDetail(\''+c.id+'\');return false">'+(c.code||'')+'</a>'),
        $('<td>').text(c.contract_type),
        $('<td>').text(c.partner_name||c.party_name),
        $('<td>').text(c.signed_date||c.contract_date),
        $('<td class="text-end">').text(fmt(c.total_amount)),
        $('<td class="text-end">').text(fmt(c.fulfilled_amount)),
        $('<td class="text-end">').text(pct(c.fulfilled_amount,c.total_amount)+'%'),
        $('<td>').html(statusBadge(c.status)),
        $('<td>').text(c.end_date||''),
        $('<td class="text-end">').append(
          c.status==='active'?$('<div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">...</button><ul class="dropdown-menu">'+
            '<li><a class="dropdown-item" href="#" onclick="showDetail(\''+c.id+'\');return false"><i class="bi bi-eye"></i> Chi tiết</a></li>'+
            '<li><a class="dropdown-item" href="#" onclick="linkTxn(\''+c.id+'\');return false"><i class="bi bi-link"></i> Liên kết chứng từ</a></li>'+
            '<li><a class="dropdown-item" href="#" onclick="addSchedule(\''+c.id+'\');return false"><i class="bi bi-calendar"></i> Lịch thanh toán</a></li>'+
            '<li><a class="dropdown-item" href="#" onclick="addAmendment(\''+c.id+'\');return false"><i class="bi bi-pencil"></i> Phụ lục</a></li>'+
            '<li><hr class="dropdown-divider"></li>'+
            '<li><a class="dropdown-item text-danger" href="#" onclick="liquidate(\''+c.id+'\');return false"><i class="bi bi-x-circle"></i> Thanh lý</a></li></ul></div>'):''
        )
      );
      tbody.append(row);
    });
  });
}

function showDetail(id){
  $.getJSON('/api/contracts/'+id+'/full',function(r){
    let d=r.data||r;
    let html='<div class="row"><div class="col-md-6"><dl class="row small">'+
      '<dt class="col-sm-4">Số HĐ</dt><dd class="col-sm-8">'+(d.code||'')+'</dd>'+
      '<dt class="col-sm-4">Loại</dt><dd class="col-sm-8">'+d.contract_type+'</dd>'+
      '<dt class="col-sm-4">Đối tác</dt><dd class="col-sm-8">'+(d.partner_name||d.party_name||'')+'</dd>'+
      '<dt class="col-sm-4">Ngày ký</dt><dd class="col-sm-8">'+d.signed_date+'</dd>'+
      '<dt class="col-sm-4">Giá trị</dt><dd class="col-sm-8">'+fmt(d.total_amount)+'</dd>'+
      '<dt class="col-sm-4">Đã TH</dt><dd class="col-sm-8">'+fmt(d.fulfilled_amount)+' ('+pct(d.fulfilled_amount,d.total_amount)+'%)</dd>'+
      '<dt class="col-sm-4">Đã TT</dt><dd class="col-sm-8">'+fmt(d.paid_amount)+' ('+pct(d.paid_amount,d.total_amount)+'%)</dd>'+
      '<dt class="col-sm-4">Từ</dt><dd class="col-sm-8">'+(d.start_date||d.contract_date||'')+'</dd>'+
      '<dt class="col-sm-4">Đến</dt><dd class="col-sm-8">'+d.end_date+'</dd></dl></div></div>';

    if(d.payment_schedules&&d.payment_schedules.length>0){
      html+='<h6 class="mt-2">Lịch thanh toán</h6><table class="table table-sm"><tr><th>Hạn</th><th class="text-end">Số tiền</th><th class="text-end">Đã TT</th><th>Cọc mốc</th><th>Trạng thái</th></tr>';
      d.payment_schedules.forEach(function(s){
        html+='<tr><td>'+s.due_date+'</td><td class="text-end vas-number">'+fmt(s.amount)+'</td><td class="text-end vas-number">'+fmt(s.paid_amount)+'</td><td>'+(s.milestone||'')+'</td><td>'+statusBadge(s.status)+'</td></tr>';
      });
      html+='</table>';
    }

    if(d.fulfillment_links&&d.fulfillment_links.length>0){
      html+='<h6 class="mt-2">Chứng từ liên quan</h6><table class="table table-sm"><tr><th>Loại</th><th>ID</th><th class="text-end">Số tiền</th><th>Mô tả</th></tr>';
      d.fulfillment_links.forEach(function(f){
        html+='<tr><td>'+f.linked_type+'</td><td>'+(f.linked_reference||f.linked_id)+'</td><td class="text-end">'+fmt(f.amount)+'</td><td>'+f.description+'</td></tr>';
      });
      html+='</table>';
    }

    if(d.amendments&&d.amendments.length>0){
      html+='<h6 class="mt-2">Phụ lục</h6><table class="table table-sm"><tr><th>Số PL</th><th>Ngày</th><th>Loại</th><th class="text-end">Thay đổi</th></tr>';
      d.amendments.forEach(function(a){
        html+='<tr><td>'+a.amendment_no+'</td><td>'+a.amendment_date+'</td><td>'+a.type+'</td><td class="text-end">'+(a.type==='increase'?'+':'')+fmt(a.amount_change)+'</td></tr>';
      });
      html+='</table>';
    }

    $('#detailBody').html(html);
    $('#detailModal').modal('show');
  });
}

function linkTxn(id){
  FormConfirm.prompt('Liên kết chứng từ','Loại chứng từ (invoice/receipt/payment/transaction):',function(type){
    if(!type)return;
    FormConfirm.prompt('Liên kết chứng từ','ID chứng từ:',function(linkedId){
      if(!linkedId)return;
      FormConfirm.prompt('Liên kết chứng từ','Số tiền:','0',function(amts){
        var amt=parseFloat(amts||'0');
        FormConfirm.prompt('Liên kết chứng từ','Mô tả:','',function(desc){
          $.post('/api/contracts/'+id+'/link',JSON.stringify({linked_type:type,linked_id:linkedId,amount:amt,description:desc}),function(){loadContracts();FormToast.success('Đã liên kết');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
        });
      });
    });
  });
}

function addSchedule(id){
  FormConfirm.prompt('Thêm lịch thanh toán','Ngày đến hạn (YYYY-MM-DD):',function(date){
    if(!date)return;
    FormConfirm.prompt('Thêm lịch thanh toán','Số tiền:','0',function(amts){
      var amt=parseFloat(amts||'0');
      FormConfirm.prompt('Thêm lịch thanh toán','Cọc mốc:','',function(milestone){
        $.post('/api/contracts/'+id+'/payment-schedule',JSON.stringify({due_date:date,amount:amt,milestone:milestone}),function(){loadContracts();FormToast.success('Đã thêm lịch thanh toán');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
      });
    });
  });
}

function addAmendment(id){
  FormConfirm.prompt('Thêm phụ lục','Số phụ lục:',function(no){
    if(!no)return;
    FormConfirm.prompt('Thêm phụ lục','Ngày:','',function(date){
      date=date||new Date().toISOString().slice(0,10);
      FormConfirm.prompt('Thêm phụ lục','Loại (increase/decrease):','increase',function(type){
        FormConfirm.prompt('Thêm phụ lục','Số tiền thay đổi:','0',function(amts){
          var amt=parseFloat(amts||'0');
          FormConfirm.prompt('Thêm phụ lục','Nội dung:','',function(desc){
            $.post('/api/contracts/'+id+'/amendment',JSON.stringify({amendment_no:no,date:date,type:type,amount_change:amt,description:desc}),function(){loadContracts();FormToast.success('Đã thêm phụ lục');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
          });
        });
      });
    });
  });
}

function liquidate(id){
  FormConfirm.confirm('Thanh lý hợp đồng','Xác nhận thanh lý hợp đồng này?',function(ok){
    if(!ok)return;
    $.post('/api/contracts/'+id+'/liquidate','{}',function(){loadContracts();FormToast.success('Đã thanh lý hợp đồng');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
  });
}

$(function(){
  loadContracts();
  $.getJSON('/api/contracts/dashboard',function(r){
    const d=r.data||r;
    if(d.stats){
      $('#statTotal').text(d.stats.total||0);
      $('#statActive').text(d.stats.active||0);
      $('#statValue').text(fmt(d.stats.total_value));
      $('#statFulfilled').text(fmt(d.stats.total_fulfilled));
      $('#statPaid').text(fmt(d.stats.total_paid));
      $('#statExpiring').text((d.expiring||[]).length);
    }
  });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
