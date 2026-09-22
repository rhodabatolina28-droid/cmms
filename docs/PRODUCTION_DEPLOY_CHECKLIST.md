# CMMS — Production Deploy Checklist & Scheduler Runbook

> **Status: READY — dokumento lang, hinihintay ang production server.**
> Ito ang **Phase 6** ng CSM roadmap (ang natitirang bahagi ng `asset-downtime-tracking.md`).
> Ang lahat ng code ay tapos at naka-push na sa `develop`; ang nasa ibaba ay ang
> **go-live operations** — kung ano ang patakbuhin, kailan, at paano i-verify.
>
> **Basahin muna ito bago ang deployment day.** Ang 3 pinakamadaling makalimutan:
> 1. **`npm run build`** — hindi naka-track sa git ang `public/build`, kaya kung walang build = walang CSS/JS.
> 2. **`MAIL_MAILER=smtp`** — kung hindi smtp, ang lahat ng notification email ay pumupunta sa **queue** (kailangan ng queue worker, at tahimik na maiipon).
> 3. **Isang cron line lang** — `schedule:run` kada minuto; saklaw na nito ang **lahat ng 6 na schedule** (hindi na kailangan ng tig-isang cron entry).

---

## 1. Server requirements

| Item | Requirement | Tandaan |
|---|---|---|
| PHP | **8.3+** (dev = 8.3.30) | Ang **PHP CLI** na gagamitin ng cron/queue ay dapat **pareho** ng extensions ng web PHP |
| PHP extensions | `dom` · `fileinfo` · `gd` · `intl` · `mbstring` · `openssl` · `pdo_mysql` · `zip` | `gd` = DomPDF images (logo, face icons, ✓ SVG) · `intl` = date/format |
| Database | **MySQL** (dev = `cmms_laurence`) | `pdo_sqlite` ay hindi kailangan sa production |
| Composer | 2.x | `composer install --no-dev --optimize-autoloader` |
| Node / npm | Node 20+ | **Build step lang** (maaaring sa build machine; hindi kailangan tumakbo sa prod) |
| Web server | Apache / Nginx / IIS | Kailangan naka-point ang document root sa **`public/`** (hindi sa project root) |
| Laravel | 13.8.0 · CSS/JS via **Vite** (`npm run build`) | |
| Timezone | `config/app.php` = **`Asia/Manila`** | Ang schedule ay naka-evaluate sa app timezone; **i-sync ang server clock** (NTP) |

---

## 2. Ang 6 na scheduled commands (walang server → walang tumatakbo)

Lahat ay naka-register sa **`routes/console.php`** (Laravel 13 ay hindi nagbabasa ng
`app/Console/Kernel.php` — **burado na** ito, tingnan ang BUG-SCHED-1). Ang `schedule:list`
ang pinaka-authoritative na listahan; ito ang nilalaman nito (verified Sept 22 2026):

| # | Command | Iskedyul | Ano ang ginagawa |
|---|---|---|---|
| 1 | `pm:generate-scheduled` | araw-araw **00:00** | Gumagawa ng PM requests para sa due schedules (`next_scheduled_date <= today`) |
| 2 | `pm:send-reminders` | araw-araw **06:00** | PM due reminders (per-branch summary) |
| 3 | `parts:check-low-stock` | araw-araw **07:00** | Low-stock alerts (may `--dry-run`) |
| 4 | `inventory:verify-asset-sets` | araw-araw **08:00** | Asset-set integrity check (read-only) |
| 5 | `csm:weekly-check` | **Lunes 07:05** | CSM Weekly Digest — bell + email sa SA (deduped 1/linggo) |
| 6 | `csm:monthly-report` | **ika-1 ng buwan 07:10** | CSM Monthly PDF + bell + email sa SA (ulit-ulit na patakbo = ulit-ulit na notification, tingnan ang §7) |

**Paano ito tumatakbo:** isang `schedule:run` kada minuto → titingnan ng Laravel kung alin sa
6 ang due sa minutong iyon → patakbuhin. **Walang runner = walang scheduled task**, kahit
naka-register pa ang mga ito (ito ang BUG-SCHED-1 lesson: tahimik na hindi tumatakbo).

