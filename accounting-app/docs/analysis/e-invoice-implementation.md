# E-Invoice Implementation (Hóa đơn điện tử)

> **Version:** 1.0
> **Created:** 2026-06-02
> **Legal basis:** TT 32/2025/TT-BTC (v2.0.0 schema, effective June 2025), NĐ 70/2025/NĐ-CP
> **Schema:** TT32 v2.0.0 XML format
> **Signature:** PKCS#7 (RSA SHA-256, 2048-bit min)

## 1. Architecture

```
[Application]
  → InvoiceService (lifecycle bridge)
    → XmlInvoiceBuilder (TT32 v2.0.0 XML + QR)
    → DigitalSignatureService (PKCS#7 signer)
    → EInvoiceGatewayInterface (port)
      → VnptEInvoiceGateway (SOAP via cURL)
      → [future] ViettelEInvoiceGateway
      → [future] MisaEInvoiceGateway
```

## 2. Gateway Interface (Port)

```php
interface EInvoiceGatewayInterface {
    public function createInvoice(array $invoiceData): array;
    public function adjustInvoice(string $originalId, array $adjustmentData): array;
    public function replaceInvoice(string $originalId, array $newData): array;
    public function cancelInvoice(string $invoiceId, string $reason): array;
    public function sendInvoice(string $invoiceId): array;
    public function retryInvoice(string $invoiceId): array;
    public function downloadInvoice(string $invoiceId): string;  // XML
    public function listInvoices(array $filter): array;
    public function getStatus(string $invoiceId): string;
    public function confirmPayment(string $invoiceId, string $paymentDate): array;
    public function updateCustomer(string $customerId, array $data): array;
    public function sync(string $fromDate, string $toDate): array;
}
```

## 3. VNPT T-VAN Adapter

SOAP-based via cURL. No WSDL parsing — raw XML envelope construction.

| Method | SOAP Action |
|---|---|
| createInvoice | ImportAndPublishInv |
| adjustInvoice | adjustInv |
| replaceInvoice | replaceInv |
| cancelInvoice | cancelInv |
| confirmPayment | confirmPayment |
| updateCustomer | UpdateCus |
| downloadInvoice | downloadInv |
| listInvoices | reportInvUsed |

## 4. XML Builder (TT32 v2.0.0)

```xml
<Invoice>
  <InvoiceType>1</InvoiceType>    <!-- 1=sale, 7=adjustment, 8=replace, 9=cancel -->
  <InvoiceSeries>AA/25E</InvoiceSeries>
  <InvoiceNumber>0000001</InvoiceNumber>
  <InvoiceDate>2026-06-01</InvoiceDate>
  <BuyerInfo>
    <BuyerName>Công ty ABC</BuyerName>
    <BuyerTaxCode>0123456789</BuyerTaxCode>
  </BuyerInfo>
  <Items>
    <Item>
      <Description>Dịch vụ tư vấn</Description>
      <UnitPrice>10000000</UnitPrice>
      <Quantity>1</Quantity>
      <Total>10000000</Total>
      <VatRate>10</VatRate>
      <VatAmount>1000000</VatAmount>
    </Item>
  </Items>
  <Total>11000000</Total>
  <MaQR><!-- QR code base64 --></MaQR>
  <Signature><!-- PKCS#7 base64 --></Signature>
</Invoice>
```

Mandatory QR element per TT 32/2025 (not optional).

## 5. Digital Signature (3 Modes)

```php
class DigitalSignatureService {
    // Mode 1: simulated (dev/test)
    public const MODE_SIMULATED = 'simulated';
    // Mode 2: cert file + password
    public const MODE_CERT = 'cert';
    // Mode 3: hardware token (HSM)
    public const MODE_TOKEN = 'token';
}
```

Implementation: `proc_open` → `openssl smime -sign` CLI. PKCS#7 detached signature.

## 6. InvoiceService Lifecycle Bridge

```php
class InvoiceService {
    public function createFromTransaction(...): array;   // Transaction → e-invoice
    public function adjustInvoice(...): array;            // Adjust existing
    public function replaceInvoice(...): array;           // Replace existing
    public function cancelInvoice(...): array;            // Cancel
    public function retryPublish(...): array;             // Retry failed
    public function listInvoices(...): array;             // List with filters
}
```

Sequence numbering: `SELECT ... FOR UPDATE` on e_invoice_sequences to prevent duplicates under concurrent access.

## 7. Multi-VAN Support

Primary: VNPT. Fallback: Viettel, MISA (configurable via DI). Provider selection strategy in config/services.php.

## 8. Tables

| Table | Purpose |
|---|---|
| e_invoices | Invoice header (supplier, buyer, totals, status, CQT response) |
| e_invoice_lines | Line items (description, qty, price, tax) |
| tvan_providers | Provider endpoints, credentials, status |

## 9. Lifecycle States

```
Draft → Pending → Published → (Adjust / Replace / Cancel)
  ↓ fail         ↓ success
  Failed ─────→ Retry(max 3)
```

## 10. Integration with Tax Module

```php
// VatService uses e-invoice data for reconciliation:
VatService::reconcileWithEInvoice(int $periodId): array
  // Compares:
  //   - declaration vat_output vs sum(e_invoices where type=sale)
  //   - declaration vat_input vs sum(e_invoices where type=purchase)
  // Returns: { declaration, e_invoice, diff, mismatches[] }
```
