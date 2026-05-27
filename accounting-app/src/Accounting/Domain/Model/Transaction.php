<?php
// src/Accounting/Domain/Model/Transaction.php

namespace Accounting\Domain\Model;

/**
 * Chứng từ kế toán — Đại diện cho một bút toán tổng hợp.
 *
 * Mỗi Transaction chứa nhiều LedgerEntry (dòng Nợ/Có) và phải đảm bảo
 * nguyên tắc: Tổng Nợ = Tổng Có (double-entry bookkeeping).
 *
 * VÒNG ĐỜI (Lifecycle):
 *   pending → submitted → approved → posted → reversed
 *   pending → posted (direct post nếu không cần duyệt)
 *   rejected → pending (sửa lại sau khi bị từ chối)
 *
 * NGHIỆP VỤ:
 * - $sourceModule: module nào tạo ra bút toán này (cash, purchase, sales...)
 * - $voucherType: loại chứng từ (PC=Phiếu chi, PT=Phiếu thu, JV=Journal Voucher)
 * - $reference: số chứng từ tự động tăng theo năm (VD: PC-2026-000001)
 *
 * RỦI RO:
 * - Không được post vào kỳ đã đóng (PeriodService kiểm tra ngày chứng từ)
 * - Khi đã ở trạng thái posted, chỉ có thể reversal (đảo ngược), không sửa/xóa
 * - Transaction date quyết định kỳ kế toán, không phải ngày hệ thống
 */
class Transaction
{
    private string $id;
    private \DateTimeImmutable $date;
    private string $description;
    private string $reference;
    private array $ledgerEntries;
    private string $status;
    private ?string $createdBy;
    private ?string $voucherType;
    private ?string $sourceModule;
    private string $currency;
    private float $exchangeRate;

    public function __construct(
        string $id,
        \DateTimeImmutable $date,
        string $description,
        string $reference,
        ?string $voucherType = null,
        ?string $sourceModule = null,
        string $currency = 'VND',
        float $exchangeRate = 1.0
    ) {
        $this->id = $id;
        $this->date = $date;
        $this->description = $description;
        $this->reference = $reference;
        $this->voucherType = $voucherType;
        $this->sourceModule = $sourceModule;
        $this->currency = $currency;
        $this->exchangeRate = $exchangeRate;
        $this->ledgerEntries = [];
        $this->status = 'pending';
    }

    // Kiểm tra chuyển trạng thái chứng từ.
    // Trạng thái cho phép: pending→submitted/posted, submitted→approved/rejected,
    // approved→posted, rejected→pending, posted→reversed, reversed không được chuyển tiếp.
    // RỦI RO: Không cho phép quay lại từ posted — chỉ được đảo ngược (reversal).
    public function isValidTransition(string $newStatus): bool
    {
        $allowed = [
            'pending' => ['submitted', 'posted'],
            'submitted' => ['approved', 'rejected'],
            'approved' => ['posted'],
            'rejected' => ['pending'],
            'posted' => ['reversed'],
            'reversed' => [],
        ];
        return in_array($newStatus, $allowed[$this->status] ?? [], true);
    }

    // Gửi duyệt: Chuyển chứng từ từ nháp lên chờ duyệt.
    // Sau khi submit, chứng từ được chuyển cho Kế toán trưởng hoặc người có thẩm quyền phê duyệt.
    // RỦI RO: Không được sửa chứng từ sau khi submit trừ khi bị từ chối (rejected).
    public function submit(): void
    {
        if (!$this->isValidTransition('submitted')) {
            throw new \InvalidArgumentException("Không thể trình duyệt: trạng thái hiện tại là '{$this->status}'.");
        }
        $this->status = 'submitted';
    }

    // Phê duyệt: Xác nhận chứng từ hợp lệ, sẵn sàng để ghi sổ (post).
    // Kế toán trưởng kiểm tra: Dr=Cr, tài khoản hợp lệ, chứng từ gốc đầy đủ.
    public function approve(): void
    {
        if (!$this->isValidTransition('approved')) {
            throw new \InvalidArgumentException("Không thể phê duyệt: trạng thái hiện tại là '{$this->status}'.");
        }
        $this->status = 'approved';
    }

