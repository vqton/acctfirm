// Core Financial Types — Vietnamese Accounting Standards (TT99/2025)

/** Branded VND amount. Never NaN/Infinity. */
export type VndAmount = number & { readonly __brand: 'VndAmount' };

/** Inventory quantity. Always >= 0. */
export type Quantity = number & { readonly __brand: 'Quantity' };

/** Percentage rate [0, 100]. */
export type Percent = number & { readonly __brand: 'Percent' };

export type CurrencyCode = 'VND' | 'USD' | 'EUR' | string;

// ---- Account ----

export interface Account {
  readonly id: string;
  readonly code: string;
  readonly name: string;
  readonly type: AccountType;
  readonly isControl: boolean;
  readonly balance?: number;
}

export type AccountType =
  | 'asset' | 'liability' | 'equity'
  | 'revenue' | 'expense';

// ---- Journal ----

export interface JournalLine {
  readonly accountCode: string;
  readonly amount: VndAmount;
  readonly isDebit: boolean;
  readonly description?: string;
}

export type JournalStatus =
  | 'draft' | 'pending' | 'posted' | 'reversed' | 'cancelled';

export interface Transaction {
  readonly id: string;
  readonly transactionDate: string;
  readonly reference: string;
  readonly description: string;
  readonly status: JournalStatus;
  readonly lines: LedgerLine[];
  readonly createdBy: string;
}

export interface LedgerLine {
  readonly accountCode: string;
  readonly accountName: string;
  readonly amount: VndAmount;
  readonly isDebit: boolean;
}

// ---- Inventory ----

export interface GoodsIssue {
  readonly id: string;
  readonly issueNumber: string;
  readonly issueDate: string;
  readonly issueType: IssueType;
  readonly status: GoodsIssueStatus;
  readonly lines: GoodsIssueLine[];
  readonly totalAmount: VndAmount;
  readonly receiverName: string;
  readonly warehouseId: string | null;
  readonly createdBy: string;
}

export type IssueType =
  | 'sale' | 'production' | 'construction' | 'internal' | 'other';

export type GoodsIssueStatus =
  | 'draft' | 'posted' | 'cancelled';

export interface GoodsIssueLine {
  readonly id: number | null;
  readonly itemId: string;
  readonly itemCode: string;
  readonly itemName: string;
  readonly uom: string | null;
  readonly requestedQty: Quantity;
  readonly actualQty: Quantity;
  readonly unitPrice: VndAmount;
  readonly totalAmount: VndAmount;
  readonly transactionId: string | null;
}

// ---- Partner (customer/supplier/employee) ----

export interface Partner {
  readonly id?: string;
  readonly code?: string;
  readonly name: string;
  readonly type: 'customer' | 'supplier' | 'employee';
}

// ---- API ----

export interface ApiResponse<T> {
  readonly data?: T;
  readonly error?: string;
  readonly ok?: true;
}

// ---- Safe number helpers ----

export function toVnd(value: unknown): VndAmount {
  const n = Number(value);
  return (Number.isFinite(n) ? n : 0) as VndAmount;
}

export function toQty(value: unknown): Quantity {
  return Math.max(0, toVnd(value)) as Quantity;
}

export function esc(s: unknown): string {
  const str = typeof s === 'string' ? s : String(s);
  return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
