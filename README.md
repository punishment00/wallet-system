# Wallet System - Concurrency Handling

## 1. Concurrency Problem

### 1.1 Race Conditions

When multiple requests access and modify the same wallet balance concurrently, race conditions can occur. For example, if two deposit requests are processed at the same time, both requests may read the initial balance, calculate the new balance independently, and then write back the result. As a result, one of the deposits may be overwritten, leading to an incorrect final balance.

### 1.2 Data Inconsistency

Concurrent operations can also cause data inconsistency between the wallet balance and the associated transaction records. If a withdrawal and a deposit occur simultaneously without proper synchronization, the balance may not accurately reflect the actual transactions.

---

## 2. Handling Concurrency

### 2.1 Pessimistic Locking

Both `deposit` and `withdraw` methods use `lockForUpdate()` to prevent race conditions when modifying balances.

```php
$wallet = Wallet::where('id', $wallet_id)->lockForUpdate()->first();
```

This ensures that no other transaction can modify the `wallet` row until the current transaction completes.

The **RebateJob** also uses `lockForUpdate()` to prevent conflicts when modifying the wallet balance.

```php
$wallet = Wallet::where('id', $this->wallet_id)->lockForUpdate()->first();
```

This ensures that the rebate calculation does not interfere with concurrent deposits or withdrawals.

### 2.2 Why Pessimistic Locking?

Pessimistic locking was chosen over optimistic locking in this system because:

-   **High Contention**: In wallet transactions, multiple users may try to update the same wallet simultaneously, increasing the risk of conflicts.
-   **Immediate Conflict Detection**: `lockForUpdate()` ensures that no other transaction can modify the wallet while an update is in progress, preventing inconsistencies.
-   **Ensuring Data Integrity**: Since deposits and withdrawals must be processed in a strict order, pessimistic locking prevents race conditions more effectively than retry-based optimistic locking.

---
