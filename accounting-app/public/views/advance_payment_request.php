<?php // Màn hình: Giấy đề nghị tạm ứng — Mẫu số 03-TT theo Thông tư 99
// API: POST /api/advance-payment/draft, POST /api/advance-payment/{id}/submit, POST /api/advance-payment/{id}/approve,
//      POST /api/advance-payment/{id}/reject, POST /api/advance-payment/{id}/cancel, POST /api/advance-payment/{id}/paid,
//      GET /api/advance-payment/{id}, GET /api/advance-payment/list
// Nghiệp vụ: TK 141 — Tạm ứng cho nhân viên (theo TT 99, Mẫu 03-TT)
// Quy trình: Tạo nháp → Gửi duyệt → Duyệt → Lập phiếu chi → Xuất quỹ
// Hạch toán: Khi lập phiếu chi từ đề nghị này: Nợ 141 / Có 1111
$title = 'Giấy đề nghị tạm ứng'; $activeMenu = 'advance_payment'; ob_start(); ?>
<style>
    .tt99-form { max-width: 800px; margin: 0 auto; padding: 20px; }
    .tt99-form .form-header { text-align: center; margin-bottom: 20px; }
    .tt99-form .form-header h4 { font-weight: bold; margin-bottom: 4px; }
    .tt99-form .company-info { font-size: 13px; }
    .tt99-form .form-ref { font-size: 12px; color: #666; }
    .tt99-form .field-group { margin-bottom: 12px; }
    .tt99-form .field-group label { font-weight: 600; font-size: 13px; margin-bottom: 2px; }
    .tt99-form .field-group .form-control, .tt99-form .field-group .form-select { font-size: 14px; }
    .tt99-form .amount-display { font-size: 24px; font-weight: bold; color: #dc3545; text-align: center; padding: 10px; }
    .tt99-form .amount-words { font-size: 14px; font-style: italic; text-align: center; padding: 6px; background: #f8f9fa; border-radius: 4px; }
    .tt99-form .signature-section { margin-top: 30px; }
    .tt99-form .signature-col { text-align: center; padding: 10px; border: 1px dashed #dee2e6; border-radius: 4px; min-height: 100px; }
    .tt99-form .signature-col strong { display: block; margin-bottom: 4px; }
    .tt99-form .signature-col em { font-size: 11px; color: #888; }
    .tt99-form .signature-row { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; }
    @media (max-width: 768px) { .tt99-form .signature-row { grid-template-columns: 1fr 1fr; } }
    .tt99-form .toolbar-print { text-align: right; margin-bottom: 10px; }
    .tt99-form .badge-lg { font-size: 14px; padding: 6px 16px; }
    .tt99-form .card-print { border: 1px solid #dee2e6; border-radius: 8px; padding: 30px; background: #fff; }
    @media print {
        .sidebar, .topbar, .toolbar, .toolbar-top, .no-print { display: none !important; }
        .content-area { margin-left: 0 !important; padding: 0 !important; }
        .tt99-form .card-print { border: none; padding: 0; }
        .tt99-form .signature-col { border-color: #ccc; }
        .page-break { page-break-before: always; }
    }
</style>

<div class="toolbar toolbar-top no-print">
    <h5>Giấy đề nghị tạm ứng <span class="stats">(Mẫu 03-TT)</span></h5>
    <div>
        <button class="btn btn-primary btn-sm" id="btnNew"><i class="bi bi-plus-lg"></i> Tạo mới</button>
        <button class="btn btn-outline-success btn-sm ms-1" id="btnSubmit" style="display:none"><i class="bi bi-send"></i> Gửi duyệt</button>
        <button class="btn btn-outline-primary btn-sm ms-1" id="btnApprove" style="display:none"><i class="bi bi-check-lg"></i> Duyệt</button>
        <button class="btn btn-outline-danger btn-sm ms-1" id="btnReject" style="display:none"><i class="bi bi-x-lg"></i> Từ chối</button>
        <button class="btn btn-outline-warning btn-sm ms-1" id="btnCancel" style="display:none"><i class="bi bi-trash"></i> Hủy</button>
        <button class="btn btn-outline-danger btn-sm ms-1" id="btnPay" style="display:none"><i class="bi bi-cash"></i> Lập phiếu chi</button>
        <button class="btn btn-outline-info btn-sm ms-1" id="btnPrint"><i class="bi bi-printer"></i> In</button>
    </div>
</div>

<div class="tt99-form">
    <!-- Danh sách đề nghị -->
    <div id="listView">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
                <select class="form-select form-select-sm d-inline-block w-auto" id="statusFilter">
                    <option value="">Tất cả trạng thái</option>
                    <option value="draft">Nháp</option>
                    <option value="submitted">Chờ duyệt</option>
                    <option value="approved">Đã duyệt</option>
                    <option value="paid">Đã chi</option>
                    <option value="cancelled">Đã hủy</option>
                </select>
            </div>
            <span class="text-muted small" id="listCount"></span>
        </div>
        <div class="card-table"><table class="table table-hover">
            <thead><tr>
                <th>Số CT</th><th>Ngày</th><th>Người đề nghị</th><th>Bộ phận</th>
                <th class="text-end">Số tiền</th><th>Lý do</th><th>TT</th><th></th>
            </tr></thead>
            <tbody id="listBody"></tbody>
        </table></div>
    </div>

    <!-- Form chi tiết / tạo mới -->
    <div id="detailView" style="display:none">
        <div class="toolbar-print no-print">
            <button class="btn btn-sm btn-outline-secondary" id="btnBack"><i class="bi bi-arrow-left"></i> Danh sách</button>
        </div>

        <div class="card-print" id="printArea">
            <!-- Header: Company + Form reference -->
            <table style="width:100%; border-bottom: 2px solid #000; padding-bottom: 6px; margin-bottom: 16px;">
                <tr>
                    <td style="width:45%; font-size:13px;">
                        <strong>Đơn vị:</strong> <span id="dispCompany">CÔNG TY TNHH ABC</span><br>
                        <strong>Bộ phận:</strong> <span id="dispDepartment"></span>
                    </td>
                    <td style="width:55%; text-align:center; font-size:12px;">
                        <strong>Mẫu số 03 - TT</strong><br>
                        <em>(Kèm theo Thông tư số 99/2025/TT-BTC<br>
                        ngày 27 tháng 10 năm 2025 của Bộ trưởng Bộ Tài chính)</em>
                    </td>
                </tr>
            </table>

            <!-- Title -->
            <div class="form-header">
                <h4>GIẤY ĐỀ NGHỊ TẠM ỨNG</h4>
                <div class="company-info">
                    <em>Ngày <span id="dispDay"></span> tháng <span id="dispMonth"></span> năm <span id="dispYear"></span></em>
                </div>
                <div class="form-ref">
                    Số: <strong><span id="dispNumber">...</span></strong>
                </div>
            </div>

            <!-- Body fields -->
            <div class="field-group">
                <label>Kính gửi:</label>
                <div class="border-bottom ps-2 py-1" id="dispKinGui">Giám đốc Công ty</div>
            </div>

            <div class="field-group">
                <label>Tên tôi là:</label>
                <input type="text" class="form-control" id="fRequesterName" placeholder="Họ và tên người đề nghị tạm ứng">
                <div class="border-bottom ps-2 py-1 d-none" id="dispRequesterName"></div>
            </div>

            <div class="field-group">
                <label>Bộ phận / Địa chỉ:</label>
                <input type="text" class="form-control" id="fDepartment" placeholder="Bộ phận công tác">
                <div class="border-bottom ps-2 py-1 d-none" id="dispDepartment"></div>
            </div>

            <div class="field-group">
                <label>Đề nghị cho tạm ứng số tiền:</label>
                <div class="amount-display" id="dispAmount"></div>
                <input type="number" class="form-control d-none" id="fAmount" step="1000" min="1" placeholder="Nhập số tiền">
            </div>
            <div class="field-group">
                <label>Bằng chữ:</label>
                <div class="amount-words" id="dispAmountWords"></div>
                <input type="text" class="form-control d-none" id="fAmountWords" placeholder="Viết bằng chữ (để trống để tự động sinh)">
            </div>

            <div class="field-group">
                <label>Lý do tạm ứng:</label>
                <textarea class="form-control" id="fReason" rows="2" placeholder="Mục đích sử dụng tiền tạm ứng (VD: Đi công tác, mua văn phòng phẩm, tiếp khách...)"></textarea>
                <div class="border-bottom ps-2 py-1 d-none" id="dispReason"></div>
            </div>

            <div class="field-group">
                <label>Thời hạn thanh toán:</label>
                <input type="text" class="form-control" id="fRepaymentTerm" placeholder="Ví dụ: Ngày 30/06/2026 hoặc Trong vòng 15 ngày">
                <div class="border-bottom ps-2 py-1 d-none" id="dispRepaymentTerm"></div>
            </div>

            <div class="field-group">
                <label>Ghi chú:</label>
                <textarea class="form-control" id="fNotes" rows="1" placeholder="Ghi chú thêm (nếu có)"></textarea>
            </div>

            <!-- Signature section -->
            <div class="signature-section">
                <div class="signature-row">
                    <div class="signature-col">
                        <strong>Giám đốc</strong>
                        <em>(Ký, họ tên)</em>
                        <div style="height:50px;"></div>
                        <div style="border-top:1px solid #000; padding-top:4px; font-size:12px;" id="dispDirectorSign"></div>
                    </div>
                    <div class="signature-col">
                        <strong>Kế toán trưởng</strong>
                        <em>(Ký, họ tên)</em>
                        <div style="height:50px;"></div>
                        <div style="border-top:1px solid #000; padding-top:4px; font-size:12px;" id="dispAccountantSign"></div>
                    </div>
                    <div class="signature-col">
                        <strong>Phụ trách bộ phận</strong>
                        <em>(Ký, họ tên)</em>
                        <div style="height:50px;"></div>
                        <div style="border-top:1px solid #000; padding-top:4px; font-size:12px;" id="dispDeptHeadSign"></div>
                    </div>
                    <div class="signature-col">
                        <strong>Người đề nghị tạm ứng</strong>
                        <em>(Ký, họ tên)</em>
                        <div style="height:50px;"></div>
                        <div style="border-top:1px solid #000; padding-top:4px; font-size:12px;" id="dispRequesterSign"></div>
                    </div>
                </div>
            </div>

            <!-- Status badge + payment info -->
            <div class="text-center mt-3">
                <span class="badge badge-lg" id="dispStatusBadge"></span>
                <div id="paymentInfo" class="mt-2 small text-muted" style="display:none"></div>
            </div>
        </div><!-- /card-print -->

        <!-- Modal: Lập phiếu chi từ đề nghị tạm ứng -->
        <div class="modal fade" id="payModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h6 class="modal-title">Lập phiếu chi từ đề nghị tạm ứng</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <label class="form-label small">Số chứng từ</label>
                            <input type="text" class="form-control form-control-sm" id="payRequestNumber" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Người đề nghị</label>
                            <input type="text" class="form-control form-control-sm" id="payRequester" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Số tiền đề nghị</label>
                            <input type="text" class="form-control form-control-sm" id="payRequestAmount" readonly>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Chọn quỹ tạm ứng <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="payFundId">
                                <option value="">-- Chọn quỹ --</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Số tiền chi <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="number" class="form-control" id="payAmount" step="1000" min="1">
                                <span class="input-group-text">VNĐ</span>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Diễn giải</label>
                            <input type="text" class="form-control form-control-sm" id="payDescription" placeholder="Chi tạm ứng...">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>
                        <button class="btn btn-sm btn-danger" id="btnConfirmPay"><i class="bi bi-check-lg"></i> Xác nhận chi tiền</button>
                    </div>
                </div>
            </div>
        </div>
    </div><!-- /detailView -->
</div>

<script>
var csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
var currentId = null;
var isEditing = false;

// Helper: amount → VND string
function fmt(n) { return (n||0).toLocaleString('vi-VN', {style:'currency',currency:'VND'}); }
function esc(s) { var d=document.createElement('div'); d.textContent=s||''; return d.innerHTML; }

// Helper: tự động sinh chữ từ Helper API
function autoAmountWords(amount) {
    // Dùng endpoint /api/helpers/vn-words nếu có, fallback về text
    $.get('/api/helpers/vn-words?amount=' + amount, function(d) {
        $('#fAmountWords').val(d.words || '');
    }).fail(function() {
        // Fallback
        $('#fAmountWords').val('');
    });
}

// Load danh sách
function loadList(status) {
    var url = '/api/advance-payment/list';
    if (status) url += '?status=' + encodeURIComponent(status);
    $.get(url, function(data) {
        var tbody = $('#listBody');
        tbody.empty();
        if (!data || data.length === 0) {
            tbody.append('<tr><td colspan="8" class="text-center text-muted py-3">Chưa có đề nghị tạm ứng nào</td></tr>');
            $('#listCount').text('0');
            return;
        }
        $('#listCount').text(data.length + ' đề nghị');
        data.forEach(function(r) {
            var badge = r.status === 'draft' ? 'badge-warning' : (r.status === 'submitted' ? 'badge-info' :
                r.status === 'approved' ? 'badge-success' : (r.status === 'paid' ? 'badge-active' : 'badge-inactive'));
            var statusMap = {draft:'Nháp',submitted:'Chờ duyệt',approved:'Đã duyệt',paid:'Đã chi',cancelled:'Đã hủy'};
            tbody.append('<tr style="cursor:pointer" onclick="openDetail(\''+r.id+'\')">' +
                '<td>'+esc(r.request_number)+'</td>' +
                '<td style="font-size:12px">'+esc(r.request_date)+'</td>' +
                '<td>'+esc(r.requester_name)+'</td>' +
                '<td>'+esc(r.requester_department||'')+'</td>' +
                '<td class="text-end font-monospace">'+fmt(r.amount)+'</td>' +
                '<td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(r.reason||'')+'</td>' +
                '<td><span class="badge-status '+badge+'">'+(statusMap[r.status]||r.status)+'</span></td>' +
                '<td><button class="btn btn-sm btn-outline-primary" onclick="event.stopPropagation();openDetail(\''+r.id+'\')"><i class="bi bi-eye"></i></button></td>' +
            '</tr>');
        });
    });
}

// Mở chi tiết / tạo mới
function openDetail(id) {
    currentId = id;
    isEditing = false;
    $('#listView').hide();
    $('#detailView').show();
    $('#printArea input, #printArea textarea').addClass('d-none');
    $('#printArea .border-bottom').removeClass('d-none');

    $.get('/api/advance-payment/' + id, function(data) {
        var d = data.data || data;
        $('#dispNumber').text(d.request_number);
        if (d.request_date) {
            var parts = d.request_date.split('-');
            $('#dispDay').text(parseInt(parts[2]) || '..');
            $('#dispMonth').text(parseInt(parts[1]) || '..');
            $('#dispYear').text(parts[0] || '....');
        }
        $('#dispKinGui').text('Giám đốc Công ty');
        $('#dispRequesterName').text(d.requester_name);
        $('#dispDepartment').text(d.requester_department||'');
        $('#dispAmount').text(fmt(d.amount));
        $('#dispAmountWords').text(d.amount_in_words||'');
        $('#dispReason').text(d.reason||'');
        $('#dispRepaymentTerm').text(d.repayment_term||'');
        $('#dispRequesterSign').text(d.requester_name);

        // Signature: hiển thị tên đã ký dựa trên status
        var sigHtml = d.status === 'approved' || d.status === 'paid' ? '<span class="text-success">✓ Đã duyệt</span>' : '';
        $('#dispDirectorSign').html(d.status === 'approved' || d.status === 'paid' ? sigHtml : '');
        $('#dispAccountantSign').html(d.status === 'submitted' || d.status === 'approved' || d.status === 'paid' ? '<span class="text-success">✓ Đã xem xét</span>' : '');
        $('#dispDeptHeadSign').html(d.status !== 'draft' ? '<span>'+esc(d.requester_department||'')+'</span>' : '');

        var statusMap = {draft:'Nháp',submitted:'Chờ duyệt',approved:'Đã duyệt',paid:'Đã chi',cancelled:'Đã hủy'};
        var badgeClass = d.status === 'draft' ? 'bg-warning' : (d.status === 'submitted' ? 'bg-info' :
            d.status === 'approved' ? 'bg-success' : (d.status === 'paid' ? 'bg-primary' : 'bg-secondary'));
        $('#dispStatusBadge').text(statusMap[d.status]||d.status).removeClass().addClass('badge badge-lg ' + badgeClass);

        // Toolbar buttons
        $('#btnApprove,#btnReject,#btnCancel,#btnSubmit,#btnPay').hide();
        if (d.status === 'draft') {
            $('#btnSubmit').show();
            $('#btnCancel').show();
        }
        if (d.status === 'submitted') {
            $('#btnApprove').show();
            $('#btnReject').show();
        }
        if (d.status === 'approved') {
            $('#btnPay').show();
        }
        if (d.status === 'paid') {
            $('#paymentInfo').show().html('<i class="bi bi-check-circle text-success"></i> Đã lập phiếu chi. <a href="/chi/quy-tien-mat" target="_blank">Xem phiếu chi</a>');
        } else {
            $('#paymentInfo').hide();
        }
    }).fail(function(x) {
        showToast('Không tìm thấy đề nghị tạm ứng', 'error');
        backToList();
    });
}

// Tạo mới
function newForm() {
    currentId = null;
    isEditing = true;
    $('#listView').hide();
    $('#detailView').show();
    $('#printArea input, #printArea textarea').removeClass('d-none').val('');
    $('#printArea .border-bottom').addClass('d-none');
    $('#dispNumber').text('...');
    $('#dispStatusBadge').text('MỚI').removeClass().addClass('badge badge-lg bg-warning');
    $('#dispAmount').text('').hide();
    $('#dispAmountWords').text('');
    $('#fAmount').removeClass('d-none').val('');
    $('#fAmountWords').removeClass('d-none').val('');
    $('#fRequesterName').removeClass('d-none').val('');
    $('#fDepartment').removeClass('d-none').val('');
    $('#fReason').removeClass('d-none').val('');
    $('#fRepaymentTerm').removeClass('d-none').val('');
    $('#fNotes').removeClass('d-none').val('');
    $('#btnApprove,#btnReject,#btnCancel,#btnSubmit').hide();

    // Ngày hiện tại
    var now = new Date();
    $('#dispDay').text(now.getDate());
    $('#dispMonth').text(now.getMonth()+1);
    $('#dispYear').text(now.getFullYear());

    // Reset signature
    $('#dispRequesterSign').text('');
    $('#dispDirectorSign').html('');
    $('#dispAccountantSign').html('');
    $('#dispDeptHeadSign').html('');
}

function backToList() {
    currentId = null;
    isEditing = false;
    $('#detailView').hide();
    $('#listView').show();
    loadList($('#statusFilter').val());
}

// Lưu đề nghị mới
function saveNew() {
    var name = $('#fRequesterName').val().trim();
    var amount = parseFloat($('#fAmount').val());
    if (!name) { showToast('Vui lòng nhập họ tên người đề nghị', 'warning'); return; }
    if (!amount || amount <= 0) { showToast('Vui lòng nhập số tiền tạm ứng', 'warning'); return; }
    var words = $('#fAmountWords').val().trim();
    if (!words) { words = ''; }
    var data = {
        requester_name: name,
        requester_department: $('#fDepartment').val().trim(),
        amount: amount,
        amount_in_words: words,
        reason: $('#fReason').val().trim(),
        repayment_term: $('#fRepaymentTerm').val().trim(),
        notes: $('#fNotes').val().trim()
    };
    $.ajax({
        url: '/api/advance-payment/draft',
        method: 'POST',
        contentType: 'application/json',
        headers: {'X-CSRF-Token': csrf},
        data: JSON.stringify(data),
        success: function(r) {
            showToast('Đã tạo đề nghị tạm ứng thành công', 'success');
            openDetail(r.id || r.request_number);
        },
        error: function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
}

// Submit duyệt
function submitRequest() {
    if (!currentId) return;
    if (!confirm('Gửi duyệt đề nghị tạm ứng này?')) return;
    $.ajax({
        url: '/api/advance-payment/' + currentId + '/submit',
        method: 'POST',
        headers: {'X-CSRF-Token': csrf},
        success: function() { showToast('Đã gửi duyệt thành công','success'); openDetail(currentId); },
        error: function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
}

// Duyệt
function approveRequest() {
    if (!currentId) return;
    if (!confirm('Duyệt đề nghị tạm ứng này?')) return;
    $.ajax({
        url: '/api/advance-payment/' + currentId + '/approve',
        method: 'POST',
        headers: {'X-CSRF-Token': csrf},
        success: function() { showToast('Đã duyệt thành công','success'); openDetail(currentId); },
        error: function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
}

// Từ chối
function rejectRequest() {
    if (!currentId) return;
    var reason = prompt('Nhập lý do từ chối:');
    if (reason === null) return;
    $.ajax({
        url: '/api/advance-payment/' + currentId + '/reject',
        method: 'POST',
        contentType: 'application/json',
        headers: {'X-CSRF-Token': csrf},
        data: JSON.stringify({reason: reason || ''}),
        success: function() { showToast('Đã từ chối đề nghị','success'); openDetail(currentId); },
        error: function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
}

// Hủy
function cancelRequest() {
    if (!currentId) return;
    if (!confirm('Hủy đề nghị tạm ứng này?')) return;
    $.ajax({
        url: '/api/advance-payment/' + currentId + '/cancel',
        method: 'POST',
        headers: {'X-CSRF-Token': csrf},
        success: function() { showToast('Đã hủy đề nghị','success'); openDetail(currentId); },
        error: function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
}

// Mở modal lập phiếu chi
function openPayModal() {
    if (!currentId) return;
    $.get('/api/advance-payment/' + currentId, function(data) {
        var d = data.data || data;
        $('#payRequestNumber').val(d.request_number);
        $('#payRequester').val(d.requester_name);
        $('#payRequestAmount').val(fmt(d.amount));
        $('#payAmount').val(d.amount);
        $('#payDescription').val('Chi tạm ứng cho ' + d.requester_name + ' - ' + d.reason);

        // Tải danh sách quỹ tạm ứng
        $.get('/api/petty-cash/funds', function(funds) {
            var sel = $('#payFundId').empty().append('<option value="">-- Chọn quỹ --</option>');
            (funds || []).forEach(function(f) {
                if (f.status === 'active') {
                    sel.append('<option value="'+f.id+'" data-balance="'+f.current_balance+'">'+
                        esc(f.fund_name)+' (SD: '+fmt(f.current_balance)+' / HM: '+fmt(f.imprest_amount)+')</option>');
                }
            });
        });
        $('#payModal').modal('show');
    });
}

// Xác nhận chi tiền
function confirmPay() {
    var fundId = $('#payFundId').val();
    var amount = parseFloat($('#payAmount').val());
    if (!fundId) { showToast('Vui lòng chọn quỹ tạm ứng', 'warning'); return; }
    if (!amount || amount <= 0) { showToast('Vui lòng nhập số tiền chi', 'warning'); return; }

    // Kiểm tra số dư quỹ
    var opt = $('#payFundId option:selected');
    var balance = parseFloat(opt.data('balance') || 0);
    if (amount > balance) { showToast('Quỹ không đủ số dư (SD: '+fmt(balance)+')', 'warning'); return; }

    if (!confirm('Xác nhận chi ' + fmt(amount) + ' từ quỹ "' + opt.text() + '"?')) return;

    $.ajax({
        url: '/api/petty-cash/disburse-from-request',
        method: 'POST',
        contentType: 'application/json',
        headers: {'X-CSRF-Token': csrf},
        data: JSON.stringify({
            fund_id: fundId,
            request_id: currentId,
            request_number: $('#payRequestNumber').val(),
            amount: amount,
            description: $('#payDescription').val()
        }),
        success: function() {
            $('#payModal').modal('hide');
            showToast('Đã lập phiếu chi thành công', 'success');
            openDetail(currentId);
        },
        error: function(x) { var m='Lỗi'; try{m=JSON.parse(x.responseText).error;}catch(e){} showToast(m,'error'); }
    });
}

// In
function printForm() { window.print(); }

// Init
$(document).ready(function() {
    loadList('');
    $('#statusFilter').change(function() { loadList($(this).val()); });
    $('#btnNew').click(newForm);
    $('#btnBack').click(backToList);
    $('#btnSubmit').click(submitRequest);
    $('#btnApprove').click(approveRequest);
    $('#btnReject').click(rejectRequest);
    $('#btnCancel').click(cancelRequest);
    $('#btnPay').click(openPayModal);
    $('#btnConfirmPay').click(confirmPay);
    $('#btnPrint').click(printForm);

    // Tự động sinh chữ khi nhập số tiền
    var amountTimer;
    $('#fAmount').on('input', function() {
        clearTimeout(amountTimer);
        amountTimer = setTimeout(function() {
            var amt = parseFloat($('#fAmount').val());
            if (amt > 0) autoAmountWords(amt);
        }, 500);
    });

    // Lưu (Ctrl+Enter hoặc dùng nút)
    $(document).on('keydown', function(e) {
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter' && isEditing) {
            e.preventDefault();
            saveNew();
        }
    });
});
</script>
<?php $content = ob_get_clean(); require __DIR__ . '/layout.php'; ?>
