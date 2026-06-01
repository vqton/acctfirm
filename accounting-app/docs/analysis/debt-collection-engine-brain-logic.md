# Debt Collection Engine — Phân tích Nghiệp vụ Thu hồi Công nợ SME Việt Nam

> **Tác giả:** BA Lead (20,000 giờ) + Chief Accountant (20,000 giờ)  
> **Cập nhật:** Tháng 6/2026  
> **Căn cứ pháp lý:** Thông tư 99/2025/TT-BTC, Thông tư 48/2019/TT-BTC, Luật Kế toán 2015, Bộ luật Dân sự 2015, Luật Thương mại 2005, Nghị định 320/2025/NĐ-CP  
> **Tham chiếu:** Kế toán Thiên Ưng, Kế toán Lê Ánh, Webketoan, MISA AMIS, Bravo ERP  
> **Hệ thống:** Accounting App — PHP/MySQL monolith, ArService hiện tại (518 dòng)

---

## Mục lục

1. [Executive Summary](#1-executive-summary)
2. [Current State Assessment](#2-current-state-assessment)
3. [Business Problem & ROI](#3-business-problem--roi)
4. [Scope Definition](#4-scope-definition)
5. [User Roles & Permissions](#5-user-roles--permissions)
6. [Use Cases](#6-use-cases)
7. [Process Flow Logic](#7-process-flow-logic)
8. [Debt Collection Lifecycle](#8-debt-collection-lifecycle)
9. [Business Rules Engine](#9-business-rules-engine)
10. [Data Flow & Workflow Logic](#10-data-flow--workflow-logic)
11. [Data Model — New Tables](#11-data-model--new-tables)
12. [ArService Extensions](#12-arservice-extensions)
13. [API Endpoints](#13-api-endpoints)
14. [User Journey](#14-user-journey)
15. [Reporting & KPIs](#15-reporting--kpis)
16. [Integration Contracts](#16-integration-contracts)
17. [SME Pain Points Addressed](#17-sme-pain-points-addressed)
18. [Functional Rules Matrix](#18-functional-rules-matrix)
19. [Implementation Roadmap](#19-implementation-roadmap)

---

## 1. Executive Summary

### 1.1 Why Debt Collection Module

Hiện tại ArService quản lý:
- Ghi nhận hóa đơn (invoice) → Nợ 131
- Thu tiền (payment) → Có 131
- Aging report
- Dự phòng (provision) theo TT 48/2019
- Xóa nợ (write-off)

**Thiếu hoàn toàn** quy trình chủ động thu hồi nợ (active debt collection):
- Không có nhắc nợ tự động (automated dunning)
- Không có theo dõi cam kết thanh toán (payment promise)
- Không có lịch sử hoạt động đòi nợ (collection activity log)
- Không có phân công nhân viên đòi nợ
- Không có quy trình leo thang (escalation workflow)
- Không có KPI/thước đo hiệu quả thu hồi

### 1.2 Business Case

| Metric | Hiện tại | Với Debt Collection |
|---|---|---|
| DSO (Days Sales Outstanding) | ~60 ngày (manually tracked) | ~40 ngày (automated dunning) |
| Bad debt ratio | ~8% (estimation) | ~3% (early intervention) |
| Collection cost/head | ~15h/tuần gọi điện, email | ~5h/tuần (system automates reminders) |
| Aging visibility | Aging report (snapshot) | Real-time dashboard + alerts |
| Write-off approval | None (direct writeOff()) | Multi-level approval workflow |
| Audit readiness | Manual tracking | Full collection history + evidence |

### 1.3 Risk Assessment

| Risk | Severity | Current State | With Module |
|---|---|---|---|
| Missed collection opportunity | High | No automated reminders | Pre-due + overdue sequence |
| Improper write-off | High | Single method, no approval | Multi-level approval + docs |
| Lost communication history | Medium | No collection log | Full activity trail |
| Inconsistent escalation | Medium | Manual judgment | Rule-based escalation |
| Wrongful legal action | High | No legal tracking | Legal hold + counsel approval |

---

## 2. Current State Assessment

### 2.1 Existing ArService Capabilities

| Capability | Implementation | Lines |
|---|---|---|
| recordInvoice() | Full (transactions, validations) | 49-88 |
| recordPayment() | Full (SELECT FOR UPDATE, partial) | 96-132 |
| recordPrepayment() | Full | 140-162 |
| recordReturn() | Full (521, 33311 reversal) | 170-200 |
| recordSettlementDiscount() | Full (635) | 208-229 |
| writeOff() | Partial (no multi-level approval) | 239-265 |
| getAgingReport() | Full (5 buckets) | 280-307 |
| getProvisionRate() | Full (TT 48/2019) | 311-318 |
| getProvisionSummary() | Full (5 TT48 buckets) | 325-375 |
| getCustomerStatement() | Full | 382-389 |
| getInvoices/getInvoice | Full | 391-409 |
| getPayments/getCustomers | Full | 411-421 |
| allocateReceipt() | Full (multi-invoice) | 445-500 |
| getReceiptAllocationDetails() | Full | 505-517 |

### 2.2 Gaps (Scope of This Module)

| Gap | Impact | Priority |
|---|---|---|
| No automated dunning queue | Collection reactive, not proactive | P0 |
| No payment promise tracking | Promises forgotten, no follow-up | P0 |
| No collection activity log | No evidence for legal/audit | P0 |
| No collection assignment | No accountability | P1 |
| No escalation workflow | Inconsistent handling of aged debt | P1 |
| No write-off approval | High risk of improper write-off | P1 |
| No hold/release mechanism | Can't pause dunning during negotiation | P1 |
| No collection performance KPIs | Can't measure team effectiveness | P2 |
| No settlement tracking | Can't track compromise agreements | P2 |
| No automated communication | Manual email/SMS per debtor | P2 |

---

## 3. Business Problem & ROI

### 3.1 Vấn đề SME Việt Nam

**Vấn đề 1: Nhắc nợ thủ công**
- Kế toán tự nhớ lịch gọi điện/email KH
- KH quên -> nợ quá hạn -> gọi điện nhắc -> stress
- Khi có >100 KH -> không thể nhắc hết

**Vấn đề 2: KH hứa hẹn không giữ**
- KH hứa "tuần sau trả" -> kế toán chờ -> không thấy
- Không có hệ thống để tự động gọi lại sau cam kết
- Mất dấu vết các lần hứa hẹn

**Vấn đề 3: Không có bằng chứng đòi nợ**
- Kiểm toán hỏi: "Đã làm gì để đòi khoản nợ này?"
- Không có log -> không chứng minh được
- Ảnh hưởng trích lập dự phòng và xóa nợ

**Vấn đề 4: Xóa nợ không quy trình**
- writeOff() được gọi trực tiếp -> không approval
- Rủi ro: Xóa nợ sai -> mất quyền đòi nợ pháp lý
- Rủi ro: Xóa nợ cho KH "quen" -> gian lận

### 3.2 ROI Calculation (SME điển hình 500 KH)

| Hạng mục | Hiện tại | Sau module | Tiết kiệm |
|---|---|---|---|
| DSO | 60 ngày | 40 ngày | 20 ngày dòng tiền |
| Bad debt | 8% (2 tỷ/năm) | 3% (750tr/năm) | 1.25 tỷ/năm |
| Nhân công đòi nợ | 1.5 người (300tr) | 0.5 người (100tr) | 200tr/năm |
| Chi phí luật sư | 100tr/năm | 50tr/năm (phát hiện sớm) | 50tr/năm |
| Tổng tiết kiệm | - | - | **~1.5 tỷ/năm** |

---

## 4. Scope Definition

### 4.1 In Scope (Phase 1 — Core)

1. **Dunning queue** — tự động tạo lịch nhắc nợ theo aging rules
2. **Collection activity log** — ghi mọi hoạt động đòi nợ (call, email, meeting)
3. **Payment promise tracking** — KH hứa hẹn ngày trả, tự động reminder
4. **Collection assignment** — phân công KH cho từng nhân viên
5. **Hold/Release** — tạm dừng nhắc nợ khi đang thương lượng
6. **Write-off approval** — multi-level approval trước khi xóa nợ
7. **API endpoints** — CRUD + actions
8. **Views** — collection dashboard, queue, detail, activity

### 4.2 In Scope (Phase 2 — Advanced)

1. **Escalation workflow** — auto-escalate dunning + debt collector
2. **Settlement/compromise** — thỏa thuận giảm nợ, trả góp
3. **Automated communication** — email/SMS templates (integration later)
4. **Collection KPIs** — dashboard, performance metrics
5. **Legal workflow** — track legal action, lawyer assignment, court dates

### 4.3 Out of Scope

- Email/SMS gateway integration (chỉ store template + log, actual send via cron + external)
- OCR/call transcription
- Credit scoring / external bureau lookup
- Full CRM (chỉ collection-specific, không general CRM)
- Mobile app (web-only)

### 4.4 Assumptions

- ArService hiện tại không thay đổi (extend, not rewrite)
- Dunning triggers run via PHP cron (no queue worker)
- Write-off approval via simple status + role check (no BPM engine)
- Communication templates stored as plain text + variable substitution

---

## 5. User Roles & Permissions

| Role | Module | Actions |
|---|---|---|
| Collection Staff | debt_collection | read, create_activity, create_promise, update_queue |
| Collection Lead | debt_collection | assign, escalate, hold, release, approve_writeoff_p0 |
| Chief Accountant | debt_collection | approve_writeoff_p1, approve_settlement, final_escalation |
| Finance Director | debt_collection | approve_writeoff_p2, approve_legal |
| AR Controller | debt_collection | read, export_reports |

Giải thích:
- `approve_writeoff_p0`: xóa nợ < 10tr
- `approve_writeoff_p1`: xóa nợ 10-100tr
- `approve_writeoff_p2`: xóa nợ > 100tr

Segregation of duties:
- Collector ≠ Approver (không ai tự assign rồi tự approve)
- Write-off proposer ≠ Write-off approver

---

## 6. Use Cases

### 6.1 DC-UC-01: Dunning Queue Generation

| Trường | Giá trị |
|---|---|
| **Tên** | Tự động sinh lịch nhắc nợ |
| **Mục tiêu** | KH quá hạn được đưa vào hàng đợi đòi nợ tự động |
| **Tác nhân** | Hệ thống (cron) |
| **Điều kiện trước** | Hóa đơn quá hạn (due_date < today). KH không ở trạng thái hold. |
| **Trigger** | Cron chạy hàng ngày 08:00 |
| **Happy path** | Hóa đơn quá hạn 7 ngày -> vào queue -> assign collector -> hiển thị dashboard |
| **Alternative** | KH đã có promise active -> skip queue (chờ đến promise_date) |
| **Exception** | KH ở hold -> không vào queue |
| **Validation** | Hóa đơn balance > 1, due_date < today, queue không trùng entry |
| **Accounting** | Không — đây là operational, không phải bút toán |
| **Kết quả** | Collection queue có entry mới |

**Quy tắc queue generation:**
```php
// Mỗi hóa đơn quá hạn tạo 1 queue entry (không trùng)
// Nếu KH có nhiều hóa đơn quá hạn -> gộp thành 1 entry (group by KH)???
// Quyết định: 1 queue entry PER INVOICE — theo dõi chi tiết hơn
// Collector có thể "group view" theo KH trên UI
```

### 6.2 DC-UC-02: Log Collection Activity

| Trường | Giá trị |
|---|---|
| **Tên** | Ghi nhận hoạt động đòi nợ |
| **Mục tiêu** | Mọi cuộc gọi, email, meeting với KH được log |
| **Tác nhân** | Collection Staff |
| **Điều kiện trước** | Queue entry tồn tại, collector được assign |
| **Trigger** | Collector thực hiện hoạt động (gọi điện, email, gặp mặt) |
| **Happy path** | Gọi KH -> KH hứa trả ngày 15 -> log "called, promise 15/06" |
| **Alternative** | Gọi KH -> KH cãi -> log dispute details |
| **Exception** | KH không bắt máy -> log "no answer" -> auto-retry logic (? retry lần) |
| **Validation** | Activity type valid (call/email/meeting/sms/dispute/other) |
| **Output** | Collection activity record created. Queue updated (last_action_date) |
| **Rủi ro** | Ghi sai thông tin -> mislead escalation decision |

### 6.3 DC-UC-03: Payment Promise Tracking

| Trường | Giá trị |
|---|---|
| **Tên** | Theo dõi cam kết thanh toán |
| **Mục tiêu** | KH hứa trả vào ngày X -> tự động nhắc lại nếu quá X |
| **Tác nhân** | Collection Staff (nhập) + Hệ thống (theo dõi) |
| **Điều kiện trước** | Queue entry active, collector đã liên lạc KH |
| **Trigger** | KH hứa "Ngày 20 tôi sẽ chuyển khoản" |
| **Happy path** | Promise date = 20/06 -> đến 20/06 check payment -> trả rồi -> đóng promise |
| **Alternative** | Quá 20/06 chưa trả -> promise broken -> flag urgent -> auto-escalate |
| **Exception** | KH hứa lại lần 2 -> update promise_date mới -> log history |
| **Validation** | promise_date > today, amount <= invoice balance |
| **Output** | Payment promise record created. Nếu promise_date đến mà chưa payment -> alert |
| **Rủi ro** | KH hứa nhiều lần không giữ -> collector mất thời gian |

### 6.4 DC-UC-04: Collection Assignment

| Trường | Giá trị |
|---|---|
| **Tên** | Phân công nhân viên đòi nợ |
| **Mục tiêu** | Mỗi queue entry có collector chịu trách nhiệm |
| **Tác nhân** | Collection Lead (hoặc auto-assign) |
| **Điều kiện trước** | Queue entry chưa được assign (< 24h tuổi) |
| **Trigger** | Queue entry created (tự động) hoặc Lead click "assign to me" |
| **Happy path** | Auto-assign theo round-robin -> collector A nhận KH X |
| **Alternative** | Manual assign -> Lead chọn collector từ dropdown |
| **Exception** | KH VIP -> assign riêng cho senior collector |
| **Validation** | Collector không quá 50 active items (load balancing) |
| **Output** | queue.assigned_to = collector_id. Notify collector. |

### 6.5 DC-UC-05: Dunning Hold/Release

| Trường | Giá trị |
|---|---|
| **Tên** | Tạm dừng/tiếp tục nhắc nợ |
| **Mục tiêu** | Cho phép tạm dừng dunning khi KH đang thương lượng |
| **Tác nhân** | Collection Lead |
| **Điều kiện trước** | Queue entry active, KH đang thương lượng thanh toán |
| **Trigger** | KH đề nghị "đừng gọi nữa, tuần sau tôi trả" — cần hold dunning |
| **Happy path** | Hold queue entry -> KH trả 50% -> release -> tiếp tục dunning phần còn lại |
| **Alternative** | Hold -> KH không trả -> release sau 14 ngày -> escalate |
| **Exception** | Hold quá 30 ngày -> auto-release + escalate to Lead |
| **Validation** | hold_reason required, hold_until_date optional |
| **Output** | Queue status = 'hold'. Cron skip hold entries. |

### 6.6 DC-UC-06: Write-Off Approval Workflow

| Trường | Giá trị |
|---|---|
| **Tên** | Phê duyệt xóa nợ phải thu khó đòi |
| **Mục tiêu** | Xóa nợ chỉ được thực hiện sau khi phê duyệt multi-level |
| **Tác nhân** | Collector (propose) + Lead + Chief Accountant (approve) |
| **Điều kiện trước** | Nợ quá hạn > 365 ngày, đã thực hiện >= 3 hoạt động đòi nợ |
| **Trigger** | Collector đề xuất xóa nợ (propose write-off) |
| **Happy path** | Collector propose -> Chief Acct approve -> writeOff() executed |
| **Alternative** | Lead reject -> quay lại queue + note lý do từ chối |
| **Exception** | Nợ > 100tr -> cần additional approval (Finance Director) |
| **Validation** |
  - balance > 0
  - overdue >= 365 days OR customer bankrupt/dissolved
  - >= 3 collection activities in last 180 days
  - Supporting documents uploaded (or noted)
| **Accounting** | writeOff() existing method: Nợ 2293 + Nợ 642 / Có 131 |
| **Output** | ar_invoices.status = 'written_off'. Audit trail entry. |

### 6.7 DC-UC-07: Escalation Workflow

| Trường | Giá trị |
|---|---|
| **Tên** | Leo thang xử lý nợ khó đòi |
| **Mục tiêu** | Tự động leo thang khi nợ quá hạn lâu hoặc promise bị vỡ |
| **Tác nhân** | Hệ thống (auto-escalate) + Collection Lead |
| **Điều kiện trước** | Queue entry overdue vượt threshold hoặc promise broken 2 lần |
| **Trigger** | Aging vượt ngưỡng hoặc promise broken |
| **Happy path** | Nợ 90+ ngày -> escalate từ collector lên Lead |
| **Alternative** | Nợ 180+ ngày -> Lead escalate lên Chief Acct -> discuss legal |
| **Exception** | KH có lịch sử dispute -> escalate đến legal sớm hơn |
| **Validation** | Escalation level theo rule (xem §9) |
| **Output** | Queue entry priority tăng, collector cấp cao hơn được assign |

### 6.8 DC-UC-08: Settlement/Compromise

| Trường | Giá trị |
|---|---|
| **Tên** | Thỏa thuận thanh toán (giảm nợ, trả góp) |
| **Mục tiêu** | Ghi nhận thỏa thuận KH được giảm nợ nếu trả sớm |
| **Tác nhân** | Collection Lead + Chief Accountant |
| **Điều kiện trước** | Nợ khó đòi, KH đồng ý trả 1 phần để tất toán |
| **Trigger** | KH đề nghị "Tôi trả 50% để xóa nợ được không?" |
| **Happy path** | Thỏa thuận 60% -> KH trả -> ghi nhận settlement discount -> xóa nợ |
| **Alternative** | Thỏa thuận trả góp 3 tháng -> theo dõi từng kỳ |
| **Exception** | KH trả không đúng thỏa thuận -> raise dispute |
| **Validation** | Settlement % >= 30% (không giảm quá sâu), có approval |
| **Accounting** | Nợ 112: 60tr, Nợ 635: 10tr (chiết khấu), Nợ 2293: 30tr, Có 131: 100tr |
| **Output** | Payment + write-off of remaining balance |

---

## 7. Process Flow Logic

### 7.1 Debt Collection End-to-End

```
Invoice Overdue
     │
     v
┌─────────────────────────┐
│ Dunning Queue Creation  │  ← Cron daily 08:00
│ (1 entry per overdue    │
│  invoice)               │
└─────────┬───────────────┘
          v
┌─────────────────────────┐
│ Auto-Assign Collector   │  ← Round-robin / VIP logic
└─────────┬───────────────┘
          v
┌─────────────────────────┐
│ Collection Diamond      │  ← Core loop
│                         │
│  Hold ←→ Active ←→ Done│
│    ↑         ↓          │
│    │    Promise Broken   │
│    │         │          │
│    └──── Escalate ─────┘
└─────────────────────────┘
          │
          v (escalated)
┌─────────────────────────┐
│ Escalated Queue         │  ← Lead + Legal
│ (Senior collector /     │
│  Lawyer assignment)     │
└─────────┬───────────────┘
          │
     ┌────┴────┐
     v         v
┌─────────┐ ┌─────────┐
│Settlement│ │Write-off│
│ (partial)│ │ (full)  │
└────┬────┘ └────┬────┘
     v           v
┌─────────────────────────┐
│ Payment Received        │
│ → ar_invoices closed    │
│ → aging refreshed       │
│ → queue entry archived  │
└─────────────────────────┘
```

### 7.2 Dunning Timeline (SME Best Practice)

```
Due Date: 15/05
Pre-due:  08/05 (T-7)  → Email nhắc: "Sắp đến hạn thanh toán"
                         → KH có thể trả trước để tránh quá hạn

D+1:      16/05 (D+1)  → Queue created, collector assigned
D+7:      22/05 (D+7)  → Cuộc gọi đầu tiên (call activity logged)
D+14:     29/05 (D+14) → Email nhắc lần 2 + SMS
D+30:     14/06 (D+30) → Công văn nhắc nợ (email PDF)
D+45:     29/06 (D+45) → Cuộc gọi lần 2 + cảnh báo ngừng giao hàng
D+60:     14/07 (D+60) → Escalate lên Lead
D+90:     13/08 (D+90) → Công văn cuối cùng + luật sư
D+180:    11/11 (D+180) → Xem xét trích lập dự phòng 30%
D+365:    15/05 (D+365) → Xem xét xóa nợ (nếu không thu được)
```

### 7.3 Collection Activity Diamond

```
           ┌─────────────┐
           │  Queue Entry │
           │ (assigned)   │
           └──────┬───────┘
                  │
        ┌─────────┴─────────┐
        │                   │
   ┌────┴────┐         ┌────┴────┐
   │ Promise │         │  Call   │
   │ Created │         │ (no ans)│
   └────┬────┘         └────┬────┘
        │                   │
   Promise Date      ┌──────┴──────┐
        │            │             │
   ┌────┴────┐   ┌──┴───┐    ┌────┴────┐
   │ Paid    │   │Retry │    │ Dispute │
   │ → Done  │   │(max3)│    │ → Esc   │
   └─────────┘   └──┬───┘    └─────────┘
                    │
              3 retries fail
                    │
                    v
              ┌─────────────┐
              │ Escalate to │
              │ Lead        │
              └─────────────┘
```

---

## 8. Debt Collection Lifecycle

### 8.1 States

```
               ┌───────────────────────────────────┐
               │           Queue Entry              │
               │                                   │
    ┌──────────┴──────────┐                        │
    │   NEW (unassigned)  │──→ auto-assign ────────┤
    └─────────────────────┘                        │
              │                                    │
              v                                    │
    ┌──────────────────┐                           │
    │ ACTIVE (assigned) │←──── release ────────────┤
    └────────┬─────────┘                           │
             │                                    │
    ┌────────┴────────┐                            │
    │                 │                            │
    v                 v                            │
┌────────┐    ┌──────────────┐                     │
│ HOLD   │    │ ESCALATED    │──→ assign lead ────┤
└────┬───┘    └──────┬───────┘                    │
     │               │                            │
     │         ┌─────┴─────┐                      │
     │         v           v                      │
     │   ┌─────────┐ ┌──────────┐                 │
     │   │SETTLEMENT│ │WRITEOFF  │                │
     └───┴─────────┘ └─────┬────┘                 │
                           │                      │
                           v                      │
                    ┌───────────────┐             │
                    │CLOSED (resolved)│←───────────┘
                    └───────────────┘
```

### 8.2 State Transition Rules

| From | To | Condition |
|---|---|---|
| NEW | ACTIVE | Collector assigned |
| ACTIVE | HOLD | Lead action + hold_reason |
| ACTIVE | ESCALATED | Overdue > threshold OR promise_broken >= 2 |
| ACTIVE | CLOSED | Balance = 0 (paid) |
| HOLD | ACTIVE | Release action OR auto-release after hold_until |
| HOLD | ESCALATED | Hold > 30 days auto-escalate |
| ESCALATED | SETTLEMENT | Settlement approved |
| ESCALATED | WRITEOFF | Write-off approved |
| SETTLEMENT | CLOSED | Payment received as per settlement |
| WRITEOFF | CLOSED | ar_invoices.status = 'written_off' |

---

## 9. Business Rules Engine

### 9.1 Dunning Schedule Rules

```php
// Mặc định cho SME:
$dunningSchedule = [
    ['day_offset' => -7,  'action' => 'email',   'template' => 'pre_due'],
    ['day_offset' => 1,   'action' => 'queue',   'template' => null],  // auto queue
    ['day_offset' => 7,   'action' => 'call',    'template' => null],
    ['day_offset' => 14,  'action' => 'email',   'template' => 'overdue_14d'],
    ['day_offset' => 30,  'action' => 'letter',  'template' => 'overdue_30d'],
    ['day_offset' => 45,  'action' => 'call',    'template' => null],
    ['day_offset' => 60,  'action' => 'escalate','template' => null],
];

// Có thể cấu hình theo loại KH:
// - VVIP: pre-due only, manual collection
// - Wholesale: standard
// - Retail: aggressive (call at D+3)
```

### 9.2 Escalation Rules

| Level | Overdue Days | Promise Broken | Collector | Action |
|---|---|---|---|---|
| 0 | 1-30 | 0 | Staff | Call + email |
| 1 | 31-60 | 1 | Staff | Email + SMS |
| 2 | 61-90 | 2 | Lead | Letter + call |
| 3 | 91-180 | 3 | Lead | Legal warning |
| 4 | 181-365 | 3+ | Chief Acct | Provision + lawyer |
| 5 | 366+ | any | FD | Write-off evaluation |

### 9.3 Hold Rules

- **Max hold duration:** 30 ngày (auto-release sau đó)
- **Hold reason required:** negotiating / medical / disaster / dispute
- **Hold count limit:** Tối đa 3 hold lần trên 1 queue entry
- **Hold không được dùng để trốn escalation**: hold không reset aging counter

### 9.4 Promise Rules

- **Max promises per queue item:** 3 (sau 3 lần hứa không giữ -> auto-escalate)
- **Promise tolerance:** +2 ngày grace (nếu KH trả chậm 2 ngày so với promise date -> không tính broken)
- **Promise amount:** Phải >= 10% invoice balance (tránh hứa 100k khi nợ 100tr)
- **Promise date:** Không được > 60 ngày từ today

### 9.5 Write-off Rules

- **Minimum overdue:** 365 days (trừ trường hợp KH phá sản/giải thể)
- **Minimum activities:** >= 3 collection activities in last 180 days
- **Approval matrix:**
  - < 10tr: Collection Lead
  - 10-100tr: Chief Accountant
  - > 100tr: Finance Director
- **Documents required:** Ghi chú lý do xóa nợ, evidence đã cố gắng thu hồi
- **Segregation:** Người propose ≠ Người approve

### 9.6 Settlement Rules

- **Minimum settlement %:** 30% of balance (không giảm quá 70%)
- **Approval rules:** Giống write-off matrix
- **Settlement must be paid within:** 14 days of agreement (nếu không -> void)
- **One settlement per invoice:** Không cho settlement lần 2

### 9.7 Activity Rules

- **Activity types:** call, email, meeting, sms, letter, dispute, other
- **Min activities per queue:** 1 activity per 14 days (nếu không -> auto-notify Lead)
- **Activity status:** completed, planned, cancelled
- **Attachment:** Có thể upload file (biên bản, email PDF)

---

## 10. Data Flow & Workflow Logic

### 10.1 ArService ↔ DebtCollectionService Interaction

```
ArService                    DebtCollectionService
---------                    --------------------
recordPayment() ──────────>  Auto-close queue entries for paid invoices
                               (cập nhật queue.status = 'closed')

writeOff() ───────────────>  Chỉ gọi sau khi approval approved
                               (DebtCollectionService kiểm tra approval status)

getAgingReport() ──────────  DebtCollectionService có thể bổ sung:
                               - getQueueByCollector()
                               - getPerformanceMetrics()

(no integration) ─────────>  Cron: generateQueueEntries()
                               read ar_invoices where balance > 1 and due_date < today
                               and not already in active queue
```

### 10.2 Payment → Queue Auto-Close Flow

```
KH trả tiền
    │
    v
recordPayment() -> UPDATE ar_invoices
    │
    v
DebtCollectionService::handlePaymentEvent($invoiceId)
    │
    ├── Tìm queue entry active cho invoice_id
    ├── Nếu found:
    │   ├── UPDATE queue.status = 'closed'
    │   ├── UPDATE queue.resolved_at = NOW()
    │   ├── UPDATE queue.resolution = 'paid'
    │   └── Log activity (auto): "Invoice fully paid. Queue closed."
    └── Nếu partial payment (< balance):
        └── Không close queue (chỉ log activity + update balance tracking)
```

### 10.3 Promise → Follow-up Flow

```
Promise created (promise_date = 20/06)
    │
    v
Cron daily 08:00: check promises
    │
    ├── promise_date < today AND unpaid
    │   └── Mark promise_broken = true
    │   ├── If promise_broken_count >= 3:
    │   │   └── Auto-escalate queue entry
    │   └── Log activity (auto): "Promise broken. Expected 20/06, unpaid."
    │
    ├── promise_date = today AND paid
    │   └── Mark promise_kept = true
    │   └── Log activity (auto): "Promise kept. Payment received on date."
    │
    └── promise_date IN [-2, +2] days from today
        └── If paid (grace): mark kept
```

### 10.4 Write-off Approval Flow

```
Collector click "Propose Write-off"
    │
    v
Validation:
    - overdue >= 365 days
    - balance > 0
    - activities >= 3
    │
    ├── Fail -> show error
    │
    └── Pass -> Create approval_request
        │
        v
    ┌──────────┐
    │ PENDING  │ ← Collection Lead review
    └────┬─────┘
         │
    ┌────┴────┐
    v         v
APPROVED   REJECTED
    │          │
    v          v
Check value  Queue active again
  │           + note reason
  │
  ├── < 10tr -> auto-execute writeOff()
  ├── 10-100tr -> Chief Accountant approval
  │   ├── Approve -> execute writeOff()
  │   └── Reject -> back to pending
  │
  └── > 100tr -> Finance Director + Chief Acct
      ├── Both approve -> execute writeOff()
      └── Either reject -> back to pending
```

---

## 11. Data Model — New Tables

### 11.1 debt_collection_queue

```sql
CREATE TABLE debt_collection_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT UNSIGNED NOT NULL,
    customer_id VARCHAR(50) NOT NULL,
    assigned_to VARCHAR(50) DEFAULT NULL,
    status ENUM('new','active','hold','escalated','settlement','writeoff','closed') DEFAULT 'new',
    priority TINYINT UNSIGNED DEFAULT 0,
    
    -- Dunning
    entered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_action_date TIMESTAMP NULL,
    next_action_date DATE NULL,
    escalation_level TINYINT UNSIGNED DEFAULT 0,
    
    -- Hold
    hold_reason VARCHAR(255) DEFAULT NULL,
    hold_until DATE NULL,
    hold_count TINYINT UNSIGNED DEFAULT 0,
    
    -- Resolution
    resolved_at TIMESTAMP NULL,
    resolution VARCHAR(50) DEFAULT NULL,    -- 'paid','writeoff','settlement','writeoff_approved'
    resolution_note TEXT DEFAULT NULL,
    
    -- Audit
    created_by VARCHAR(50) DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (invoice_id) REFERENCES ar_invoices(id),
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    INDEX idx_status (status),
    INDEX idx_assigned (assigned_to),
    INDEX idx_invoice (invoice_id),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 11.2 debt_collection_activities

```sql
CREATE TABLE debt_collection_activities (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id INT UNSIGNED NOT NULL,
    activity_type ENUM('call','email','meeting','sms','letter','dispute','other','auto') NOT NULL,
    
    -- Content
    summary VARCHAR(500) NOT NULL,           -- Short description: "Called KH, promised 15/06"
    detail TEXT DEFAULT NULL,                 -- Full notes
    contact_person VARCHAR(200) DEFAULT NULL, -- Người collector đã nói chuyện
    contact_phone VARCHAR(20) DEFAULT NULL,   -- SĐT đã gọi
    
    -- Result
    result VARCHAR(50) DEFAULT NULL,          -- 'promise','no_answer','dispute','payment','callback'
    promise_date DATE NULL,                   -- If promise result
    promise_amount DECIMAL(15,2) NULL,        -- If partial promise
    
    -- Meta
    duration_minutes SMALLINT UNSIGNED DEFAULT NULL,  -- Call duration
    attachment_path VARCHAR(500) DEFAULT NULL,         -- Upload path
    
    -- Audit
    created_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (queue_id) REFERENCES debt_collection_queue(id) ON DELETE CASCADE,
    INDEX idx_queue (queue_id),
    INDEX idx_type (activity_type),
    INDEX idx_created (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 11.3 debt_collection_promises

```sql
CREATE TABLE debt_collection_promises (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id INT UNSIGNED NOT NULL,
    activity_id INT UNSIGNED DEFAULT NULL,       -- Activity log gốc
    promise_date DATE NOT NULL,
    promise_amount DECIMAL(15,2) NOT NULL,
    promise_note VARCHAR(500) DEFAULT NULL,
    
    -- Tracking
    status ENUM('active','kept','broken','cancelled') DEFAULT 'active',
    kept_date DATE NULL,
    broken_reason VARCHAR(500) DEFAULT NULL,
    broken_count TINYINT UNSIGNED DEFAULT 0,     -- Số lần broken (accumulated)
    
    -- Audit
    created_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (queue_id) REFERENCES debt_collection_queue(id) ON DELETE CASCADE,
    FOREIGN KEY (activity_id) REFERENCES debt_collection_activities(id),
    INDEX idx_queue (queue_id),
    INDEX idx_promise_date (promise_date),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 11.4 debt_collection_approvals

```sql
CREATE TABLE debt_collection_approvals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id INT UNSIGNED NOT NULL,
    approval_type ENUM('writeoff','settlement','escalate','hold_extend') NOT NULL,
    
    -- Request
    requested_by VARCHAR(50) NOT NULL,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    request_note TEXT NOT NULL,
    
    -- Value
    amount DECIMAL(15,2) NOT NULL,
    settlement_percent DECIMAL(5,2) DEFAULT NULL,  -- If settlement
    settlement_amount DECIMAL(15,2) DEFAULT NULL,   -- Proposed payment
    
    -- Approval chain
    level_1_approver VARCHAR(50) DEFAULT NULL,
    level_1_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    level_1_at TIMESTAMP NULL,
    level_1_note TEXT DEFAULT NULL,
    
    level_2_approver VARCHAR(50) DEFAULT NULL,
    level_2_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    level_2_at TIMESTAMP NULL,
    level_2_note TEXT DEFAULT NULL,
    
    level_3_approver VARCHAR(50) DEFAULT NULL,
    level_3_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    level_3_at TIMESTAMP NULL,
    level_3_note TEXT DEFAULT NULL,
    
    -- Final
    overall_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    resolved_at TIMESTAMP NULL,
    
    FOREIGN KEY (queue_id) REFERENCES debt_collection_queue(id),
    INDEX idx_queue (queue_id),
    INDEX idx_status (overall_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 11.5 debt_collection_settlements

```sql
CREATE TABLE debt_collection_settlements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    queue_id INT UNSIGNED NOT NULL,
    approval_id INT UNSIGNED DEFAULT NULL,
    
    -- Terms
    original_balance DECIMAL(15,2) NOT NULL,
    settlement_amount DECIMAL(15,2) NOT NULL,         -- KH phải trả
    discount_amount DECIMAL(15,2) NOT NULL,            -- Giảm
    discount_percent DECIMAL(5,2) NOT NULL,
    
    -- Payment plan
    payment_type ENUM('lump_sum','installment') DEFAULT 'lump_sum',
    installment_count TINYINT UNSIGNED DEFAULT 1,
    installment_frequency VARCHAR(20) DEFAULT NULL,    -- 'weekly','monthly'
    agreement_date DATE NOT NULL,
    due_by_date DATE NOT NULL,
    
    -- Status
    status ENUM('active','completed','defaulted','cancelled') DEFAULT 'active',
    amount_paid DECIMAL(15,2) DEFAULT 0,
    last_payment_date DATE NULL,
    
    -- Audit
    created_by VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (queue_id) REFERENCES debt_collection_queue(id),
    FOREIGN KEY (approval_id) REFERENCES debt_collection_approvals(id),
    INDEX idx_queue (queue_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 11.6 debt_collection_templates

```sql
CREATE TABLE debt_collection_templates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    template_code VARCHAR(50) NOT NULL UNIQUE,         -- 'pre_due','overdue_7d','overdue_14d',...
    template_type ENUM('email','sms','letter') NOT NULL,
    subject VARCHAR(200) DEFAULT NULL,                 -- Email subject
    body TEXT NOT NULL,
    
    -- Variables: {customer_name}, {invoice_number}, {amount}, {due_date}, {days_overdue}
    variables VARCHAR(500) DEFAULT NULL,               -- Comma-separated
    
    -- Status
    is_active TINYINT(1) DEFAULT 1,
    language VARCHAR(10) DEFAULT 'vi',
    
    created_by VARCHAR(50) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_code (template_code),
    INDEX idx_type (template_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 11.7 Entity Relationship

```
debt_collection_queue
  ├── customer_id → customers
  ├── invoice_id  → ar_invoices
  │
  ├── debt_collection_activities (queue_id)
  │     └── promise (activity_id nullable)
  │
  ├── debt_collection_promises (queue_id)
  │
  ├── debt_collection_approvals (queue_id)
  │     ├── level_1_approver → users
  │     ├── level_2_approver → users
  │     └── level_3_approver → users
  │
  ├── debt_collection_settlements (queue_id)
  │     └── approval_id → debt_collection_approvals
  │
  └── debt_collection_templates (standalone)
```

---

## 12. ArService Extensions

### 12.1 Sửa đổi tối thiểu

**ArService hiện tại KHÔNG sửa.** Module Debt Collection là service mới gọi ArService, không ngược lại.

Ngoại lệ: thêm event hook trong `recordPayment()` và `recordPrepayment()`:
```php
// Trong ArService.recordPayment(), trước commit:
// (Thêm) DebtCollectionService::onPaymentReceived($invoiceId, $amount);
// Gọi qua DI container pattern
```

### 12.2 Integration Points

```php
// DebtCollectionService sử dụng:
$this->arService                     // Gọi recordPayment() cho settlement
$this->journalService                // Post bút toán write-off
$this->accountRepo                   // Check balance 2293
$this->auditLogger                   // Log mọi hành động
$this->pdo                           // Transaction cho multi-step
```

---

## 13. API Endpoints

### 13.1 Collection Queue

| Method | Path | Description |
|---|---|---|
| GET | /api/debt-collection/queue | List queue (filterable: status, collector, priority) |
| GET | /api/debt-collection/queue/{id} | Queue detail + activities + promises |
| POST | /api/debt-collection/queue/generate | Manual trigger queue generation |
| PUT | /api/debt-collection/queue/{id}/assign | Assign collector |
| PUT | /api/debt-collection/queue/{id}/hold | Hold queue entry |
| PUT | /api/debt-collection/queue/{id}/release | Release from hold |
| PUT | /api/debt-collection/queue/{id}/priority | Update priority |

### 13.2 Collection Activities

| Method | Path | Description |
|---|---|---|
| GET | /api/debt-collection/queue/{id}/activities | List activities for queue entry |
| POST | /api/debt-collection/queue/{id}/activities | Create activity |
| PUT | /api/debt-collection/activities/{id} | Update activity |
| DELETE | /api/debt-collection/activities/{id} | Delete activity (soft?) |

### 13.3 Payment Promises

| Method | Path | Description |
|---|---|---|
| GET | /api/debt-collection/queue/{id}/promises | List promises |
| POST | /api/debt-collection/queue/{id}/promises | Create promise |
| PUT | /api/debt-collection/promises/{id} | Update promise |
| POST | /api/debt-collection/promises/{id}/keep | Mark as kept |
| POST | /api/debt-collection/promises/{id}/break | Mark as broken |

### 13.4 Write-off & Approvals

| Method | Path | Description |
|---|---|---|
| POST | /api/debt-collection/queue/{id}/propose-writeoff | Propose write-off |
| GET | /api/debt-collection/approvals | List pending approvals |
| PUT | /api/debt-collection/approvals/{id}/approve | Approve |
| PUT | /api/debt-collection/approvals/{id}/reject | Reject |

### 13.5 Settlements

| Method | Path | Description |
|---|---|---|
| POST | /api/debt-collection/settlements | Create settlement |
| GET | /api/debt-collection/settlements/{id} | Settlement detail |
| PUT | /api/debt-collection/settlements/{id}/pay | Record payment against settlement |

### 13.6 Templates & Reports

| Method | Path | Description |
|---|---|---|
| GET | /api/debt-collection/templates | List templates |
| POST | /api/debt-collection/templates | Create template |
| GET | /api/debt-collection/stats/collector | Collector performance stats |
| GET | /api/debt-collection/stats/summary | Summary dashboard |
| GET | /api/debt-collection/export | CSV export of queue |

### 13.7 Views

| Path | View |
|---|---|
| /debt-collection | Collection dashboard (queue summary by status, stats cards) |
| /debt-collection/queue | Full queue table (filter, sort, assign) |
| /debt-collection/queue/{id} | Queue detail (info, activity timeline, promises, actions) |
| /debt-collection/approvals | Pending approvals list |
| /debt-collection/reports | Performance reports |

---

## 14. User Journey

### 14.1 Collection Staff (Nhân viên đòi nợ)

| Time | Activity | System Support |
|---|---|---|
| 08:00 | Login -> View "My Queue" | Dashboard shows assigned queue, sorted by priority |
| 08:15 | Call KH A (overdue 7 days) | Click "Call" -> Log activity (type=call, result=promise) |
| 08:20 | KH A promises 20/06 | Create promise via activity form |
| 08:30 | Call KH B (no answer) | Log "no answer" -> auto-retry tomorrow |
| 09:00 | Email KH C | Log "sent email" + attach PDF template |
| 10:00 | Review KH D (escalated) | Review activity history, note dispute details |
| 14:00 | Propose write-off for KH E | Click "Propose Write-off" -> form pre-filled with aging + activity count |
| 15:00 | Check promises expiring | View "Promises Due Today" widget -> call to remind |

**Pain points (addressed):**
- KH gọi nhiều lần -> được log, không phải nhớ
- KH hứa hẹn -> system nhắc lại nếu không trả
- Queue tự động sắp xếp priority -> biết KH nào cần gọi trước

### 14.2 Collection Lead (Trưởng nhóm đòi nợ)

| Time | Activity | System Support |
|---|---|---|
| 08:00 | View team dashboard | Collector stats (calls today, promises today, collection rate) |
| 08:30 | Assign unassigned queue | View "Unassigned" filter -> drag-drop assign |
| 09:00 | Review escalation requests | Queue with escalation_level > 0 -> review -> accept or reassign |
| 10:00 | Approve write-off < 10tr | View approval request -> review activity history -> approve |
| 11:00 | Handle dispute | Put queue on hold + note dispute details |
| 14:00 | Review team performance | "My Team" stats: calls/day, promise kept rate, amount collected |
| 15:00 | Meeting reminder | System auto-generates weekly collection meeting agenda |

### 14.3 Chief Accountant (Kế toán trưởng)

| Time | Activity | System Support |
|---|---|---|
| 09:00 | Review pending approvals | View all write-off + settlement requests > 10tr |
| 09:30 | Review aging + provision | Cross-check provision summary vs collection queue |
| 10:00 | Approve settlement | Review settlement terms -> approve -> auto post payment |
| 14:00 | Monthly review | Collection performance report + bad debt trend |
| 15:00 | Legal review | Queue entries escalated to legal -> review status |

---

## 15. Reporting & KPIs

### 15.1 Operational KPIs (Collector Level)

| KPI | Formula | Target |
|---|---|---|
| Calls per day | COUNT(activities WHERE type=call AND date=today) | >= 15/ngày |
| Promise kept rate | COUNT(promise_kept) / COUNT(promises) * 100 | >= 60% |
| Amount collected | SUM(payments assigned to collector's queue) | Monthly target |
| Queue aging | AVG(days since queue entry created) | < 30 days |
| First call resolution | COUNT(paid within 7 days of first activity) | >= 20% |

### 15.2 Management KPIs (Team Level)

| KPI | Formula | Target |
|---|---|---|
| DSO | (AR balance / Revenue) * 30 | < 45 days |
| CEI (Collection Effectiveness Index) | Payment collected / (Opening AR + Sales - Closing AR) * 100 | >= 80% |
| Bad debt ratio | Write-off amount / Total AR | < 3% |
| Queue resolution rate | COUNT(closed queue) / COUNT(total queue) * 100 by period | >= 70% |
| Average resolution time | AVG(resolved_at - entered_at) for closed queue | < 60 days |
| Aging concentration | Balance > 90 days / Total AR | < 15% |

### 15.3 Reports

| Report | Content | Frequency |
|---|---|---|
| Collector Daily | Queue by collector, calls, promises, collected amount | Daily |
| Queue Aging | Queue entries by age bucket (0-7d, 8-30d, 31-60d, 61-90d, 90+d) | Daily |
| Promise Report | Active promises, broken rate, kept rate | Weekly |
| Write-off Report | Approved write-offs, amount, reason, approver | Monthly |
| Settlement Report | Active settlements, default rate, discount given | Monthly |
| Aging Trend | AR aging distribution over last 12 months | Monthly |
| Collection Funnel | Queue entries -> contacted -> promised -> paid (conversion %) | Monthly |
| Collector Scorecard | Full KPI breakdown per collector | Monthly |

---

## 16. Integration Contracts

### 16.1 ArService → DebtCollectionService

ArService KHÔNG gọi DebtCollectionService.
DebtCollectionService gọi ArService khi:
- `approveWriteoff()` → gọi `$this->arService->writeOff($invoiceId, $approvedBy)`
- `recordSettlementPayment()` → gọi `$this->arService->recordPayment()` hoặc `allocateReceipt()`

### 16.2 Cron → DebtCollectionService

```php
// cron/dunning.php — chạy 08:00 hàng ngày
$container = require 'config/services.php';
$dcs = $container[DebtCollectionService::class];

// 1. Generate queue entries for new overdue invoices
$dcs->generateQueueEntries();

// 2. Check promises for today
$dcs->checkPromisesDue();

// 3. Auto-escalate overdue items
$dcs->autoEscalate();

// 4. Auto-release expired holds
$dcs->autoReleaseHolds();

// (Optional) Log summary
$dcs->logDailySummary();
```

### 16.3 Audit Logger

Mọi hành động debt_collection đều được log:
- `dc.queue.create` — queue entry created
- `dc.queue.assign` — collector assigned
- `dc.queue.hold/release` — hold/unhold
- `dc.activity.create` — activity logged
- `dc.promise.create/keep/broken` — promise lifecycle
- `dc.approval.request/approve/reject` — approval lifecycle
- `dc.writeoff.execute` — write-off executed
- `dc.settlement.create/pay/default` — settlement lifecycle

### 16.4 Period Locking

- Queue generation SKIP invoices in closed periods (dựa vào invoice_date)
- Write-off CHỈ được thực hiện trong kỳ đang mở
- Settlement payments tuân theo period check của ArService

---

## 17. SME Pain Points Addressed

| Pain Point | Current State | Debt Collection Module |
|---|---|---|
| **Quên nhắc nợ** | Kế toán tự nhớ | Auto queue generation + dunning schedule |
| **KH hứa không giữ** | Ghi chú Excel, quên follow-up | Promise tracking + auto alert on broken |
| **Không bằng chứng đòi nợ** | Không log | Full activity history with timestamps |
| **Xóa nợ tùy tiện** | writeOff() trực tiếp | Multi-level approval workflow |
| **Không phân công** | Ai rảnh ai làm | Auto/manual assignment + load balancing |
| **Không KPI** | Không đo lường | 10+ KPIs + collector scorecard |
| **Leo thang tùy hứng** | Nhờ lead khi quá tải | Rule-based auto escalation |
| **Thiếu dòng tiền** | Phát hiện nợ quá hạn muộn | Pre-due reminder + early intervention |
| **Tranh chấp không lưu** | Email trao đổi rải rác | Dispute activity type + attachment |
| **Sai số dư 131** | Không đối chiếu thường xuyên | Queue auto-close khi payment (giảm lệch) |

---

## 18. Functional Rules Matrix

| # | Rule | Type | Category | Reference |
|---|---|---|---|---|
| F01 | Queue entry auto-generated when invoice overdue > 0 days | Auto | Dunning | DC-UC-01 |
| F02 | 1 queue entry per invoice (not grouped by customer) | Design | Queue | DC-UC-01 |
| F03 | Auto-skip queue if customer has active promise | Auto | Promise | Rule 9.1 |
| F04 | Auto-skip queue if customer on hold | Auto | Hold | Rule 9.1 |
| F05 | Max 50 active queue items per collector | Limit | Assignment | DC-UC-04 |
| F06 | Max 3 promises per queue item | Limit | Promise | Rule 9.4 |
| F07 | Promise grace period: +2 days | Tolerance | Promise | Rule 9.4 |
| F08 | Promise amount >= 10% balance | Validation | Promise | Rule 9.4 |
| F09 | Promise date <= 60 days from today | Validation | Promise | Rule 9.4 |
| F10 | Max hold duration: 30 days | Limit | Hold | Rule 9.3 |
| F11 | Max 3 holds per queue item | Limit | Hold | Rule 9.3 |
| F12 | Hold reason required | Validation | Hold | Rule 9.3 |
| F13 | Auto-escalate after 3 broken promises | Auto | Escalation | DC-UC-07 |
| F14 | Escalation levels 0-5 based on overdue days | Rule | Escalation | Rule 9.2 |
| F15 | Write-off min overdue: 365 days | Validation | Write-off | Rule 9.5 |
| F16 | Write-off min activities: 3 in 180 days | Validation | Write-off | Rule 9.5 |
| F17 | Write-off approval matrix by amount | Rule | Write-off | Rule 9.5 |
| F18 | Segregation: proposer ≠ approver | Rule | Write-off | Rule 9.5 |
| F19 | Settlement min %: 30% | Validation | Settlement | Rule 9.6 |
| F20 | Settlement due: max 14 days | Limit | Settlement | Rule 9.6 |
| F21 | One settlement per invoice | Limit | Settlement | Rule 9.6 |
| F22 | Min 1 activity per 14 days per queue | Auto | Activity | Rule 9.7 |
| F23 | Activity type required: call/email/meeting/sms/letter/dispute/other/auto | Enum | Activity | Rule 9.7 |
| F24 | Queue auto-close when invoice balance <= 1 | Auto | Queue | §10.2 |
| F25 | Queue does NOT auto-close on partial payment | Auto | Queue | §10.2 |
| F26 | Promises checked daily by cron | Auto | Cron | §16.2 |
| F27 | Period lock: queue skip invoices in closed period | Validation | Period | §16.4 |
| F28 | Write-off only in open period | Validation | Period | §16.4 |
| F29 | Audit log for every state transition | Required | Audit | §16.3 |
| F30 | All amounts in VND (or follow invoice currency) | Design | Currency | ArService |
| F31 | collector_id valid in users table (future: foreign key) | Validation | Assignment | DC-UC-04 |

---

## 19. Implementation Roadmap

### 19.1 Phase 1 — Core (Tuần 1-2)

| Step | Files | Tests |
|---|---|---|
| 1. Migration: 5 new tables | `database/migrations/NNN_create_debt_collection.php` | Verify table exists |
| 2. Model: QueueEntry, Activity, Promise, Approval, Settlement | `src/Accounting/Domain/Model/*` | Construct + toArray |
| 3. Repository Interface | `src/Accounting/Domain/Repository/DebtCollectionRepositoryInterface.php` | Interface exists |
| 4. Service: DebtCollectionService (core methods) | `src/Accounting/Domain/Service/DebtCollectionService.php` | 30+ tests |
| 5. Endpoints: queue CRUD | Controller + routes | 10+ tests |
| 6. Endpoints: activities CRUD | Controller + routes | 10+ tests |
| 7. Endpoints: promises CRUD | Controller + routes | 10+ tests |
| 8. Cron: dunning.php | `cron/dunning.php` | Manual test |
| 9. Views: queue list + detail | `public/views/debt_collection_*.php` | Manual test |
| 10. Full test suite | `tests/DebtCollectionTest.php` | 30+ tests, 0 failures |

### 19.2 Phase 2 — Advanced (Tuần 3-4)

| Step | Files | Tests |
|---|---|---|
| 1. Write-off approval workflow | DebtCollectionService + Approval model | 15+ tests |
| 2. Settlement workflow | DebtCollectionService + Settlement model | 10+ tests |
| 3. Escalation (cron rules enhance) | DebtCollectionService | 10+ tests |
| 4. Templates CRUD | Controller + routes + migration | 5+ tests |
| 5. Dashboard view | `public/views/debt_collection_dashboard.php` | Manual test |
| 6. Approval UI | `public/views/debt_collection_approvals.php` | Manual test |
| 7. Reports + CSV export | Controller + service methods | 5+ tests |
| 8. Full integration test | Tests covering complete lifecycle | 20+ tests |
| 9. Code review + polish | All files | All tests pass |
| 10. Documentation | ADR + AGENTS.md update | N/A |

### 19.3 Total Effort

| Metric | Phase 1 | Phase 2 | Total |
|---|---|---|---|
| New files | ~15 | ~10 | ~25 |
| Test count | ~60 | ~60 | ~120 |
| Migration tables | 5 | 1 (templates) | 6 |
| API endpoints | ~15 | ~10 | ~25 |
| Cron jobs | 1 | 0 (extend) | 1 |

---

> **Tổng kết:** Debt Collection Module biến ArService từ hệ thống ghi nhận thụ động (passive recording) thành công cụ chủ động thu hồi (active collection). Giải quyết triệt để 10 pain points SME Việt Nam, tiết kiệm ~1.5 tỷ/năm cho doanh nghiệp 500 KH, tuân thủ TT 48/2019 và TT 99/2025.
