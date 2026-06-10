<?php
namespace Accounting\Domain\Contract;

/**
 * Giao diện dịch vụ hàng tồn kho.
 *
 * Nghiệp vụ: Xử lý toàn bộ nghiệp vụ nhập — xuất — tồn kho theo
 * phương pháp bình quân gia quyền (Weighted Average) hoặc FIFO.
 *
 * Các nghiệp vụ chính:
 *   - Nhập kho (mua hàng, nhập từ sản xuất, nhập từ ký gửi)
 *   - Xuất kho (bán hàng, sản xuất, khuyến mại)
 *   - Chuyển kho, gửi hàng ký gửi
 *   - Kiểm kê, điều chỉnh tồn kho
 *   - Ghi nhận giảm giá trị hàng tồn kho (impairment)
 *   - Hàng đang đi trên đường (in-transit)
 *   - Báo cáo tồn kho, phân tích lão hóa, vòng quay
 *
 * Mọi giao dịch đều ghi nhận bút toán kép thông qua JournalService.
 */
interface InventoryServiceInterface
{
    /**
     * Nhập kho hàng hóa (Nợ 152/153/155/156 / Có 331/111/112).
     *
     * @param string $itemId      Mã mặt hàng
     * @param float  $qty         Số lượng nhập
     * @param float  $unitPrice   Đơn giá nhập (chưa bao gồm chi phí bổ sung)
     * @param array  $addonCosts  Chi phí bổ sung (vận chuyển, bảo hiểm, ...)
     * @param string $reference   Số chứng từ tham chiếu (PNK)
     * @param string $createdBy   Người tạo phiếu nhập
     * @param string|null $batchCode Mã lô hàng (nếu quản lý theo lô)
     * @param string|null $expiryDate Ngày hết hạn (nếu quản lý hạn dùng)
     * @return array              Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function receiveGoods(
        string $itemId, float $qty, float $unitPrice, array $addonCosts,
        string $reference, string $createdBy,
        ?string $batchCode = null, ?string $expiryDate = null
    ): array;

    /**
     * Xuất kho hàng hóa (Nợ 632 / Có 152/153/155/156).
     *
     * @param string $itemId    Mã mặt hàng
     * @param float  $qty       Số lượng xuất
     * @param string $issueType Loại xuất (sale, production, return, write_off, ...)
     * @param string $reference Số chứng từ tham chiếu (PXK)
     * @param string $createdBy Người tạo phiếu xuất
     * @return array            Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function issueGoods(
        string $itemId, float $qty, string $issueType,
        string $reference, string $createdBy
    ): array;

    /**
     * Chuyển kho hàng hóa giữa các kho (xuất kho nguồn → nhập kho đích).
     *
     * @param string      $itemId           Mã mặt hàng
     * @param float       $qty              Số lượng chuyển
     * @param string|null $fromWarehouseId  Mã kho nguồn (null nếu không quản lý kho)
     * @param string      $toWarehouseId    Mã kho đích
     * @param string      $reference        Số chứng từ tham chiếu
     * @param string      $createdBy        Người tạo chứng từ
     * @return array                        Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function transferGoods(
        string $itemId, float $qty, ?string $fromWarehouseId,
        string $toWarehouseId, string $reference, string $createdBy
    ): array;

    /**
     * Ghi nhận hàng mua đang đi trên đường (Nợ 157 / Có 331).
     *
     * @param string $itemId      Mã mặt hàng
     * @param float  $qty         Số lượng hàng đi đường
     * @param float  $unitPrice   Đơn giá mua
     * @param array  $addonCosts  Chi phí bổ sung
     * @param string $reference   Số chứng từ tham chiếu
     * @param string $createdBy   Người tạo chứng từ
     * @return array              Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordInTransit(
        string $itemId, float $qty, float $unitPrice, array $addonCosts,
        string $reference, string $createdBy
    ): array;

    /**
     * Ghi nhận hàng đi đường đã về đến kho (Nợ 152/153/155/156 / Có 157).
     *
     * @param string $transitId Mã giao dịch hàng đi đường
     * @param float  $qty       Số lượng đã nhận kho
     * @param string $reference Số chứng từ tham chiếu
     * @param string $createdBy Người tạo chứng từ
     * @return array            Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function receiveFromTransit(
        string $transitId, float $qty, string $reference, string $createdBy
    ): array;

    /**
     * Gửi hàng đi ký gửi (Nợ 157 / Có 152/153/155/156).
     *
     * @param string $itemId     Mã mặt hàng
     * @param float  $qty        Số lượng gửi ký gửi
     * @param string $consignee  Bên nhận ký gửi
     * @param string $reference  Số chứng từ tham chiếu
     * @param string $createdBy  Người tạo chứng từ
     * @return array             Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function consignGoods(
        string $itemId, float $qty, string $consignee,
        string $reference, string $createdBy
    ): array;

    /**
     * Ghi nhận hàng ký gửi đã bán được (Nợ 632 / Có 157).
     *
     * @param string $consignmentId Mã lô hàng ký gửi
     * @param float  $qty           Số lượng đã bán
     * @param string $reference     Số chứng từ tham chiếu
     * @param string $createdBy     Người tạo chứng từ
     * @return array                Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function sellConsigned(
        string $consignmentId, float $qty, string $reference, string $createdBy
    ): array;

    /**
     * Ghi nhận hàng ký gửi bị trả lại (Nợ 152/153/155/156 / Có 157).
     *
     * @param string $consignmentId Mã lô hàng ký gửi
     * @param float  $qty           Số lượng trả lại
     * @param string $reference     Số chứng từ tham chiếu
     * @param string $createdBy     Người tạo chứng từ
     * @return array                Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function returnConsigned(
        string $consignmentId, float $qty, string $reference, string $createdBy
    ): array;

    /**
     * Điều chỉnh tồn kho theo kết quả kiểm kê thực tế.
     *
     * @param string $itemId     Mã mặt hàng
     * @param float  $actualQty  Số lượng tồn thực tế
     * @param string $reference  Số chứng từ tham chiếu
     * @param string $createdBy  Người tạo chứng từ
     * @return array             Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function adjustPhysicalCount(
        string $itemId, float $actualQty, string $reference, string $createdBy
    ): array;

    /**
     * Tạo phiên kiểm kê kho với nhiều mặt hàng.
     *
     * @param array  $lines     Danh sách các dòng kiểm kê (itemId, expectedQty, actualQty)
     * @param string $reference Số chứng từ tham chiếu
     * @param string $notes     Ghi chú kiểm kê
     * @param string $createdBy Người tạo chứng từ
     * @return array            Thông tin phiên kiểm kê đã tạo
     */
    public function createCountSession(
        array $lines, string $reference, string $notes, string $createdBy
    ): array;

