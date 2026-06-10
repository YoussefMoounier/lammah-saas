# Lammah SaaS Phase 5 Client Handoff

## Web: Next.js

Path: `web-next`

- Main implementation for Vercel deployment.
- Uses Next.js App Router, React, Tailwind CSS, and lucide-react icons.
- First screen is the operational console, not a landing page.
- Reads `NEXT_PUBLIC_LAMMAH_API_BASE_URL`, with an in-app override for the Railway API base URL.
- Stores the Sanctum bearer token, merchant ULID, and store ULID in browser storage from the connection panel.

## Web: Vue Reference

Path: `web-vue`

- Vue 3 Composition API layout equivalent for teams that prefer Vue.
- Mirrors the same dashboard shell, metric cards, mobile bottom navigation, and API contract.
- Reads `VITE_LAMMAH_API_BASE_URL`.

## Mobile: Flutter

Path: `mobile-flutter`

- Mobile operations console plus API integration layer for the Phase 4 Laravel endpoints.
- Uses Dio, Flutter Secure Storage, and Riverpod providers.
- Build APK with:

```bash
flutter build apk --release --dart-define=LAMMAH_API_BASE_URL=https://your-api-domain.com
```

## API Coverage

- Dashboard summary
- Sales forecasts
- Fraud signals and scan trigger
- RFM churn scores and refresh trigger
- Net profit snapshots and recalculation
- Staff shifts
- Bulk price update actions
