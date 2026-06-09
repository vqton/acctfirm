<?php ob_start(); ?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-gear"></i> Sản xuất & Giá thành</h4>
    <button class="btn btn-primary btn-sm" onclick="showCreateOrder()"><i class="bi bi-plus"></i> Lệnh SX</button>
  </div>

  <ul class="nav nav-tabs mb-3" id="mfgTabs">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#orders">Lệnh SX</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#bom">Định mức (BOM)</a></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane active" id="orders">
      <div class="row mb-3" id="poStats">
        <div class="col"><div class="card bg-primary text-white p-2 text-center"><small>Tổng</small><strong id="sTotal">0</strong></div></div>
        <div class="col"><div class="card bg-secondary text-white p-2 text-center"><small>Nháp</small><strong id="sDraft">0</strong></div></div>
        <div class="col"><div class="card bg-warning text-white p-2 text-center"><small>Đã release</small><strong id="sReleased">0</strong></div></div>
        <div class="col"><div class="card bg-info text-white p-2 text-center"><small>Hoàn thành</small><strong id="sCompleted">0</strong></div></div>
        <div class="col"><div class="card bg-success text-white p-2 text-center"><small>Đã tính giá</small><strong id="sCosted">0</strong></div></div>
        <div class="col"><div class="card bg-danger text-white p-2 text-center"><small>Chi phí</small><strong id="sCost">0</strong></div></div>
      </div>
      <div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr>
        <th>Mã lệnh</th><th>Sản phẩm</th><th class="text-end">SL</th><th class="text-end">Hoàn thành</th><th class="text-end">CP NVL</th><th class="text-end">Tổng CP</th><th class="text-end">Đơn giá</th><th>Trạng thái</th><th></th>
      </tr></thead><tbody id="poTable"></tbody></table></div></div>
    </div>
    <div class="tab-pane" id="bom">
      <button class="btn btn-primary btn-sm mb-2" onclick="showCreateBom()"><i class="bi bi-plus"></i> BOM mới</button>
      <div class="card"><div class="table-responsive"><table class="table table-sm table-hover mb-0"><thead><tr>
        <th>Mã SP</th><th>Phiên bản</th><th>Ngày HL</th><th>Trạng thái</th><th>SL NVL</th><th></th>
      </tr></thead><tbody id="bomTable"></tbody></table></div></div>
    </div>
  </div>
</div>

<div class="modal fade" id="orderModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Lệnh SX</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="orderBody"></div></div></div></div>

<script>
// Uses global fmt() from layout

function loadDashboard(){
  $.getJSON('/api/san-xuat/dashboard',function(r){
    const d=r.data||r;
    if(d.stats){
      $('#sTotal').text(d.stats.total);$('#sDraft').text(d.stats.draft);
      $('#sReleased').text(d.stats.released);$('#sCompleted').text(d.stats.completed);
      $('#sCosted').text(d.stats.costed);$('#sCost').text(fmt(d.stats.total_cost));
    }
    const tbody=$('#poTable').empty();
    (d.orders||[]).forEach(function(o){
      tbody.append($('<tr>').append(
        $('<td>').html('<a href="#" onclick="showOrder(\''+o.id+'\');return false">'+o.reference+'</a>'),
        $('<td>').text(o.product_code+' - '+o.product_name),
        $('<td class="text-end vas-number">').text(o.qty),$('<td class="text-end vas-number">').text(o.completed_qty),
        $('<td class="text-end vas-number">').text(fmt(o.material_cost)),
        $('<td class="text-end vas-number">').text(fmt(o.total_cost)),
        $('<td class="text-end vas-number">').text(fmt(o.unit_cost)),
        $('<td>').html(statusBadge(o.status)),
        $('<td>').html(
          '<div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">...</button><ul class="dropdown-menu">'+
          '<li><a class="dropdown-item" href="#" onclick="showOrder(\''+o.id+'\');return false"><i class="bi bi-eye"></i> Chi tiết</a></li>'+
          '<li><a class="dropdown-item" href="#" onclick="releaseOrder(\''+o.id+'\');return false"><i class="bi bi-play"></i> Release</a></li>'+
          '<li><a class="dropdown-item" href="#" onclick="issueMaterial(\''+o.id+'\');return false"><i class="bi bi-box"></i> Xuất NVL</a></li>'+
          '<li><a class="dropdown-item" href="#" onclick="addLabor(\''+o.id+'\');return false"><i class="bi bi-person"></i> Nhân công</a></li>'+
          '<li><a class="dropdown-item" href="#" onclick="addOverhead(\''+o.id+'\');return false"><i class="bi bi-lightning"></i> CPSXC</a></li>'+
          '<li><a class="dropdown-item" href="#" onclick="completePo(\''+o.id+'\');return false"><i class="bi bi-check"></i> Hoàn thành</a></li>'+
          '<li><a class="dropdown-item" href="#" onclick="calcCost(\''+o.id+'\');return false"><i class="bi bi-calculator"></i> Tính giá</a></li>'+
          '<li><hr class="dropdown-divider"></li>'+
          '<li><a class="dropdown-item text-danger" href="#" onclick="closeOrder(\''+o.id+'\');return false"><i class="bi bi-x-circle"></i> Đóng</a></li></ul></div>'
        )
      ));
    });
  });
  $.getJSON('/api/san-xuat/bom',function(r){
    const tbody=$('#bomTable').empty();
    (r.data||r||[]).forEach(function(b){
      tbody.append($('<tr>').append(
        $('<td>').text(b.product_id),$('<td>').text(b.version),
        $('<td>').text(b.effective_date),
        $('<td>').html($('<span class="badge bg-'+(b.status==='active'?'success':'secondary')+'">').text(b.status)),
        $('<td>').text((b.lines||[]).length),
        $('<td>').html(b.status==='draft'?'<button class="btn btn-sm btn-outline-success" onclick="activateBom(\''+b.id+'\')">Kích hoạt</button>':'')
      ));
    });
  });
}

