<?php
namespace Accounting\Interfaces\HTTP;

use Accounting\Domain\Model\Account;
use Accounting\Domain\Repository\AccountRepositoryInterface;
use Accounting\Infrastructure\Database\AuditLogger;

class AccountController
{
    private AccountRepositoryInterface $repo;

    public function __construct(AccountRepositoryInterface $repo) { $this->repo = $repo; }

    public function list(): void { echo json_encode(array_map(fn($x) => $x->toArray(), $this->repo->findAll())); }

    public function get(string $id): void
    {
        $x = $this->repo->findById($id);
        if (!$x) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        echo json_encode($x->toArray());
    }

    public function create(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['code'], $data['name'], $data['type'])) {
            http_response_code(400); echo json_encode(['error' => 'code, name, type required']); return;
        }
        if ($this->repo->findByCode($data['code'])) {
            http_response_code(409); echo json_encode(['error' => 'Code already exists']); return;
        }
        $x = new Account(
            $data['id'] ?? uniqid('coa_'), $data['code'], $data['name'], $data['type'],
            $data['parent_id'] ?? null, $data['normal_balance'] ?? 'D',
            $data['account_class'] ?? null, $data['description'] ?? null
        );
        $this->repo->save($x);
        AuditLogger::log('account.create', 'account', $x->getId(), null, $x->toArray(), $_SERVER['PHP_AUTH_USER'] ?? 'system');
        http_response_code(201);
        echo json_encode($x->toArray());
    }

    public function update(string $id): void
    {
        $x = $this->repo->findById($id);
        if (!$x) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) { http_response_code(400); echo json_encode(['error' => 'Invalid data']); return; }
        $old = $x->toArray();
        if (isset($data['code'])) $x->setCode($data['code']);
        if (isset($data['name'])) $x->setName($data['name']);
        if (isset($data['type'])) $x->setType($data['type']);
        if (isset($data['parent_id'])) $x->setParentId($data['parent_id']);
        if (isset($data['normal_balance'])) $x->setNormalBalance($data['normal_balance']);
        if (isset($data['account_class'])) $x->setAccountClass($data['account_class']);
        if (isset($data['description'])) $x->setDescription($data['description']);
        if (isset($data['status'])) $x->setStatus((bool)$data['status']);
        $this->repo->save($x);
        AuditLogger::log('account.update', 'account', $id, $old, $x->toArray(), $_SERVER['PHP_AUTH_USER'] ?? 'system');
        echo json_encode($x->toArray());
    }

    public function delete(string $id): void
    {
        $x = $this->repo->findById($id);
        if (!$x) { http_response_code(404); echo json_encode(['error' => 'Not found']); return; }
        $old = $x->toArray();
        $this->repo->delete($id);
        AuditLogger::log('account.delete', 'account', $id, $old, null, $_SERVER['PHP_AUTH_USER'] ?? 'system');
        echo json_encode(['message' => 'Deleted']);
    }

    public function seed(): void
    {
        // Full Chart of Accounts per Circular 99/2025/TT-BTC Appendix II
        // See docs/APPENDIX_II_COA.md for complete reference.
        // Format: [code, name, type, class, normal_balance, parent_code?]
        $coa = [];

        // ======== LOẠI 1-2: TÀI SẢN (Assets) ========
        $coa[] = ['111','Tiền mặt','asset','1','D'];
        $coa[] = ['112','Tiền gửi không kỳ hạn','asset','1','D'];
        $coa[] = ['113','Tiền đang chuyển','asset','1','D'];
        $coa[] = ['121','Chứng khoán kinh doanh','asset','1','D'];
        $coa[] = ['128','Đầu tư nắm giữ đến ngày đáo hạn','asset','1','D'];
        $coa[] = ['1281','Tiền gửi có kỳ hạn','asset','1','D','128'];
        $coa[] = ['1282','Trái phiếu','asset','1','D','128'];
        $coa[] = ['1283','Cho vay','asset','1','D','128'];
        $coa[] = ['1288','Đầu tư khác nắm giữ đến ngày đáo hạn','asset','1','D','128'];
        $coa[] = ['131','Phải thu của khách hàng','asset','1','D'];
        $coa[] = ['133','Thuế GTGT được khấu trừ','asset','1','D'];
        $coa[] = ['1331','Thuế GTGT được khấu trừ của hàng hóa, dịch vụ','asset','1','D','133'];
        $coa[] = ['1332','Thuế GTGT được khấu trừ của TSCĐ','asset','1','D','133'];
        $coa[] = ['136','Phải thu nội bộ','asset','1','D'];
        $coa[] = ['1361','Vốn kinh doanh ở đơn vị trực thuộc','asset','1','D','136'];
        $coa[] = ['1362','Phải thu nội bộ về chênh lệch tỷ giá','asset','1','D','136'];
        $coa[] = ['1363','Phải thu nội bộ về chi phí đi vay đủ điều kiện vốn hóa','asset','1','D','136'];
        $coa[] = ['1368','Phải thu nội bộ khác','asset','1','D','136'];
        $coa[] = ['138','Phải thu khác','asset','1','D'];
        $coa[] = ['1381','Tài sản thiếu chờ xử lý','asset','1','D','138'];
        $coa[] = ['1383','Thuế TTĐB của hàng nhập khẩu','asset','1','D','138'];
        $coa[] = ['1388','Phải thu khác','asset','1','D','138'];
        $coa[] = ['141','Tạm ứng','asset','1','D'];
        $coa[] = ['151','Hàng mua đang đi đường','asset','2','D'];
        $coa[] = ['152','Nguyên liệu, vật liệu','asset','2','D'];
        $coa[] = ['153','Công cụ, dụng cụ','asset','2','D'];
        $coa[] = ['154','Chi phí SXKD dở dang','asset','2','D'];
        $coa[] = ['155','Sản phẩm','asset','2','D'];
        $coa[] = ['156','Hàng hóa','asset','2','D'];
        $coa[] = ['157','Hàng gửi đi bán','asset','2','D'];
        $coa[] = ['158','Nguyên liệu, vật tư tại kho bảo thuế','asset','2','D'];
        $coa[] = ['171','Giao dịch mua bán lại trái phiếu chính phủ','asset','1','D'];
        $coa[] = ['211','TSCĐ hữu hình','asset','2','D'];
        $coa[] = ['212','TSCĐ thuê tài chính','asset','2','D'];
        $coa[] = ['213','TSCĐ vô hình','asset','2','D'];
        $coa[] = ['214','Hao mòn TSCĐ','asset','2','C'];
        $coa[] = ['2141','Hao mòn TSCĐ hữu hình','asset','2','C','214'];
        $coa[] = ['2142','Hao mòn TSCĐ thuê tài chính','asset','2','C','214'];
        $coa[] = ['2143','Hao mòn TSCĐ vô hình','asset','2','C','214'];
        $coa[] = ['2147','Hao mòn BĐS đầu tư','asset','2','C','214'];
        $coa[] = ['215','Tài sản sinh học','asset','2','D'];
        $coa[] = ['2151','Súc vật nuôi cho sản phẩm định kỳ','asset','2','D','215'];
        $coa[] = ['2152','Súc vật nuôi lấy sản phẩm một lần','asset','2','D','215'];
        $coa[] = ['2153','Cây trồng theo mùa vụ hoặc lấy sản phẩm một lần','asset','2','D','215'];
        $coa[] = ['217','Bất động sản đầu tư','asset','2','D'];
        $coa[] = ['221','Đầu tư vào công ty con','asset','2','D'];
        $coa[] = ['222','Đầu tư vào công ty liên doanh, liên kết','asset','2','D'];
        $coa[] = ['228','Đầu tư khác','asset','2','D'];
        $coa[] = ['2281','Đầu tư góp vốn vào đơn vị khác','asset','2','D','228'];
        $coa[] = ['2288','Đầu tư khác','asset','2','D','228'];
        $coa[] = ['229','Dự phòng tổn thất tài sản','asset','2','C'];
        $coa[] = ['2291','DP giảm giá chứng khoán kinh doanh','asset','2','C','229'];
        $coa[] = ['2292','DP tổn thất đầu tư vào đơn vị khác','asset','2','C','229'];
        $coa[] = ['2293','DP phải thu khó đòi','asset','2','C','229'];
        $coa[] = ['2294','DP giảm giá hàng tồn kho','asset','2','C','229'];
        $coa[] = ['2295','DP tổn thất tài sản sinh học','asset','2','C','229'];
        $coa[] = ['241','XDCB dở dang','asset','2','D'];
        $coa[] = ['2411','Mua sắm TSCĐ','asset','2','D','241'];
        $coa[] = ['2412','Xây dựng cơ bản','asset','2','D','241'];
        $coa[] = ['2413','Sửa chữa, bảo dưỡng định kỳ TSCĐ','asset','2','D','241'];
        $coa[] = ['2414','Nâng cấp, cải tạo TSCĐ','asset','2','D','241'];
        $coa[] = ['242','Chi phí chờ phân bổ','asset','2','D'];
        $coa[] = ['243','Tài sản thuế TNDN hoãn lại','asset','2','D'];
        $coa[] = ['244','Ký quỹ, ký cược','asset','2','D'];

        // ======== LOẠI 3: NỢ PHẢI TRẢ (Liabilities) ========
        $coa[] = ['331','Phải trả người bán','liability','3','C'];
        $coa[] = ['332','Phải trả cổ tức, lợi nhuận','liability','3','C'];
        $coa[] = ['333','Thuế và các khoản phải nộp NN','liability','3','C'];
        $coa[] = ['3331','Thuế GTGT phải nộp','liability','3','C','333'];
        $coa[] = ['33311','Thuế GTGT đầu ra','liability','3','C','3331'];
        $coa[] = ['33312','Thuế GTGT hàng nhập khẩu','liability','3','C','3331'];
        $coa[] = ['3332','Thuế TTĐB','liability','3','C','333'];
        $coa[] = ['3333','Thuế xuất, nhập khẩu','liability','3','C','333'];
        $coa[] = ['3334','Thuế TNDN','liability','3','C','333'];
        $coa[] = ['3335','Thuế TNCN','liability','3','C','333'];
        $coa[] = ['3336','Thuế tài nguyên','liability','3','C','333'];
        $coa[] = ['3337','Thuế nhà đất, tiền thuê đất','liability','3','C','333'];
        $coa[] = ['3338','Thuế BVMT và các loại khác','liability','3','C','333'];
        $coa[] = ['33381','Thuế BVMT','liability','3','C','3338'];
        $coa[] = ['33382','Các loại thuế khác','liability','3','C','3338'];
        $coa[] = ['3339','Phí, lệ phí và các khoản khác','liability','3','C','333'];
        $coa[] = ['334','Phải trả người lao động','liability','3','C'];
        $coa[] = ['335','Chi phí phải trả','liability','3','C'];
        $coa[] = ['336','Phải trả nội bộ','liability','3','C'];
        $coa[] = ['3361','Phải trả nội bộ về vốn kinh doanh','liability','3','C','336'];
        $coa[] = ['3362','Phải trả nội bộ về chênh lệch tỷ giá','liability','3','C','336'];
        $coa[] = ['3363','Phải trả nội bộ về chi phí đi vay vốn hóa','liability','3','C','336'];
        $coa[] = ['3368','Phải trả nội bộ khác','liability','3','C','336'];
        $coa[] = ['337','Thanh toán theo tiến độ hợp đồng XD','liability','3','C'];
        $coa[] = ['338','Phải trả khác','liability','3','C'];
        $coa[] = ['3381','Tài sản thừa chờ giải quyết','liability','3','C','338'];
        $coa[] = ['3382','Kinh phí công đoàn','liability','3','C','338'];
        $coa[] = ['3383','BHXH','liability','3','C','338'];
        $coa[] = ['3384','BHYT','liability','3','C','338'];
        $coa[] = ['3386','BHTN','liability','3','C','338'];
        $coa[] = ['3387','Doanh thu chờ phân bổ','liability','3','C','338'];
        $coa[] = ['3388','Phải trả, phải nộp khác','liability','3','C','338'];
        $coa[] = ['341','Vay và nợ thuê tài chính','liability','3','C'];
        $coa[] = ['3411','Các khoản đi vay','liability','3','C','341'];
        $coa[] = ['3412','Nợ thuê tài chính','liability','3','C','341'];
        $coa[] = ['343','Trái phiếu chuyển đổi','liability','3','C'];
        $coa[] = ['3431','Trái phiếu thường','liability','3','C','343'];
        $coa[] = ['3432','Trái phiếu chuyển đổi','liability','3','C','343'];
        $coa[] = ['344','Nhận ký quỹ, ký cược','liability','3','C'];
        $coa[] = ['347','Thuế TNDN hoãn lại phải trả','liability','3','C'];
        $coa[] = ['352','Dự phòng phải trả','liability','3','C'];
        $coa[] = ['3521','DP bảo hành sản phẩm, hàng hóa','liability','3','C','352'];
        $coa[] = ['3522','DP bảo hành công trình XD','liability','3','C','352'];
        $coa[] = ['3523','DP tái cơ cấu DN','liability','3','C','352'];
        $coa[] = ['3525','DP phải trả khác','liability','3','C','352'];
        $coa[] = ['353','Quỹ khen thưởng, phúc lợi','liability','3','C'];
        $coa[] = ['3531','Quỹ khen thưởng','liability','3','C','353'];
        $coa[] = ['3532','Quỹ phúc lợi','liability','3','C','353'];
        $coa[] = ['3533','Quỹ phúc lợi hình thành TSCĐ','liability','3','C','353'];
        $coa[] = ['3534','Quỹ thưởng BQL điều hành','liability','3','C','353'];
        $coa[] = ['356','Quỹ phát triển KHCN','liability','3','C'];
        $coa[] = ['3561','Quỹ phát triển KHCN','liability','3','C','356'];
        $coa[] = ['3562','Quỹ KHCN đã hình thành TSCĐ','liability','3','C','356'];
        $coa[] = ['357','Quỹ bình ổn giá','liability','3','C'];
        $coa[] = ['358','Doanh thu chưa thực hiện ngắn hạn','liability','3','C'];

        // ======== LOẠI 4: VỐN CHỦ SỞ HỮU (Equity) ========
        $coa[] = ['411','Vốn đầu tư của chủ sở hữu','equity','4','C'];
        $coa[] = ['4111','Vốn góp của chủ sở hữu','equity','4','C','411'];
        $coa[] = ['41111','Cổ phiếu phổ thông có quyền biểu quyết','equity','4','C','4111'];
        $coa[] = ['41112','Cổ phiếu ưu đãi','equity','4','C','4111'];
        $coa[] = ['4112','Thặng dư vốn cổ phần','equity','4','C','411'];
        $coa[] = ['4113','Quyền chọn chuyển đổi trái phiếu','equity','4','C','411'];
        $coa[] = ['4118','Vốn khác','equity','4','C','411'];
        $coa[] = ['412','Chênh lệch đánh giá lại tài sản','equity','4','C'];
        $coa[] = ['413','Chênh lệch tỷ giá hối đoái','equity','4','C'];
        $coa[] = ['414','Quỹ đầu tư phát triển','equity','4','C'];
        $coa[] = ['418','Các quỹ khác thuộc vốn chủ sở hữu','equity','4','C'];
        $coa[] = ['419','Cổ phiếu mua lại của chính mình','equity','4','D'];
        $coa[] = ['421','Lợi nhuận sau thuế chưa phân phối','equity','4','C'];
        $coa[] = ['4211','LNST chưa phân phối lũy kế','equity','4','C','421'];
        $coa[] = ['4212','LNST chưa phân phối năm nay','equity','4','C','421'];

        // ======== LOẠI 5: DOANH THU (Revenue) ========
        $coa[] = ['511','Doanh thu bán hàng và CCDV','revenue','5','C'];
        $coa[] = ['515','Doanh thu hoạt động tài chính','revenue','5','C'];
        $coa[] = ['521','Các khoản giảm trừ doanh thu','revenue','5','D'];

        // ======== LOẠI 6: CHI PHÍ SXKD (Expenses) ========
        $coa[] = ['621','Chi phí NVL trực tiếp','expense','6','D'];
        $coa[] = ['622','Chi phí nhân công trực tiếp','expense','6','D'];
        $coa[] = ['623','Chi phí sử dụng máy thi công','expense','6','D'];
        $coa[] = ['627','Chi phí sản xuất chung','expense','6','D'];
        $coa[] = ['632','Giá vốn hàng bán','expense','6','D'];
        $coa[] = ['635','Chi phí tài chính','expense','6','D'];
        $coa[] = ['641','Chi phí bán hàng','expense','6','D'];
        $coa[] = ['642','Chi phí quản lý doanh nghiệp','expense','6','D'];

        // ======== LOẠI 7: THU NHẬP KHÁC (Other Income) ========
        $coa[] = ['711','Thu nhập khác','revenue','7','C'];

        // ======== LOẠI 8: CHI PHÍ KHÁC (Other Expenses) ========
        $coa[] = ['811','Chi phí khác','expense','8','D'];
        $coa[] = ['821','Chi phí thuế TNDN','expense','8','D'];
        $coa[] = ['8211','Chi phí thuế TNDN hiện hành','expense','8','D','821'];
        $coa[] = ['82112','Chi phí thuế tối thiểu toàn cầu','expense','8','D','821'];
        $coa[] = ['8212','Chi phí thuế TNDN hoãn lại','expense','8','D','821'];

        // ======== LOẠI 9: XÁC ĐỊNH KQKD (Result) ========
        $coa[] = ['911','Xác định kết quả kinh doanh','expense','9','D'];

        // Seeding: create new + update existing (handles renames)
        $count = 0;
        $updateCount = 0;
        foreach ($coa as $row) {
            $existing = $this->repo->findByCode($row[0]);
            if ($existing) {
                $changed = false;
                if ($existing->getName() !== $row[1]) { $existing->setName($row[1]); $changed = true; }
                if ($existing->getType() !== $row[2]) { $existing->setType($row[2]); $changed = true; }
                if ($existing->getAccountClass() !== $row[3]) { $existing->setAccountClass($row[3]); $changed = true; }
                if ($existing->getNormalBalance() !== $row[4]) { $existing->setNormalBalance($row[4]); $changed = true; }
                if (($existing->getParentId() ?: null) !== ($row[5] ?? null)) { $existing->setParentId($row[5] ?? null); $changed = true; }
                if ($changed) { $this->repo->save($existing); $updateCount++; }
                continue;
            }
            $a = new Account(uniqid('coa_'), $row[0], $row[1], $row[2],
                $row[5] ?? null, $row[4], $row[3]);
            $this->repo->save($a);
            $count++;
        }

        // Mark all Level 1 accounts that have sub-accounts as control accounts
        $parents = [];
        foreach ($coa as $row) {
            if (!empty($row[5])) $parents[$row[5]] = true;
        }
        foreach (array_keys($parents) as $code) {
            $a = $this->repo->findByCode($code);
            if ($a && !$a->isControl()) { $a->setControl(true); $this->repo->save($a); }
        }

        AuditLogger::log('account.seed', 'account', null, null, ['new' => $count, 'updated' => $updateCount], $_SERVER['PHP_AUTH_USER'] ?? 'system');
        echo json_encode(['message' => 'Seeded', 'new' => $count, 'updated' => $updateCount]);
    }
}