---

## 3. Scheduler runner — ang isang cron line

### 3a. Linux (production server) — cron

```cron
# CMMS scheduler — isang linya lang, saklaw ang lahat ng 6 na scheduled command
* * * * * cd /var/www/cmms && php artisan schedule:run >> /dev/null 2>&1
```

- I-install bilang user ng web server (hal. `www-data`): `sudo crontab -u www-data -e`
- Kung kailangan ng log: palitan ang `>> /dev/null 2>&1` ng `>> storage/logs/scheduler.log 2>&1`
  (**babantayan** ang file na ito — lalaki; i-rotate o `>> /dev/null` ang default)
- **`cd` ay mahalaga** — doon nakabase ang `.env` at `storage/` ng Laravel.

### 3b. Windows Server — Task Scheduler

Gumawa ng maliit na wrapper batch file **sa labas ng project folder** (hal. `C:\cmms-scheduler.bat`
— hindi ito kasama sa git; ops-side file ito), para sigurado ang working directory at may log:

```bat
@echo off
REM CMMS scheduler runner — Windows equivalent ng Linux cron line sa 3a
REM (Kung wala sa PATH ang CLI PHP, palitan ang "php" ng buong path, hal.
REM  "C:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe")
cd /d C:\cmms
php artisan schedule:run >> storage\logs\scheduler.log 2>&1
```

Register (cmd bilang Administrator) — tumatakbo kada minuto, tuloy-tuloy:
```bat
schtasks /Create /TN "CMMS Scheduler" /TR "C:\cmms-scheduler.bat" /SC MINUTE /MO 1 /RU SYSTEM /RL HIGHEST /F
```

**Windows traps (madalas ito ang dahilan ng "hindi tumatakbo"):**
- Sa Task Scheduler GUI, ang **"Start in (optional)"** ay dapat = `C:\cmms` — kung blangko, mali ang landas ng `.env`.
- Piliin ang **"Run whether user is logged on or not"**; i-check ang **"Run with highest privileges"**.
- Gamitin ang **buong path ng `php.exe`** — hindi laging nasa `PATH` ang CLI PHP ng IIS/Apache.
- **Iba ang php.ini ng web vs CLI**: i-verify na may `pdo_mysql`, `gd`, `mbstring`, `intl` ang CLI PHP
  (`php -m`), kung hindi ay pumapalya nang tahimik ang mga command (hal. migration/PDF).

### 3c. Alternatibo (walang cron): `schedule:work`

```bash
php artisan schedule:work        # tumatakbo nang tuloy-tuloy, kada minuto ang tsek
```

- **Local dev (Laragon) ito ang gamitin** habang wala pang server — pinapatakbo ang 6 na schedule
  nang live sa iyong makina.
- Sa production ay kailangan itong **long-running service** (systemd unit o Windows Service via
  NSSM) na may auto-restart — kaya mas simple pa rin ang cron/Task Scheduler para sa ops.
- Systemd sample: `ExecStart=/usr/bin/php /var/www/cmms/artisan schedule:work` · `Restart=always`.

### 3d. Beripikasyon ng scheduler (gawin sa deployment day)

```bash
php artisan schedule:list          # dapat 6 entries, lahat may "Next Due"
php artisan schedule:test          # interaktibong pagpili ng task para patakbuhin ngayon
php artisan schedule:run -v        # manual na isang tsek (dapat "No scheduled commands are ready to run." kapag wala pang due)
```
---

## 4. Mail + Queue — ang pinakamadaling makalimutan 🔴

### 4a. Ang totoong code path (`app/Models/Notification.php` L114-120)

```php
// SMTP: send directly so emails go out immediately without needing a queue worker.
// log/array: queue is fine since it just writes to log/memory.
if ($mailer === 'smtp') {
    Mail::to($user->email)->send($mailable);     // AGAD-AGAD, walang worker
} else {
    Mail::to($user->email)->queue($mailable);    // naka-pila sa `jobs` table
}
```

