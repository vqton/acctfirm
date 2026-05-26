<?php $title = 'Kê khai BHXH'; $activeMenu = 'payroll_bhxh'; ob_start(); ?>
<div class="toolbar"><div><h5>Kê khai bảo hiểm xã hội</h5></div>
  <div>
    <select class="form-select form-select-sm d-inline-block w-auto" id="bhPeriod"></select>
    <button class="btn btn-sm btn-primary" onclick="loadBhxh()"><i class="bi bi-file-text"></i> Xem</button>
  </div>
</div>
<div id="bhxhContent"><div class="text-muted text-center py-5">Chọn kỳ lương để xem dữ liệu kê khai BHXH</div></div>
<script>
$.get('/api/payroll/periods').done(function(r){var s='';if(r.data)r.data.forEach(function(p){s+='<option value="'+p.id+'">'+p.period_code+'</option>';});$('#bhPeriod').html(s);});
function loadBhxh(){var pid=$('#bhPeriod').val();if(!pid)return;
  $.get('/api/payroll/entries').done(function(r){if(!r.data||r.data.length===0){$('#bhxhContent').html('<div class="text-muted text-center py-4">Chưa có bảng lương</div>');return;}
    var eid=null;for(var i=0;i<r.data.length;i++){if(r.data[i].period_id===pid){eid=r.data[i].id;break;}}
    if(!eid){$('#bhxhContent').html('<div class="text-muted text-center py-4">Chưa có bảng lương cho kỳ này</div>');return;}
    $.get('/api/payroll/entries/'+eid+'/details').done(function(r2){
      var totalBhxh=0,totalBhyt=0,totalBhtn=0,totalGross=0,totalEmp=0;
      if(r2.data?.details){r2.data.details.forEach(function(d){totalGross+=d.gross_salary;totalBhxh+=d.insurance_ee*0.8/0.105;totalBhyt+=d.insurance_ee*0.15/0.105;totalBhtn+=d.insurance_ee*0.1/0.105;totalEmp++;});}
      var h='<div class="card-table p-3"><h6>Dữ liệu kê khai BHXH</h6><hr><table class="table"><tbody>';
      h+='<tr><td>Số lao động tham gia</td><td class="text-end">'+totalEmp+'</td></tr>';
      h+='<tr><td>Tổng quỹ lương đóng BHXH</td><td class="text-end">'+fmt(Math.round(totalBhxh))+'</td></tr>';
      h+='<tr><td>Tổng quỹ lương đóng BHYT</td><td class="text-end">'+fmt(Math.round(totalBhyt))+'</td></tr>';
      h+='<tr><td>Tổng quỹ lương đóng BHTN</td><td class="text-end">'+fmt(Math.round(totalBhtn))+'</td></tr>';
      h+='<tr class="table-primary"><td><strong>Tổng BHXH phải nộp (8%+17.5%)</strong></td><td class="text-end"><strong>'+fmt(Math.round(totalBhxh*(0.08+0.175)))+'</strong></td></tr>';
      h+='<tr><td>Tổng BHYT phải nộp (1.5%+3%)</td><td class="text-end">'+fmt(Math.round(totalBhyt*(0.015+0.03)))+'</td></tr>';
      h+='<tr><td>Tổng BHTN phải nộp (1%+1%)</td><td class="text-end">'+fmt(Math.round(totalBhtn*(0.01+0.01)))+'</td></tr>';
      h+='</tbody></table><p class="text-muted small mt-2">* Số liệu tham khảo. Vui lòng đối chiếu với biểu mẫu D02-LT, D01-TS, TK3-TS.</p></div>';
      $('#bhxhContent').html(h);}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
