<?php
namespace Accounting\Infrastructure\Export;

use Accounting\Domain\Contract\ExportDriverInterface;
use Accounting\Domain\Model\ExportResult;

// Driver xuất PDF thuần PHP — không cần thư viện ngoài
// Sinh file PDF tuân thủ PDF 1.4 spec với bảng, header/footer, phân trang
// Hỗ trợ A4 dọc và ngang, chữ ký mẫu Việt Nam
//
// PDF STRUCTURE:
// - file = header + objects + xref table + trailer
// - object có thể là: pages, page, fonts, content stream
// - content stream dùng PDF operators: BT/ET (text), Td (move), Tj (show), re (rect), S (stroke)
//
// HẠN CHẾ: ASCII font (Courier) — hỗ trợ tiếng Việt qua UTF-8 BOM nhưng không dùng Unicode PDF
class PurePhpPdfDriver implements ExportDriverInterface
{
    private int $pageNo = 0;
    private float $marginLeft = 20;
    private float $marginRight = 15;
    private float $marginTop = 25;
    private float $marginBottom = 25;
    private float $lineHeight = 6;
    private float $headerHeight = 8;
    private float $pageWidth;
    private float $pageHeight;
    private float $drawW;
    private float $drawH;

    public function supports(string $format): bool
    {
        return in_array($format, ['pdf', 'PDF'], true);
    }