function showOrder(id){
  $.getJSON('/api/san-xuat/'+id+'/report',function(r){
    const d=r.data||r,o=d.order||{};
    let html='<div class="row"><div class="col-md-6"><dl class="row small">'+
      '<dt class="col-sm-4">Mã</dt><dd class="col-sm-8">'+o.reference+'</dd>'+
      '<dt class="col-sm-4">SP</dt><dd class="col-sm-8">'+o.product_id+'</dd>'+
      '<dt class="col-sm-4">SL kế hoạch</dt><dd class="col-sm-8">'+o.qty+'</dd>'+
      '<dt class="col-sm-4">Hoàn thành</dt><dd class="col-sm-8">'+o.completed_qty+'</dd>'+
      '<dt class="col-sm-4">CP NVL</dt><dd class="col-sm-8">'+fmt(o.material_cost)+'</dd>'+
      '<dt class="col-sm-4">CP NC</dt><dd class="col-sm-8">'+fmt(o.labor_cost)+'</dd>'+
      '<dt class="col-sm-4">CPSXC</dt><dd class="col-sm-8">'+fmt(o.overhead_cost)+'</dd>'+
      '<dt class="col-sm-4">Tổng CP</dt><dd class="col-sm-8">'+fmt(o.total_cost)+'</dd>'+
      '<dt class="col-sm-4">Đơn giá</dt><dd class="col-sm-8">'+fmt(o.unit_cost)+'</dd></dl></div></div>';
    if(d.materials&&d.materials.length>0){
      html+='<h6>NVL</h6><table class="table table-sm"><tr><th>VT</th><th class="text-end">KH</th><th class="text-end">TT</th><th class="text-end">ĐG</th><th class="text-end">TT</th></tr>';
      d.materials.forEach(function(m){html+='<tr><td>'+m.material_code+'</td><td class="text-end">'+m.planned_qty+'</td><td class="text-end">'+m.actual_qty+'</td><td class="text-end">'+fmt(m.unit_cost)+'</td><td class="text-end">'+fmt(m.total_cost)+'</td></tr>';});
      html+='</table>';
    }
    $('#orderBody').html(html);$('#orderModal').modal('show');
  });
}

function showCreateOrder(){
  var today=new Date().toISOString().slice(0,10);
  FormModal.create({
    id:'createOrderModal',title:'Lệnh SX mới',size:'md',
    body:'<div class="mb-2"><label>Mã sản phẩm (item ID)</label><input class="form-control" id="poProductId" data-v-required="Mã sản phẩm"></div>'+
      '<div class="mb-2"><label>Số lượng</label><input type="number" class="form-control" id="poQty" value="1" data-v-required="Số lượng" data-v-number="Số lượng"></div>'+
      '<div class="mb-2"><label>Ngày bắt đầu</label><input type="date" class="form-control" id="poStartDate" value="'+today+'"></div>'+
      '<div class="mb-2"><label>Ngày đến hạn</label><input type="date" class="form-control" id="poDueDate"></div>'+
      '<div class="mb-2"><label>BOM ID (nếu có)</label><input class="form-control" id="poBomId"></div>',
    onSave:function(modal){
      var v=FormValidation.validate('#createOrderModal');if(!v.valid)return false;
      var pid=$('#poProductId').val();if(!pid)return false;
      var qty=parseFloat($('#poQty').val())||1;
      var sd=$('#poStartDate').val()||today;
      var dd=$('#poDueDate').val()||null;
      var bom=$('#poBomId').val()||null;
      return $.post('/api/san-xuat',JSON.stringify({product_id:pid,qty:qty,start_date:sd,due_date:dd,bom_id:bom}),
        function(){FormModal.close('createOrderModal');loadDashboard();FormToast.success('Đã tạo lệnh SX');})
        .fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
    }
  });
  setTimeout(function(){FormValidation.setup('#createOrderModal');},100);
}

