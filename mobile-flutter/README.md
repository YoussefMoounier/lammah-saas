# Lammah SaaS Flutter App

Flutter mobile operations app for the Phase 4 Laravel API. It uses Dio, Riverpod, and secure token storage.

## Setup

```bash
flutter pub get
flutter run --dart-define=LAMMAH_API_BASE_URL=https://your-railway-api.up.railway.app
```

For Android emulator local Laravel API, the default base URL is:

```text
http://10.0.2.2:8000
```

The app opens to a mobile operations console. Enter the Sanctum token, merchant ULID, and store ULID, then tap Connect. The token is stored with Flutter Secure Storage and all repositories attach it automatically.

## APK

```bash
flutter build apk --release --dart-define=LAMMAH_API_BASE_URL=https://your-railway-api.up.railway.app
```

The APK will be generated under `build/app/outputs/flutter-apk/app-release.apk`.
