<?php
// E-INVOICE SERVICES
// Phụ thuộc: 00_core (pdo), 20_infrastructure (posting), 30_journal, 33_financial (vatService), 40_controllers

use Accounting\Domain\Contract\EInvoiceGatewayInterface;
use Accounting\Infrastructure\EInvoice\XmlInvoiceBuilder;
use Accounting\Infrastructure\EInvoice\DigitalSignatureService;
use Accounting\Infrastructure\EInvoice\VnptEInvoiceGateway;
use Accounting\Domain\Service\InvoiceService;
use Accounting\Domain\Service\VatDeclarationEngine;
use Accounting\Interfaces\HTTP\EInvoiceController;

$xmlBuilder = new XmlInvoiceBuilder();

// Cấu hình chữ ký số (từ system_settings)
$signatureMode = getenv('EINVOICE_SIGNATURE_MODE') ?: 'dev';
$signatureConfig = [
    'mode' => $signatureMode,
    'cert_path' => getenv('EINVOICE_CERT_PATH') ?: __DIR__ . '/../../data/certs/dev.pem',
    'key_path' => getenv('EINVOICE_KEY_PATH') ?: __DIR__ . '/../../data/certs/dev.key',
    'key_password' => getenv('EINVOICE_KEY_PASSWORD') ?: 'dev123',
    'pkcs11_module' => getenv('EINVOICE_PKCS11_MODULE') ?: '',
    'token_pin' => getenv('EINVOICE_TOKEN_PIN') ?: '',
    'cert_serial' => getenv('EINVOICE_CERT_SERIAL') ?: '',
];
$digitalSignatureService = new DigitalSignatureService($signatureConfig);

// T-VAN provider config (mặc định VNPT)
$tvanConfig = [
    'api_url' => getenv('TVAN_API_URL') ?: 'https://api.vnpt-invoice.vn/ws/services/InvoiceWS',
    'username' => getenv('TVAN_USERNAME') ?: '',
    'password' => getenv('TVAN_PASSWORD') ?: '',
    'account' => getenv('TVAN_ACCOUNT') ?: '',
    'acpass' => getenv('TVAN_ACPASS') ?: '',
    'pattern' => getenv('TVAN_PATTERN') ?: '1',
    'serial' => getenv('TVAN_SERIAL') ?: '01GTKT0/001',
];
$einvoiceGateway = new VnptEInvoiceGateway($tvanConfig, $digitalSignatureService);

$invoiceService = new InvoiceService($pdo, $xmlBuilder, $digitalSignatureService, $einvoiceGateway, $auditLogger, $voucherService, $accountService);
$vatDeclarationEngine = new VatDeclarationEngine($pdo);
$einvoiceController = new EInvoiceController($invoiceService, $vatDeclarationEngine);
