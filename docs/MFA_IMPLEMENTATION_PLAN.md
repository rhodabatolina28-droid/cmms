# Implement PHPUnit Tests & MFA (TOTP)

This plan covers two action items from `SYSTEM_REVIEW.md`:
- **#13**: Add PHPUnit test coverage for auth + RBAC flows
- **#15**: Add MFA (TOTP) for Admin / Super Admin accounts

## User Review Required

> [!IMPORTANT]
> Adding MFA (Multi-Factor Authentication) will significantly change the login flow for `admin` and `super_admin` users. They will be required to use an authenticator app (like Google Authenticator, Authy, or Microsoft Authenticator) to log in. 
> 
> **Are you okay with enforcing this for all admin and super admin users immediately upon deployment?** (If they haven't set it up, they will be forced to set it up after entering their username/password).

## Proposed Changes

### 1. Multi-Factor Authentication (MFA) Setup

#### [NEW] Database Migration
- Create a migration to add `two_factor_secret`, `two_factor_recovery_codes`, and `two_factor_confirmed_at` to the `users` table.

#### [MODIFY] `composer.json`
- Install required packages:
  - `pragmarx/google2fa-laravel` (Core TOTP logic)
  - `bacon/bacon-qr-code` (For generating QR codes during setup)

#### [MODIFY] `app/Models/User.php`
- Add the new 2FA columns to the `$hidden` array for security.

#### [NEW] `app/Http/Controllers/MfaController.php`
- Handle the MFA logic:
  - `showSetup()`: Generate a new secret and display the QR code.
  - `confirmSetup()`: Verify the code and activate MFA.
  - `showVerify()`: Show the form to enter the TOTP code during login.
  - `verify()`: Validate the code and complete the login process.

#### [MODIFY] `app/Http/Controllers/AuthController.php`
- Modify the `login()` method. Instead of fully logging in `admin` and `super_admin` users immediately, we will do a "partial login" (store user ID in session) and redirect them to the MFA verification page.
- If an admin hasn't set up MFA yet, redirect them to the MFA setup page.

#### [MODIFY] `routes/web.php`
- Add routes for `/mfa/setup`, `/mfa/confirm`, `/mfa/verify`, and `/mfa/verify-post`.

#### [NEW] `resources/views/auth/mfa-setup.blade.php` & `resources/views/auth/mfa-verify.blade.php`
- Create the UI for scanning the QR code and entering the 6-digit TOTP code.

---

### 2. PHPUnit Test Coverage

#### [NEW] `tests/Feature/AuthTest.php`
- Write automated tests to verify:
  - Successful login flow.
  - Failed login attempts (wrong password).
  - Rate limiting / anti-brute force mechanism (locking out after 5 attempts).
  - Deactivated users cannot log in.

#### [NEW] `tests/Feature/RoleBasedAccessControlTest.php`
- Write automated tests to verify route protection (403 Forbidden):
  - A regular `user` cannot access the `super_admin` or `admin` dashboards.
  - An `admin` cannot access the `super_admin` users management page.
  - An `it` personnel cannot access Super Admin-only configuration pages (like PM Schedule Generation config).

## Verification Plan

### Automated Tests
- Run `php artisan test` to ensure all new authentication and RBAC tests pass.

### Manual Verification
1. Log in as a regular user (should skip MFA).
2. Log in as an admin or super admin for the first time (should redirect to MFA setup with QR code).
3. Scan QR code and verify setup.
4. Log out and log back in (should prompt for the 6-digit code).
5. Ensure failing the 6-digit code denies access.