    /**
     * Ghi nhận giảm giá trị hàng tồn kho (dự phòng giảm giá).
     * Nợ 632 / Có 2294 (hoặc trực tiếp giảm giá trị hàng tồn kho).
     *
     * @param string $itemId    Mã mặt hàng
     * @param float  $amount    Số tiền giảm giá
     * @param string $reference Số chứng từ tham chiếu
     * @param string $notes     Lý do ghi nhận giảm giá
     * @param string $createdBy Người tạo chứng từ
     * @return array            Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function recordImpairment(
        string $itemId, float $amount, string $reference, string $notes, string $createdBy
    ): array;

    /**
     * Hoàn nhập dự phòng giảm giá hàng tồn kho.
     *
     * @param string $impairmentId Mã nghiệp vụ giảm giá cần hoàn nhập
     * @param float  $amount       Số tiền hoàn nhập (không vượt quá số đã ghi nhận)
     * @param string $reference    Số chứng từ tham chiếu
     * @param string $createdBy    Người tạo chứng từ
     * @return array               Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function reverseImpairment(
        string $impairmentId, float $amount, string $reference, string $createdBy
    ): array;

    /**
     * Xuất kho hàng khuyến mại (Nợ 641 / Có 152/153/155/156).
     *
     * @param string $itemId         Mã mặt hàng
     * @param float  $qty            Số lượng xuất khuyến mại
     * @param string $reference      Số chứng từ tham chiếu
     * @param string $createdBy      Người tạo chứng từ
     * @param float|null $deemedSaleValue Giá trị tính thuế GTGT hàng KM (nếu có)
     * @param float  $vatRate        Thuế suất GTGT hàng KM
     * @return array                 Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function issuePromotional(
        string $itemId, float $qty, string $reference, string $createdBy,
        ?float $deemedSaleValue = null, float $vatRate = 0
    ): array;

    /**
     * Xuất kho từ lô hàng cụ thể (áp dụng cho hàng quản lý theo lô).
     *
     * @param string $itemId     Mã mặt hàng
     * @param float  $qty        Số lượng xuất
     * @param string $batchCode  Mã lô hàng
     * @param string $issueType  Loại xuất
     * @param string $reference  Số chứng từ tham chiếu
     * @param string $createdBy  Người tạo chứng từ
     * @return array             Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function issueFromBatch(
        string $itemId, float $qty, string $batchCode, string $issueType,
        string $reference, string $createdBy
    ): array;

    /**
     * Lấy tỷ giá quy đổi cho mặt hàng nhập khẩu.
     *
     * @param string $currencyCode Mã tiền tệ (USD, EUR, ...)
     * @return float               Tỷ giá hiện tại
     */
    public function getExchangeRate(string $currencyCode): float;

