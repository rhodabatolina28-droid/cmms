# CMMS Asset Management Refactor

**Date:** 2026-07-20  
**Scope:** `resources/css/`, `resources/js/`, `public/`, Blade views, `vite.config.js`, `SecurityHeaders.php`

---

## Overview

Dalawang pangunahing pagbabago ang ginawa:

1. **Folder Restructure** — Inilipat ang lahat ng CSS at JS files mula sa nested `modules/` subfolder patungong root ng `resources/css/` at `resources/js/`
2. **Vite Migration** — Pinalit ang lahat ng `asset('css/...')` at `asset('js/...')` calls sa Blade views ng tamang `@vite()` directive

---

## Part 1 — Folder Restructure

### Dati (Before)

```
resources/
├── css/
│   ├── app.css
│   ├── components/     ← empty
│   ├── layouts/        ← empty
│   └── modules/        ← lahat ng 22 CSS files dito
│       ├── admin.css
│       ├── landing.css
│       ├── login.css
│       └── ...
└── js/
    ├── app.js
    ├── components/     ← empty
    └── modules/        ← lahat ng 19 JS files dito
        ├── inventory.js
        ├── maintenance-form.js
        └── ...
```

### Ngayon (After) — Current Structure

```
resources/
├── css/
│   ├── app.css                        ← shared (Tailwind)
│   ├── components/
│   │   ├── official.css               ← @import ng _typography, _layout, _components, _print
│   │   ├── responsive.css             ← @import ng _forms, _layout, _modals, _tables
│   │   ├── official/                  ← partials
│   │   └── responsive/                ← partials
│   ├── layouts/
│   │   ├── admin.css                  ← @import ng _sidebar, _main, _grids, _topbar, _cards, _tables
│   │   ├── auth.css                   ← @import ng _base, _login-box, _form, _responsive
│   │   ├── admin/                     ← partials
│   │   └── auth/                      ← partials
│   └── modules/
│       ├── dashboard/                 ← admin.css, it.css, user.css, super-admin.css + partials
│       ├── inventory/                 ← index.css, detail.css, qr-batch.css + partials
│       ├── landing/                   ← landing.css + partials
│       ├── maintenance/               ← pm.css, ict-form.css + partials
│       ├── personnel/                 ← admin.css
│       ├── requests/                  ← admin.css, super-admin.css
│       ├── survey/                    ← survey.css, modal.css, responsive.css, consent.css + partials
│       └── users/                     ← super-admin.css + partials
└── js/
    ├── app.js                         ← shared
    ├── layouts/                       ← auth.js
    ├── components/                    ← disabled-button.js, qr-scanner.js
    └── modules/
        ├── inventory/                 ← index.js, init.js, detail.js, qr-batch.js, physical-count.js
        ├── maintenance/               ← pm-form.js, pm-assets.js, schedules.js, ict-form.js, ict-form-init.js
        ├── personnel/                 ← admin.js
        ├── requests/                  ← super-admin.js
        ├── survey/                    ← survey.js, consent.js
        └── users/                     ← super-admin.js
```

### Architecture Pattern

Ang CSS ay gumagamit ng **partial-based architecture**:
- Parent file (e.g., `admin.css`) ay nag-i-import ng mga partials (e.g., `_sidebar.css`, `_main.css`)
- Ginagamit ang `@import` sa CSS (hindi Sass/LESS)
- Consistent naming: `_filename.css` para sa partials

---

## Part 2 — Vite Migration

### Problema sa Dati

Ang lumang setup ay **manually nakalagay ang files sa `public/`** at ginagamit ang `asset()` helper:

```html
<!-- Lumang paraan — manual, workaround cache busting, walang HMR -->
<link href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}" rel="stylesheet">
<script src="{{ asset('js/inventory.js') }}?v={{ filemtime(public_path('js/inventory.js')) }}"></script>
```