**Dalawang konklusyon:**

| Setup | Epekto |
|---|---|
| **`MAIL_MAILER=smtp`** (production target) | Diretso ang email — **hindi kailangan ng queue worker** para sa notifications |
| `log` / `array` / iba pa | Email → **`jobs` table** → kailangan ng `queue:work`, kung wala = **tahimik na naiipon** |

**⚠️ Live na ebidensya ng panganib na ito (dev DB, Sept 22 2026):**
```
jobs: 409 | failed: 0
```
409 pending jobs dahil `MAIL_MAILER=log` + `QUEUE_CONNECTION=database` at walang worker.
Sa dev ay harmless (log emails lang). **Sa production, ito ang eksaktong senaryo na
magiging "bakit walang dumarating na email?"** — naka-pila lang sa DB.

### 4b. Recommended production setup (gawin ang DALAWA)

1. **`MAIL_MAILER=smtp`** + totoong SMTP relay → notifications ay diretso.
2. **Patakbuhin pa rin ang queue worker** bilang safety net (para sa anumang `->queue()` at
   para sa `jobs` na naipon bago pa nabago ang `.env`).

`.env` (production mail block):
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.<office-mail-provider>     # hal. smtp.gmail.com kung Google Workspace
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME=<account>
MAIL_PASSWORD=<app-password>
MAIL_FROM_ADDRESS="cmms@<office-domain>"
MAIL_FROM_NAME="NCMB CMMS"
```

**Queue worker** (Linux — supervisor ang inirerekomenda):
```ini
; /etc/supervisor/conf.d/cmms-worker.conf
[program:cmms-worker]
command=php /var/www/cmms/artisan queue:work --tries=3 --timeout=120 --sleep=3
directory=/var/www/cmms
autostart=true
autorestart=true
user=www-data
numprocs=1
```
Windows: isang batch **sa labas ng project folder** (hal. `C:\cmms-queue.bat`), na may sariling
restart loop — i-register sa Task Scheduler na naka-trigger sa **startup** ("Run whether user is
logged on or not", "Run with highest privileges", at i-restart kapag pumalya):

```bat
@echo off
REM CMMS queue worker — tumatakbo nang tuloy-tuloy, restart kapag namatay
cd /d C:\cmms
:loop
php artisan queue:work --tries=3 --timeout=120 --sleep=3 >> storage\logs\queue-worker.log 2>&1
timeout /t 5 /nobreak > nul
goto loop
```

```bat
schtasks /Create /TN "CMMS Queue Worker" /TR "C:\cmms-queue.bat" /SC ONSTART /RU SYSTEM /RL HIGHEST /F
```


**Mga command na kailangan sa ops:**
```bash
php artisan queue:work --once      # isang job lang (mabilis na smoke test)
php artisan queue:failed           # listahan ng bigong job
php artisan queue:retry all        # i-retry lahat ng bigo (pagkatapos ayusin ang mail config)
php artisan queue:flush            # burahin ang failed (kapag tapon na — hal. lumang log jobs)
```

**Paglinis ng dev backlog (opsyonal, dev machine lang — hindi production):**
```bash
php artisan tinker --execute="DB::table('jobs')->truncate(); DB::table('failed_jobs')->truncate();"
```
*(Sa production: **huwag basta-basta i-truncate** ang `jobs` — may totoong email na nakapila.)*

### 4c. Production email safety na naka-code na (walang gagawin)

- **Alias skip:** sa production (`app()->environment('local') === false`), ang email na may `+`
  (hal. `name+test@…`) ay **hindi pinapadala** — nilolog lang ("Skipped email to alias").
- **SA flood guard:** ang `super_admin` ay **in-app lang** (walang email) para sa lahat ng
  notification type — **maliban** sa `… for Review` (D9.20) at **`CSM*`** types (D9.32).
  Kaya ang CSM Monthly/Digest/Severe Alert ang tanging email na napupunta sa SA (~2–4/buwan).
- **Local preview:** kapag `APP_ENV=local`, ang email ay isinusulat sa `storage/logs/laravel.log`
  bilang readable preview (`[CMMS Email Preview]`) — kaya walang spam sa dev.

---

## 5. Deployment steps (sunod-sunod — huwag laktawan)

```bash
# 0) MAINTENANCE MODE (huwag mag-deploy habang may nagta-trabaho)
php artisan down --render="errors::503" --retry=60

