// FormToast — Thông báo dạng toast (thay thế showToast gốc)
// Hỗ trợ: success, error, warning, info. Auto-dismiss after N seconds.

type ToastType = 'success' | 'error' | 'warning' | 'info';

interface ToastTypeConfig {
  icon: string;
  color: string;
}

const defaults: {
  duration: number;
  types: Record<ToastType, ToastTypeConfig>;
} = {
  duration: 3000,
  types: {
    success: { icon: 'bi-check-circle-fill', color: '#10b981' },
    error: { icon: 'bi-x-circle-fill', color: '#ef4444' },
    warning: { icon: 'bi-exclamation-triangle-fill', color: '#f59e0b' },
    info: { icon: 'bi-info-circle-fill', color: '#4f6ef7' },
  },
};

let toastContainer: JQuery | null = null;

function getContainer(): JQuery {
  if (!toastContainer || !toastContainer.length) {
    toastContainer = $('#toastContainer');
    if (!toastContainer.length) {
      $('body').append('<div id="toastContainer" style="position:fixed;top:16px;right:16px;z-index:9999;"></div>');
      toastContainer = $('#toastContainer');
    }
  }
  return toastContainer;
}

function show(msg: string, type?: ToastType, duration?: number, callback?: () => void): JQuery {
  type = type || 'info';
  duration = duration || defaults.duration;
  const cfg = defaults.types[type] || defaults.types.info;

  const $t = $(
    '<div class="toast show align-items-center border-0" role="alert" style="border-left:4px solid ' +
      cfg.color +
      ';margin-bottom:6px;">' +
      '<div class="d-flex">' +
        '<div class="toast-body">' +
          '<i class="bi ' + cfg.icon + ' me-2" style="color:' + cfg.color + '"></i>' +
          esc(msg) +
        '</div>' +
        '<button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>' +
      '</div>' +
    '</div>'
  );

  getContainer().append($t);

  if (duration > 0) {
    setTimeout(() => {
      $t.remove();
      if (callback) callback();
    }, duration);
  }

  $t.find('.btn-close').on('click', () => {
    $t.remove();
    if (callback) callback();
  });

  return $t;
}

function success(msg: string, duration?: number, cb?: () => void): JQuery {
  return show(msg, 'success', duration, cb);
}
function error(msg: string, duration?: number, cb?: () => void): JQuery {
  return show(msg, 'error', duration, cb);
}
function warning(msg: string, duration?: number, cb?: () => void): JQuery {
  return show(msg, 'warning', duration, cb);
}
function info(msg: string, duration?: number, cb?: () => void): JQuery {
  return show(msg, 'info', duration, cb);
}

function esc(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

export const FormToast = { show, success, error, warning, info };
export type FormToastApi = typeof FormToast;