**Mga problema nito:**
- Kailangan pang mag-manually copy ng files sa `public/`
- Ang `?v=filemtime(...)` ay workaround lang para sa cache busting
- Walang Hot Module Replacement (HMR) sa development
- Hindi tamang Laravel/Vite practice

### Tamang Paraan Ngayon

```html
<!-- Bagong paraan — Vite ang bahala sa lahat -->
@vite('resources/css/admin.css')
@vite('resources/js/inventory.js')
```

**Mga benepisyo:**
- Automatic **cache busting** via content hashing (e.g., `admin-j23sYPZn.css`)
- **HMR** (Hot Module Replacement) sa `npm run dev`
- Walang manual file copying sa `public/`
- Tamang Laravel best practice

### Flow Ngayon

```
resources/css/*.css   ─┐
resources/js/*.js     ─┤  npm run build  →  public/build/assets/*.[hash].css/js
                        └──────────────────  @vite() sa Blade → tamang URL
```

---

## Part 3 — CSS Cleanup (July 2026)

Inilipat ang lahat ng inline `<style>` blocks sa blades papunta sa external CSS files na naka-`@vite()`:

| Blade | Before | After |
|---|---|---|
| `dashboard/admin.blade.php` | Inline `<style>` (240 lines) | `@vite('resources/css/modules/dashboard/admin.css')` |
| `dashboard/it.blade.php` | Inline `<style>` (239 lines) | `@vite('resources/css/modules/dashboard/it.css')` |
| `dashboard/user.blade.php` | Inline `<style>` (226 lines) | `@vite('resources/css/modules/dashboard/user.css')` |
| `dashboard/super-admin.blade.php` | Inline `<style>` (283 lines) | `@vite('resources/css/modules/dashboard/super-admin.css')` |
| `inventory/detail.blade.php` | Inline `<style>` (530 lines) | `@vite('resources/css/modules/inventory/detail.css')` |
| `inventory/qr-batch.blade.php` | Inline `<style>` (316 lines) | `@vite('resources/css/modules/inventory/qr-batch.css')` |
| `admin/requests/index.blade.php` | Inline `<style>` (318 lines) | `@vite('resources/css/modules/requests/admin.css')` |
| `super-admin/requests/index.blade.php` | Inline `<style>` (228 lines) | `@vite('resources/css/modules/requests/super-admin.css')` |
| `admin/personnel/index.blade.php` | Inline `<style>` (300+ lines) | `@vite('resources/css/modules/personnel/admin.css')` |

### Dead Code Removed
- `InventoryController::publicProfile()` — walang route, potential IDOR risk
- `maintenance.create` at `maintenance.store` redirect routes — obsolete, PM creation handled by scheduler

---

## Development Workflow

### Dev Mode (HMR)
```bash
npm run dev
```
- Automatic browser refresh sa bawat save
- No need to manually rebuild

### Production Build
```bash
npm run build
```
- Compiles at fingerprints lahat ng assets
- Output: `public/build/`

---

## Notes para sa Future Development

> [!IMPORTANT]
> Sundin ang mga alituntuning ito para maiwasan ang regression:

1. **Bagong CSS/JS file** → ilagay sa `resources/css/` o `resources/js/`, tapos idagdag sa `vite.config.js` input array, tapos gamitin ang `@vite()` sa blade
2. **Huwag gumamit ng `asset()`** para sa CSS/JS — `@vite()` na lang palagi
3. **Inline `<style>`** → iwasan. Ilagay sa external CSS file at gamitin ang `@vite()`
4. **Images** → ilagay direkta sa `public/images/` (hindi sa `resources/images/`)
5. **Vendor libraries** (pre-minified) → ilagay sa `public/js/` at gamitin ang `asset()` — hindi `@vite()`
6. **Pagkatapos ng `npm run build`**, ang lahat ng CSS/JS ay nasa `public/build/assets/` na may hash suffix — normal ito