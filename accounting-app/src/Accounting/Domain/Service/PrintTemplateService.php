<?php
namespace Accounting\Domain\Service;

use PDO;
use InvalidArgumentException;
use RuntimeException;

// NGHIỆP VỤ: Print Template Service (R-10 Print Designer v1)
//
// Lưu trữ + render template in ấn cho các nghiệp vụ:
//   - ap_invoice (hóa đơn mua hàng)
//   - ar_invoice (hóa đơn bán hàng)
//   - sales_order (đơn bán hàng)
//   - payment (phiếu chi)
//   - receipt (phiếu thu)
//   - financial_report (báo cáo tài chính)
//
// Template engine (mini, không phụ thuộc thư viện ngoài):
//   {{var}}           → thay thế bằng giá trị (escape HTML)
//   {{{var}}}         → thay thế thô (KHÔNG escape) — dùng cho HTML có sẵn
//   {{#if var}}...{{/if}}     → điều kiện (var truthy = không rỗng/0/null/false)
//   {{#unless var}}...{{/unless}} → điều kiện phủ định
//   {{#each list}}...{{/each}} → lặp qua array, trong block {{this}} = item, {{@index}} = index
//   {{#each list}}...{{else}}...{{/each}} → nếu list rỗng render else block
//
// RỦI RO:
//   - Template chứa code nguy hiểm (XSS): escape HTML mặc định, chỉ {{{var}}} mới thô
//   - Template tham chiếu biến không tồn tại: trả về "[missing: varname]" thay vì lỗi
//   - Loop vô hạn: giới hạn max iterations = 1000
//   - Template quá lớn: giới hạn content size = 1MB
class PrintTemplateService
{
    private const MAX_TEMPLATE_SIZE = 1_048_576; // 1MB
    private const MAX_LOOP_ITERATIONS = 1000;
    private const MAX_NESTED_DEPTH = 16;

    public function __construct(private PDO $pdo) {}

