/**
 * FormModal — Hộp thoại Modal dùng chung
 * ===
 * Hỗ trợ: sm/md/lg/xl, body HTML hoặc URL tải nội dung
 * Enter để submit, Escape để đóng
 * Callbacks: onSave, onShow, onHide
 * Tự động focus vào input đầu tiên
 * Loading state khi save
 * Backward-compatible với modal có sẵn
 */

var FormModal = (function () {
    var counter = 0;

    function create(opts) {
        opts = opts || {};
        var id = opts.id || 'formModal_' + (++counter);
        var size = opts.size || 'md';
        var sizeClass = size === 'sm' ? 'modal-sm' : size === 'lg' ? 'modal-lg' : size === 'xl' ? 'modal-xl' : '';
        var title = opts.title || '';
        var bodyHtml = opts.body || '';
        var footerHtml = opts.footer;
        var hasFooter = footerHtml !== undefined;
        var backdrop = opts.backdrop !== undefined ? opts.backdrop : 'static';
        var keyboard = opts.keyboard !== undefined ? opts.keyboard : true;

        // Default footer nếu không truyền
        if (footerHtml === undefined) {
            footerHtml =
                '<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>' +
                '<button type="button" class="btn btn-sm btn-primary" id="' + id + '_save">' +
                    (opts.saveText || 'Lưu') +
                '</button>';
            hasFooter = true;
        }

        var html =
            '<div class="modal fade" id="' + id + '" tabindex="-1" data-bs-backdrop="' + backdrop + '" data-bs-keyboard="' + keyboard + '">' +
                '<div class="modal-dialog modal-dialog-centered ' + sizeClass + '">' +
                    '<div class="modal-content">' +
                        '<div class="modal-header">' +
                            '<h6 class="modal-title">' + title + '</h6>' +
                            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
                        '</div>' +
                        '<div class="modal-body" id="' + id + '_body">' +
                            bodyHtml +
                        '</div>' +
                        (hasFooter
                            ? '<div class="modal-footer" id="' + id + '_footer">' + footerHtml + '</div>'
                            : '') +
                    '</div>' +
                '</div>' +
            '</div>';

        // Remove existing
        $('#' + id).remove();
        $('body').append(html);

        var $modal = $('#' + id);
        var $save = $('#' + id + '_save');
        var $body = $('#' + id + '_body');

        // Auto-focus input đầu tiên
        $modal.on('shown.bs.modal', function () {
            $body.find('input:visible,textarea:visible,select:visible').first().focus();
            if (opts.onShow) opts.onShow($modal);
        });

        $modal.on('hidden.bs.modal', function () {
            $modal.remove();
            if (opts.onHide) opts.onHide();
        });

        // Enter để save
        $modal.on('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey && !$(e.target).is('textarea')) {
                e.preventDefault();
                $save.click();
            }
        });

        $save.on('click', function () {
            if (opts.onSave) {
                var btn = this;
                var origHtml = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang xử lý...';
                var doneFn = function () {
                    btn.disabled = false;
                    btn.innerHTML = origHtml;
                };
                var result = opts.onSave($modal, doneFn);
                if (result === false) {
                    doneFn(); // validation failed, re-enable immediately
                } else if (result && result.always) {
                    result.always(doneFn); // promise-based, re-enable on complete
                } else if (result === undefined) {
                    doneFn(); // sync save, re-enable immediately
                }
            }
        });

        // Close button
        $modal.find('[data-bs-dismiss="modal"]').on('click', function () {
            if (opts.onCancel) opts.onCancel($modal);
        });

        $modal.modal('show');
        return $modal;
    }

    function close(id) {
        $('#' + id).modal('hide');
    }

    function setBody(id, html) {
        $('#' + id + '_body').html(html);
    }

    function loading(id, show) {
        var $body = $('#' + id + '_body');
        if (show) {
            $body.html(
                '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted" style="font-size:13px;">Đang tải...</p></div>'
            );
        }
    }

    return { create: create, close: close, setBody: setBody, loading: loading };
})();
