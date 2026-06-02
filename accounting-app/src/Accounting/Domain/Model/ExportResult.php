<?php
namespace Accounting\Domain\Model;

// Kết quả xuất file — chứa nội dung binary, MIME type, tên file và kích thước
// Được tạo bởi ExportDriverInterface::export() và trả về cho ExportController
// Controller dùng các getter này để set HTTP header và echo nội dung
class ExportResult
{
    public function __construct(
        private string $content,
        private string $mimeType,
        private string $filename,
        private int $size
    ) {}

    public function getContent(): string { return $this->content; }
    public function getMimeType(): string { return $this->mimeType; }
    public function getFilename(): string { return $this->filename; }
    public function getSize(): int { return $this->size; }
}
