<?php $title = 'Trích bảo hiểm'; $activeMenu = 'payroll_insurance'; ob_start(); ?>
<div class="toolbar"><div><h5>Tính bảo hiểm</h5></div></div>
<div class="card-table p-3">
  <div class="row g-3 mb-3">
    <div class="col-md-3"><label class="form-label">Tổng lương</label><input class="form-control" id="insGross" value="15000000"></div>
    <div class="col-md-3"><label class="form-label">Lương tham gia BH</label><input class="form-control" id="insSalary" value="15000000"></div>
    <div class="col-md-2"><label class="form-label">Vùng</label><select class="form-select" id="insRegion"><option value="I">Vùng I</option><option value="II">Vùng II</option><option value="III">Vùng III</option><option value="IV" selected>Vùng IV</option></select></div>
    <div class="col-md-2"><label class="form-label">BHXH tối đa</label><input class="form-control" id="insCeiling" readonly></div>
  </div>
  <button class="btn btn-primary" onclick="doInsCalc()"><i class="bi bi-calculator"></i> Tính BH</button>
  <div id="insResult" class="mt-3"></div>
</div>
<script>
function updateCeiling(){var r=$('#insRegion').val();var mw={I:4960000,II:4410000,III:3860000,IV:3450000}[r]||3450000;$('#insCeiling').val((mw*20).toLocaleString('vi-VN'));}
$('#insRegion').change(updateCeiling);updateCeiling();
function doInsCalc(){$.get('/api/payroll/calculate/insurance',{gross:$('#insGross').val(),insurance_salary:$('#insSalary').val(),region:$('#insRegion').val()}).done(function(r){if(!r.data)return;
    var d=r.data;var h='<div class="card-table mt-2"><table class="table"><thead><tr><th>Khoản</th><th class="text-end">NLĐ</th><th class="text-end">DN</th></tr></thead><tbody>';
    h+='<tr><td>BHXH (8% / 17.5%)</td><td class="text-end">'+fmt(d.bhxh_ee)+'</td><td class="text-end">'+fmt(d.bhxh_er)+'</td></tr>';
    h+='<tr><td>BHYT (1.5% / 3%)</td><td class="text-end">'+fmt(d.bhyt_ee)+'</td><td class="text-end">'+fmt(d.bhyt_er)+'</td></tr>';
    h+='<tr><td>BHTN (1% / 1%)</td><td class="text-end">'+fmt(d.bhtn_ee)+'</td><td class="text-end">'+fmt(d.bhtn_er)+'</td></tr>';
    h+='<tr class="table-primary"><td><strong>Tổng</strong></td><td class="text-end"><strong>'+fmt(d.total_ee)+'</strong></td><td class="text-end"><strong>'+fmt(d.total_er)+'</strong></td></tr>';
    h+='</tbody></table></div>';$('#insResult').html(h);}).fail(function(x){showToast(x.responseJSON?.error||'Lỗi','error');});}
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
