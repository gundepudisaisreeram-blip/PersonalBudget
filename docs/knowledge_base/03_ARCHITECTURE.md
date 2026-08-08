# 03_ARCHITECTURE.md

## Status

Version: 1.0  
Status: ARCHITECTURE BASELINE  
Purpose: Define how the Laravel application is structured and how domain responsibilities are separated.

## 1. Architecture Principles

1. Domain/business rules are authoritative.
2. Controllers orchestrate; they do not own financial calculations.
3. Models represent persistence and relationships.
4. Services/Actions own meaningful business operations.
5. Financial calculations are centralized and testable.
6. Staged imports are isolated from the actual ledger.
7. Database transactions protect multi-record financial operations.
8. Historical data is preserved.
9. External bank parsing is adapter-based.
10. No unnecessary infrastructure.

## 2. Technology

- Laravel 13
- PHP 8.3+
- MySQL
- Blade
- Bootstrap 5
- Vanilla JS / Alpine.js where justified
- Chart.js
- PWA
- Hostinger shared hosting

Production must not require Node.js.

## 3. Suggested Laravel Layers

```text
HTTP
 ├── Controllers
 ├── Form Requests
 └── Policies

Application
 ├── Actions
 ├── Services
 └── Jobs

Domain-oriented services
 ├── Accounts
 ├── Transactions
 ├── Obligations
 ├── Budgets
 ├── Statements
 ├── Categorization
 ├── Reconciliation
 └── Forecasting

Persistence
 ├── Eloquent Models
 ├── Migrations
 └── Database

Presentation
 └── Blade + Bootstrap
```

Do not create unnecessary repositories/interfaces merely for abstraction.

## 4. Core Services

Expected services include:

- AccountBalanceService
- TransactionService
- TransferService
- PaymentObligationService
- BudgetService
- SafeToSpendService
- StatementImportService
- StatementValidationService
- CategorizationService
- ReconciliationService
- BalanceReconciliationService
- MonthlyGenerationService
- ReportingService

Exact names may change if implementation demonstrates a better coherent structure.

## 5. Transaction Boundary Rules

Use database transactions for:

- Transfers
- Creating a transaction with multiple ledger movements
- Allocating transactions to obligations
- Committing statement transactions
- Adjustments
- Reversals/refunds where multiple records must remain consistent

Failure must roll back the complete financial operation.

## 6. Ledger Architecture

A Transaction is a business event.

Ledger movements/entries represent account-level effects.

The design must support:

- One-account effects where appropriate.
- Two-account transfers.
- Asset/liability movements.
- Future loan principal/interest splits.

The implementation does not need a full general-ledger accounting UI.

## 7. Planned vs Actual vs Staged

Planned records never affect actual account balances.

Staged records never affect actual account balances.

Actual ledger movements affect derived balances.

Derived values must never become a second source of truth.

## 8. Monthly Generation

Monthly generation must:

1. Determine applicable active templates.
2. Determine target financial period.
3. Calculate due date.
4. Create obligation only if an equivalent obligation does not already exist.
5. Preserve historical instances.
6. Be safe to run repeatedly.

The scheduler and a manual recovery action may invoke the same service.

## 9. Statement Parser Architecture

Use a common parsing contract and bank-specific adapters.

Conceptually:

```text
StatementParser
├── ICICIParser
├── SBIParser
├── KVBParser
└── KotakParser
```

A parser should produce normalized statement transactions independent of UI/database details.

Parser failures must be explicit.

## 10. Statement Pipeline

```text
Upload
→ Secure Storage
→ Detect/Confirm Bank
→ Parse
→ Normalize
→ Validate
→ Deduplicate
→ Categorize
→ Match
→ Review
→ Commit/Link/Ignore
```

No direct import-to-ledger shortcut.

## 11. Reconciliation Architecture

Separate:

### Transaction Reconciliation

Statement transaction ↔ existing ledger transaction and/or planned obligation.

### Balance Reconciliation

Ledger balance ↔ statement closing balance.

Do not merge these into one status.

## 12. Categorization Architecture

Rules are deterministic and ordered.

A rule may use:

- Normalized description
- Reference
- Bank
- Account
- Direction
- Amount range where appropriate

Rules produce suggestions with confidence/priority.

User corrections can create/update rules through explicit actions.

## 13. Authorization

All user-owned financial resources must be scoped to the authenticated user.

Policies should protect:

- Accounts
- Transactions
- Obligations
- Budgets
- Statements
- Reports
- Goals

Never trust IDs supplied by the browser.

## 14. File Security

Uploaded bank statements must:

- Be validated by MIME/type and size.
- Be stored privately.
- Never be executable.
- Not be directly publicly accessible.
- Have controlled lifecycle/retention.

## 15. PWA Architecture

Use:

- Web manifest
- Service worker
- Cache static assets
- Offline fallback

No offline mutation synchronization in V1.

## 16. Scheduler / Queue

Use Laravel Scheduler for recurring generation and scheduled processing.

Use database-backed queues initially if asynchronous processing is required and Hostinger supports the required setup.

Design jobs to be idempotent.

## 17. Error Handling

Financial operations should fail loudly and safely.

User-facing errors should be understandable without exposing sensitive internals.

Technical details should be logged securely.

## 18. Observability

Maintain:

- Application logs
- Financial audit logs
- Import processing status
- Failed job information
- Reconciliation history

Never log secrets or bank credentials.

## 19. Architecture Boundaries

Do not add:

- Microservices
- External event buses
- CQRS
- Event sourcing
- Full accounting ERP features
- Direct bank API integration

unless explicitly approved by a later architecture decision.

## 20. Testing Architecture

Use unit tests for deterministic calculations and feature/integration tests for:

- Transactions
- Transfers
- Obligations
- Statement import
- Reconciliation
- Authorization
- Database integrity

## 21. Deployment

The production application must be deployable to Hostinger shared hosting with:

- PHP
- MySQL
- Composer-compatible dependencies
- Prebuilt frontend assets
- Laravel scheduler/cron where available

No production Node runtime.

## 22. Architecture Decision Records

Significant changes to this architecture should be recorded in the changelog or an architecture decision record with:

- Decision
- Reason
- Alternatives
- Consequences
- Approval/status
