<?php
namespace Accounting\Infrastructure\EInvoice;

//
// XÂY DỰNG XML HÓA ĐƠN ĐIỆN TỬ THEO CHUẨN TCT
//
// Tuân thủ: Thông tư 32/2025/TT-BTC (thay TT 78/2021)
// Chuẩn XML: v2.0.0 theo QĐ TCT (30/05/2025)
// Ký hiệu mẫu số: 1=GTGT, 2=Bán hàng, 7=TMĐT, 8/9=tích hợp biên lai
// Ký hiệu hóa đơn: T, D, L, M, N, B, G, H, X (mới)
//
// NGHIỆP VỤ: Tạo XML hóa đơn từ dữ liệu giao dịch kế toán
// - Chuyển đổi số tiền thành chữ (VnWords)
// - Áp dụng VAT 8% hoặc 10% theo NQ 204/2025
// - Tạo QR code URL (nếu cần)
// - Output: XML string chưa ký (chờ signature)
//
class XmlInvoiceBuilder
{
    // Tạo XML hóa đơn GTGT (mẫu số 1)
    // Input: array với các key: seller, buyer, items, totals
    // Output: XML string (chưa ký)
    public function buildGtgt(array $data): string
    {
        $seller = $data['seller'];
        $buyer = $data['buyer'];
        $items = $data['items'];
        $totals = $data['totals'];
        $templateCode = $data['templateCode'] ?? '1';
        $templateSymbol = $data['templateSymbol'] ?? '01GTKT0/001';
        $invoiceNumber = $data['invoiceNumber'] ?? '00000001';
        $issueDate = $data['issueDate'] ?? date('Y-m-d');
        $currency = $data['currency'] ?? 'VND';
        $exchangeRate = $data['exchangeRate'] ?? 1;
        $paymentMethod = $data['paymentMethod'] ?? 'TM';

        $sellerName = $this->escape($seller['name'] ?? '');
        $sellerTaxCode = $this->escape($seller['taxCode'] ?? '');
        $sellerAddress = $this->escape($seller['address'] ?? '');
        $buyerName = $this->escape($buyer['name'] ?? '');
        $buyerTaxCode = $this->escape($buyer['taxCode'] ?? '');
        $buyerAddress = $this->escape($buyer['address'] ?? '');
        $grandTotal = $totals['grandTotal'] ?? 0;
        $grandTotalWords = $this->numberToWords($grandTotal);
        $qrCode = $this->buildQrCode($sellerTaxCode, $templateCode, $templateSymbol, $invoiceNumber, $grandTotal, $currency);

        $linesXml = '';
        $idx = 1;
        foreach ($items as $item) {
            $isService = ($item['isService'] ?? false) ? '2' : '1';
            $linesXml .= <<<LINE
          <HHDVu>
            <TChat>{$isService}</TChat>
            <STT>{$idx}</STT>
            <MHHDVu>{$this->escape($item['productCode'] ?? '')}</MHHDVu>
            <THHDVu>{$this->escape($item['productName'] ?? '')}</THHDVu>
            <DVTinh>{$this->escape($item['unit'] ?? '')}</DVTinh>
            <SLuong>{$this->fmt($item['quantity'] ?? 0)}</SLuong>
            <DGia>{$this->fmt($item['unitPrice'] ?? 0)}</DGia>
            <TLCKhau>{$this->fmt($item['discountRate'] ?? 0)}</TLCKhau>
            <STCKhau>{$this->fmt($item['discountAmount'] ?? 0)}</STCKhau>
            <ThTien>{$this->fmt($item['totalBeforeVat'] ?? 0)}</ThTien>
            <TSuat>{$this->vatRateString($item['vatRate'] ?? 10)}</TSuat>
          </HHDVu>

LINE;
            $idx++;
        }

        // VAT summary by rate
        $vatSummaryXml = '';
        foreach ($this->groupByVatRate($items) as $rate => $group) {
            $vatSummaryXml .= <<<VAT
          <LTSuat>
            <TSuat>{$this->vatRateString($rate)}</TSuat>
            <ThTien>{$this->fmt($group['total'])}</ThTien>
            <TThue>{$this->fmt($group['vat'])}</TThue>
          </LTSuat>

VAT;
        }

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<HDon xmlns="http://www.gdt.gov.vn/2025/invoice">
  <DLHDon>
    <TTChung>
      <PBan>2.0.0</PBan>
      <THDon>HÓA ĐƠN GIÁ TRỊ GIA TĂNG</THDon>
      <KHMSHDon>{$templateCode}</KHMSHDon>
      <KHHDon>{$templateSymbol}</KHHDon>
      <SHDon>{$invoiceNumber}</SHDon>
      <NLap>{$issueDate}</NLap>
      <DVTTe>{$currency}</DVTTe>
      <TGia>{$exchangeRate}</TGia>
      <HTTToan>{$paymentMethod}</HTTToan>
      <MaQR>{$qrCode}</MaQR>
    </TTChung>
    <NDHDon>
      <NBan>
        <Ten>{$sellerName}</Ten>
        <MST>{$sellerTaxCode}</MST>
        <DChi>{$sellerAddress}</DChi>
      </NBan>
      <NMua>
        <Ten>{$buyerName}</Ten>
        <MST>{$buyerTaxCode}</MST>
        <DChi>{$buyerAddress}</DChi>
      </NMua>
      <DSHHDVu>
{$linesXml}      </DSHHDVu>
      <TToan>
        <TgTCThue>{$this->fmt($totals['totalBeforeVat'] ?? 0)}</TgTCThue>
        <TgTThue>{$this->fmt($totals['totalVat'] ?? 0)}</TgTThue>
        <TgTTTBSo>{$this->fmt($totals['grandTotal'] ?? 0)}</TgTTTBSo>
        <TgTTTBChu>{$grandTotalWords}</TgTTTBChu>
        <THTTLTSuat>
{$vatSummaryXml}        </THTTLTSuat>
      </TToan>
    </NDHDon>
  </DLHDon>
</HDon>

XML;

        return $xml;
    }

