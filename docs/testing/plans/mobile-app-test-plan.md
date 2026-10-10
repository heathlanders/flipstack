# Mobile App Test Plan

| Field            | Value                                              |
|------------------|----------------------------------------------------|
| Document ID      | TP-MOB                                             |
| Domain           | Mobile App (Flutter)                               |
| Last Updated     | 2026-04-15                                         |

## Scope

Flutter mobile app using Riverpod for state management, GoRouter for navigation, and Dio HTTP client for API communication. 18 feature modules across 38 screens. Communicates with Laravel backend via Sanctum-authenticated REST API.

## Current Test Coverage

- 1 placeholder `widget_test.dart` -- no meaningful coverage
- No integration tests, no provider tests

## Test Types

| Type             | Framework          | Location                        |
|------------------|--------------------|---------------------------------|
| Widget tests     | flutter_test       | `mobile/test/`                  |
| Integration tests| integration_test   | `mobile/integration_test/`      |
| Provider tests   | flutter_test + Riverpod | `mobile/test/providers/`   |

## Test Case Files

| File ID    | Title               | Cases | Priority Areas                              |
|------------|----------------------|-------|---------------------------------------------|
| TC-MOB-001 | Authentication       | 8     | Login, 2FA, token storage, logout           |
| TC-MOB-002 | Fulfillment          | 8     | Pick, pack, ship, returns                   |
| TC-MOB-003 | Inventory            | 8     | Products, barcode lookup, categories        |
| TC-MOB-004 | Dashboard            | 6     | KPIs, role routing, alerts                  |
| TC-MOB-005 | Marketplace          | 6     | Connection status, eBay/Shopify stats       |
| TC-MOB-006 | Barcode Scanning     | 6     | Camera, UPC scan, quick scan, offline       |
| TC-MOB-007 | Notifications        | 6     | List, badges, preferences                   |
| TC-MOB-008 | Profile              | 6     | Edit profile, password, sessions            |

## Key Risks

- Camera/barcode scanning requires physical device testing
- Secure storage behavior differs across iOS and Android
- Offline-to-online sync has race condition potential
- Token refresh timing under poor network conditions

## Environment Requirements

- Flutter SDK 3.x with Dart 3.x
- Physical device or emulator with camera support for barcode tests
- Local Laravel backend running with Sanctum token endpoint accessible
- Mock server for isolated widget/provider tests

## Exit Criteria

- All P0 and P1 test cases pass on both iOS and Android
- No critical or high-severity defects open
- Barcode scanning verified on at least one physical device per platform
- Offline queue sync validated with network interruption simulation
