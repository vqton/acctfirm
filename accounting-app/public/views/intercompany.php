<?php // Màn hình: Đối chiếu và loại trừ giao dịch nội bộ
// API: GET /api/ic/entities, GET /api/ic/match/{id}, POST /api/ic/eliminate/{id}, GET /api/ic/consolidated
// Nghiệp vụ: Đối chiếu TK 136 (phải thu nội bộ) với TK 336 (phải trả nội bộ) giữa các đơn vị
// Hợp nhất: Loại trừ giao dịch IC để lập BCTC hợp nhất — không được tính doanh thu/lãi nội bộ
// Rủi ro: Không loại trừ IC sẽ làm sai BC01 hợp nhất (lãi kép)
$title = 'Giao dịch nội bộ'; $activeMenu = 'intercompany'; ob_start(); ?>
<div class="toolbar">
    <h5>Đối chiếu & Loại trừ giao dịch nội bộ</h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto" id="entitySelect">
            <option value="">-- Chọn đơn vị --</option>
        </select>
        <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'giao-dich-noi-bo')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
        <button class="btn btn-primary btn-sm" onclick="loadMatch()"><i class="bi bi-search"></i> Đối chiếu</button>
        <button class="btn btn-warning btn-sm" onclick="eliminateAll()"><i class="bi bi-trash"></i> Loại trừ IC</button>
        <button class="btn btn-outline-info btn-sm" onclick="loadConsolidated()"><i class="bi bi-globe"></i> Tổng hợp</button>
    </div>
</div>

<div id="matchContainer" class="card p-3 mt-2" style="display:none">
    <h6 class="mb-3"><i class="bi bi-arrow-left-right me-1"></i> Đối chiếu — <span id="entityNameSpan"></span></h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered" id="matchTable">
            <thead><tr>
                <th>Đối tác</th><th class="text-end">Phải thu (136)</th><th class="text-end">Phải trả (336)</th>
                <th class="text-end">Chênh lệch</th><th>Trạng thái</th><th>GD</th><th>Ngày cũ nhất</th><th>Ngày mới nhất</th>
            </tr></thead>
            <tbody id="matchBody"></tbody>
        </table>
    </div>
</div>

<div id="consolidatedContainer" class="card p-3 mt-2" style="display:none">
    <h6 class="mb-3"><i class="bi bi-diagram-3 me-1"></i> Báo cáo tổng hợp IC</h6>
    <div id="consolidatedSummary" class="mb-2"></div>
    <div class="table-responsive">
        <table class="table table-sm table-bordered" id="consolidatedTable">
            <thead><tr>
                <th>Đơn vị</th><th>Đối tác</th><th class="text-end">Số dư</th>
                <th class="text-end">Đối ứng</th><th class="text-end">Chênh lệch</th><th>Trạng thái</th>
            </tr></thead>
            <tbody id="consolidatedBody"></tbody>
        </table>
    </div>
</div>

<script>
function loadEntities() {
    $.get('/api/ic/entities', function(data){
        var sel = $('#entitySelect'); sel.html('<option value="">-- Chọn đơn vị --</option>');
        (data||[]).forEach(function(e) {
            sel.append('<option value="'+e.id+'" data-code="'+esc(e.code)+'">['+esc(e.type)+'] '+esc(e.name)+' ('+esc(e.code)+')</option>');
        });
    });
}

function getEntityId() { return $('#entitySelect').val(); }
function getEntityName() { return $('#entitySelect').find(':selected').text(); }

function loadMatch() {
    var id = getEntityId(); if(!id) return;
    $('#entityNameSpan').text(getEntityName());
    $.get('/api/ic/match/'+id, function(d) {
        var html = '';
        (d.items||[]).forEach(function(r) {
            var cls = r.status === 'matched' ? 'text-success' : 'text-danger';
            html += '<tr><td>'+esc(r.counterparty_code)+' — '+esc(r.counterparty_name)+'</td>'+
                '<td class="text-end font-monospace">'+num(r.receivable_balance)+'</td>'+
                '<td class="text-end font-monospace">'+num(r.contra_balance)+'</td>'+
                '<td class="text-end font-monospace '+cls+'">'+(r.difference < 10 ? '—' : num(r.difference))+'</td>'+
                '<td><span class="badge bg-'+(r.status==='matched'?'success':'danger')+'">'+esc(r.status)+'</span></td>'+
                '<td class="text-center">'+r.txn_count+'</td>'+
                '<td>'+esc(r.oldest_date||'')+'</td>'+
                '<td>'+esc(r.newest_date||'')+'</td></tr>';
        });
        if(html === '') html = '<tr><td colspan="8" class="text-center text-muted">Không có giao dịch nội bộ</td></tr>';
        $('#matchBody').html(html);
        $('#matchContainer').show();
        $('#consolidatedContainer').hide();
    });
}

// Loại trừ giao dịch nội bộ — POST /api/ic/eliminate/{id}
// Nghiệp vụ: Bút toán loại trừ — Nợ 336/Có 136 (phần đã khớp)
// RỦI RO: Nếu có chênh lệch chưa khớp, loại trừ sẽ không cân — cần xử lý trước
function eliminateAll() {
    var id = getEntityId(); if(!id) return;
    if(!confirm('Xác nhận loại trừ giao dịch nội bộ cho đơn vị này?')) return;
    $.ajax({url:'/api/ic/eliminate/'+id, method:'POST',
        success:function(d) {
            var msg = 'Đã loại trừ '+d.eliminations_count+' cặp';
            showAlert(msg, 'success');
            loadMatch();
        },
        error: function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showAlert(m, 'danger'); }
    });
}

function loadConsolidated() {
    $.get('/api/ic/consolidated', function(d) {
        $('#consolidatedSummary').html(
            '<span class="me-3">Tổng đơn vị: <strong>'+d.entity_count+'</strong></span>'+
            '<span class="me-3">Cặp IC: <strong>'+d.pair_count+'</strong></span>'+
            '<span>Chưa khớp: <strong class="text-danger">'+d.unmatched_count+'</strong></span>'
        );
        var html = '';
        (d.pairs||[]).forEach(function(r) {
            var cls = r.status === 'matched' ? 'text-success' : 'text-danger';
            html += '<tr><td>'+esc(r.entity_code)+' — '+esc(r.entity_name)+'</td>'+
                '<td>'+esc(r.counterparty_code)+'</td>'+
                '<td class="text-end font-monospace">'+num(r.receivable_balance)+'</td>'+
                '<td class="text-end font-monospace">'+num(r.contra_balance)+'</td>'+
                '<td class="text-end font-monospace '+cls+'">'+(r.difference < 10 ? '—' : num(r.difference))+'</td>'+
                '<td><span class="badge bg-'+(r.status==='matched'?'success':'danger')+'">'+esc(r.status)+'</span></td></tr>';
        });
        if(html === '') html = '<tr><td colspan="6" class="text-center text-muted">Chưa có dữ liệu</td></tr>';
        $('#consolidatedBody').html(html);
        $('#consolidatedContainer').show();
        $('#matchContainer').hide();
    });
}

$(document).ready(function(){loadEntities();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
