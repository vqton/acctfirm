<?php ob_start(); ?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-file-earmark-bar-graph"></i> Tự thiết kế báo cáo</h4>
    <button class="btn btn-primary btn-sm" onclick="showNewReport()"><i class="bi bi-plus"></i> Báo cáo mới</button>
  </div>

  <ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#saved">Báo cáo đã lưu</a></li>
    <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#adhoc">Ad-hoc</a></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane active" id="saved">
      <div id="reportList"></div>
    </div>
    <div class="tab-pane" id="adhoc">
      <div class="card p-3">
        <div class="row g-2">
          <div class="col-md-3"><select id="adhocTable" class="form-select form-select-sm" onchange="updateAdhocFields()"></select></div>
          <div class="col-md-6"><select id="adhocFields" class="form-select form-select-sm" multiple size="4"></select></div>
          <div class="col-md-3"><button class="btn btn-sm btn-primary" onclick="runAdhoc()"><i class="bi bi-play"></i> Chạy</button></div>
        </div>
        <div id="adhocResult" class="mt-2"></div>
      </div>
    </div>
  </div>
</div>

<script>
// Uses global fmt() from layout
let tables={};

function loadSaved(){
  $.getJSON('/api/report-builder',function(r){
    const reports=r.data||r||[];
    let html='<div class="card"><table class="table table-sm table-hover mb-0"><thead class="table-light"><tr><th>Tên</th><th>Bảng</th><th>Loại</th><th></th></tr></thead><tbody>';
    reports.forEach(function(rpt){
      html+='<tr><td>'+rpt.name+'</td><td>'+rpt.source_table+'</td><td>'+rpt.type+'</td>'+
        '<td><div class="dropdown"><button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">...</button><ul class="dropdown-menu">'+
        '<li><a class="dropdown-item" href="#" onclick="runReport(\''+rpt.id+'\');return false"><i class="bi bi-play"></i> Chạy</a></li>'+
        '<li><a class="dropdown-item" href="/api/report-builder/'+rpt.id+'/export?format=csv" target="_blank"><i class="bi bi-download"></i> Xuất CSV</a></li>'+
        '<li><hr class="dropdown-divider"></li>'+
        '<li><a class="dropdown-item text-danger" href="#" onclick="deleteReport(\''+rpt.id+'\');return false"><i class="bi bi-trash"></i> Xóa</a></li></ul></div></td></tr>';
    });
    html+='</tbody></table></div>';
    $('#reportList').html(html);
  });
}

function loadTables(){
  $.getJSON('/api/report-builder/tables',function(r){
    tables={};
    (r.data||r||[]).forEach(function(t){tables[t.table]=t.fields;});
    const sel=$('#adhocTable').empty().append('<option value="">Chọn bảng</option>');
    Object.keys(tables).forEach(function(k){sel.append('<option value="'+k+'">'+k+'</option>');});
    const sel2=$('#adhocTable2').empty().append('<option value="">Chọn bảng</option>');
    Object.keys(tables).forEach(function(k){sel2.append('<option value="'+k+'">'+k+'</option>');});
  });
}

function updateAdhocFields(){
  const table=$('#adhocTable').val();
  const sel=$('#adhocFields').empty();
  if(!table||!tables[table])return;
  tables[table].forEach(function(f){sel.append('<option value="'+f+'">'+f+'</option>');});
}

function runAdhoc(){
  const table=$('#adhocTable').val();
  if(!table){FormConfirm.alert('Lỗi','Chọn bảng trước khi chạy');return;}
  const fields=$('#adhocFields').val()||['*'];
  $.post('/api/report-builder/adhoc',JSON.stringify({source_table:table,fields:fields}),
    function(r){
      const d=r.data||r;
      if(!d.data||d.data.length===0){$('#adhocResult').html('<p class="text-muted">Không có dữ liệu.</p>');return;}
      let html='<div class="table-responsive"><table class="table table-sm table-hover"><thead class="table-light"><tr>';
      Object.keys(d.data[0]).forEach(function(k){html+='<th>'+k+'</th>';});
      html+='</tr></thead><tbody>';
      d.data.forEach(function(row){
        html+='<tr>';
        Object.values(row).forEach(function(v){html+='<td>'+(v??'')+'</td>';});
        html+='</tr>';
      });
      html+='</tbody></table><small class="text-muted">'+d.count+' dòng</small></div>';
      $('#adhocResult').html(html);
    }).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
}

function runReport(id){
  $.getJSON('/api/report-builder/'+id+'/run',function(r){
    const d=r.data||r;
    if(!d.data||d.data.length===0){FormConfirm.alert('Báo cáo','Không có dữ liệu');return;}
    let html='<div class="table-responsive"><table class="table table-sm table-hover"><thead class="table-light"><tr>';
    Object.keys(d.data[0]).forEach(function(k){html+='<th>'+k+'</th>';});
    html+='</tr></thead><tbody>';
    d.data.forEach(function(row){
      html+='<tr>';
      Object.values(row).forEach(function(v){html+='<td>'+(v??'')+'</td>';});
      html+='</tr>';
    });
    html+='</tbody></table><small class="text-muted">'+d.count+' dòng</small></div>';
    showModal('Kết quả: '+(d.definition?.name||''),html);
  }).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
}

function showNewReport(){
  var tblKeys=Object.keys(tables).join(', ');
  FormModal.create({
    id:'reportModal',title:'Báo cáo mới',size:'sm',
    body:'<div class="mb-2"><label>Tên báo cáo</label><input class="form-control" id="rptName" data-v-required="Tên báo cáo"></div>'+
      '<div class="mb-2"><label>Bảng ('+tblKeys+')</label><input class="form-control" id="rptTable" data-v-required="Bảng"></div>'+
      '<div class="mb-2"><label>Trường (cách nhau bằng dấu phẩy)</label><input class="form-control" id="rptFields" placeholder="VD: id, name, amount"></div>',
    onSave:function(){
      var v=FormValidation.validate('#reportModal');if(!v.valid)return false;
      var name=$('#rptName').val();if(!name)return false;
      var table=$('#rptTable').val();if(!table||!tables[table]){FormToast.error('Bảng không hợp lệ');return false;}
      var fieldsStr=$('#rptFields').val();
      var fields=fieldsStr?fieldsStr.split(',').map(function(s){return s.trim();}):['*'];
      return $.post('/api/report-builder',JSON.stringify({name:name,source_table:table,fields:fields,type:'list'}),
        function(){FormModal.close('reportModal');loadSaved();FormToast.success('Đã lưu báo cáo');}).fail(function(x){FormToast.error(x.responseJSON?.error||'Lỗi');});
    }
  });
  setTimeout(function(){FormValidation.setup('#reportModal');},100);
}

function deleteReport(id){
  FormConfirm.confirmDelete('/api/report-builder/'+id,'báo cáo này',function(){loadSaved();});
}

function showModal(title,body){
  FormModal.create({
    id:'rptResultModal',title:title,size:'xl',footer:'',
    body:body,keyboard:true,backdrop:true
  });
}

$(function(){loadSaved();loadTables();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
