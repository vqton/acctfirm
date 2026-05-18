<?php $title = 'Khóa sổ cuối kỳ'; $activeMenu = 'period_close'; ob_start(); ?>
<style>
.step-indicator { display:flex; gap:0; margin-bottom:24px; }
.step { flex:1; text-align:center; padding:12px 8px; background:#e9ecef; position:relative; font-size:13px; }
.step.active { background:#0d6efd; color:#fff; }
.step.done { background:#198754; color:#fff; }
.step:not(:last-child)::after { content:''; position:absolute; right:-10px; top:50%; transform:translateY(-50%); border-left:10px solid #e9ecef; border-top:15px solid transparent; border-bottom:15px solid transparent; }
.step.active::after { border-left-color:#0d6efd; }
.step.done::after { border-left-color:#198754; }
.step .step-num { display:inline-block; width:24px; height:24px; line-height:24px; border-radius:50%; background:rgba(255,255,255,.3); margin-right:6px; font-size:12px; }
</style>

<div class="toolbar">
    <h5>Khóa sổ cuối kỳ</h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto" id="periodSelect">
            <option value="">-- Chọn kỳ cần khóa --</option>
        </select>
    </div>
</div>

<div id="stepIndicator" class="step-indicator">
    <div class="step active" id="step1"><span class="step-num">1</span> Kiểm tra</div>
    <div class="step" id="step2"><span class="step-num">2</span> Kết chuyển P&L → 421</div>
    <div class="step" id="step3"><span class="step-num">3</span> Khóa sổ</div>
    <div class="step" id="step4"><span class="step-num">4</span> Xuất BCTC</div>
    <div class="step" id="step5"><span class="step-num">5</span> Lưu trữ</div>
</div>

<div id="stepContent" class="card p-4">
    <div id="step1Content">
        <h6 class="mb-3"><i class="bi bi-check-circle me-1"></i> Kiểm tra trước khi khóa sổ</h6>
        <div id="checksContainer"><p class="text-muted">Chọn kỳ kế toán để kiểm tra</p></div>
        <button class="btn btn-primary btn-sm mt-2" onclick="runChecks()"><i class="bi bi-arrow-repeat"></i> Kiểm tra</button>
    </div>
    <div id="step2Content" style="display:none">
        <h6 class="mb-3"><i class="bi bi-arrow-left-right me-1"></i> Kết chuyển lãi/lỗ sang TK 421</h6>
        <p class="text-muted">Thực hiện bút toán kết chuyển doanh thu, chi phí và xác định kết quả kinh doanh.</p>
        <div id="closingStatus"></div>
        <button class="btn btn-warning btn-sm mt-2" id="executeBtn" onclick="executeClosing()"><i class="bi bi-play"></i> Thực hiện kết chuyển</button>
    </div>
    <div id="step3Content" style="display:none">
        <h6 class="mb-3"><i class="bi bi-lock me-1"></i> Khóa sổ kỳ kế toán</h6>
        <p class="text-muted">Sau khi khóa, không thể ghi nhận giao dịch mới trong kỳ này.</p>
        <div id="closeStatus"></div>
        <button class="btn btn-danger btn-sm mt-2" id="closeBtn" onclick="closePeriodNow()"><i class="bi bi-lock-fill"></i> Khóa sổ</button>
    </div>
    <div id="step4Content" style="display:none">
        <h6 class="mb-3"><i class="bi bi-file-earmark-text me-1"></i> Xuất báo cáo tài chính</h6>
        <p class="text-muted">Xem và xuất BCTC theo Thông tư 99.</p>
        <div class="d-flex gap-2 mb-2">
            <a class="btn btn-outline-primary btn-sm" target="_blank" id="viewBC01Link"><i class="bi bi-file-text"></i> BC CĐKT (BC 01)</a>
            <a class="btn btn-outline-primary btn-sm" target="_blank" id="viewBC02Link"><i class="bi bi-file-text"></i> KQKD (BC 02)</a>
            <button class="btn btn-outline-success btn-sm" onclick="exportTT99()"><i class="bi bi-download"></i> Xuất TT99</button>
        </div>
        <pre id="tt99Output" class="border rounded p-2 mt-2" style="max-height:300px;overflow:auto;font-size:11px;display:none"></pre>
    </div>
    <div id="step5Content" style="display:none">
        <h6 class="mb-3"><i class="bi bi-archive me-1"></i> Lưu trữ & Ký số</h6>
        <p class="text-muted">Chụp ảnh số dư cuối kỳ và lưu trữ phục vụ đối chiếu sau này.</p>
        <div id="archiveStatus"></div>
        <button class="btn btn-success btn-sm mt-2" onclick="archivePeriod()"><i class="bi bi-archive"></i> Lưu trữ</button>
        <button class="btn btn-outline-secondary btn-sm mt-2" onclick="window.print()"><i class="bi bi-printer"></i> In báo cáo</button>
    </div>
</div>

<script>
var selectedPeriodId=null, selectedPeriodCode=null;

function loadPeriods(){
    $.get('/api/periods',function(data){
        var sel=$('#periodSelect');sel.html('<option value="">-- Chọn kỳ --</option>');
        data.forEach(function(p){
            if(p.status==='open')sel.append('<option value="'+p.id+'" data-code="'+esc(p.period_code)+'">'+esc(p.name)+' ('+esc(p.period_code)+')</option>');
        });
    });
}

$('#periodSelect').on('change',function(){
    selectedPeriodId=$(this).val();
    selectedPeriodCode=$(this).find(':selected').data('code');
    if(selectedPeriodId){runChecks();resetSteps();}
});

function resetSteps(){
    $('#step1,#step2,#step3,#step4,#step5').removeClass('active done');
    $('#step1').addClass('active');
    $('#step1Content').show();$('#step2Content,#step3Content,#step4Content,#step5Content').hide();
}

function goToStep(n){
    for(var i=1;i<=5;i++){$('#step'+i).removeClass('active done');if(i<n)$('#step'+i).addClass('done');}
    $('#step'+n).addClass('active');
    $('#step'+n+'Content').show();
    for(i=1;i<=5;i++){if(i!==n)$('#step'+i+'Content').hide();}
}

function runChecks(){
    $.get('/api/periods/'+selectedPeriodId+'/can-close',function(d){
        var html='';
        if(d.checks&&d.checks.length){d.checks.forEach(function(c){html+='<div class="mb-1"><i class="bi bi-'+(c.passed?'check-circle-fill text-success':'x-circle-fill text-danger')+' me-1"></i> '+esc(c.check)+(c.note?' <span class="text-muted">('+esc(c.note)+')</span>':'')+'</div>';});}
        html+='<div class="mt-2 fw-bold">'+(d.can_close?'<span class="text-success">Sẵn sàng khóa sổ</span>':'<span class="text-danger">Cần xử lý trước khi khóa</span>')+'</div>';
        $('#checksContainer').html(html);
        if(d.can_close){$('#step1').addClass('done');goToStep(2);}
    });
}

function executeClosing(){
    $('#executeBtn').prop('disabled',true).text('Đang xử lý...');
    $('#closingStatus').html('<div class="text-info"><i class="bi bi-hourglass"></i> Đang kết chuyển...</div>');
    $.ajax({url:'/api/periods/'+selectedPeriodId+'/execute-closing',method:'POST',
        success:function(){
            $('#closingStatus').html('<div class="text-success"><i class="bi bi-check-circle"></i> Đã kết chuyển lãi/lỗ sang TK 421</div>');
            $('#step2').addClass('done');
            $('#executeBtn').prop('disabled',false).text('Thực hiện kết chuyển');
            goToStep(3);
        },
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){};$('#closingStatus').html('<div class="text-danger"><i class="bi bi-x-circle"></i> '+esc(m)+'</div>');$('#executeBtn').prop('disabled',false).text('Thực hiện kết chuyển');}
    });
}

function closePeriodNow(){
    if(!confirm('Xác nhận khóa sổ kỳ kế toán này?'))return;
    $('#closeBtn').prop('disabled',true).text('Đang khóa...');
    $('#closeStatus').html('<div class="text-info"><i class="bi bi-hourglass"></i> Đang khóa sổ...</div>');
    $.ajax({url:'/api/periods/'+selectedPeriodId+'/close',method:'POST',
        success:function(){
            $('#closeStatus').html('<div class="text-success"><i class="bi bi-check-circle"></i> Đã khóa sổ kỳ kế toán</div>');
            $('#step3').addClass('done');
            $('#closeBtn').prop('disabled',false).text('Khóa sổ');
            setupFSLinks();
            goToStep(4);
        },
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){};$('#closeStatus').html('<div class="text-danger"><i class="bi bi-x-circle"></i> '+esc(m)+'</div>');$('#closeBtn').prop('disabled',false).text('Khóa sổ');}
    });
}

function setupFSLinks(){
    var code=selectedPeriodCode;
    $('#viewBC01Link').attr('href','/bao-cao/tinh-hinh-tai-chinh?period='+code);
    $('#viewBC02Link').attr('href','/bao-cao/ket-qua-kinh-doanh?period='+code);
}

function exportTT99(){
    $.get('/api/fs/tt99?period='+(selectedPeriodCode||''),function(d){
        var html='<div class="fw-bold mb-1">BÁO CÁO TÀI CHÍNH TT99 - Kỳ '+esc(selectedPeriodCode)+'</div>';
        if(d.errors&&d.errors.length){d.errors.forEach(function(e){html+='<div class="text-danger">⚠ '+esc(e)+'</div>';});}
        html+='<table class="table table-sm mt-1"><thead><tr><th>Mã số</th><th>Chỉ tiêu</th><th class="text-end">Giá trị</th></tr></thead><tbody>';
        (d.items||[]).forEach(function(r){
            html+='<tr><td>'+esc(r.ma_so)+'</td><td>'+esc(r.name_vi)+'</td><td class="text-end font-monospace">'+parseFloat(r.value).toLocaleString()+'</td></tr>';
        });
        html+='</tbody></table>';
        if(d.cash_flow){html+='<div class="fw-bold mt-2">Lưu chuyển tiền tệ</div><table class="table table-sm"><tbody>';
            d.cash_flow.forEach(function(r){html+='<tr><td>'+esc(r.ma_so)+'</td><td>'+esc(r.name_vi)+'</td><td class="text-end font-monospace">'+parseFloat(r.value).toLocaleString()+'</td></tr>';});
            html+='</tbody></table>';}
        $('#tt99Output').html(html).show();
    });
}

function archivePeriod(){
    $.ajax({url:'/api/periods/'+selectedPeriodId+'/archive',method:'POST',
        success:function(){
            $('#archiveStatus').html('<div class="text-success"><i class="bi bi-check-circle"></i> Đã lưu trữ dữ liệu cuối kỳ</div>');
            $('#step5').addClass('done');
        },
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){};$('#archiveStatus').html('<div class="text-danger"><i class="bi bi-x-circle"></i> '+esc(m)+'</div>');}
    });
}

$(document).ready(function(){loadPeriods();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
