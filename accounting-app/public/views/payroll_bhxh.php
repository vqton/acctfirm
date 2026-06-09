<?php $title = 'Nộp BHXH'; $activeMenu = 'payroll_bhxh'; ob_start(); ?>
<div class="toolbar"><div><h5>Kê khai & nộp bảo hiểm xã hội</h5></div>
  <div>
    <select class="form-select form-select-sm d-inline-block w-auto" id="bhPeriod"></select>
    <button class="btn btn-sm btn-primary" onclick="loadBhxh()"><i class="bi bi-file-text"></i> Xem</button>
  </div>
</div>
<div id="bhxhContent"><div class="text-muted text-center py-5">Chọn kỳ lương để xem dữ liệu kê khai BHXH</div></div>

<div class="modal fade" id="payInsuranceModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="payInsuranceForm">
<div class="modal-header"><h5 class="modal-title">Nộp BHXH, BHYT, BHTN</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <input type="hidden" id="payInsPeriodId">
    <p class="text-muted">Hạch toán: Nợ 3383 / Có 111, 112</p>
    <div class="mb-2"><label>Số tiền nộp</label><input type="number" class="form-control" id="payInsAmount" step="1000" min="1" data-v-required="Số tiền" required></div>
    <div class="mb-2"><label>Nguồn tiền</label><select class="form-select" id="payInsAccount"><option value="1121">1121 - Tiền gửi NH</option><option value="1111">1111 - Tiền mặt</option></select></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button><button type="submit" class="btn btn-sm btn-primary">Xác nhận nộp</button></div>
</form>
</div></div></div>

<script>
var currentPeriodId='';
$.get('/api/payroll/periods').done(function(r){var s='';if(r.data)r.data.forEach(function(p){s+='<option value="'+p.id+'">'+p.period_code+'</option>';});$('#bhPeriod').html(s);});
function loadBhxh(){var pid=$('#bhPeriod').val();if(!pid)return;currentPeriodId=pid;
  $.get('/api/payroll/entries').done(function(r){if(!r.data||r.data.length===0){$('#bhxhContent').html('<div class="text-muted text-center py-4">Chưa có bảng lương</div>');return;}
    var eid=null;for(var i=0;i<r.data.length;i++){if(r.data[i].period_id===pid){eid=r.data[i].id;break;}}
    if(!eid){$('#bhxhContent').html('<div class="text-muted text-center py-4">Chưa có bảng lương cho kỳ này</div>');return;}
    $.get('/api/payroll/entries/'+eid+'/details').done(function(r2){
      var totalBhxh=0,totalBhyt=0,totalBhtn=0,totalGross=0,totalEmp=0;
      if(r2.data?.details){r2.data.details.forEach(function(d){totalGross+=d.gross_salary;totalBhxh+=d.insurance_ee*0.8/0.105;totalBhyt+=d.insurance_ee*0.15/0.105;totalBhtn+=d.insurance_ee*0.1/0.105;totalEmp++;});}
      var totalInsurance=Math.round(totalBhxh*(0.08+0.175)+totalBhyt*(0.015+0.03)+totalBhtn*(0.01+0.01));
      var h='<div class="card-table p-3"><h6>Dữ liệu kê khai BHXH</h6><hr><table class="table"><tbody>';
      h+='<tr><td>Số lao động tham gia</td><td class="text-end">'+totalEmp+'</td></tr>';
      h+='<tr><td>Tổng quỹ lương đóng BHXH</td><td class="text-end">'+fmt(Math.round(totalBhxh))+'</td></tr>';
      h+='<tr><td>Tổng quỹ lương đóng BHYT</td><td class="text-end">'+fmt(Math.round(totalBhyt))+'</td></tr>';
      h+='<tr><td>Tổng quỹ lương đóng BHTN</td><td class="text-end">'+fmt(Math.round(totalBhtn))+'</td></tr>';
      h+='<tr class="table-primary"><td><strong>Tổng BHXH phải nộp (8%+17.5%)</strong></td><td class="text-end"><strong>'+fmt(totalInsurance)+'</strong></td></tr>';
      h+='<tr><td>Tổng BHYT phải nộp (1.5%+3%)</td><td class="text-end">'+fmt(Math.round(totalBhyt*(0.015+0.03)))+'</td></tr>';
      h+='<tr><td>Tổng BHTN phải nộp (1%+1%)</td><td class="text-end">'+fmt(Math.round(totalBhtn*(0.01+0.01)))+'</td></tr>';
      h+='</tbody></table><div class="text-end mt-2"><button class="btn btn-primary btn-sm" onclick="openPayInsurance('+totalInsurance+')"><i class="bi bi-cash"></i> Nộp BHXH</button></div>';
      h+='<p class="text-muted small mt-2">* Số liệu tham khảo. Vui lòng đối chiếu với biểu mẫu D02-LT, D01-TS, TK3-TS.</p></div>';
      $('#bhxhContent').html(h);}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}
function openPayInsurance(amount){$('#payInsPeriodId').val(currentPeriodId);$('#payInsAmount').val(amount);$('#payInsuranceModal').modal('show');}
$('#payInsuranceForm').submit(function(e){e.preventDefault();
    var v=FormValidation.validate('#payInsuranceForm');if(!v.valid)return;
    $.ajax({url:'/api/payroll/insurance/pay',method:'POST',contentType:'application/json',data:JSON.stringify({period_id:$('#payInsPeriodId').val(),amount:parseFloat($('#payInsAmount').val()),bank_account:$('#payInsAccount').val()}),
        success:function(){$('#payInsuranceModal').modal('hide');FormToast.success('Ghi nhận nộp BHXH thành công');loadBhxh();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}FormToast.error(m);}
    });
});
$(document).ready(function(){FormValidation.setup('#payInsuranceForm');});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
