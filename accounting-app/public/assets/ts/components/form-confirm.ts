// FormConfirm — Hộp thoại xác nhận dùng Bootstrap modal
// Thay thế confirm()/alert()/prompt(). Hỗ trợ Enter/Escape.

type ConfirmCallback = (value: boolean | string) => void;

// eslint-disable-next-line @typescript-eslint/no-unused-vars
import { FormToast } from './form-toast';

let $modal: JQuery<HTMLElement> | null = null;
let $title: JQuery | null = null;
let $body: JQuery | null = null;
let $input: JQuery | null = null;
let $cancel: JQuery | null = null;
let $ok: JQuery | null = null;
let currentCallback: ConfirmCallback | null = null;
let isPrompt = false;

function init(): void {
  if ($modal) return;

  const html =
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

  $modal.on('hidden.bs.modal', () => {
    currentCallback = null;
    $input!.val('').hide();
  });

  $ok.on('click', () => {
    if (currentCallback) {
      const val = isPrompt ? $input!.val() as string : true;
      currentCallback(val);
    }
    $modal!.modal('hide');
  });

  $input.on('keydown', (e: JQuery.KeyDownEvent) => {
    if (e.key === 'Enter') $ok!.click();
  });

  $modal.on('keydown', (e: JQuery.KeyDownEvent) => {
    if (e.key === 'Escape') $modal!.modal('hide');
  });
}

function confirm(title: string, message: string, callback: (ok: boolean) => void): void {
  init();
  isPrompt = false;
  $title!.text(title || 'Xác nhận');
  $body!.text(message || 'Bạn có chắc chắn?');
  $input!.hide().val('');
  $cancel!.show();
  $ok!.text('Xác nhận').removeClass('btn-danger').addClass('btn-primary');
  currentCallback = callback as ConfirmCallback;
  $modal!.modal('show');
}

function alert(title: string, message: string, callback?: () => void): void {
  init();
  isPrompt = false;
  $title!.text(title || 'Thông báo');
  $body!.text(message || '');
  $input!.hide().val('');
  $cancel!.hide();
  $ok!.text('Đóng').removeClass('btn-danger').addClass('btn-primary');
  currentCallback = callback ? (() => { callback(); }) as ConfirmCallback : null;
  $modal!.modal('show');
}

function prompt(title: string, message: string, callback: (val: string) => void, defaultValue?: string): void {
  init();
  isPrompt = true;
  $title!.text(title || 'Nhập dữ liệu');
  $body!.text(message || '');
  $input!.show().val(defaultValue || '');
  $cancel!.show();
  $ok!.text('Xác nhận').removeClass('btn-danger').addClass('btn-primary');
  currentCallback = callback as ConfirmCallback;
  $modal!.modal('show');
  $input!.focus();
}

// confirmDelete with CSRF
// eslint-disable-next-line @typescript-eslint/no-explicit-any
declare const csrf: string | undefined;

function confirmDelete(url: string, name: string, callback?: () => void): void {
  init();
  isPrompt = false;
  $title!.text('Xóa');
  $body!.text('Bạn có chắc chắn muốn xóa "' + (name || '') + '"?');
  $input!.hide().val('');
  $cancel!.show();
  $ok!.text('Xóa').removeClass('btn-primary').addClass('btn-danger');
  currentCallback = (ok: boolean | string) => {
    if (!ok) return;
    $.ajax({
      url: url,
      method: 'DELETE',
      headers: { 'X-CSRF-Token': csrf || '' },
    })
      .done(() => {
        FormToast.success('Đã xóa dữ liệu thành công.');
        if (callback) callback();
      })
      .fail((x: JQuery.jqXHR) => {
        let m = 'Có lỗi xảy ra khi xóa dữ liệu.';
        try { m = JSON.parse(x.responseText).error; } catch (_) { /* ignore */ }
        FormToast.error(m);
      });
  };
  $modal!.modal('show');
}

export const FormConfirm = { confirm, alert, prompt, confirmDelete };
export type FormConfirmApi = typeof FormConfirm;
