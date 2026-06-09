/**
 * FormConfirm — Hộp thoại xác nhận dùng Bootstrap modal
 * ===
 * Thay thế hoàn toàn confirm()/alert()/prompt() native
 * Hỗ trợ: confirm (có/hủy), alert (thông báo), prompt (nhập liệu)
 * Hỗ trợ callback pattern: function(ok, value)
 * Hỗ trợ Enter để xác nhận, Escape để hủy
 * Backward-compatible: giữ nguyên confirmDelete()
 */

var FormConfirm = (function () {
    var $modal, $title, $body, $input, $cancel, $ok, $okText;
    var currentCallback = null;
    var isPrompt = false;

    function init() {
        if ($modal) return;
        var html =
            '<div class="modal fade" id="formConfirmModal" tabindex="-1" data-bs-backdrop="static">' +
                '<div class="modal-dialog modal-dialog-centered modal-sm">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<h6 class="modal-title" id="formConfirmTitle">Xác nhận</h6>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
                        '</div>' +
                        '<div class="modal-body">' +
                            '<p id="formConfirmMessage" style="margin:0;font-size:14px;color:#374151;"></p>' +
                            '<input type="text" id="formConfirmInput" class="form-control mt-2" style="display:none;">' +
                        '</div>' +
                        '<div class="modal-footer">' +
                            '<button type="button" class="btn btn-sm btn-secondary" id="formConfirmCancel" data-bs-dismiss="modal">Hủy</button>' +
                            '<button type="button" class="btn btn-sm btn-primary" id="formConfirmOk">Xác nhận</button>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>';
        $('body').append(html);
        $modal = $('#formConfirmModal');
        $title = $('#formConfirmTitle');
        $body = $('#formConfirmMessage');
        $input = $('#formConfirmInput');
        $cancel = $('#formConfirmCancel');
        $ok = $('#formConfirmOk');
        $okText = $ok.text();

        $modal.on('hidden.bs.modal', function () {
            currentCallback = null;
            $input.val('').hide();
        });

        $ok.on('click', function () {
            if (currentCallback) {
                var val = isPrompt ? $input.val() : true;
                currentCallback(val);
            }
            $modal.modal('hide');
        });

        $input.on('keydown', function (e) {
            if (e.key === 'Enter') { $ok.click(); }
        });

        $modal.on('keydown', function (e) {
            if (e.key === 'Escape') { $modal.modal('hide'); }
        });
    }

    function confirm(title, message, callback) {
        init();
        isPrompt = false;
        $title.text(title || 'Xác nhận');
        $body.text(message || 'Bạn có chắc chắn?');
        $input.hide().val('');
        $cancel.show();
        $ok.text('Xác nhận').removeClass('btn-danger').addClass('btn-primary');
        currentCallback = callback;
        $modal.modal('show');
    }

    function alert(title, message, callback) {
        init();
        isPrompt = false;
        $title.text(title || 'Thông báo');
        $body.text(message || '');
        $input.hide().val('');
        $cancel.hide();
        $ok.text('Đóng').removeClass('btn-danger').addClass('btn-primary');
        currentCallback = callback ? function () { callback(); } : null;
        $modal.modal('show');
    }

    function prompt(title, message, callback, defaultValue) {
        init();
        isPrompt = true;
        $title.text(title || 'Nhập dữ liệu');
        $body.text(message || '');
        $input.show().val(defaultValue || '');
        $cancel.show();
        $ok.text('Xác nhận').removeClass('btn-danger').addClass('btn-primary');
        currentCallback = callback;
        $modal.modal('show');
        $input.focus();
    }

    function confirmDelete(url, name, callback) {
        init();
        isPrompt = false;
        $title.text('Xóa');
        $body.text('Bạn có chắc chắn muốn xóa "' + (name || '') + '"?');
        $input.hide().val('');
        $cancel.show();
        $ok.text('Xóa').removeClass('btn-primary').addClass('btn-danger');
        currentCallback = function (ok) {
            if (!ok) return;
            $.ajax({ url: url, method: 'DELETE', headers: { 'X-CSRF-Token': csrf } })
                .done(function () {
                    FormToast.success('Đã xóa dữ liệu thành công.');
                    if (callback) callback();
                })
                .fail(function (x) {
                    var m = 'Có lỗi xảy ra khi xóa dữ liệu.';
                    try { m = JSON.parse(x.responseText).error; } catch (e) {}
                    FormToast.error(m);
                });
        };
        $modal.modal('show');
    }

    return { confirm: confirm, alert: alert, prompt: prompt, confirmDelete: confirmDelete };
})();
