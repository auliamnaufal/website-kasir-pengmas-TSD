# P1 Data & Kasir Contract — for P2/P3

Final schemas and authorization rules delivered by P1 (spec #3). Read this instead
of P1's source when building P2/P3 features that touch employees, salary-based
authorization, or debt/credit (piutang) tracking.

## `users` table — new columns

| Column | Type | Nullable | Rule |
|---|---|---|---|
| `employee_id` | `string`, unique | yes | Auto-generated on create if empty: `EMP` + 5-digit zero-padded sequence (`EMP00001`, `EMP00002`, ...). The sequence is `count(employee_id IS NOT NULL) + 1` — **not** max-based, so a delete can make a future insert reuse a retired `employee_id`. Treat it as a display/lookup key, not a guaranteed-unique-forever identifier. |
| `nik` | `string`, unique | yes | National ID number. No format validation at the DB level. |
| `jabatan` | `string` | yes | Free-text job title, matched against `jabatan_configs.jabatan` (see below). Not a foreign key. |
| `gaji` | `decimal(12,2)` | yes | Base salary. |

All four are nullable because accounts created before this feature (and some
created after, e.g. non-employee admins) legitimately have none of them set.
**Any code reading `jabatan`/`gaji` to enforce a limit must treat null as "not
configured, do not block"** — this is the existing convention (see the
`potong_gaji` check in `KasirController`), not an edge case to "fix".

## `jabatan_configs` table

| Column | Type | Notes |
|---|---|---|
| `jabatan` | `string`, unique | Matches `users.jabatan` by value (not FK). |
| `level` | `unsigned int` | Hierarchy rank. Higher number = higher rank. |
| `simpanan_wajib` | `decimal(12,2)` | Mandatory savings amount for this jabatan. |
| `max_potongan_persen` | `decimal(5,2)` | Max percentage of `gaji` that can be outstanding via `potong_gaji` at once. |

**Hierarchy comparison rule:** a user's level is looked up via
`User::jabatanLevel(): int`, which returns
`jabatan_configs.level` for the user's `jabatan`, or **`0`** if the user's
`jabatan` is null or has no matching row. A missing/unconfigured jabatan is the
lowest rank, never treated as unrestricted. Compare two users with plain
`$a->jabatanLevel() > $b->jabatanLevel()`.

## `piutang` table — generic debt/credit ledger

Shared ledger for any module that creates an employee debt against future
salary deduction. P1 only ever writes `source_type = 'pembelian'` (kasir
purchases paid via `potong_gaji`); `pinjaman` and `kredit` are reserved enum
values for P2/P3 to use.

| Column | Type | Notes |
|---|---|---|
| `user_id` | FK → `users.user_id`, cascade delete | Debtor. |
| `source_type` | `enum('pembelian','pinjaman','kredit')` | What created this debt. |
| `source_id` | `unsigned bigint` | ID of the originating record. **Not a real foreign key** — which table it points to depends on `source_type` (e.g. `pembelian` → `payments.payment_id`). P2/P3 adding a new kind of debt should add a new `source_type` value and point `source_id` at their own record, following the same pattern. |
| `amount` | `decimal(12,2)` | |
| `status` | `enum('belum_lunas','lunas')`, default `belum_lunas` | |
| `period` | `string`, `"YYYY-MM"` | See cutoff convention below. |
| `settled_at` | `timestamp`, nullable | |
| `settled_by` | FK → `users.user_id`, nullable, set-null on delete | Admin who marked it lunas. |

**Period/cutoff convention** (`Piutang::periodFor(DateTimeInterface $date): string`,
implemented exactly as below — quote, don't re-derive):

> A period window runs from the 16th of the PREVIOUS month through the 15th of
> THIS month, and is labeled (`Y-m`) by the month containing its CLOSING day
> (the 15th) — not the month containing its opening day (the 16th). So:
> - day <= 15 -> window closes on the 15th of THIS month -> period = this month
> - day >= 16 -> window closes on the 15th of NEXT month -> period = next month

Always compute the period with `Piutang::periodFor($date)`; never format a
period string by hand. Outstanding-balance checks (e.g. "is this debt within
the employee's limit for the current period") sum `amount` where `status =
'belum_lunas' AND period = <that period>` for the user.

## `payments.payment_method` enum

Final values: `cash`, `qris`, `potong_gaji`. (Replaces the old
`credit_card`/`cash`/`bank_transfer` set — those are gone, not kept for
backward compat.) `potong_gaji` is the only value that creates a `piutang` row.

## Authorization: the `updateSalary` Gate

Defined in `app/Providers/AppServiceProvider.php`:

```php
Gate::define('updateSalary', function (User $actor, User $target) {
    return $actor->role === 'admin' || $actor->jabatanLevel() > $target->jabatanLevel();
});
```

Rule: allowed if the actor is `role === 'admin'`, OR the actor's jabatan level
is strictly greater than the target's (equal level does not authorize).

Call it from a controller/action exactly like the existing usage:

```php
Gate::authorize('updateSalary', $targetUser); // throws 403 if denied
```

Reuse this same Gate (don't define a new one) for any P2/P3 action that should
be gated by "admin or higher jabatan level than the target user" — e.g.
loan/credit approval.
