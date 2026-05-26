<?php
namespace Accounting\Infrastructure;

// Alias cho VnWords — chuyển đổi số tiền thành chữ (VD: "Một trăm triệu đồng")
// Dùng trên phiếu thu, phiếu chi, hóa đơn theo yêu cầu của Kế toán trưởng
// Yêu cầu pháp lý: Thông tư 200/2014/TT-BTC — số tiền trên chứng từ kế toán phải viết bằng chữ
// Dùng trên: Phiếu thu (mẫu 01-TT), Phiếu chi (mẫu 02-TT), Hóa đơn GTGT, Séc, UNC
// Implementation thực tế tại Domain/ValueObject/VnWords.php
class VnWords extends \Accounting\Domain\ValueObject\VnWords {}