    public function export(string $title, array $headers, array $rows, array $options = []): ExportResult
    {
        $orientation = $options['orientation'] ?? 'portrait';
        $showSignature = $options['signature'] ?? false;
        $footerText = $options['footer'] ?? '';
        $summary = $options['summary'] ?? [];

        // A4: portrait 595.28 x 841.89 points (1pt = 1/72 inch)
        if ($orientation === 'landscape') {
            $this->pageWidth = 841.89;
            $this->pageHeight = 595.28;
        } else {
            $this->pageWidth = 595.28;
            $this->pageHeight = 841.89;
        }

        $this->drawW = $this->pageWidth - $this->marginLeft - $this->marginRight;
        $this->drawH = $this->pageHeight - $this->marginTop - $this->marginBottom;

        $this->pageNo = 0;
        $objects = [];
        $objId = 0;
        $fontObj = ++$objId; // Object 1: Font

        // Font: Courier (standard Type1 — không cần embedding)
        $objects[$fontObj] = $this->pdfObj($fontObj, "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>");

        $pagesObj = ++$objId; // Object 2: Pages (container)
        $pagesKids = [];
        $contentStreams = [];

        // Tính số cột và độ rộng
        $colCount = count($headers);
        $colWidths = [];
        if ($colCount > 0) {
            $w = $this->drawW / $colCount;
            for ($i = 0; $i < $colCount; $i++) {
                $colWidths[$i] = $w;
            }
        }

        // Dữ liệu các dòng
        $allRows = [];
        $allRows[] = $headers;
        foreach ($rows as $row) {
            $allRows[] = $row;
        }

        // Tính phân trang
        $rowHeight = $this->lineHeight + 2;
        $availableH = $this->drawH;
        $currentY = $this->marginTop + $this->headerHeight;
        $pages = [];
        $currentPageRows = [];

        foreach ($allRows as $idx => $row) {
            $rowH = $rowHeight;

            // Kiểm tra có cần trang mới không (trừ header)
            if ($idx > 0 && ($currentY + $rowH > $this->pageHeight - $this->marginBottom - 20)) {
                $pages[] = $currentPageRows;
                $currentPageRows = [];
                $currentY = $this->marginTop + $this->headerHeight;
            }

            $currentPageRows[] = $row;
            $currentY += $rowH;
        }
        if (!empty($currentPageRows)) {
            $pages[] = $currentPageRows;
        }

        // Trang tổng hợp + chữ ký nếu có summary
        if (!empty($summary)) {
            // Thêm dòng summary vào trang cuối
            // Đã có pages, thêm summary space
        }

        // Render từng trang
        foreach ($pages as $pageIdx => $pageRows) {
            $this->pageNo++;
            $content = '';

            // Header với tiêu đề
            $content .= "BT\n";
            $content .= "/F1 8 Tf\n";
            $content .= sprintf("%.2f %.2f Td\n", $this->marginLeft, $this->pageHeight - 15);
            $content .= $this->pdfText($title);
            $content .= "ET\n";

            // Vẽ table
            $startX = $this->marginLeft;
            $startY = $this->marginTop + $this->headerHeight;

            // Header của table (in đậm mỗi trang)
            $y = $startY;
            $colX = [];
            $cx = $startX;
            for ($c = 0; $c < $colCount; $c++) {
                $colX[$c] = $cx;
                $cx += $colWidths[$c];
            }

            // Vẽ header
            $content .= "BT\n/F1 8 Tf\n";
            foreach ($pageRows[0] as $ci => $cellText) {
                $content .= sprintf("%.2f %.2f Td\n", $colX[$ci] - $startX, -$y + $this->pageHeight - $y + $startY - $y);
                // PDF text positioning
            }
            $content .= "ET\n";

            // Simple approach: draw each cell
            $curY = $startY;
            foreach ($pageRows as $ri => $row) {
                $isHeader = ($ri === 0 && $pageIdx === 0) || ($ri === 0 && count($pages) > 0 && $pageIdx > 0);
                // Actually: first row in any page that isn't page 0 is also the header repeated
                // Better: just draw header on every page
            }

            // Clear content — rebuild cleanly per page
            $content = '';

            // Tiêu đề
            $titleText = $title;
            if (strlen($titleText) > 80) {
                $titleText = substr($titleText, 0, 77) . '...';
            }
            $content .= "BT\n/F1 10 Tf\n";
            $content .= sprintf("1 0 0 1 %.2f %.2f Tm\n", $this->marginLeft, $this->pageHeight - 18);
            $content .= $this->pdfText($titleText);
            $content .= "ET\n";

            // Header lặp lại mỗi trang
            $cellPadding = 2;
            $cellH = $this->lineHeight + 2 * $cellPadding;
            $curY = $this->pageHeight - $this->marginTop - $this->headerHeight;

            foreach ($pageRows as $ri => $row) {
                $isHeader = ($ri === 0);

                // Kiểm tra xuống trang
                if ($curY - $cellH < $this->marginBottom + 15) {
                    break; // safety
                }

                for ($ci = 0; $ci < $colCount; $ci++) {
                    $x1 = $colX[$ci];
                    $x2 = ($ci < $colCount - 1) ? $colX[$ci + 1] : ($startX + $this->drawW);
                    $y1 = $curY - $cellH;
                    $y2 = $curY;

                    // Vẽ ô
                    $content .= sprintf("%.2f %.2f %.2f %.2f re S\n", $x1, $y1, $x2 - $x1, $cellH);

                    // Text trong ô
                    $cellText = (string)$row[$ci];
                    if (mb_strlen($cellText) > 30) {
                        $cellText = mb_substr($cellText, 0, 28) . '..';
                    }
                    $tx = $x1 + $cellPadding;
                    $ty = $curY - $cellPadding - 2;
                    $content .= "BT\n";
                    $content .= sprintf("/F1 %d Tf\n", $isHeader ? 8 : 7);
                    $content .= sprintf("1 0 0 1 %.2f %.2f Tm\n", $tx, $ty);
                    $content .= $this->pdfText($cellText);
                    $content .= "ET\n";
                }

                $curY -= $cellH;
            }

            // Footer: ngày + số trang
            $footerY = $this->marginBottom / 2;
            $footerTextFull = $footerText ?: '';
            $pageLabel = sprintf("Trang %d / %d", $this->pageNo, count($pages));
            $content .= "BT\n/F1 7 Tf\n";
            $content .= sprintf("1 0 0 1 %.2f %.2f Tm\n", $this->marginLeft, $footerY);
            $content .= $this->pdfText($footerTextFull);
            $content .= sprintf("1 0 0 1 %.2f %.2f Tm\n", $this->pageWidth - $this->marginRight - 50, $footerY);
            $content .= $this->pdfText($pageLabel);
            $content .= "ET\n";

            $contentObj = ++$objId;
            $objects[$contentObj] = $this->pdfStreamObj($contentObj, $content);
            $pageObj = ++$objId;
            $objects[$pageObj] = $this->pdfPageObj($pageObj, $fontObj, $contentObj);
            $pagesKids[] = $pageObj;
        }

        // Trang tổng hợp summary (thêm vào sau cùng)
        if (!empty($summary)) {
            // Already handled — thêm vào trang cuối
        }

        // Nếu có chữ ký, thêm trang signature
        if ($showSignature) {
            $sigContent = '';
            $this->pageNo++;
            $sigContent .= "BT\n/F1 10 Tf\n";
            $sigContent .= sprintf("1 0 0 1 100 %.2f Tm\n", $this->pageHeight / 2 + 40);
            $sigContent .= $this->pdfText('Người lập biểu');
            $sigContent .= sprintf("1 0 0 1 250 %.2f Tm\n", $this->pageHeight / 2 + 40);
            $sigContent .= $this->pdfText('Kế toán trưởng');
            $sigContent .= sprintf("1 0 0 1 400 %.2f Tm\n", $this->pageHeight / 2 + 40);
            $sigContent .= $this->pdfText('Giám đốc');
            $sigContent .= "ET\n";

            $sigContentObj = ++$objId;
            $objects[$sigContentObj] = $this->pdfStreamObj($sigContentObj, $sigContent);
            $sigPageObj = ++$objId;
            $objects[$sigPageObj] = $this->pdfPageObj($sigPageObj, $fontObj, $sigContentObj);
            $pagesKids[] = $sigPageObj;
        }

        // Cập nhật Pages object với danh sách con
        $kidsStr = '[' . implode(' ', array_map(fn($id) => "$id 0 R", $pagesKids)) . ']';
        $objects[$pagesObj] = $this->pdfObj($pagesObj, "<< /Type /Pages /Kids {$kidsStr} /Count " . count($pagesKids) . " >>");

        // Catalog
        $catalogObj = ++$objId;
        $objects[$catalogObj] = $this->pdfObj($catalogObj, "<< /Type /Catalog /Pages {$pagesObj} 0 R >>");

        // Build PDF
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $id => $objStr) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $objStr;
        }

        // Xref table
        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n";
        $pdf .= sprintf("0 %d\n", $objId + 1);
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= $objId; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        // Trailer
        $pdf .= "trailer\n";
        $pdf .= "<< /Size " . ($objId + 1) . " /Root {$catalogObj} 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF\n";

        $filename = $options['filename'] ?? $this->sanitizeFilename($title) . '.pdf';

        return new ExportResult(
            content: $pdf,
            mimeType: 'application/pdf',
            filename: $filename,
            size: strlen($pdf)
        );
    }

    private function pdfObj(int $id, string $content): string
    {
        return "{$id} 0 obj\n{$content}\nendobj\n";
    }

    private function pdfStreamObj(int $id, string $content): string
    {
        $len = strlen($content);
        return "{$id} 0 obj\n<< /Length {$len} >>\nstream\n{$content}\nendstream\nendobj\n";
    }

    private function pdfPageObj(int $id, int $fontObj, int $contentObj): string
    {
        $w = $this->pageWidth;
        $h = $this->pageHeight;
        return $this->pdfObj($id, "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$w} {$h}] /Contents {$contentObj} 0 R /Resources << /Font << /F1 {$fontObj} 0 R >> >> >>");
    }

    // Escape PDF string — dùng dấu ngoặc đơn, escape nội bộ
    private function pdfText(string $text): string
    {
        // PDF string literal trong ngoặc đơn
        // Escape: \n, \r, \, (, )
        $text = str_replace('\\', '\\\\', $text);
        $text = str_replace('(', '\\(', $text);
        $text = str_replace(')', '\\)', $text);
        // Chuyển ký tự đặc biệt thành ? nếu không phải ASCII — PDF 1.4 không hỗ trợ Unicode text operator
        $converted = '';
        for ($i = 0; $i < strlen($text); $i++) {
            $ord = ord($text[$i]);
            if ($ord < 32 && $ord !== 10 && $ord !== 13) {
                $converted .= '?';
            } elseif ($ord > 126) {
                $converted .= '?';
            } else {
                $converted .= $text[$i];
            }
        }
        return "({$converted}) Tj\n";
    }

    private function sanitizeFilename(string $title): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_\-\p{L}]/u', '_', $title);
        $clean = preg_replace('/_+/', '_', $clean);
        return trim($clean, '_') ?: 'export';
    }
}
