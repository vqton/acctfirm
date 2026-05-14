<?php $title = 'Quản lý kỳ kế toán'; $activeMenu = 'periods'; ob_start(); ?>
<div class="toolbar">
    <h5>Quản lý kỳ kế toán</h5>
    <div><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#periodModal"><i class="bi bi-plus-lg"></i> Tạo kỳ</button></div>
</div>
<div class="card-table"><table class="table table-hover">
    <thead><tr><th>Mã kỳ</th><th>Tên</th><th>Loại</th><th>Từ</th><th>Đến</th><th>Trạng thái</th><th>Mở lại</th><th></th></tr></thead>
    <tbody id="dataBody"></tbody>
</table></div>

<div class="modal fade" id="periodModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
<form id="periodForm">
<div class="modal-header"><h5 class="modal-title">Tạo kỳ kế toán</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3"><label>Loại kỳ</label>
        <select class="form-select" id="periodType">
            <option value="month">Tháng</option>
            <option value="quarter">Quý</option>
            <option value="year">Năm</option>
        </select>
    </div>
    <div class="mb-3"><label>Mã kỳ</label><input type="text" class="form-control" id="periodCode" placeholder="VD: 2026-05" required></div>
    <div class="mb-3"><label>Tên kỳ</label><input type="text" class="form-control" id="periodName" placeholder="VD: Tháng 5/2026" required></div>
    <div class="mb-3"><label>Ngày bắt đầu</label><input type="date" class="form-control" id="periodStart" required></div>
    <div class="mb-3"><label>Ngày kết thúc</label><input type="date" class="form-control" id="periodEnd" required></div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-sm btn-primary">Tạo</button>
</div>
</form>
</div></div></div>

<script>
function loadData() {
    $.get('/api/periods', function(data) {
        var tbody=$('#dataBody'); tbody.empty();
        if(data.length===0){tbody.append('<tr><td colspan="8" class="text-center text-muted py-4">Chưa có kỳ kế toán</td></tr>');return;}
        data.forEach(function(p){
            var badge = p.status==='open'?'badge-active':'<span class="badge-status badge-inactive">'+esc(p.status)+'</span>';
            var actions = '';
            if(p.status==='open'){
                actions += '<button class="btn btn-sm btn-outline-success me-1" onclick="closePeriod('+p.id+')"><i class="bi bi-lock"></i> Khóa sổ</button>';
            } else {
                actions += '<button class="btn btn-sm btn-outline-warning me-1" onclick="reopenPeriod('+p.id+')"><i class="bi bi-unlock"></i> Mở lại</button>';
            }
            tbody.append('<tr><td>'+esc(p.period_code)+'</td><td>'+esc(p.name)+'</td><td>'+esc(p.period_type)+'</td><td>'+esc(p.start_date)+'</td><td>'+esc(p.end_date)+'</td><td>'+badge+'</td><td>'+(p.re_open_count||'0')+'</td><td>'+actions+'</td></tr>');
        });
    });
}

function closePeriod(id) {
    if(!confirm('Khóa sổ kỳ kế toán này? Không thể ghi nhận giao dịch mới trong kỳ đã khóa.'))return;
    // First execute closing entries
    $.ajax({url:'/api/periods/'+id+'/execute-closing',method:'POST',
        success:function(){
            // Then close the period
            $.ajax({url:'/api/periods/'+id+'/close',method:'POST',
                success:function(){showToast('Đã khóa sổ kỳ kế toán','success');loadData();},
                error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
            });
        },
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
}

function reopenPeriod(id) {
    if(!confirm('Mở lại kỳ kế toán này? Chỉ thực hiện khi có điều chỉnh trọng yếu.'))return;
    $.ajax({url:'/api/periods/'+id+'/reopen',method:'POST',
        success:function(){showToast('Đã mở lại kỳ kế toán','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
}

$('#periodForm').submit(function(e){e.preventDefault();
    $.ajax({url:'/api/periods',method:'POST',contentType:'application/json',
        data:JSON.stringify({
            period_type:$('#periodType').val(),
            period_code:$('#periodCode').val(),
            name:$('#periodName').val(),
            start_date:$('#periodStart').val(),
            end_date:$('#periodEnd').val()
        }),
        success:function(){$('#periodModal').modal('hide');$('#periodForm')[0].reset();showToast('Tạo kỳ thành công','success');loadData();},
        error:function(x){var m='Lỗi';try{m=JSON.parse(x.responseText).error;}catch(e){}showToast(m,'error');}
    });
});

$(document).ready(function(){loadData();});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