function releaseOrder(id){
  FormConfirm.confirm('Release lệnh SX','Xác nhận release lệnh SX?',function(ok){
    if(!ok)return;
    $.post('/api/san-xuat/'+id+'/release','{}',function(){loadDashboard();FormToast.success('Đã release');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
  });
}

function issueMaterial(id){
  FormModal.create({
    id:'issueMatModal',title:'Xuất NVL cho lệnh SX',size:'sm',
    body:'<div class="mb-2"><label>Mã vật tư (item ID)</label><input class="form-control" id="imMaterialId" data-v-required="Mã vật tư"></div>'+
      '<div class="mb-2"><label>Số lượng</label><input type="number" class="form-control" id="imQty" value="1" step="0.01" data-v-required="Số lượng" data-v-number="Số lượng"></div>'+
      '<div class="mb-2"><label>Đơn giá</label><input type="number" class="form-control" id="imUnitCost" value="0" step="1000" data-v-required="Đơn giá" data-v-number="Đơn giá"></div>',
    onSave:function(){
      var v=FormValidation.validate('#issueMatModal');if(!v.valid)return false;
      var mid=$('#imMaterialId').val();if(!mid)return false;
      var qty=parseFloat($('#imQty').val())||1;
      var uc=parseFloat($('#imUnitCost').val())||0;
      return $.post('/api/san-xuat/'+id+'/issue-material',JSON.stringify({material_id:mid,qty:qty,unit_cost:uc}),
        function(){FormModal.close('issueMatModal');loadDashboard();FormToast.success('Đã xuất NVL');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
    }
  });
  setTimeout(function(){FormValidation.setup('#issueMatModal');},100);
}

function addLabor(id){
  FormModal.create({
    id:'laborModal',title:'Nhân công lệnh SX',size:'sm',
    body:'<div class="mb-2"><label>Số giờ</label><input type="number" class="form-control" id="labHours" step="0.5" data-v-required="Số giờ" data-v-number="Số giờ"></div>'+
      '<div class="mb-2"><label>Đơn giá/giờ</label><input type="number" class="form-control" id="labRate" step="1000" data-v-required="Đơn giá" data-v-number="Đơn giá"></div>'+
      '<div class="mb-2"><label>Loại</label><select class="form-select" id="labType"><option value="direct">Direct</option><option value="indirect">Indirect</option></select></div>',
    onSave:function(){
      var v=FormValidation.validate('#laborModal');if(!v.valid)return false;
      var hrs=parseFloat($('#labHours').val())||0;
      var rate=parseFloat($('#labRate').val())||0;
      var type=$('#labType').val();
      return $.post('/api/san-xuat/'+id+'/labor',JSON.stringify({hours:hrs,rate:rate,labor_type:type}),
        function(){FormModal.close('laborModal');loadDashboard();FormToast.success('Đã ghi nhận NC');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
    }
  });
  setTimeout(function(){FormValidation.setup('#laborModal');},100);
}

function addOverhead(id){
  FormModal.create({
    id:'overheadModal',title:'CPSXC lệnh SX',size:'sm',
    body:'<div class="mb-2"><label>Loại</label><select class="form-select" id="ohType"><option value="electricity">Electricity</option><option value="water">Water</option><option value="depreciation">Depreciation</option><option value="other">Other</option></select></div>'+
      '<div class="mb-2"><label>Cơ sở phân bổ</label><input type="number" class="form-control" id="ohBase" step="1000" data-v-required="Cơ sở phân bổ" data-v-number="Cơ sở phân bổ"></div>'+
      '<div class="mb-2"><label>Tỷ lệ</label><input type="number" class="form-control" id="ohRate" step="0.01" data-v-required="Tỷ lệ" data-v-number="Tỷ lệ"></div>',
    onSave:function(){
      var v=FormValidation.validate('#overheadModal');if(!v.valid)return false;
      var type=$('#ohType').val();
      var base=parseFloat($('#ohBase').val())||0;
      var rate=parseFloat($('#ohRate').val())||0;
      return $.post('/api/san-xuat/'+id+'/overhead',JSON.stringify({type:type,base:base,rate:rate}),
        function(){FormModal.close('overheadModal');loadDashboard();FormToast.success('Đã ghi nhận CPSXC');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
    }
  });
  setTimeout(function(){FormValidation.setup('#overheadModal');},100);
}

function completePo(id){
  var today=new Date().toISOString().slice(0,10);
  FormModal.create({
    id:'completeModal',title:'Hoàn thành lệnh SX',size:'sm',
    body:'<div class="mb-2"><label>Số lượng hoàn thành</label><input type="number" class="form-control" id="cpQty" step="1" data-v-required="Số lượng" data-v-number="Số lượng"></div>'+
      '<div class="mb-2"><label>Ngày kết thúc</label><input type="date" class="form-control" id="cpEndDate" value="'+today+'"></div>',
    onSave:function(){
      var v=FormValidation.validate('#completeModal');if(!v.valid)return false;
      var qty=parseFloat($('#cpQty').val())||0;
      var ed=$('#cpEndDate').val()||today;
      return $.post('/api/san-xuat/'+id+'/complete',JSON.stringify({completed_qty:qty,end_date:ed}),
        function(){FormModal.close('completeModal');loadDashboard();FormToast.success('Đã hoàn thành');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
    }
  });
  setTimeout(function(){FormValidation.setup('#completeModal');},100);
}

function calcCost(id){
  FormConfirm.confirm('Tính giá thành','Tính giá thành cho lệnh SX?',function(ok){
    if(!ok)return;
    $.post('/api/san-xuat/'+id+'/calculate-cost','{}',function(r){const d=r.data||r;loadDashboard();FormConfirm.alert('Giá thành','ĐVG: '+fmt(d.unit_cost)+', Tổng: '+fmt(d.total_cost));}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
  });
}

function closeOrder(id){
  FormConfirm.confirm('Đóng lệnh SX','Xác nhận đóng lệnh SX?',function(ok){
    if(!ok)return;
    $.post('/api/san-xuat/'+id+'/close','{}',function(){loadDashboard();FormToast.success('Đã đóng lệnh SX');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
  });
}

function showCreateBom(){
  var today=new Date().toISOString().slice(0,10);
  FormModal.create({
    id:'bomModal',title:'BOM mới',size:'lg',
    body:'<div class="row g-2 mb-2"><div class="col-6"><label>Mã sản phẩm (item ID)</label><input class="form-control" id="bomProductId" data-v-required="Mã SP"></div>'+
      '<div class="col-6"><label>Ngày hiệu lực</label><input type="date" class="form-control" id="bomEffDate" value="'+today+'"></div></div>'+
      '<label class="fw-bold">Định mức NVL</label><div id="bomLinesContainer"></div>',
    onSave:function(){
      var pid=$('#bomProductId').val();if(!pid){FormToast.error('Nhập mã sản phẩm');return false;}
      var lines=bomGrid.getData().filter(function(l){return l.material_id&&l.material_id.trim();}).map(function(l,i){
        return {id:'l_'+Date.now()+'_'+i,material_id:l.material_id,qty_per_unit:parseFloat(l.qty)||1,wastage_pct:0,unit:'cai'};
      });
      if(lines.length===0){FormToast.error('Thêm ít nhất 1 dòng NVL');return false;}
      return $.post('/api/san-xuat/bom',JSON.stringify({product_id:pid,effective_date:$('#bomEffDate').val(),lines:lines}),
        function(){FormModal.close('bomModal');loadDashboard();FormToast.success('Đã tạo BOM');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
    }
  });
  var bomGrid=FormGrid.create('#bomLinesContainer',{
    columns:[
      {key:'material_id',label:'Mã NVL',type:'text',width:200},
      {key:'qty',label:'SL/1 SP',type:'number',width:100}
    ],
    addRowText:'Thêm NVL',
    data:[{material_id:'',qty:1}]
  });
  setTimeout(function(){FormValidation.setup('#bomModal');},100);
}

function activateBom(id){
  FormConfirm.confirm('Kích hoạt BOM','Kích hoạt BOM này?',function(ok){
    if(!ok)return;
    $.post('/api/san-xuat/bom/'+id+'/activate','{}',function(){loadDashboard();FormToast.success('Đã kích hoạt BOM');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
  });
}

$(loadDashboard);
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
