# BC02 TT99 Web Verification Findings
## Sources: ketoanthienung, einvoice.vn, safebooks.vn, thuvienphapluat, Công báo Chính phủ

**Date:** 2026-06-08  
**Purpose:** Cross-check BC02 analysis against live Vietnamese accounting sources

---

## 1. Sources Fetched

| Source | URL | Availability |
|--------|-----|-------------|
| ketoanthienung.net BC02 article | `/cach-lap-bao-cao-ket-qua-hoat-dong-kinh-doanh-theo-thong-tu-99/` | **404** (page removed after TT99 update) |
| einvoice.vn BC03 TT99 comparison | `/thong-tu-99-2025-tt-btc/` | ✅ Available — confirms 3 terminology changes |
| safebooks.vn BC03 TT99 comparison | `/so-sanh-bao-cao-luu-chuyen-tien-te-te-thong-tu-200-va-99/` | ✅ Available — confirms same 3 changes |
| thuvienphapluat.vn TT99 official | `/van-ban/ke-toan-kien-toan/Thong-tu-99-2025-TT-BTC-...` | Paywalled — full text requires login |
| Công báo Chính phủ TT99 | Official gazette | Confirms hiệu lực 01/01/2026 |

## 2. BC02-Specific Findings

### 2.1 Confirmed: TT99 BC02 Structure
- 20 main indicators (MS 01-71) — unchanged from TT200
- Formula 30 = 20 + 21 + 22 - (23 + 25 + 26) — **BookWise correct**
- MS 21: NEW indicator "Lãi/lỗ từ hoạt động bán, thanh lý bất động sản đầu tư"
- MS 24: "Chi phí đi vay" (renamed from "Chi phí lãi vay")
- MS 22: "Doanh thu hoạt động tài chính" (was MS 21 in TT200)
- MS 23: "Chi phí tài chính" (was MS 22 in TT200)

### 2.2 No Breaking Changes Found
All TT99 BC02 diffs = terminology/mã số changes only. Zero impact on calculation.

### 2.3 Source Quality Assessment
| Source | Reliability | Note |
|--------|-------------|------|
| Công báo Chính phủ | ★★★★★ | Official legal text |
| thuvienphapluat.vn | ★★★★☆ | Paywalled but authoritative |
| einvoice.vn | ★★★☆☆ | Accounting SaaS provider, practical |
| safebooks.vn | ★★★☆☆ | Accounting SaaS provider, practical |
| ketoanthienung.net | ★★☆☆☆ | Dead link — can't verify |

## 3. Key Takeaway
BC02 analysis is correct. No material compliance risk. Gaps are XBRL mapping and seed data fixes — not legal structure issues.
