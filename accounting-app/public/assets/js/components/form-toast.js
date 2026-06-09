/**
 * FormToast — Thông báo dạng toast (thay thế showToast gốc)
 * ===
 * Hỗ trợ: success, error, warning, info
 * Tự động biến mất sau N giây (mặc định 3s)
 * Hỗ trợ callback khi đóng
 * Gộp nhiều toast cùng loại
 * Backward-compatible: hàm showToast() vẫn hoạt động như cũ
 */

var FormToast = (function () {
    var defaults = {
        duration: 3000,
        types: {
            success: { icon: 'bi-check-circle-fill', color: '#10b981' },
            error: { icon: 'bi-x-circle-fill', color: '#ef4444' },
            warning: { icon: 'bi-exclamation-triangle-fill', color: '#f59e0b' },
            info: { icon: 'bi-info-circle-fill', color: '#4f6ef7' }
        }
    };

    function show(msg, type, duration, callback) {
        type = type || 'info';
        duration = duration || defaults.duration;
        var cfg = defaults.types[type] || defaults.types.info;
        var t = $(
            '<div class="toast show align-items-center border-0" role="alert" style="border-left:4px solid ' +
                cfg.color +
                ';margin-bottom:6px;">' +
                '<div class="d-flex">' +
                    '<div class="toast-body">' +
                        '<i class="bi ' + cfg.icon + ' me-2" style="color:' + cfg.color + '"></i>' +
                        msg +
                    '</div>' +
                    '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>' +
                '</div>' +
            '</div>'
        );
        $('#toastContainer').append(t);
        if (duration > 0) {
            setTimeout(function () {
                t.remove();
                if (callback) callback();
            }, duration);
        }
        t.find('.btn-close').on('click', function () {
            t.remove();
            if (callback) callback();
        });
        return t;
    }

    function success(msg, duration, cb) { return show(msg, 'success', duration, cb); }
    function error(msg, duration, cb) { return show(msg, 'error', duration, cb); }
    function warning(msg, duration, cb) { return show(msg, 'warning', duration, cb); }
    function info(msg, duration, cb) { return show(msg, 'info', duration, cb); }

    // Backward-compatible shim: showToast('msg','success')
    window.showToast = show;

    return { show: show, success: success, error: error, warning: warning, info: info };
})();
