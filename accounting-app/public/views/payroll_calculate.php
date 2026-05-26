<?php $title = 'Tính lương'; $activeMenu = 'payroll_calculate'; ob_start(); ?>
<div class="toolbar"><div><h5>Tính lương thử</h5></div></div>
<div class="card-table p-3">
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Nhân viên</label><select class="form-select" id="calcEmployee"></select></div>
    <div class="col-md-2"><label class="form-label">Lương gross</label><input class="form-control" id="calcGross" value="10000000"></div>
    <div class="col-md-2"><label class="form-label">Phụ cấp</label><input class="form-control" id="calcAllowance" value="0"></div>
    <div class="col-md-2"><label class="form-label">Khấu trừ</label><input class="form-control" id="calcDeduction" value="0"></div>
    <div class="col-md-2"><label class="form-label">Tăng ca</label><input class="form-control" id="calcOvertime" value="0"></div>
  </div>
  <button class="btn btn-primary" onclick="doCalc()"><i class="bi bi-calculator"></i> Tính</button>
  <div id="calcResult" class="mt-3"></div>
</div>
<script>
$.get('/api/payroll/employees').done(function(r){var s='<option value="">-- Chọn --</option>';if(r.data)r.data.forEach(function(e){s+='<option value="'+e.id+'">'+e.code+' - '+esc(e.name)+'</option>';});$('#calcEmployee').html(s);});
function doCalc(){var eid=$('#calcEmployee').val();if(!eid){showToast('Chọn nhân viên','error');return;}
  var g=$('#calcGross').val();
  $.get('/api/payroll/calculate/employee',{employee_id:eid,gross:g}).done(function(r){if(!r.data){showToast('Không có kết quả','error');return;}
    var d=r.data;var h='<div class="card-table mt-2"><table class="table"><thead><tr><th>Chỉ tiêu</th><th class="text-end">Giá trị</th></tr></thead><tbody>';
    h+='<tr><td>Lương gross</td><td class="text-end">'+fmt(d.gross_salary)+'</td></tr>';
    h+='<tr><td>Phụ cấp</td><td class="text-end">'+fmt(d.allowances)+'</td></tr>';
    h+='<tr><td>Khấu trừ</td><td class="text-end">-'+fmt(d.deductions)+'</td></tr>';
    h+='<tr><td>BHXH NLĐ (8%)</td><td class="text-end">-'+fmt(d.insurance_bhxh_ee)+'</td></tr>';
    h+='<tr><td>BHYT NLĐ (1.5%)</td><td class="text-end">-'+fmt(d.insurance_bhyt_ee)+'</td></tr>';
    h+='<tr><td>BHTN NLĐ (1%)</td><td class="text-end">-'+fmt(d.insurance_bhtn_ee)+'</td></tr>';
    h+='<tr><td>Thuế TNCN</td><td class="text-end">-'+fmt(d.tax_amount)+'</td></tr>';
    h+='<tr class="table-primary"><td><strong>Lương thực nhận</strong></td><td class="text-end"><strong>'+fmt(d.net_pay)+'</strong></td></tr>';
    h+='<tr><td>BHXH DN (17.5%)</td><td class="text-end">'+fmt(d.insurance_bhxh_er)+'</td></tr>';
    h+='<tr><td>BHYT DN (3%)</td><td class="text-end">'+fmt(d.insurance_bhyt_er)+'</td></tr>';
    h+='<tr><td>BHTN DN (1%)</td><td class="text-end">'+fmt(d.insurance_bhtn_er)+'</td></tr>';
    h+='<tr class="table-info"><td><strong>Tổng chi phí DN</strong></td><td class="text-end"><strong>'+fmt(d.total_cost)+'</strong></td></tr>';
    h+='</tbody></table></div>';$('#calcResult').html(h);}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
