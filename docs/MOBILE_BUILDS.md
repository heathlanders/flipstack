# Mobile Builds

The Flutter app ships in two Android product flavors so QA can install develop and prod side by side.

| Flavor | Backend | Package id | App label | APK filename | Download page | Raw APK URL |
|---|---|---|---|---|---|---|
| `prod` | `https://flipstack.app` | `com.example.ecommerce_mobile_app` | `FlipStack` | `flipstack.apk` | `https://flipstack.app/download` | `https://flipstack.app/storage/downloads/flipstack.apk` |
| `develop` | `https://develop.flipstack.app` | `com.example.ecommerce_mobile_app.develop` | `FlipStack Dev` | `flipstack-develop.apk` | `https://develop.flipstack.app/download` | `https://develop.flipstack.app/storage/downloads/flipstack-develop.apk` |

`/download` is the user-facing landing page (`DownloadController@index` -> `resources/js/Pages/Download.vue`) — give this to QA/testers. The raw APK URL is the artifact the CI uploads to and what the page links to.

The develop login page also surfaces the develop APK link automatically (`resources/js/Pages/Auth/Login.vue`).

> Note: `DownloadController@index` currently hardcodes `downloads/flipstack.apk`, so on `develop.flipstack.app/download` it links to `flipstack.apk` rather than `flipstack-develop.apk`. If the develop server only has `flipstack-develop.apk`, the develop download page will be empty — make the controller host/flavor-aware before relying on the develop `/download` page.

## How env routing works

- Backend URL: `--dart-define=API_BASE_URL=...` — read by `mobile/lib/core/config/app_config.dart` (`AppConfig.baseUrl`).
- Flavor: `--dart-define=APP_FLAVOR=prod|develop` — exposed as `AppConfig.appFlavor` for any in-app branching (debug banner, About screen).
- Android package id + app label come from product flavors in `mobile/android/app/build.gradle.kts` (the `develop` flavor adds `applicationIdSuffix=".develop"` + a `resValue` override for `app_name`).
- `AppConfig.isStaging` / `isProduction` / `isDevelopment` are derived from `baseUrl`.

## Local builds

```bash
# Local dev (defaults to LAN dev server)
flutter run --flavor prod

# Point at develop staging
flutter run \
  --flavor develop \
  --dart-define=API_BASE_URL=https://develop.flipstack.app \
  --dart-define=APP_FLAVOR=develop

# Release APK for develop staging
flutter build apk --release \
  --flavor develop \
  --dart-define=API_BASE_URL=https://develop.flipstack.app \
  --dart-define=APP_FLAVOR=develop
```

Flutter requires `--flavor` once product flavors are defined.

## CI

`.github/workflows/build-mobile.yml` runs a matrix of `prod` + `develop`. Gating:

- Push to `main` (touching `mobile/**`) → builds `prod`, uploads to `/data/flipstack/shared/storage/app/public/downloads/flipstack.apk`.
- Push to `develop` (touching `mobile/**`) → builds `develop`, uploads to `/data/flipstack-staging/shared/storage/app/public/downloads/flipstack-develop.apk`.
- `workflow_dispatch` with `target=both|prod|develop|auto` to force a manual rebuild.

Both legs reuse the same `SSH_PRIVATE_KEY` / `SERVER_HOST` / `SERVER_USER` secrets — only the deploy path differs.

## Backend prerequisites for develop QA

Two things to confirm in the develop server `.env` before pointing a real device at it:

- `BACKGROUND_REMOVAL_URL` — develop's internal Docker DNS (`http://background-removal:5000`) is NOT reachable from a phone. Either stand up a `bg-dev.flipstack.app` Cloudflare tunnel (mirror `background-removal-server/cloudflared-config.yml`) or temporarily point develop at the existing `https://bg.flipstack.app`.
- `FCM_PROJECT_ID` + `FCM_CREDENTIALS_PATH` — should target a `flipstack-dev` Firebase project. If develop shares the prod project, dev notifications will broadcast to prod devices. If a separate project is created, drop the dev `google-services.json` at `mobile/android/app/src/develop/google-services.json` (per-flavor Firebase config).

## iOS

Not yet wired. APK is the only artifact today. When TestFlight is added, mirror the flavor concept with Xcode schemes (`Runner-Develop` / `Runner-Prod`).
