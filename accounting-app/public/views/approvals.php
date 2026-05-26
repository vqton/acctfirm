<?php // Màn hình: Danh sách phê duyệt chờ xử lý
// API: GET /api/approvals/pending, POST /api/approvals/{id}/{action}, GET /api/approvals/history/{id}
// Nghiệp vụ: Duyệt/từ chối chứng từ chờ phê duyệt, xem lịch sử phê duyệt
// Rủi ro: Duyệt bút toán sai sẽ ảnh hưởng đến số dư tài khoản — cần xác nhận trước khi approve
$title = 'Phê duyệt chờ xử lý'; $activeMenu = 'approvals'; ob_start(); ?>
<div class="toolbar">
    <div><h5>Phê duyệt chờ xử lý</h5></div>
</div>
<div class="card-table">
    <div class="card-header-x">
        <i class="bi bi-check-circle text-success"></i> <span id="pendingCount">0</span> chứng từ chờ phê duyệt
        <button class="btn btn-sm btn-outline-primary ms-auto" onclick="loadPending()"><i class="bi bi-arrow-clockwise"></i></button>
    </div>
    <table class="table" id="approvalTable">
        <thead><tr>
            <th>Số CT</th><th>Ngày</th><th>Diễn giải</th><th>Người tạo</th><th>Số tiền</th><th>Thao tác</th>
        </tr></thead>
        <tbody id="approvalBody"><tr><td colspan="6" class="text-muted text-center py-4">Đang tải...</td></tr></tbody>
    </table>
</div>

<!-- Approve/Reject Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
<div class="modal-dialog"><div class="modal-content">
<form id="actionForm">
<input type="hidden" id="actionType" name="action">
<input type="hidden" id="txnId" name="txnId">
<div class="modal-header"><h5 class="modal-title" id="actionModalLabel">Xác nhận</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
    <div class="mb-3">
        <label class="form-label" id="commentLabel">Lý do / Ghi chú</label>
        <textarea class="form-control" id="commentInput" rows="3" required></textarea>
    </div>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
    <button type="submit" class="btn btn-primary" id="actionSubmitBtn">Xác nhận</button>
</div>
</form>
</div></div>
</div>

<!-- History Modal -->
<div class="modal fade" id="historyModal" tabindex="-1">
<div class="modal-dialog modal-lg"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Lịch sử phê duyệt</h5>
<button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body" id="historyBody">Đang tải...</div>
</div></div>
</div>

<script>
function fmt(n) { return new Intl.NumberFormat('vi-VN').format(n) + ' ₫'; }

// Tải danh sách chứng từ chờ phê duyệt — GET /api/approvals/pending
// Mỗi dòng có 3 thao tác: Duyệt, Từ chối, Xem lịch sử
function loadPending() {
    $('#approvalBody').html('<tr><td colspan="6" class="text-muted text-center py-4">Đang tải...</td></tr>');
    $.get('/api/approvals/pending', function(res) {
        var data = res.data || res;
        $('#pendingCount').text(data.length);
        if (data.length === 0) {
            $('#approvalBody').html('<tr><td colspan="6" class="text-success text-center py-4"><i class="bi bi-check-circle"></i> Không có chứng từ chờ phê duyệt</td></tr>');
            return;
        }
        var html = '';
        data.forEach(function(t) {
            html += '<tr><td>' + (t.reference || '') + '</td><td>' + (t.date || '').substring(0,10) + '</td><td>' + (t.description || '') + '</td><td>' + (t.created_by || '') + '</td><td class="text-end">' + fmt(0) + '</td>'
                + '<td class="text-nowrap">'
                + '<button class="btn btn-sm btn-success me-1" onclick="openAction(\'' + t.id + '\',\'approve\')"><i class="bi bi-check-lg"></i> Duyệt</button>'
                + '<button class="btn btn-sm btn-danger me-1" onclick="openAction(\'' + t.id + '\',\'reject\')"><i class="bi bi-x-lg"></i> Từ chối</button>'
                + '<button class="btn btn-sm btn-outline-secondary" onclick="showHistory(\'' + t.id + '\')"><i class="bi bi-clock-history"></i></button>'
                + '</td></tr>';
        });
        $('#approvalBody').html(html);
    }).fail(function() {
        $('#approvalBody').html('<tr><td colspan="6" class="text-danger text-center py-4">Lỗi tải dữ liệu</td></tr>');
    });
}

// Mở modal xác nhận duyệt/từ chối — điều chỉnh label và validation theo action
// Duyệt: comment không bắt buộc
// Từ chối: comment bắt buộc (lý do từ chối)
function openAction(txnId, action) {
    $('#txnId').val(txnId);
    $('#actionType').val(action);
    if (action === 'approve') {
        $('#actionModalLabel').text('Xác nhận duyệt chứng từ');
        $('#commentLabel').text('Ghi chú (không bắt buộc)');
        $('#commentInput').prop('required', false);
        $('#actionSubmitBtn').text('Duyệt').removeClass('btn-danger').addClass('btn-success');
    } else {
        $('#actionModalLabel').text('Từ chối chứng từ');
        $('#commentLabel').text('Lý do từ chối (bắt buộc)');
        $('#commentInput').prop('required', true);
        $('#actionSubmitBtn').text('Từ chối').removeClass('btn-success').addClass('btn-danger');
    }
    $('#commentInput').val('');
    $('#actionModal').modal('show');
}

// Gửi yêu cầu duyệt/từ chối — POST /api/approvals/{id}/{action}
// RỦI RO: Approve sẽ ghi sổ bút toán — không thể undo
$('#actionForm').on('submit', function(e) {
    e.preventDefault();
    var txnId = $('#txnId').val();
    var action = $('#actionType').val();
    var comment = $('#commentInput').val();
    var url = '/api/approvals/' + txnId + '/' + action;
    var data = {};
    if (action === 'approve') data.comment = comment;
    else data.reason = comment || 'Không có lý do';

    $.ajax({ url: url, method: 'POST', contentType: 'application/json', data: JSON.stringify(data),
        headers: { 'X-CSRF-Token': $('meta[name=csrf-token]').attr('content') },
        success: function() { $('#actionModal').modal('hide'); loadPending(); },
        error: function(x) { alert('Lỗi: ' + (x.responseJSON?.error || x.statusText)); }
    });
});

// Xem lịch sử phê duyệt của chứng từ — GET /api/approvals/history/{id}
// Hiển thị audit trail: thời gian, hành động, người thực hiện, ghi chú
function showHistory(txnId) {
    $('#historyBody').html('Đang tải...');
    $('#historyModal').modal('show');
    $.get('/api/approvals/history/' + txnId, function(res) {
        var data = res.data || res;
        var html = '<table class="table table-sm"><thead><tr><th>Thời gian</th><th>Hành động</th><th>Người thực hiện</th><th>Ghi chú</th></tr></thead><tbody>';
        data.forEach(function(h) {
            var badge = {submit:'primary', approve:'success', reject:'danger', return:'warning'}[h.action] || 'secondary';
            html += '<tr><td>' + h.created_at + '</td><td><span class="badge bg-' + badge + '">' + h.action + '</span></td><td>' + (h.actor || '') + '</td><td>' + (h.comment || '') + '</td></tr>';
        });
        html += '</tbody></table>';
        $('#historyBody').html(html);
    }).fail(function() { $('#historyBody').html('<div class="text-danger">Lỗi tải lịch sử</div>'); });
}

$(document).ready(loadPending);
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
