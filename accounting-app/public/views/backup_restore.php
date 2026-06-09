<?php
$title = 'Sao lưu & Phục hồi';
$activeMenu = 'backup_restore';
ob_start();
?>
<div class="toolbar">
    <h5>Sao lưu & Phục hồi dữ liệu</h5>
    <button class="btn btn-primary btn-sm" id="btnBackup"><i class="bi bi-cloud-arrow-up"></i> Sao lưu ngay</button>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white py-2"><h6 class="mb-0"><i class="bi bi-cloud-arrow-up"></i> Sao lưu dữ liệu</h6></div>
            <div class="card-body p-3">
                <p class="text-muted small">Sao lưu toàn bộ dữ liệu kế toán bao gồm: chứng từ, sổ sách, danh mục, cấu hình hệ thống.</p>
                <button class="btn btn-primary" id="btnBackupMain"><i class="bi bi-cloud-arrow-up"></i> Sao lưu ngay</button>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-warning text-dark py-2"><h6 class="mb-0"><i class="bi bi-cloud-arrow-down"></i> Phục hồi dữ liệu</h6></div>
            <div class="card-body p-3">
                <p class="text-muted small">Phục hồi dữ liệu từ file sao lưu. Lưu ý: dữ liệu hiện tại sẽ bị ghi đè.</p>
                <input type="file" class="form-control mb-2" id="restoreFile" accept=".sql,.gz,.zip">
                <button class="btn btn-warning" id="btnRestore"><i class="bi bi-cloud-arrow-down"></i> Phục hồi</button>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white py-2"><h6 class="mb-0"><i class="bi bi-clock-history"></i> Lịch sử sao lưu</h6></div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Thời gian</th><th>Kích thước</th><th>Người thực hiện</th><th>Trạng thái</th><th></th></tr></thead>
                    <tbody id="backupHistory"><tr><td colspan="5" class="text-muted text-center">Chưa có bản sao lưu nào</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function loadBackups() {
    $.get('/api/system/backups', function(data) {
        var tbody = $('#backupHistory').empty();
        if (!data || !data.length) {
            tbody.html('<tr><td colspan="5" class="text-muted text-center">Chưa có bản sao lưu nào</td></tr>');
            return;
        }
        data.forEach(function(b) {
            tbody.append('<tr><td>' + esc(b.created_at || '') + '</td><td>' + esc(b.file_size || '') + '</td><td>' + esc(b.created_by || '') + '</td><td>' + statusBadge(b.status) + '</td><td><a href="' + esc(b.download_url || '#') + '" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a></td></tr>');
        });
    }).fail(function() {
        $('#backupHistory').html('<tr><td colspan="5" class="text-danger text-center">Lỗi tải lịch sử</td></tr>');
    });
}

$('#btnBackup, #btnBackupMain').click(function() {
    var btn = $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Đang sao lưu...');
    $.ajax({url:'/api/system/backup', method:'POST', headers:{'X-CSRF-Token':csrf},
        success:function() { showToast('Sao lưu hoàn tất','success'); loadBackups(); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); },
        complete:function() { btn.prop('disabled', false).html('<i class="bi bi-cloud-arrow-up"></i> Sao lưu ngay'); }
    });
});

$('#btnRestore').click(function() {
    var file = $('#restoreFile')[0].files[0];
    if (!file) { showToast('Chọn file sao lưu trước', 'error'); return; }
    if (!confirm('Phục hồi sẽ ghi đè dữ liệu hiện tại. Bạn có chắc chắn?')) return;
    var fd = new FormData();
    fd.append('backup', file);
    $.ajax({url:'/api/system/restore', method:'POST', data:fd, processData:false, contentType:false, headers:{'X-CSRF-Token':csrf},
        success:function() { showToast('Phục hồi hoàn tất','success'); loadBackups(); },
        error:function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
});

$(document).ready(function() { loadBackups(); });
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
