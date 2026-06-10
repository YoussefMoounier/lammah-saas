# Lammah SaaS Web Console

Next.js App Router operations console for the Phase 4 Laravel API. It includes dashboard metrics, forecasts, fraud radar, RFM churn, net profit snapshots, shifts, and bulk price actions.

## Setup

```bash
npm install
npm run dev
```

Environment:

```bash
NEXT_PUBLIC_LAMMAH_API_BASE_URL=https://your-railway-api.up.railway.app
```

The first screen is the operational dashboard. Enter the Railway API base URL, Sanctum token, merchant ULID, and store ULID in the connection panel. The app persists those values in browser storage and keeps demo data visible until a live connection succeeds.

## Deploy

```bash
npm run build
```

On Vercel, set `NEXT_PUBLIC_LAMMAH_API_BASE_URL` to your Railway Laravel API URL.
