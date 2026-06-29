<!-- new README for CMMS v1.5 -->

# CMMS v1.5 — Maintenance & Supply Management System

## Log-in Instructions (TEST ACCOUNTS)

Super Admin
- Email: test.superadmin@cmms.test
- Password: password

Supply Officer (Administrative Division)
- Email: test.supply@cmms.test
- Password: password

Regular User
- Email: laurence@gmail.com
- Password: password

## Credential Storage Policy
These accounts are dummy/test profiles only.
Any credential, password hint, or plaintext secret must NOT be stored in the repository (commit history, docs, notes, or issue descriptions) to keep the project clean and secure.

## Required Database Tables
requests
inventory_assets
inventory_history
csm_surveys
users
physical_count_sessions
pm_schedules
pm_instances
pm_records

## Common Troubleshooting (System Fixes & Known Issues)

Security Headers / CSP
- Signed-in pages reviewed to remove/move nonces from inline script blocks that loader CSP does not match.
- We now keep CSP enabled but constrained to real usage in the app: local scripts, inline handlers, styles, and nonce-backed blocks.
- Share the same cspNonce from SecurityHeaders into views so nonce-based tags can render safely.

Login flow
- login.js must not contain a bare return at the top level (it becomes a SyntaxError).
- Session config: file driver recommended; avoid domain/encrypt mismatch.

Dashboard
- warranty_expiration and date_acquired are expected on inventory_assets; code catches DB exceptions if columns are missing.
- Standardize user.office and user.department in UPPERCASE to avoid case-sensitive role/branch/office mismatches.

Personnel Management
- Admin users are scoped to their own office/division in Manage Personnel.
- Supply Officer (can_supply) is still division-scoped in personnel views; no cross-office viewing here.
- Branch-level access is enforced first, then office is enforced after standardization.

Inventory / Asset pages
- Inventory view depends on correct script ordering; transfer/batch actions need their handler registered before events fire.
- Missing DB columns cause silent failures; check DB schema if errors mention unknown columns.

Survey / CSM
- Blade CSM form uses a @php block with strings; escape single quotes inside those strings or the view will throw a ParseError.
- Existing surveys store sqd answers as separate columns (sqd1..sqd9).

## Active Helper Scripts (root)
- clear_data.php — drops demo/test data and resets users
- reset_password.php — resets the test accounts' passwords
- check_columns.php — checks inventory_assets required columns
- fix_missing_columns.php — adds missing date_acquired/warranty_expiration columns
- standardize_case.php / uppercase_all.php — standardizes offices/departments to uppercase
- check_user_offices.php / check_user_11.php — quick audits for office/branch consistency
- update_supply_division.php — moves the supply officer to the Administrative Division

## Laravel 11 Quick Start
1) composer install
2) npm install
3) cp .env.example .env and set DB credentials
4) php artisan key:generate
5) php artisan migrate --seed
6) npm run build (or npm run dev)

## Suggested Flow
Login with Super Admin -> create adjust division admins -> assign office/branch -> manage inventory -> supply officer handles requests/assets

## Notes
- Use uppercase entries for office, department, branch, region to avoid logic gaps.
- CSM forms require the consent field to be "yes" before saving survey answers.