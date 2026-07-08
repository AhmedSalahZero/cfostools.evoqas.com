# Client Error Audit — consulting.evoqas.com & investawatch.veroanalysis.com

**Date:** 2026-07-01  
**Status:** Remediation completed in codebase (verify on staging/production)

## Summary

| Severity | Found | Fixed |
|----------|-------|-------|
| Critical | 4 | 4 |
| High | 7 | 7 |
| Medium | 6 | 5 (CE-16 deferred) |

## Findings — Final Status

| ID | Severity | Issue | Status |
|----|----------|-------|--------|
| CE-01 | Critical | Queue uploads stuck without worker | Fixed — `failed()` on jobs + Vue polling every 4s |
| CE-02 | Critical | `company_phase->value` null crash | Fixed — `?->value` on portfolio index |
| CE-03 | Critical | `importSalesDimension` wrong columns | Fixed — map `product`→`product_item`, `category`→`product_category` |
| CE-04 | Critical | Missing authorization on 8 controllers | Fixed — `authorizeCompany()` on Document, FinancialStudy, ModelStudio, OpeningBalance, Expense, Profitability, FinancialPlanning, CashForecast |
| CE-05 | High | Export Sales partial auth | Fixed — all `ExportSalesAnalysisController` actions |
| CE-06 | High | FS export no try/catch | Fixed — export wrapped with user-friendly error |
| CE-07 | High | FS template no ownership check | Fixed — `abort_unless` statement belongs to company |
| CE-08 | High | CashForecast leaks exception message | Fixed — generic client message + log |
| CE-09 | High | Upload pages no polling | Fixed — Sales, ExportSales, Expense Upload.vue |
| CE-10 | High | Jobs no `failed()` handler | Fixed — all three Process*Upload jobs |
| CE-11 | High | investawatch dashboard budget N+1 | Fixed — aggregated budget queries |
| CE-12 | Medium | `Projecttask.php` case mismatch | Fixed — renamed to `ProjectTask.php` |
| CE-13 | Medium | Vue missing prop defaults | Fixed — Editor, Create, Show |
| CE-14 | Medium | `company.access` unused | Fixed — applied on `portfolio-companies.show` |
| CE-15 | Medium | Raw upload error in expense UI | Fixed — `friendlyUploadError()` |
| CE-16 | Medium | Financial Planning on public disk | Deferred — requires migration of existing files |
| CE-17 | Medium | Pending index migration | Fixed — `2026_07_01_120000_add_audit_performance_indexes` |

## Smoke Test Checklist

Static verification completed 2026-07-01:

- [x] `php -l` on modified controllers — pass
- [x] `npm run build` — pass (both systems)
- [x] Index migration `2026_07_01_120000` — Ran (consulting)
- [x] `portfolio-companies.show` has `company.access:view_company` middleware
- [x] Export Sales auth on all 11 controller entry points (investawatch)
- [x] `ProjectTask.php` filename matches class (Linux case-sensitive)

Manual verification on staging/production:

- [ ] Portfolio index with NULL `company_phase` — no 500
- [ ] Financial Study → import dimension `column=product` — returns items
- [ ] Sales upload → status updates within 4s when queue worker runs
- [ ] FS export → succeeds; corrupt file shows friendly error
- [ ] Viewer without permission → 403 on protected module
- [ ] investawatch dashboard loads for org with 10+ companies

## Deployment

```bash
php artisan migrate
php artisan optimize:clear
npm run build
php artisan queue:work --sleep=3 --tries=3   # production: use Supervisor
```
