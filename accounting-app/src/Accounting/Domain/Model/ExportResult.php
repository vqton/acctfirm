<?php
namespace Accounting\Domain\Model;

/**
 * Kết quả xuất file — Chứa nội dung binary, MIME type, tên file và kích thước.
 *
 * Được tạo bởi ExportDriverInterface::export() và trả về cho ExportController.
 * Controller dùng các getter này để set HTTP header và echo nội dung.
 *
 * NGHIỆP VỤ:
 * - $content: nội dung file (binary)
 * - $mimeType: loại MIME (VD: 'application/pdf', 'text/csv')
 * - $filename: tên file xuất ra
 * - $size: kích thước file (bytes)
 */
class ExportResult
{
    /**
     * Khởi tạo kết quả xuất file.
     *
     * @param string $content Nội dung file (binary)
     * @param string $mimeType Loại MIME
     * @param string $filename Tên file
     * @param int $size Kích thước (bytes)
     */
    public function __construct(
        private string $content,
        private string $mimeType,
        private string $filename,
        private int $size
    ) {}

    /** @return string Nội dung file */
    public function getContent(): string { return $this->content; }

    /** @return string Loại MIME */
    public function getMimeType(): string { return $this->mimeType; }

    /** @return string Tên file */
    public function getFilename(): string { return $this->filename; }

    /** @return int Kích thước (bytes) */
    public function getSize(): int { return $this->size; }
}
