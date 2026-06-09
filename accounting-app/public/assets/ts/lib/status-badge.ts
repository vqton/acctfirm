// statusBadge — Tạo badge HTML cho trạng thái nghiệp vụ
// Dùng trong mọi view hiển thị danh sách (AP, AR, journal, inventory, ...)

export function statusBadge(status: string): string {
  const labels: Record<string, string> = {
    draft:'Nháp',submitted:'Chờ duyệt',pending:'Chờ duyệt',
    approved:'Đã duyệt',posted:'Đã ghi sổ',paid:'Đã chi',
    cancelled:'Đã hủy',settled:'Đã hoàn ứng',reversed:'Đã đảo',closed:'Đã đóng',
    active:'Hoạt động',inactive:'Ngừng',
    completed:'Hoàn thành',confirmed:'Đã xác nhận',
    finalised:'Đã chốt',issued:'Đã phát hành',
    matched:'Đã đối chiếu',sent:'Đã gửi',
    running:'Đang chạy',in_progress:'Đang thực hiện',
    written_off:'Đã xóa sổ',prepayment:'Tạm ứng',
    unpaid:'Chưa TT',partial:'Một phần',
    released:'Đã phát hành',costed:'Đã tính giá',
    liquidated:'Đã thanh lý',open:'Đang mở',
    verified:'Đã xác thực',unverified:'Chưa xác thực',
    yes:'Có',no:'Không',
    warning:'Cảnh báo',mismatch:'Lệch',
    pending_approval:'Chờ duyệt',partially_received:'Nhận một phần',
    rejected:'Từ chối',fulfilled:'Đã đặt hàng'
  };
  const classes: Record<string, string> = {
    draft:'badge-warning',submitted:'badge-info',pending:'badge-info',
    approved:'badge-active',posted:'badge-active',paid:'badge-active',
    cancelled:'badge-inactive',reversed:'badge-inactive',closed:'badge-secondary',
    active:'badge-active',inactive:'badge-inactive',
    completed:'badge-active',confirmed:'badge-active',
    finalised:'badge-success',issued:'badge-active',
    matched:'badge-active',sent:'badge-active',
    running:'badge-warning',in_progress:'badge-warning',
    written_off:'badge-inactive',prepayment:'badge-warning',
    unpaid:'badge-warning',partial:'badge-warning',
    released:'badge-warning',costed:'badge-success',
    liquidated:'badge-light',open:'badge-active',
    verified:'badge-active',unverified:'badge-warning',
    yes:'badge-active',no:'badge-inactive',
    warning:'badge-warning',mismatch:'badge-danger',
    pending_approval:'badge-info',partially_received:'badge-info',
    rejected:'badge-danger',fulfilled:'badge-inactive'
  };
  const label = labels[status] || status;
  const cls = classes[status] || 'badge-secondary';
  return '<span class="badge-status ' + cls + '">' + esc(label) + '</span>';
}

function esc(s: string): string {
  return String(s).replace(/[&<>"']/g, function(m: string) {
    if (m === '&') return '&amp;';
    if (m === '<') return '&lt;';
    if (m === '>') return '&gt;';
    if (m === '"') return '&quot;';
    return '&#39;';
  });
}