    // Lấy template active mặc định cho 1 loại
    public function getDefault(string $templateType): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT * FROM print_templates
             WHERE template_type = ? AND is_default = 1 AND is_active = 1
             ORDER BY updated_at DESC LIMIT 1"
        );
        $stmt->execute([$templateType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    // Lấy template theo id
    public function getById(string $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM print_templates WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrate($row) : null;
    }

    // Danh sách templates (filter theo type + active)
    public function list(string $templateType = null, bool $activeOnly = true): array
    {
        $sql = "SELECT * FROM print_templates WHERE 1=1";
        $params = [];
        if ($templateType !== null) {
            $sql .= " AND template_type = ?";
            $params[] = $templateType;
        }
        if ($activeOnly) {
            $sql .= " AND is_active = 1";
        }
        $sql .= " ORDER BY template_type, is_default DESC, name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[] = $this->hydrate($row);
        }
        return $out;
    }

    // Tạo mới / cập nhật template
    // Trả về id; nếu trùng (template_type, code) → update
    public function save(array $data, string $actor): string
    {
        foreach (['template_type', 'code', 'name', 'content'] as $req) {
            if (empty($data[$req])) {
                throw new InvalidArgumentException("Thiếu trường bắt buộc: {$req}");
            }
        }
        if (strlen($data['content']) > self::MAX_TEMPLATE_SIZE) {
            throw new InvalidArgumentException("Template vượt quá 1MB");
        }
        // Validate template syntax (dry-run render with sample data)
        $this->validateSyntax($data['content']);

        $variables = isset($data['variables']) ? json_encode($data['variables'], JSON_UNESCAPED_UNICODE) : null;
        $id = $data['id'] ?? 'tpl_' . substr(uniqid('', true), 0, 15);
        $isDefault = !empty($data['is_default']) ? 1 : 0;
        $isActive = isset($data['is_active']) ? (int)(bool)$data['is_active'] : 1;
        $description = $data['description'] ?? null;

        // Check duplicate (template_type, code)
        $check = $this->pdo->prepare(
            "SELECT id FROM print_templates WHERE template_type = ? AND code = ?"
        );
        $check->execute([$data['template_type'], $data['code']]);
        $existing = $check->fetchColumn();

        if ($existing) {
            // Giữ nguyên variables nếu không truyền (update name/content/desc thôi)
            if (!isset($data['variables'])) {
                $cur = $this->pdo->prepare("SELECT variables FROM print_templates WHERE id = ?");
                $cur->execute([$existing]);
                $variables = $cur->fetchColumn() ?: null;
            }
            $stmt = $this->pdo->prepare(
                "UPDATE print_templates SET name = ?, description = ?, content = ?, variables = ?,
                    is_default = ?, is_active = ? WHERE id = ?"
            );
            $stmt->execute([
                $data['name'], $description, $data['content'], $variables,
                $isDefault, $isActive, $existing,
            ]);
            return $existing;
        }

        $stmt = $this->pdo->prepare(
            "INSERT INTO print_templates (id, template_type, code, name, description, content,
                variables, is_default, is_active, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $id, $data['template_type'], $data['code'], $data['name'], $description,
            $data['content'], $variables, $isDefault, $isActive, $actor,
        ]);
        return $id;
    }

    // Xóa mềm (set is_active = 0)
    public function deactivate(string $id): bool
    {
        $stmt = $this->pdo->prepare("UPDATE print_templates SET is_active = 0 WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    // Render template với data
    // Trả về HTML string đã substitute
    public function render(string $templateContent, array $data): string
    {
        return $this->compile($templateContent, $data, 0);
    }

    // Render template từ DB (id) với data
    public function renderById(string $templateId, array $data): string
    {
        $tpl = $this->getById($templateId);
        if (!$tpl) {
            throw new RuntimeException("Không tìm thấy template: {$templateId}");
        }
        return $this->render($tpl['content'], $data);
    }

    // Lấy danh sách variables đã khai báo trong template
    public function getDeclaredVariables(array $template): array
    {
        if (!$template['variables']) return [];
        $vars = json_decode($template['variables'], true);
        return is_array($vars) ? $vars : [];
    }

    // Validate cú pháp template bằng dry-run render với empty data
    // Throw nếu có lỗi parse
    private function validateSyntax(string $content): void
    {
        // Check balanced {{#if}} {{/if}}, {{#each}} {{/each}}, {{#unless}} {{/unless}}
        foreach (['if', 'each', 'unless'] as $tag) {
            $open = preg_match_all('/\{\{#' . $tag . '\s+[^}]+\}\}/', $content);
            $close = preg_match_all('/\{\{\/' . $tag . '\}\}/', $content);
            if ($open !== $close) {
                throw new InvalidArgumentException("Template không cân bằng thẻ {{#{$tag}}}/{{/{$tag}}} ({$open} mở vs {$close} đóng)");
            }
        }
    }

    // Compile template với data
    private function compile(string $template, array $data, int $depth): string
    {
        if ($depth > self::MAX_NESTED_DEPTH) {
            throw new RuntimeException("Template nested quá sâu (max " . self::MAX_NESTED_DEPTH . ")");
        }

        // 1) Xử lý {{#each list}}...{{/each}}
        $template = $this->processEach($template, $data, $depth);

        // 2) Xử lý {{#if var}}...{{else}}...{{/if}} và {{#unless var}}...{{/unless}}
        $template = $this->processIf($template, $data, $depth);

        // 3) Xử lý {{{var}}} (raw) TRƯỚC {{var}} (escape) — chấp nhận @index, @key
        $template = preg_replace_callback('/\{\{\{(@?[a-zA-Z_][a-zA-Z0-9_\.\[\]]*)\}\}\}/', function($m) use ($data) {
            $val = $this->resolvePath($m[1], $data);
            return $val === null ? '' : (string)$val;
        }, $template);

        // 4) Xử lý {{var}} (escape HTML)
        $template = preg_replace_callback('/\{\{(@?[a-zA-Z_][a-zA-Z0-9_\.\[\]]*)\}\}/', function($m) use ($data) {
            $val = $this->resolvePath($m[1], $data);
            if ($val === null) return "[missing: {$m[1]}]";
            if (is_array($val) || is_object($val)) return '[object]';
            return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
        }, $template);

        return $template;
    }

    private function processEach(string $template, array $data, int $depth): string
    {
        $pattern = '/\{\{#each\s+([a-zA-Z_][a-zA-Z0-9_\.\[\]]*)\}\}(.*?)\{\{\/each\}\}/s';
        return preg_replace_callback($pattern, function($m) use ($data, $depth) {
            $listPath = $m[1];
            $body = $m[2];
            $list = $this->resolvePath($listPath, $data);

            if (!is_array($list) || count($list) === 0) {
                // Check {{else}} block — chỉ render else, KHÔNG render phần trước else
                if (preg_match('/^(.*?)\{\{else\}\}(.*)$/s', $body, $elseMatch)) {
                    return $this->compile($elseMatch[2], $data, $depth + 1);
                }
                return '';
            }

            $out = '';
            $idx = 0;
            foreach ($list as $item) {
                if ($idx++ >= self::MAX_LOOP_ITERATIONS) break;
                $itemData = is_array($item) ? $item : ['this' => $item];
                $itemData['@index'] = $idx - 1;
                $itemData['this'] = is_array($item) ? null : $item;
                $out .= $this->compile($body, $itemData, $depth + 1);
            }
            return $out;
        }, $template);
    }

    private function processIf(string $template, array $data, int $depth): string
    {
        // {{#if var}}body{{else}}elseBody{{/if}}
        $pattern = '/\{\{#if\s+([a-zA-Z_][a-zA-Z0-9_\.\[\]]*)\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{\/if\}\}/s';
        $template = preg_replace_callback($pattern, function($m) use ($data, $depth) {
            $varPath = $m[1];
            $thenBody = $m[2];
            $elseBody = $m[3] ?? '';
            $val = $this->resolvePath($varPath, $data);
            $truthy = $val !== null && $val !== '' && $val !== 0 && $val !== false && $val !== [];
            $chosen = $truthy ? $thenBody : $elseBody;
            return $this->compile($chosen, $data, $depth + 1);
        }, $template);

        // {{#unless var}}body{{/unless}}
        $patternUnless = '/\{\{#unless\s+([a-zA-Z_][a-zA-Z0-9_\.\[\]]*)\}\}(.*?)\{\{\/unless\}\}/s';
        $template = preg_replace_callback($patternUnless, function($m) use ($data, $depth) {
            $val = $this->resolvePath($m[1], $data);
            $truthy = $val !== null && $val !== '' && $val !== 0 && $val !== false && $val !== [];
            return $truthy ? '' : $this->compile($m[2], $data, $depth + 1);
        }, $template);

        return $template;
    }

    // Resolve path kiểu "a.b.c" hoặc "items[0].name"
    private function resolvePath(string $path, array $data)
    {
        // items[0].name → items.0.name
        $path = preg_replace('/\[(\d+)\]/', '.$1', $path);
        $parts = explode('.', $path);
        $cur = $data;
        foreach ($parts as $p) {
            if (is_array($cur) && array_key_exists($p, $cur)) {
                $cur = $cur[$p];
            } elseif (is_object($cur) && isset($cur->$p)) {
                $cur = $cur->$p;
            } else {
                return null;
            }
        }
        return $cur;
    }

    private function hydrate(array $row): array
    {
        $row['variables_arr'] = $row['variables'] ? json_decode($row['variables'], true) : [];
        $row['is_default'] = (bool)$row['is_default'];
        $row['is_active'] = (bool)$row['is_active'];
        return $row;
    }
}
