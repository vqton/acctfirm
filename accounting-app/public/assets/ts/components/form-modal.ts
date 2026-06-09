// FormModal — Hộp thoại Modal dùng chung
// Hỗ trợ: sm/md/lg/xl, body HTML hoặc URL, Enter submit, callbacks

interface ModalOpts {
  id?: string;
  size?: 'sm' | 'md' | 'lg' | 'xl';
  title?: string;
  body?: string;
  footer?: string;
  saveText?: string;
  backdrop?: boolean | 'static';
  keyboard?: boolean;
  onSave?: ($modal: JQuery, done: () => void) => boolean | { always: (fn: () => void) => void } | void;
  onShow?: ($modal: JQuery) => void;
  onHide?: () => void;
  onCancel?: ($modal: JQuery) => void;
}

let counter = 0;

function create(opts: ModalOpts): JQuery {
  opts = opts || {};
  const id = opts.id || 'formModal_' + (++counter);
  const size = opts.size || 'md';
  const sizeClass = size === 'sm' ? 'modal-sm' : size === 'lg' ? 'modal-lg' : size === 'xl' ? 'modal-xl' : '';
  const title = opts.title || '';
  const bodyHtml = opts.body || '';
  const backdrop = opts.backdrop !== undefined ? opts.backdrop : 'static';
  const keyboard = opts.keyboard !== undefined ? opts.keyboard : true;

  let footerHtml: string;
  const hasFooter = opts.footer !== undefined;
  if (opts.footer === undefined) {
    footerHtml =
      '<button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Hủy</button>' +
      '<button type="button" class="btn btn-sm btn-primary" id="' + id + '_save">' +
        (opts.saveText || 'Lưu') +
      '</button>';
  } else {
    footerHtml = opts.footer;
  }

  const html =
    '<div class="modal fade" id="' + id + '" tabindex="-1" data-bs-backdrop="' + backdrop + '" data-bs-keyboard="' + keyboard + '">' +
      '<div class="modal-dialog modal-dialog-centered ' + sizeClass + '">' +
        '<div class="modal-content">' +
          '<div class="modal-header">' +
            '<h6 class="modal-title">' + esc(title) + '</h6>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal"></button>' +
          '</div>' +
          '<div class="modal-body" id="' + id + '_body">' +
            bodyHtml +
          '</div>' +
          (hasFooter || opts.footer === undefined
            ? '<div class="modal-footer" id="' + id + '_footer">' + footerHtml + '</div>'
            : '') +
        '</div>' +
      '</div>' +
    '</div>';

  $('#' + id).remove();
  $('body').append(html);

  const $modal = $('#' + id);
  const $save = $('#' + id + '_save');
  const _$body = $('#' + id + '_body');

  $modal.on('shown.bs.modal', () => {
    _$body.find('input:visible,textarea:visible,select:visible').first().focus();
    if (opts.onShow) opts.onShow($modal);
  });

  $modal.on('hidden.bs.modal', () => {
    $modal.remove();
    if (opts.onHide) opts.onHide();
  });

  $modal.on('keydown', (e: JQuery.KeyDownEvent) => {
    if (e.key === 'Enter' && !e.shiftKey && !$(e.target).is('textarea')) {
      e.preventDefault();
      $save.click();
    }
  });

  $save.on('click', function (this: HTMLElement) {
    if (opts.onSave) {
      const btn = this;
      const origHtml = btn.innerHTML;
      (btn as HTMLButtonElement).disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Đang xử lý...';
      const doneFn = () => {
        (btn as HTMLButtonElement).disabled = false;
        btn.innerHTML = origHtml;
      };
      const result = opts.onSave($modal, doneFn);
      if (result === false) {
        doneFn();
      } else if (result && typeof result === 'object' && 'always' in result) {
        (result as { always: (fn: () => void) => void }).always(doneFn);
      } else if (result === undefined) {
        doneFn();
      }
    }
  });

  $modal.find('[data-bs-dismiss="modal"]').on('click', () => {
    if (opts.onCancel) opts.onCancel($modal);
  });

  $modal.modal('show');
  return $modal;
}

function close(id: string): void {
  $('#' + id).modal('hide');
}

function setBody(id: string, html: string): void {
  $('#' + id + '_body').html(html);
}

function loading(id: string, show: boolean): void {
  const $body = $('#' + id + '_body');
  if (show) {
    $body.html(
      '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div><p class="mt-2 text-muted" style="font-size:13px;">Đang tải...</p></div>'
    );
  }
}

function esc(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

export const FormModal = { create, close, setBody, loading };
export type FormModalApi = typeof FormModal;