# 1) CODE
git fetch origin && git checkout develop && git pull --ff-only origin develop

# 2) DEPENDENCIES (walang dev packages sa production)
composer install --no-dev --optimize-autoloader

# 3) FRONT-END ASSETS  🔴 HINDI NAKA-TRACK SA GIT ang public/build
npm ci && npm run build
#    (kung walang Node sa prod server: i-build sa build machine at i-upload ang public/build/)

# 4) ENVIRONMENT — .env ay HINDI naka-commit (nasa .gitignore), kaya mano-manong gawin sa server
#    APP_ENV=production · APP_DEBUG=false · APP_URL=https://<host>
#    DB_* (MySQL) · MAIL_MAILER=smtp + SMTP block (§4b)
#    SESSION_DRIVER=database · CACHE_STORE=database · QUEUE_CONNECTION=database
#    LOG_CHANNEL=stack · LOG_LEVEL=warning
#    APP_KEY: kung sariwang install lang (WALANG laman ang DB), php artisan key:generate
#             kung may existing data na → HUWAG mag-key:generate, kopyahin ang lumang APP_KEY
#             (ang pagbabago nito ay mag-i-invalidate ng lahat ng encrypted session at na-save na data)

# 5) DATABASE (--force = kailangan sa production, walang confirmation prompt)
php artisan migrate --force

# 6) STORAGE PERMISSIONS (private disk = legal records: pirmas + PDFs)
#    Linux: chown -R www-data:www-data storage bootstrap/cache && chmod -R 775 storage bootstrap/cache
#    Windows (IIS): bigyan ng write access ang AppPool identity sa storage\ at bootstrap\cache\

# 7) CACHES (speed + consistency) — isang command lang sa Laravel 13
php artisan optimize            # = config:cache + event:cache + route:cache + view:clear/cache
php artisan storage:link        # kung wala pa (public symlink; hindi kailangan ng private files)

# 8) I-RESTART ANG MGA LONG-RUNNING PROCESS (kung may queue worker/systemd)
php artisan queue:restart
sudo systemctl restart cmms-worker   # kung supervisor/systemd ang gamit

