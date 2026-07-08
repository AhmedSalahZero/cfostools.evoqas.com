# Audit Report — consulting.evoqas.com & investawatch.veroanalysis.com

**Date:** 2026-07-01  
**Scope:** Security, Performance, Errors, Stability, System Drift

---

## Executive Summary

| Severity | Count | Status |
|----------|-------|--------|
| Critical | 5 | Phase 1 fixes in progress |
| High | 8 | Phase 2 planned |
| Medium | 10 | Phase 3 planned |
| Low | 4 | Phase 4 planned |

**Top 5 priorities:**
1. Company access control (IDOR on `show()` and `{company}` routes)
2. InvestaDocs org IDOR via URL `orgId` manipulation
3. Dashboard notes API without authorization
4. `PortfolioCompanyController::show()` — 50+ queries per request
5. Queue workers required for file uploads (`QUEUE_CONNECTION=database`)

---

## Already Fixed (pre-audit)

| ID | Issue | Status |
|----|-------|--------|
| FIX-01 | `FinancialStatementExport` — `$fontSize` parameter | Fixed in code |
| FIX-02 | `FinancialStatementTemplateExport` — `Color::setBold()` | Fixed in code |
| FIX-03 | Cash flow O/I/F + `cf_category` | Fixed both systems |
| FIX-04 | `canEditPortfolioCompany()` / `canManagePortfolioCompanies()` | Fixed both systems |
| FIX-05 | `DashboardController` (consulting) | Done — investawatch pending |

---

## Security Findings

| ID | Sev | System | File | Problem | Fix |
|----|-----|--------|------|---------|-----|
| SEC-01 | Critical | Both | `PortfolioCompanyController::show()` | No org/assignment check — IDOR | `canAccessPortfolioCompany()` |
| SEC-02 | Critical | Both | `InvestaDocsController::resolveOrg()` | Route `orgId` ignored for non super-admin | Validate `orgId === user.organization_id` |
| SEC-03 | Critical | Both | `routes/web.php` notes API | GET/POST/DELETE without auth checks | `CompanyNoteController` + access guard |
| SEC-04 | High | Both | `InvestaDocsController::download/destroy` | No org authorization | Call `resolveOrg()` + validate |
| SEC-05 | High | Both | `PortfolioCompanyController::destroy()` | Admin can delete cross-org company | `canManagePortfolioCompany()` |
| SEC-06 | High | Both | `FinancialStatementController::export()` | No statement ownership check | Verify statement belongs to company |
| SEC-07 | High | Both | `SalesAnalysisController` | `findOrFail` only — no access check | `canAccessPortfolioCompany(..., 'sales_analysis')` |
| SEC-08 | Medium | Both | `UserCompanyPermission` | Stored in DB, never enforced | `hasCompanyPermission()` in access layer |
| SEC-09 | Medium | Both | InvestaDocs mutating routes | `auth` only, not admin | Restrict write to admin role |
| SEC-10 | Medium | Both | `ProjectController::getCompany()` | Admin bypasses org check | Use unified access method |

---

## Performance Findings

| ID | Sev | System | File | Problem | Fix |
|----|-----|--------|------|---------|-----|
| PERF-01 | High | Both | `PortfolioCompanyController::show()` | 50+ sequential DB queries | Aggregated queries, lazy tabs |
| PERF-02 | High | Both | `show()` budget block | N+1 per income section | Batch load line items + actuals |
| PERF-03 | High | Both | `show()` FS block | Per-section `sum()` queries | Single grouped query |
| PERF-04 | High | consulting | `/dashboard-legacy` | 200+ queries in closure | Remove or super-admin only |
| PERF-05 | High | investawatch | `/dashboard` closure | 330-line inline logic | Extract `DashboardController` |
| PERF-06 | Medium | Both | `StatisticaController::index()` | N+1 entries per series | `whereIn` batch load |
| PERF-07 | Medium | Both | `ModelStudio/Editor.vue` | Static Univer imports ~1.2MB | Dynamic `import()` |
| PERF-08 | Medium | Both | Missing DB indexes | Slow notes, budget, KPI lookups | Add composite indexes |

---

## Errors & Logs (`laravel.log`)

| ID | Sev | Error | Fix |
|----|-----|-------|-----|
| ERR-01 | High | `User::canManagePortfolioCompanies()` undefined | Deploy latest code + clear opcache |
| ERR-02 | Medium | `Unknown named parameter $fontSize` | Fixed — redeploy |
| ERR-03 | Medium | `sales_data.product` column not found | Verify no legacy references |
| ERR-04 | Medium | Vite manifest `app.css` missing | Run `npm run build` on server |
| ERR-05 | Low | Disk `[private]` not configured | Configure `filesystems.php` |
| ERR-06 | High | `StatisticaController` null org → 500 | `abort_unless($org, 404)` |

---

## Stability Findings

| ID | Sev | Problem | Fix |
|----|-----|---------|-----|
| STAB-01 | High | Upload jobs need `queue:work` | Document + supervisor config |
| STAB-02 | Medium | `destroy()` no `DB::transaction()` | Wrap cascade delete |
| STAB-03 | Medium | `show()` writes `current_valuation` on GET | Compute only, no side-effect |
| STAB-04 | Low | `queue.after_commit = false` | Set `true` for database driver |

---

## System Drift (consulting vs investawatch)

| Feature | consulting | investawatch |
|---------|------------|--------------|
| Dashboard | `DashboardController` (contracts) | Inline closure (PE) |
| Customer Contracts | Yes | No (by design) |
| Export fixes | Synced | Synced |
| Permission helpers | Synced | Synced |
| Access enforcement | Being added | Being added |

---

## Remediation Phases

See implementation plan: Critical → High → Medium → Low.

**Queue worker setup** (`docs/QUEUE_SETUP.md`):
```bash
php artisan queue:work database --sleep=3 --tries=3
```
