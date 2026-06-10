<?php
namespace Accounting\Infrastructure\SubLedger;

use Accounting\Domain\Contract\SubLedgerReportInterface;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Domain\Service\GlService;
use Accounting\Domain\Service\PeriodService;

/**
 * SỔ CÁI: Báo cáo chi tiết phát sinh theo tài khoản (Mẫu S05-DN theo TT 99).
 *
 * Nghiệp vụ: Sổ cái là báo cáo nền tảng, hiển thị tất cả giao dịch của một tài khoản
 * kèm số dư lũy kế. Dùng GlService::getGeneralLedger() làm nguồn dữ liệu chính.
 *
 * RỦI RO: GlService trả về dữ liệu thời gian thực. Nếu kỳ đã đóng mà GL vẫn thay đổi
 * → số dư không khớp với báo cáo tài chính đã nộp → rủi ro kiểm toán.
 */
class GeneralLedgerReport implements SubLedgerReportInterface
{
    private GlService $glService;
    private AccountRepositoryInterface $accountRepo;
    private PeriodService $periodService;

    /**
     * @param GlService $glService Service sổ cái.
     * @param AccountRepositoryInterface $accountRepo Repository tài khoản.
     * @param PeriodService $periodService Service kỳ kế toán.
     */
    public function __construct(GlService $glService, AccountRepositoryInterface $accountRepo, PeriodService $periodService)
    {
        $this->glService = $glService;
        $this->accountRepo = $accountRepo;
        $this->periodService = $periodService;
    }

    /**
     * Lấy loại báo cáo.
     *
     * @return string 'general_ledger'.
     */
    public function getReportType(): string
    {
        return 'general_ledger';
    }

    /**
     * Lấy tham số báo cáo.
     *
     * @return array Mảng tham số với name, label, type, required.
     */
    public function getParameters(): array
    {
        return [
            ['name' => 'account_code', 'label' => 'Tài khoản', 'type' => 'account_select', 'required' => true],
            ['name' => 'from_date', 'label' => 'Từ ngày', 'type' => 'date', 'required' => false],
            ['name' => 'to_date', 'label' => 'Đến ngày', 'type' => 'date', 'required' => false],
        ];
    }

    /**
     * Lấy dữ liệu sổ cái.
     *
     * @param array $params Tham số: account_code, from_date, to_date.
     * @return array Dữ liệu báo cáo gồm title, period, opening_balance, closing_balance, headers, rows, totals.
     * @throws \InvalidArgumentException Nếu không chọn tài khoản.
     */
    public function getData(array $params): array
    {
        $accountCode = $params['account_code'] ?? '';
        if (!$accountCode) {
            throw new \InvalidArgumentException('Vui lòng chọn tài khoản kế toán.');
        }

        $fromDate = $params['from_date'] ?? null;
        $toDate = $params['to_date'] ?? null;

        // Lấy dữ liệu sổ cái từ GlService
        $glData = $this->glService->getGeneralLedger($accountCode, $fromDate, $toDate);

        $account = $this->accountRepo->findByCode($accountCode);

        // Xây dựng headers và rows cho export
        $headers = ['Ngày', 'Số CT', 'Diễn giải', 'Phát sinh Nợ', 'Phát sinh Có', 'TK ĐƯ', 'Số dư'];

        $rows = [];
        $running = $glData['opening_balance'];
        $isDebitNormal = in_array($glData['account_type'] ?? '', ['asset', 'expense']);

        foreach ($glData['entries'] as $entry) {
            $running = $entry['running_balance'];
            $rows[] = [
                'date' => $entry['date'],
                'reference' => $entry['reference'],
                'description' => $entry['description'],
                'debit' => $entry['debit'],
                'credit' => $entry['credit'],
                'contra_account' => $entry['contra_account'],
                'running_balance' => $running,
            ];
        }

        return [
            'report_type' => 'general_ledger',
            'title' => 'Sổ cái tài khoản ' . $accountCode . ' - ' . ($account ? $account->getName() : ''),
            'period' => ($fromDate ?? 'Đầu kỳ') . ' → ' . ($toDate ?? 'Cuối kỳ'),
            'opening_balance' => $glData['opening_balance'],
            'closing_balance' => $glData['closing_balance'],
            'headers' => $headers,
            'rows' => $rows,
            'totals' => [
                'total_debit' => array_sum(array_column($rows, 'debit')),
                'total_credit' => array_sum(array_column($rows, 'credit')),
            ],
            'account_info' => [
                'code' => $accountCode,
                'name' => $account ? $account->getName() : '',
                'type' => $glData['account_type'] ?? '',
            ],
        ];
    }
}