# 9) BUMALIK SA LIVE
php artisan up
```

**Kapag may binago sa `routes/console.php` (bagong schedule):**
```bash
php artisan optimize:clear && php artisan optimize   # + i-verify: php artisan schedule:list
```
*(Sa Laravel 11+, ang `schedule:list` ay hindi umaasa sa route cache, pero ang `config:cache` ay dapat
i-refresh kapag nagbago ang `.env` — laging `optimize:clear` pagkatapos mag-edit ng `.env`.)*

---

## 6. Storage, backup, at retention

| Ano | Saan | Bakit mahalaga |
|---|---|---|
| **CSM Monthly PDFs** | `storage/app/private/csm-reports/{Y-m}/` | ARTA/CSC compliance record (buwan-buwan, kasama ang "no responses" na buwan) |
| **CSM survey copies** | `storage/app/private/csm-copies/` | 1:1 replica ng bawat sinagutang form (may pirma/email ng respondent) |
| **Signatures** | `storage/app/private/signatures/{year}/{MonthName}/` | Naka-private + authed route lang (D5b) |
| **Ticket/PM/PR PDFs** | `storage/app/private/{ict-pdfs, pm-pdfs, pr-pdfs, count-pdfs, pr-forms}/` | Auto-archived na official copies (D6/D7) |
| **Attachments** | `storage/app/private/{asset-attachments, pr-attachments}/` | Mga resibo (sensitibo — D5c) |

**Backup rule (dapat naka-iskedyul):**
1. **MySQL dump** araw-araw (hal. `mysqldump cmms_laurence > cmms_$(date +%F).sql`)
2. **`storage/app/private`** araw-araw — **hindi ito ma-regenerate** (ang mga PDF ay official records;
   ang CSM report ay *pwede* i-regenerate, pero ang mga pirmang PDF at survey copies ay hindi)
3. **`.env`** (APP_KEY!) — i-secure na kopya; **huwag** isama sa git
4. Tandaan: ang `.gitignore` ay may `*.pdf` at `/public/build` → **wala sa git ang mga ito**, kaya
   ang storage backup ay hindi opsyonal.

**Retention (pagpaplanuhan pagkatapos ng unang taon):** `csm-reports` = 12 files/taon (maliit) ·
`csm-copies` = 1 file kada survey (pinakamabilis lumaki) · `ict-pdfs`/`pm-pdfs` = 1 kada completed
ticket. I-verify kada taon na kasya sa disk at may off-server copy (D5.6 ay naka-design na para sa
gDrive-ready na config).

---

## 7. Go-live verification checklist (Day 1)

### ✅ Agad-agad pagkatapos ng deploy

| # | Check | Command / paraan | Dapat resulta |
|---|---|---|---|
| 1 | Health endpoint | `curl -I https://<host>/up` | **200 OK** |
| 2 | App config | `php artisan about` | `Environment: production` · `Debug: OFF` · Timezone `Asia/Manila` · Mail `smtp` · DB `mysql` |
| 3 | 6 schedules | `php artisan schedule:list` | 6 entries, lahat may **Next Due** |
| 4 | Routes | `php artisan route:list \| Select-String "csm/reports"` | may `/csm/reports/{year}/{month}/download` (SA-only) |
| 5 | Build assets | buksan ang login page | tama ang CSS/JS (**walang** 404 sa `/build/assets/...`) |
| 6 | Queue health | `php artisan tinker --execute="echo DB::table('jobs')->count();"` | maliit o 0; **hindi lumalaki bawat minuto** |
| 7 | Failed jobs | `php artisan queue:failed` | walang laman |

### ✅ Smoke tests na may totoong epekto (isa-isa lang)

| # | Test | Ano ang asahan |
|---|---|---|
| 8 | Gumawa ng ticket → i-assign → i-complete | 🔔 bell + 📧 email (sa `smtp`) + **archived PDF** sa `storage/app/private/ict-pdfs/{Y-m}/` |
| 9 | Buksan ang naka-archive na PDF at ang signature | Inline na bumubukas, **hindi** 403 para sa may access; **403** para sa walang access |
| 10 | Mag-submit ng CSM survey (normal: lahat Agree) | Walang severe alert (tama) · naupload ang survey copy PDF |
| 11 | Mag-submit ng CSM survey na **3+ Strongly Disagree** | 🔔 **CSM Severe Alert** bell + 📧 email sa SA, listahan ng binagsak na tanong (1 alert lang sa araw na iyon) |
| 12 | Manual: `php artisan csm:weekly-check 2026-09-14` | Lumalabas ang digest; **muling patakbo sa parehong araw = deduped** (walang duplicate) |
| 13 | Manual: `php artisan parts:check-low-stock --dry-run` | Listahan lang, **walang notification** |
| 14 | Manual: `php artisan inventory:verify-asset-sets` | Read-only na report ng violations |

### ✅ Unang gabi (pagkatapos ng unang 24 oras ng scheduler)

- `php artisan schedule:list` — ang **Next Due** ng 6 ay dapat naka-move pasulong (patunay na tumatakbo).
- `storage/logs/laravel.log` — maghanap ng: `Failed to send notification email` · `Skipped email to alias`.
- `php artisan queue:failed` — dapat walang laman.
- Kung **Windows Task Scheduler**: i-check ang **Last Run Result** (dapat `0x0`) at ang
  `storage/logs/scheduler.log` (dapat lumalaki kada minuto).

---

## 8. Mga patibong at caveats (basahin bago mag-reklamo 😄)