    /**
     * Nhập kho hàng nhập khẩu bằng ngoại tệ.
     *
     * @param string $itemId      Mã mặt hàng
     * @param float  $qty         Số lượng nhập
     * @param float  $unitPriceFC Đơn giá nhập (ngoại tệ)
     * @param array  $addonCosts  Chi phí bổ sung (VND)
     * @param string $currencyCode Mã tiền tệ
     * @param float|null $exchangeRate Tỷ giá quy đổi (null = tỷ giá hệ thống)
     * @param string $reference   Số chứng từ tham chiếu
     * @param string $createdBy   Người tạo chứng từ
     * @return array              Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function receiveGoodsFC(
        string $itemId, float $qty, float $unitPriceFC, array $addonCosts,
        string $currencyCode, ?float $exchangeRate,
        string $reference, string $createdBy
    ): array;

    /**
     * Nhập kho hàng bán bị trả lại (Nợ 152/153/155/156 / Có 632).
     *
     * @param string $itemId    Mã mặt hàng
     * @param float  $qty       Số lượng trả lại
     * @param string $reference Số chứng từ tham chiếu
     * @param string $createdBy Người tạo chứng từ
     * @return array            Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function returnFromCustomer(
        string $itemId, float $qty, string $reference, string $createdBy
    ): array;

    /**
     * Trả lại hàng cho nhà cung cấp (Nợ 331 / Có 152/153/155/156).
     *
     * @param string $itemId    Mã mặt hàng
     * @param float  $qty       Số lượng trả
     * @param string $reference Số chứng từ tham chiếu
     * @param string $createdBy Người tạo chứng từ
     * @return array            Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function returnToSupplier(
        string $itemId, float $qty, string $reference, string $createdBy
    ): array;

    /**
     * Xóa sổ hàng tồn kho (hết hạn, hư hỏng, mất mát).
     * Nợ 632 (hoặc TK chi phí khác) / Có 152/153/155/156.
     *
     * @param string $itemId         Mã mặt hàng
     * @param float  $qty            Số lượng xóa sổ
     * @param string $reason         Lý do xóa sổ
     * @param string $expenseAccount Mã tài khoản chi phí (632, 642, ...)
     * @param string $reference      Số chứng từ tham chiếu
     * @param string $createdBy      Người tạo chứng từ
     * @param string $notes          Ghi chú bổ sung
     * @return array                 Thông tin bút toán và giao dịch đã ghi nhận
     */
    public function writeOffGoods(
        string $itemId, float $qty, string $reason, string $expenseAccount,
        string $reference, string $createdBy, string $notes = ''
    ): array;

