<?php $title = 'Phiếu lương'; $activeMenu = 'payroll_payslip'; ob_start(); ?>
<div class="toolbar"><div><h5>Phiếu lương</h5></div>
  <div>
    <select class="form-select form-select-sm d-inline-block w-auto" id="psPeriod"></select>
    <select class="form-select form-select-sm d-inline-block w-auto" id="psEmployee"><option value="">-- Tất cả --</option></select>
    <button class="btn btn-sm btn-primary" onclick="loadPayslips()"><i class="bi bi-search"></i> Xem</button>
  </div>
</div>
<div id="payslipContent"><div class="text-muted text-center py-5">Chọn kỳ lương và nhân viên để xem phiếu lương</div></div>
<script>
$.get('/api/payroll/periods').done(function(r){var s='';if(r.data)r.data.forEach(function(p){s+='<option value="'+p.id+'">'+p.period_code+'</option>';});$('#psPeriod').html(s);});
$.get('/api/payroll/employees').done(function(r){var s=$('#psEmployee').html();if(r.data)r.data.forEach(function(e){s+='<option value="'+e.id+'">'+e.code+' - '+esc(e.name)+'</option>';});$('#psEmployee').html(s);});
function loadPayslips(){var pid=$('#psPeriod').val();if(!pid){showToast('Chọn kỳ lương','error');return;}
  $.get('/api/payroll/entries',{period_id:pid}).done(function(r){if(!r.data||r.data.length===0){$('#payslipContent').html('<div class="text-muted text-center py-4">Chưa có bảng lương cho kỳ này</div>');return;}
    var eid=r.data[0].id;
    $.get('/api/payroll/entries/'+eid+'/details').done(function(r2){
      var empFilter=$('#psEmployee').val();var h='';if(r2.data?.details){r2.data.details.forEach(function(d){if(empFilter&&d.employee_id!==empFilter)return;
        h+='<div class="card-table mb-3 p-3"><h6>Phiếu lương: '+esc(d.employee_code)+' - '+esc(d.employee_name)+'</h6><hr><table class="table table-sm"><tbody>';
        h+='<tr><td>Lương gross</td><td class="text-end">'+fmt(d.gross_salary)+'</td></tr><tr><td>BHXH NLĐ</td><td class="text-end">-'+fmt(d.insurance_ee)+'</td></tr><tr><td>Thuế TNCN</td><td class="text-end">-'+fmt(d.tax_amount)+'</td></tr><tr class="table-primary"><td><strong>Thực nhận</strong></td><td class="text-end"><strong>'+fmt(d.net_pay)+'</strong></td></tr>';
        h+='<tr><td>BHXH DN</td><td class="text-end">'+fmt(d.insurance_er)+'</td></tr><tr><td>Tổng chi phí DN</td><td class="text-end">'+fmt(d.total_cost)+'</td></tr>';
        h+='<tr><td>Số công</td><td class="text-end">'+d.working_days+'</td></tr>';
        h+='</tbody></table></div>';});}$('#payslipContent').html(h||'<div class="text-muted text-center py-4">Không có dữ liệu</div>');}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
