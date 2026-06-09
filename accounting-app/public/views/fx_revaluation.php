<?php // Màn hình: Đánh giá lại số dư ngoại tệ cuối kỳ
// API: GET /api/periods, GET /api/fx/report/{id}, POST /api/fx/revaluate/{id}
// Nghiệp vụ: Đánh giá lại số dư các TK có gốc ngoại tệ (1112, 1122, 131, 331...) theo tỷ giá cuối kỳ
// Tuân thủ: Thông tư 200 — chênh lệch tỷ giá hạch toán vào TK 413 (chênh lệch tỷ giá)
// Rủi ro: Không đánh giá lại sẽ làm sai số dư VND trên BC01
$title = 'Đánh giá lại ngoại tệ'; $activeMenu = 'fx_revaluation'; ob_start(); ?>
<div class="toolbar">
    <h5>Đánh giá lại ngoại tệ</h5>
    <div>
        <select class="form-select form-select-sm d-inline-block w-auto" id="periodSelect">
            <option value="">-- Chọn kỳ --</option>
        </select>
        <button class="btn btn-outline-success btn-sm me-1" onclick="exportCSV('#dataBody', 'danh-gia-lai-ngoai-te')"><i class="bi bi-file-earmark-excel"></i> Xuất Excel</button>
        <button class="btn btn-primary btn-sm" onclick="loadReport()"><i class="bi bi-search"></i> Xem báo cáo</button>
        <button class="btn btn-warning btn-sm" onclick="executeRevaluation()"><i class="bi bi-currency-exchange"></i> Thực hiện đánh giá lại</button>
    </div>
</div>

<div id="reportContainer" class="card p-3" style="display:none">
    <h6 class="mb-3"><i class="bi bi-table me-1"></i> Chi tiết đánh giá lại — <span id="periodCodeSpan"></span></h6>
    <div class="table-responsive">
        <table class="table table-sm table-bordered" id="reportTable">
            <thead><tr>
                <th>TK</th><th>NT</th><th class="text-end">Số dư NT</th><th class="text-end">Số dư VND</th>
                <th class="text-end">TG bình quân</th><th class="text-end">TG cuối kỳ</th>
                <th class="text-end">Giá trị sau đánh giá</th><th class="text-end">Chênh lệch</th>
            </tr></thead>
            <tbody id="reportBody"></tbody>
        </table>
    </div>
    <div id="summaryArea" class="mt-2 p-2 rounded bg-light"></div>
</div>

<script>
function loadPeriods() {
    $.get('/api/periods', function(data){
        var sel = $('#periodSelect'); sel.html('<option value="">-- Chọn kỳ --</option>');
        (data||[]).forEach(function(p) {
            sel.append('<option value="'+p.id+'" data-code="'+esc(p.period_code)+'">'+esc(p.name)+' ('+esc(p.period_code)+')</option>');
        });
    });
}

function getPeriodId() { return $('#periodSelect').val(); }
function getPeriodCode() { return $('#periodSelect').find(':selected').data('code'); }

// Xem báo cáo đánh giá lại ngoại tệ — GET /api/fx/report/{id}
// Hiển thị: số dư ngoại tệ, số dư VND, tỷ giá bình quân, tỷ giá cuối kỳ, giá trị sau đánh giá, chênh lệch
// Công thức: Chênh lệch = Số dư NT × (Tỷ giá cuối kỳ - Tỷ giá bình quân)
function loadReport() {
    var id = getPeriodId(); if(!id) return;
    $('#periodCodeSpan').text(getPeriodCode());
    $.get('/api/fx/report/'+id, function(d) {
        var html = '';
        (d.items||[]).forEach(function(r) {
            var cls = r.unrealized_gain_loss >= 0 ? 'text-success' : 'text-danger';
            html += '<tr><td>'+esc(r.account_code)+'</td><td>'+esc(r.fc_currency)+'</td>'+
                '<td class="text-end font-monospace">'+num(r.fc_balance)+'</td>'+
                '<td class="text-end font-monospace">'+num(r.vnd_balance)+'</td>'+
                '<td class="text-end font-monospace">'+r.avg_rate.toFixed(4)+'</td>'+
                '<td class="text-end font-monospace">'+r.end_rate.toFixed(4)+'</td>'+
                '<td class="text-end font-monospace">'+num(r.revalued_vnd)+'</td>'+
                '<td class="text-end font-monospace '+cls+'">'+(r.unrealized_gain_loss >= 0 ? '+' : '')+num(r.unrealized_gain_loss)+'</td></tr>';
        });
        if(html === '') html = '<tr><td colspan="8" class="text-center text-muted">Không có số dư ngoại tệ cần đánh giá lại</td></tr>';
        $('#reportBody').html(html);
        $('#summaryArea').html('');
        $('#reportContainer').show();
    });
}

// Thực hiện đánh giá lại ngoại tệ — POST /api/fx/revaluate/{id}
// Nghiệp vụ: Ghi nhận chênh lệch tỷ giá vào TK 413 (chênh lệch tỷ giá)
// Lãi: Nợ TK gốc/Có 413 — Lỗ: Nợ 413/Có TK gốc
// Hiển thị tổng hợp: lãi, lỗ, ảnh hưởng ròng
function executeRevaluation() {
    var id = getPeriodId(); if(!id) return;
    if(!confirm('Xác nhận thực hiện đánh giá lại ngoại tệ cho kỳ này?')) return;
    $.ajax({url:'/api/fx/revaluate/'+id, method:'POST',
        success:function(d) {
            var html = '<div class="fw-bold">Kết quả: <span class="text-'+(d.status==='adjusted'?'success':'info')+'">'+esc(d.status)+'</span></div>'+
                '<div>Lãi tỷ giá: <span class="text-success font-monospace">'+num(d.total_gain)+'</span></div>'+
                '<div>Lỗ tỷ giá: <span class="text-danger font-monospace">'+num(d.total_loss)+'</span></div>'+
                '<div>Ảnh hưởng ròng: <span class="font-monospace '+(d.net_fx_impact>=0?'text-success':'text-danger')+'">'+(d.net_fx_impact>=0?'+':'')+num(d.net_fx_impact)+'</span></div>';
            $('#summaryArea').html(html);
            loadReport();
        },
        error: function(x) {
            var m = 'Lỗi'; try{ m = JSON.parse(x.responseText).error; }catch(e){}
            $('#summaryArea').html('<div class="text-danger">'+esc(m)+'</div>');
        }
    });
}

$(document).ready(function(){loadPeriods();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