    /**
     * Kết chuyển tồn kho cuối kỳ (điều chỉnh giá trị tồn kho theo phương pháp tính giá).
     *
     * @param string $itemId         Mã mặt hàng
     * @param float  $closingQty     Số lượng tồn cuối kỳ
     * @param float  $closingUnitCost Đơn giá tồn cuối kỳ
     * @param string $reference      Số chứng từ tham chiếu
     * @param string $createdBy      Người tạo chứng từ
     * @return array                 Thông tin bút toán đã ghi nhận
     */
    public function closePeriodicInventory(
        string $itemId, float $closingQty, float $closingUnitCost,
        string $reference, string $createdBy
    ): array;

    /**
     * Khóa sổ tồn kho cho một kỳ kế toán.
     * Sau khi khóa sổ, không thể thực hiện nghiệp vụ nhập/xuất trong kỳ này.
     *
     * @param int    $periodId   Mã kỳ kế toán
     * @param string $periodCode Mã định danh kỳ (VD: '2025-06')
     * @param string $startDate  Ngày bắt đầu kỳ
     * @param string $endDate    Ngày kết thúc kỳ
     * @param string $closedBy   Người thực hiện khóa sổ
     * @return array             Kết quả khóa sổ
     */
    public function closeInventoryForPeriod(
        int $periodId, string $periodCode, string $startDate, string $endDate, string $closedBy
    ): array;

    /**
     * Hoàn tác khóa sổ tồn kho (mở lại kỳ).
     *
     * @param int    $periodId     Mã kỳ kế toán
     * @param string $rolledBackBy Người thực hiện mở khóa
     * @return array               Kết quả mở khóa sổ
     */
    public function rollbackInventoryForPeriod(int $periodId, string $rolledBackBy): array;

    /**
     * Lấy báo cáo lão hóa hàng tồn kho (tồn kho theo thời gian lưu kho).
     *
     * @param string|null $itemId      Mã mặt hàng (null = tất cả)
     * @param string|null $warehouseId Mã kho (null = tất cả)
     * @return array                   Báo cáo lão hóa tồn kho
     */
    public function getAgingReport(?string $itemId = null, ?string $warehouseId = null): array;

    /**
     * Tính hệ số vòng quay hàng tồn kho trong kỳ.
     *
     * @param string      $periodStart Ngày bắt đầu kỳ
     * @param string      $periodEnd   Ngày kết thúc kỳ
     * @param string|null $itemId      Mã mặt hàng (null = tất cả)
     * @return array                   Hệ số vòng quay và số ngày tồn kho bình quân
     */
    public function getTurnoverRatio(
        string $periodStart, string $periodEnd, ?string $itemId = null
    ): array;

    /**
     * Lấy báo cáo giá trị hàng tồn kho (tồn kho theo giá trị, nhập/xuất/tồn).
     *
     * @param string|null $itemId      Mã mặt hàng (null = tất cả)
     * @param string|null $warehouseId Mã kho (null = tất cả)
     * @param string|null $fromDate    Ngày bắt đầu (null = đầu kỳ)
     * @param string|null $toDate      Ngày kết thúc (null = cuối kỳ)
     * @return array                   Báo cáo giá trị tồn kho
     */
    public function getValuationReport(
        ?string $itemId = null, ?string $warehouseId = null,
        ?string $fromDate = null, ?string $toDate = null
    ): array;
}