    // Tạo XML hóa đơn điều chỉnh
    public function buildAdjustment(string $originalFkey, array $data): string
    {
        $xml = $this->buildGtgt($data);
        // Thêm thông tin điều chỉnh
        $adjustInfo = <<<ADJ
  <DLHDon>
    <TTChung>
      <PBan>2.0.0</PBan>
      <TDieuChinh>HÓA ĐƠN ĐIỀU CHỈNH</TDieuChinh>
      <HDDonGoc>
        <FKey>{$originalFkey}</FKey>
      </HDDonGoc>
    </TTChung>
  </DLHDon>

ADJ;
        return $adjustInfo . $xml;
    }

    private function vatRateString(int $rate): string
    {
        if ($rate === 0) return '0%';
        if ($rate === 5) return '5%';
        if ($rate === 8) return '8%';
        if ($rate === 10) return '10%';
        return $rate . '%';
    }

    private function groupByVatRate(array $items): array
    {
        $groups = [];
        foreach ($items as $item) {
            $rate = (int)($item['vatRate'] ?? 10);
            if (!isset($groups[$rate])) {
                $groups[$rate] = ['total' => 0, 'vat' => 0];
            }
            $groups[$rate]['total'] += ($item['totalBeforeVat'] ?? 0);
            $groups[$rate]['vat'] += ($item['vatAmount'] ?? 0);
        }
        return $groups;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function fmt(float $value): string
    {
        // Làm tròn 2 chữ số thập phân (theo chuẩn TCT)
        return number_format($value, 2, '.', '');
    }

    // Tạo dữ liệu QR code theo chuẩn TCT (TT 32/2025)
    // Format: {MST Seller}_{TemplateCode}_{TemplateSymbol}_{InvoiceNumber}_{Total}_{Currency}
    // Mã hóa base64 để nhúng vào XML
    private function buildQrCode(string $taxCode, string $tCode, string $tSymbol, string $invNo, float $total, string $currency): string
    {
        $raw = implode('_', [$taxCode, $tCode, $tSymbol, $invNo, $this->fmt($total), $currency]);
        return base64_encode($raw);
    }

    private function numberToWords(float $amount): string
    {
        // Sử dụng VnWords helper nếu có
        if (class_exists('\\Accounting\\Domain\\ValueObject\\VnWords')) {
            return \Accounting\Domain\ValueObject\VnWords::toWords($amount);
        }
        // Fallback
        return number_format($amount, 0) . ' đồng';
    }
}
