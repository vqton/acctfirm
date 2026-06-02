<?php $content = ob_start(); ?>
<div class="container-fluid">
    <div class="toolbar">
        <h5><i class="bi bi-file-earmark-text me-2"></i>Thuyết minh Báo cáo tài chính (BC09)</h5>
        <div class="d-flex gap-2">
            <select class="form-select form-select-sm" id="periodSelect" style="width:auto;">
                <option value="">Chọn kỳ kế toán...</option>
            </select>
            <button class="btn btn-sm btn-primary" id="btnGenerate"><i class="bi bi-magic me-1"></i>Sinh tự động</button>
            <button class="btn btn-sm btn-success" id="btnValidate"><i class="bi bi-check-circle me-1"></i>Kiểm tra</button>
            <button class="btn btn-sm btn-secondary" id="btnExport"><i class="bi bi-download me-1"></i>Xuất Excel</button>
        </div>
    </div>

    <ul class="nav nav-tabs" id="bc09Tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabIV" type="button">IV. Chính sách kế toán</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabV" type="button">V. Thông tin bổ sung BC01</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabVI" type="button">VI. Doanh thu, chi phí</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabVII" type="button">VII. Các khoản mục ngoài BC</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabVIII" type="button">VIII. Giao dịch bên liên quan</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabIX" type="button">IX. Nợ tiềm tàng</button>
        </li>
    </ul>

    <div class="tab-content mt-3" id="bc09TabContent">
        <!-- Section IV: Chính sách kế toán -->
        <div class="tab-pane fade show active" id="tabIV">
            <div class="card shadow-sm">
                <div class="card-body">
                    <p class="text-muted mb-3">Các chính sách kế toán áp dụng theo Thông tư 99/2025/TT-BTC.</p>
                    <div id="policyContent">
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-info-circle me-2"></i>Vui lòng chọn kỳ kế toán để xem chính sách.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section V: Thông tin bổ sung BC01 (auto-calc) -->
        <div class="tab-pane fade" id="tabV">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span><strong>V.</strong> Thông tin bổ sung cho Bảng Cân đối kế toán</span>
                    <span class="badge bg-info">Tự động từ số dư tài khoản</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="sectionVTable">
                            <thead>
                                <tr>
                                    <th style="width:80px">Mã số</th>
                                    <th>Chỉ tiêu</th>
                                    <th style="width:180px">Số đầu năm</th>
                                    <th style="width:180px">Số cuối năm</th>
                                    <th style="width:100px">Loại</th>
                                </tr>
                            </thead>
                            <tbody id="sectionVBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section VI: Doanh thu, chi phí -->
        <div class="tab-pane fade" id="tabVI">
            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <span><strong>VI.</strong> Doanh thu, chi phí phát sinh trong kỳ</span>
                    <span class="badge bg-info">Tự động từ số phát sinh</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="sectionVITable">
                            <thead>
                                <tr>
                                    <th style="width:80px">Mã số</th>
                                    <th>Chỉ tiêu</th>
                                    <th style="width:180px">Số đầu năm</th>
                                    <th style="width:180px">Số cuối năm</th>
                                    <th style="width:100px">Loại</th>
                                </tr>
                            </thead>
                            <tbody id="sectionVIBody">
                                <tr><td colspan="5" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section VII: Các khoản mục ngoài BC -->
        <div class="tab-pane fade" id="tabVII">
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <span><strong>VII.</strong> Các khoản mục ngoài Bảng Cân đối kế toán</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="sectionVIITable">
                            <thead>
                                <tr>
                                    <th style="width:80px">Mã số</th>
                                    <th>Chỉ tiêu</th>
                                    <th style="width:180px">Số đầu năm</th>
                                    <th style="width:180px">Số cuối năm</th>
                                    <th style="width:300px">Thuyết minh</th>
                                    <th style="width:100px">Loại</th>
                                </tr>
                            </thead>
                            <tbody id="sectionVIIBody">
                                <tr><td colspan="6" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sections VIII, IX -->
        <div class="tab-pane fade" id="tabVIII">
            <div class="card shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-pencil-square d-block mb-2" style="font-size:2rem;"></i>
                    <p>Mục VIII — Giao dịch với bên liên quan. Nhập tay.</p>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="tabIX">
            <div class="card shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-pencil-square d-block mb-2" style="font-size:2rem;"></i>
                    <p>Mục IX — Nợ tiềm tàng và cam kết. Nhập tay.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Validation Results Panel -->
    <div id="validationPanel" class="mt-3" style="display:none;">
        <div class="card shadow-sm border-warning">
            <div class="card-header bg-warning bg-opacity-10 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-shield-check me-2 text-warning"></i>Kết quả kiểm tra chéo</span>
                <button class="btn-close btn-sm" onclick="document.getElementById('validationPanel').style.display='none'"></button>
            </div>
            <div class="card-body" id="validationBody">
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    loadPeriods();

    function loadPeriods() {
        $.get('/api/periods', function(res) {
            var sel = $('#periodSelect');
            sel.empty().append('<option value="">Chọn kỳ kế toán...</option>');
            (res.data || res || []).forEach(function(p) {
                sel.append('<option value="'+p.id+'">'+p.name+' ('+p.period_code+')</option>');
            });
        });
    }

    $('#periodSelect').change(function() {
        var id = $(this).val();
        if (id) {
            loadReport(id);
            loadPolicies();
        }
    });

    $('#btnGenerate').click(function() {
        var id = $('#periodSelect').val();
        if (!id) { showToast('Vui lòng chọn kỳ kế toán','error'); return; }
        $.ajax({
            url: '/api/fs/bc09/'+id+'/generate',
            method: 'POST',
            success: function(res) {
                showToast('Đã sinh tự động '+res.indicators_generated+' chỉ tiêu','success');
                loadReport(id);
            },
            error: function(xhr) {
                var msg = xhr.responseJSON ? xhr.responseJSON.error : 'Lỗi máy chủ';
                showToast(msg,'error');
            }
        });
    });

    $('#btnValidate').click(function() {
        var id = $('#periodSelect').val();
        if (!id) { showToast('Vui lòng chọn kỳ kế toán','error'); return; }
        $.post('/api/fs/bc09/'+id+'/validate', function(res) {
            var html = '';
            if (res.errors && res.errors.length > 0) {
                html += '<div class="alert alert-danger mb-2"><strong>Lỗi:</strong><ul>';
                res.errors.forEach(function(e) { html += '<li>'+esc(e)+'</li>'; });
                html += '</ul></div>';
            }
            if (res.warnings && res.warnings.length > 0) {
                html += '<div class="alert alert-warning mb-2"><strong>Cảnh báo:</strong><ul>';
                res.warnings.forEach(function(w) { html += '<li>'+esc(w)+'</li>'; });
                html += '</ul></div>';
            }
            if (!html) {
                html = '<div class="alert alert-success mb-0"><i class="bi bi-check-circle me-2"></i>Tất cả chỉ tiêu đã khớp. Không có lỗi.</div>';
            }
            html += '<div class="small text-muted">Tổng số kiểm tra: '+res.total_checks+'</div>';
            $('#validationBody').html(html);
            $('#validationPanel').show();
        }).fail(function(xhr) {
            showToast(xhr.responseJSON ? xhr.responseJSON.error : 'Lỗi kiểm tra','error');
        });
    });

    $('#btnExport').click(function() {
        var id = $('#periodSelect').val();
        if (!id) { showToast('Vui lòng chọn kỳ kế toán','error'); return; }
        window.open('/api/export/csv/bc09/'+id, '_blank');
    });

    function loadReport(id) {
        $.get('/api/fs/bc09/'+id, function(res) {
            var sections = res.sections || {};

            // Section V
            var vItems = sections['V'] || [];
            renderTable('sectionVBody', vItems, false);

            // Section VI
            var viItems = sections['VI'] || [];
            renderTable('sectionVIBody', viItems, false);

            // Section VII
            var viiItems = sections['VII'] || [];
            renderTable('sectionVIIBody', viiItems, true);
        }).fail(function() {
            showToast('Không thể tải dữ liệu BC09','error');
        });
    }

    function loadPolicies() {
        $.get('/api/fs/bc09/policies', function(res) {
            var policies = res.policies || [];
            var html = '<div class="accordion" id="policyAccordion">';
            policies.forEach(function(p, idx) {
                html += '<div class="accordion-item">';
                html += '<h2 class="accordion-header"><button class="accordion-button '+(idx>0?'collapsed':'')+'" data-bs-toggle="collapse" data-bs-target="#policy'+idx+'">';
                html += '<strong>'+esc(p.code)+'</strong>&nbsp;'+esc(p.name);
                html += '</button></h2>';
                html += '<div id="policy'+idx+'" class="accordion-collapse collapse '+(idx===0?'show':'')+'" data-bs-parent="#policyAccordion">';
                html += '<div class="accordion-body small">'+esc(p.default)+'</div>';
                html += '</div></div>';
            });
            html += '</div>';
            $('#policyContent').html(html);
        });
    }

    function renderTable(tbodyId, items, showNote) {
        var tbody = $('#'+tbodyId);
        if (!items || items.length === 0) {
            tbody.html('<tr><td colspan="'+(showNote?6:5)+'" class="text-center text-muted py-4">Chưa có dữ liệu</td></tr>');
            return;
        }
        var html = '';
        items.forEach(function(item) {
            var label = item.is_auto_calc
                ? '<span class="badge bg-info" title="Tự động tính">Tự động</span>'
                : '<span class="badge bg-warning text-dark" title="Nhập tay">Thủ công</span>';
            var startVal = Number(item.year_start || 0).toLocaleString('vi-VN');
            var endVal = Number(item.year_end || 0).toLocaleString('vi-VN');
            html += '<tr>';
            html += '<td class="fw-medium">'+esc(item.indicator_code)+'</td>';
            html += '<td>'+esc(item.indicator_name)+'</td>';

            if (item.is_auto_calc) {
                html += '<td class="text-end fw-medium">'+startVal+'</td>';
                html += '<td class="text-end fw-medium">'+endVal+'</td>';
            } else {
                html += '<td><input type="text" class="form-control form-control-sm text-end manual-input" data-code="'+esc(item.indicator_code)+'" data-field="year_start" value="'+startVal+'"></td>';
                html += '<td><input type="text" class="form-control form-control-sm text-end manual-input" data-code="'+esc(item.indicator_code)+'" data-field="year_end" value="'+endVal+'"></td>';
            }

            html += '<td>'+label+'</td>';

            if (showNote) {
                var note = item.note_text ? esc(item.note_text) : '';
                html += '<td><input type="text" class="form-control form-control-sm manual-input" data-code="'+esc(item.indicator_code)+'" data-field="note_text" value="'+note+'"></td>';
            }

            html += '</tr>';
        });
        tbody.html(html);

        // Auto-save on input change
        tbody.find('.manual-input').on('change', function() {
            var code = $(this).data('code');
            var periodId = $('#periodSelect').val();
            var field = $(this).data('field');
            var allInputs = tbody.find('.manual-input[data-code="'+code+'"]');
            var data = { year_start: 0, year_end: 0, note_text: '' };
            allInputs.each(function() {
                var f = $(this).data('field');
                var val = $(this).val().replace(/\./g,'');
                if (f === 'year_start') data.year_start = parseFloat(val) || 0;
                else if (f === 'year_end') data.year_end = parseFloat(val) || 0;
                else if (f === 'note_text') data.note_text = val;
            });
            $.ajax({
                url: '/api/fs/bc09/'+periodId+'/indicator/'+code,
                method: 'PUT',
                contentType: 'application/json',
                data: JSON.stringify(data),
                success: function() { showToast('Đã lưu chỉ tiêu '+code,'success'); },
                error: function(xhr) { showToast(xhr.responseJSON ? xhr.responseJSON.error : 'Lỗi lưu','error'); }
            });
        });
    }
});
</script>
<?php
$content = ob_get_clean();
$title = 'Thuyết minh BCTC (BC09)';
$activeMenu = 'fs_bc09';
require __DIR__ . '/layout.php';
