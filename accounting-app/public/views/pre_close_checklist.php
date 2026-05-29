<?php
$title = 'Kiểm tra trước khi khóa sổ';
$activeMenu = 'pre_close';
ob_start();
?>
<div class="toolbar">
    <h5>Kiểm tra trước khi khóa sổ</h5>
</div>

<div class="card mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-auto">
                <label class="form-label">Kỳ kế toán</label>
                <select class="form-select" id="periodId"></select>
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" id="btnCheck"><i class="bi bi-check-circle"></i> Kiểm tra</button>
            </div>
        </div>
    </div>
</div>

<div id="resultArea" class="d-none">
    <div class="card mb-3">
        <div class="card-body py-2 d-flex justify-content-between">
            <span><strong id="periodTitle"></strong></span>
            <span class="font-monospace">Đạt: <span id="passedCount">0</span> / <span id="totalCount">0</span></span>
        </div>
    </div>
    <div id="checksList"></div>

    <div id="closeArea" class="d-none mt-3">
        <button class="btn btn-success" id="btnCloseWithChecklist"><i class="bi bi-lock"></i> Khóa sổ</button>
    </div>
</div>

<script>
function loadPeriods() {
    $.get('/api/periods', function(data) {
        var o = '';
        data.forEach(function(p) {
            o += '<option value="' + p.id + '">' + esc(p.period_code) + ' - ' + esc(p.name) + ' (' + p.status + ')</option>';
        });
        $('#periodId').html(o);
    });
}

function renderCheckIcon(passed) {
    return passed ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle-fill text-danger"></i>';
}

$('#btnCheck').click(function() {
    var id = $('#periodId').val();
    if (!id) return;
    $.get('/api/periods/' + id + '/checklist', function(r) {
        var d = r.data;
        $('#resultArea').removeClass('d-none');
        $('#periodTitle').text(d.period_code + ' - ' + d.period_name);
        $('#passedCount').text(d.passed_count);
        $('#totalCount').text(d.total_count);
        var checksDiv = $('#checksList').empty();
        d.checks.forEach(function(c) {
            var badge = c.passed ? 'bg-success' : 'bg-danger';
            checksDiv.append(
                '<div class="card mb-2"><div class="card-body d-flex justify-content-between align-items-center py-2">' +
                '<span>' + renderCheckIcon(c.passed) + ' ' + esc(c.check) + '</span>' +
                '<span><span class="badge ' + badge + '">' + esc(c.note) + '</span></span></div></div>'
            );
        });
        if (d.can_close && d.status === 'open') {
            $('#closeArea').removeClass('d-none');
        } else {
            $('#closeArea').addClass('d-none');
        }
    });
});

$('#btnCloseWithChecklist').click(function() {
    var id = $('#periodId').val();
    if (!confirm('Xác nhận khóa sổ kỳ này? Mọi bút toán sẽ được kết chuyển và kỳ sẽ chuyển sang trạng thái đóng.')) return;
    $.ajax({url:'/api/periods/' + id + '/close-with-checklist', method:'POST', headers:{'X-CSRF-Token':csrf},
        success:function() { showToast('Đã khóa sổ kỳ kế toán thành công.','success'); $('#btnCheck').click(); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
});

$(document).ready(function() { loadPeriods(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