| # | Isyu | Ano ang gagawin |
|---|---|---|
| 1 | **`csm:monthly-report` ay WALANG dedup sa loob ng command** | Tuwing patakbo = bagong PDF + **bagong** bell/email. Huwag itong patakbuhin muli sa parehong araw pagkatapos ng scheduled run. Para sa lumang buwan, tanggapin ang isang notification. |
| 2 | **`csm:weekly-check` / Severe Alert dedup** | Nasa **notifications table** (`whereDate('created_at', today())`) — muling patakbo sa parehong araw = **no-op**. Ngunit kung **binura** ang notification row na iyon, makakapagpadala ulit ito. |
| 3 | **Walang notification kapag walang CSM responses sa linggo** | Tama ang design (walang spam) — huwag ituring na bug. |
| 4 | **Auto-generated PM surveys at ang response rate** | Kasama sila sa denominator (D9.30 note) — kaya mababa ang % (hal. 47%). Kung kailangan, documented follow-up ang pag-exclude sa `is_auto_generated` PMs. |
| 5 | **Local = log email, Production = tunay** | Sa dev ay `storage/logs/laravel.log` ang "inbox" (`[CMMS Email Preview]`). Sa production, dapat `smtp` — kung hindi, purong queue (§4a). |
| 6 | **Email sa SA ay limitado** | `super_admin` = in-app lang, maliban sa `… for Review` at `CSM*` types. Kaya kung "wala akong email" ang SA para sa ibang notification, **ito ay by design**. |
| 7 | **Timezone** | App = `Asia/Manila`. Ang Linux cron ay tumatakbo sa server timezone (karaniwang UTC) — **walang problema**: ang `schedule:run` kada minuto ay nag-e-evaluate gamit ang app timezone. Huwag i-edit ang `config/app.php`. |
| 8 | **`PHP_CLI_SERVER_WORKERS`** (nasa `.env.example`) | Para lang sa `artisan serve` — hindi gagamitin sa production (IIS/Apache/Nginx). |
| 9 | **`storage/app/private` = hindi `storage/app`** | Sa Laravel 13, `local` disk root = `storage/app/private`. Ang `Storage::disk('local')->put('csm-reports/...')` ay napupunta doon. |
| 10 | **`php artisan down` ay hindi sumasaklaw sa scheduler/queue** | Patuloy na tatakbo ang scheduled commands sa maintenance mode. Kung kailangan ng tuluyang hinto: i-disable muna ang cron line (o `schedule:pause` kung available sa bersyon). |

---

## 9. Rollback (kung may pumalya pagkatapos ng deploy)

```bash
php artisan down
git checkout <naunang-tag-o-commit>              # hal. ang huling green commit
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate:rollback --step=1 --force    # KUNG ang huling migration lang ang problema
php artisan optimize:clear && php artisan optimize
php artisan queue:restart
php artisan up
```

- **DB restore** (kung nasira ang data): ibalik ang pinakahuling dump —
  `mysql cmms_laurence < cmms_2026-09-22.sql` **habang naka-maintenance mode**.
- **Storage:** ibalik ang `storage/app/private` kung nasira/kulang ang mga naka-archive na PDF.
- **Bago ang bawat deploy: DB dump + storage copy** (walang pagbabago sa §6 na tuntunin).

---

## 10. Cross-reference

- **CSM roadmap (Phases 1–5 + Phase 6 = ito):** `docs/asset-downtime-tracking.md` §D9.31–D9.34
- **BUG-SCHED-1** (bakit walang tumatakbo kahit naka-register): `asset-downtime-tracking.md`,
  changelog entry Sept 18 2026 — ang `app/Console/Kernel.php` ay **burado**; ang tunay na
  pinagmulan ng schedule ay **`routes/console.php`**
- **D5 storage/private disk:** parehong dokyumento, §5a (D5b/D5c/D5d)
- **Health endpoint:** `/up` (naka-define sa `bootstrap/app.php` → `health: '/up'`)
- **Local dev na katumbas ng cron:** `php artisan schedule:work` (Laragon) + `MAIL_MAILER=log`