    // Từ chối: Trả lại chứng từ cho người tạo để sửa.
    // Lý do từ chối phải được ghi lại (audit trail bắt buộc).
    public function reject(): void
    {
        if (!$this->isValidTransition('rejected')) {
            throw new \InvalidArgumentException("Không thể từ chối: trạng thái hiện tại là '{$this->status}'.");
        }
        $this->status = 'rejected';
    }

    // Quay lại nháp: Cho phép sửa chứng từ sau khi bị từ chối.
    // Chỉ áp dụng cho trạng thái rejected — không cho phép quay lại từ submitted/approved.
    public function returnToDraft(): void
    {
        if ($this->status !== 'rejected') {
            throw new \InvalidArgumentException("Không thể quay lại trạng thái nháp: trạng thái hiện tại là '{$this->status}'.");
        }
        $this->status = 'pending';
    }

    public function getId(): string { return $this->id; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getDescription(): string { return $this->description; }
    public function getReference(): string { return $this->reference; }
    public function getLedgerEntries(): array { return $this->ledgerEntries; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedBy(): ?string { return $this->createdBy; }
    public function getVoucherType(): ?string { return $this->voucherType; }
    public function getSourceModule(): ?string { return $this->sourceModule; }
    public function getCurrency(): string { return $this->currency; }
    public function getExchangeRate(): float { return $this->exchangeRate; }

    public function setStatus(string $v): void { $this->status = $v; }
    public function setCreatedBy(?string $v): void { $this->createdBy = $v; }

    // Thêm dòng chi tiết (LedgerEntry) vào chứng từ.
    // Mỗi dòng là một bên Nợ hoặc Có. Tổng Dr phải = tổng Cr khi post.
    // RỦI RO: Không kiểm tra Dr=Cr tại đây — defer đến lúc post để linh hoạt khi nhập liệu.
    public function addLedgerEntry(LedgerEntry $entry): void
    {
        $this->ledgerEntries[] = $entry;
    }

    // Ghi sổ — hành động quan trọng nhất: xác nhận bút toán chính thức vào sổ kế toán.
    // Kiểm tra bắt buộc: (1) trạng thái là pending hoặc approved, (2) tổng Nợ = tổng Có.
    // RỦI RO: Sau khi post, không được sửa/xóa — chỉ có thể đảo ngược (reverse).
    // Sai sót sau post → phải điều chỉnh bằng bút toán điều chỉnh (adjusting entry).
    public function post(string $createdBy): void
    {
        if (!in_array($this->status, ['pending', 'approved'], true)) {
            throw new \InvalidArgumentException('Bút toán không thể ghi sổ từ trạng thái hiện tại: ' . $this->status . '.');
        }

        $debitTotal = 0.0;
        $creditTotal = 0.0;

        foreach ($this->ledgerEntries as $entry) {
            if ($entry->isDebit()) {
                $debitTotal += $entry->getAmount();
            } else {
                $creditTotal += $entry->getAmount();
            }
        }

        if ($debitTotal !== $creditTotal) {
            throw new \InvalidArgumentException('Tổng Nợ và tổng Có phải cân bằng. Vui lòng kiểm tra lại.');
        }

        $this->status = 'posted';
        $this->createdBy = $createdBy;
    }

    // Đảo ngược bút toán — phương pháp sửa sai duy nhất cho chứng từ đã ghi sổ.
    // Nguyên tắc: bút toán đảo ngược ghi Nợ thành Có và ngược lại với cùng số tiền.
    // RỦI RO: Bút toán gốc vẫn giữ nguyên trong sổ — không xóa, không sửa.
    // Bút toán reversal tạo ra một Transaction mới với reference gốc + "/REVERSED".
    public function reverse(string $reversedBy): void
    {
        if ($this->status !== 'posted') {
            throw new \InvalidArgumentException('Chỉ có thể hoàn nhập bút toán đã ghi sổ.');
        }

        $this->status = 'reversed';
    }
}