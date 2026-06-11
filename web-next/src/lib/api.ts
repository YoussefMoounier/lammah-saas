import type {
  ApiEnvelope,
  ConnectionSettings,
  DashboardSummary,
  FraudSignal,
  PriceUpdateBatch,
  ProfitSnapshot,
  RfmScore,
  SalesForecast,
  StaffShift,
  WooCommerceConnectionResult,
  WooCommerceStore,
  WooCommerceStorePayload,
  WooCommerceStoreResponse,
  WooCommerceSyncResponse
} from './types'

const DEFAULT_API_BASE_URL = process.env.NEXT_PUBLIC_LAMMAH_API_BASE_URL ?? 'http://localhost:8000'
const CONNECTION_KEY = 'lammah_connection'
const TOKEN_KEY = 'lammah_token'

type RequestOptions = {
  token: string
  baseUrl?: string
  method?: 'GET' | 'POST' | 'PATCH'
  body?: unknown
  query?: Record<string, string | number | boolean | undefined>
  timeoutMs?: number
}

export function readBearerToken() {
  if (typeof window === 'undefined') return ''

  return window.localStorage.getItem(TOKEN_KEY) ?? ''
}

export function readConnectionSettings(): ConnectionSettings {
  if (typeof window === 'undefined') {
    return { apiBaseUrl: DEFAULT_API_BASE_URL, token: '', merchantId: '', storeId: '' }
  }

  const fallback: ConnectionSettings = {
    apiBaseUrl: DEFAULT_API_BASE_URL,
    token: window.localStorage.getItem(TOKEN_KEY) ?? '',
    merchantId: '',
    storeId: ''
  }

  try {
    const stored = window.localStorage.getItem(CONNECTION_KEY)
    if (!stored) return fallback

    return { ...fallback, ...(JSON.parse(stored) as Partial<ConnectionSettings>) }
  } catch {
    return fallback
  }
}

export function saveConnectionSettings(settings: ConnectionSettings) {
  if (typeof window === 'undefined') return

  window.localStorage.setItem(TOKEN_KEY, settings.token)
  window.localStorage.setItem(CONNECTION_KEY, JSON.stringify(settings))
}

async function apiFetch<T>(path: string, options: RequestOptions): Promise<T> {
  const url = new URL(`/api/v1${path}`, options.baseUrl ?? DEFAULT_API_BASE_URL)

  Object.entries(options.query ?? {}).forEach(([key, value]) => {
    if (value !== undefined && value !== '') url.searchParams.set(key, String(value))
  })

  let response: Response

  const controller = new AbortController()
  const timeoutId = window.setTimeout(() => controller.abort(), options.timeoutMs ?? 15000)

  try {
    response = await fetch(url, {
      method: options.method ?? 'GET',
      signal: controller.signal,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${options.token}`
      },
      body: options.body ? JSON.stringify(options.body) : undefined
    })
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') {
      throw new Error(`الطلب أخذ وقتًا أطول من المتوقع. Railway أو WooCommerce بطيء الآن، جرّب التحديث بعد لحظات.`)
    }

    throw new Error(`تعذر الوصول إلى ${url.origin}. تأكد من رابط Railway وإعدادات CORS ثم أعد النشر.`)
  } finally {
    window.clearTimeout(timeoutId)
  }

  if (!response.ok) {
    const errorBody = await response.text()
    throw new Error(parseApiError(errorBody) || `API request failed with ${response.status}`)
  }

  return response.json() as Promise<T>
}

function parseApiError(body: string) {
  try {
    const json = JSON.parse(body) as { message?: string }
    return json.message ?? body
  } catch {
    return body
  }
}

type ApiContext = Pick<ConnectionSettings, 'apiBaseUrl' | 'token'>

export const lammahApi = {
  dashboard: (merchantId: string, storeId: string | undefined, context: ApiContext) =>
    apiFetch<ApiEnvelope<DashboardSummary>>(`/merchants/${merchantId}/dashboard/summary`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      query: { store_id: storeId, days: 30 }
    }),

  stores: (merchantId: string, context: ApiContext) =>
    apiFetch<ApiEnvelope<WooCommerceStore[]>>(`/merchants/${merchantId}/stores`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      query: { per_page: 50 }
    }),

  createStore: (merchantId: string, context: ApiContext, payload: WooCommerceStorePayload) =>
    apiFetch<WooCommerceStoreResponse>(`/merchants/${merchantId}/stores`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      method: 'POST',
      body: payload
    }),

  testStore: (merchantId: string, storeId: string, context: ApiContext) =>
    apiFetch<{ data: WooCommerceStore; connection: WooCommerceConnectionResult }>(`/merchants/${merchantId}/stores/${storeId}/test`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      method: 'POST'
    }),

  syncStore: (merchantId: string, storeId: string, context: ApiContext) =>
    apiFetch<WooCommerceSyncResponse>(`/merchants/${merchantId}/stores/${storeId}/sync`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      method: 'POST',
      timeoutMs: 8000,
      body: { queued: true, resources: ['categories', 'products', 'orders'] }
    }),

  forecasts: (merchantId: string, storeId: string, context: ApiContext) =>
    apiFetch<ApiEnvelope<SalesForecast[]>>(`/merchants/${merchantId}/stores/${storeId}/forecasts`, {
      token: context.token,
      baseUrl: context.apiBaseUrl
    }),

  generateForecast: (merchantId: string, storeId: string, context: ApiContext) =>
    apiFetch<ApiEnvelope<SalesForecast> | { accepted: boolean; queued: boolean }>(
      `/merchants/${merchantId}/stores/${storeId}/forecasts/generate`,
      { token: context.token, baseUrl: context.apiBaseUrl, method: 'POST', body: { training_months: 6, queued: true } }
    ),

  fraudSignals: (merchantId: string, storeId: string, context: ApiContext) =>
    apiFetch<ApiEnvelope<FraudSignal[]>>(`/merchants/${merchantId}/stores/${storeId}/fraud-signals`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      query: { status: 'open', per_page: 10 }
    }),

  scanFraud: (merchantId: string, storeId: string, context: ApiContext) =>
    apiFetch<{ accepted: boolean; queued: boolean }>(`/merchants/${merchantId}/stores/${storeId}/fraud-signals/scan`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      method: 'POST',
      body: { lookback_days: 30, minimum_score: 35 }
    }),

  updateFraudSignal: (merchantId: string, signalId: string, status: string, context: ApiContext) =>
    apiFetch<ApiEnvelope<FraudSignal>>(`/merchants/${merchantId}/fraud-signals/${signalId}`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      method: 'PATCH',
      body: { status }
    }),

  rfmScores: (merchantId: string, storeId: string, context: ApiContext) =>
    apiFetch<ApiEnvelope<RfmScore[]>>(`/merchants/${merchantId}/stores/${storeId}/rfm-scores`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      query: { min_churn: 0.45, per_page: 10 }
    }),

  refreshRfmScores: (merchantId: string, storeId: string, context: ApiContext) =>
    apiFetch<{ accepted: boolean; queued: boolean }>(`/merchants/${merchantId}/stores/${storeId}/rfm-scores/refresh`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      method: 'POST',
      body: { lookback_days: 180, queued: true }
    }),

  profits: (merchantId: string, storeId: string, context: ApiContext) =>
    apiFetch<ApiEnvelope<ProfitSnapshot[]>>(`/merchants/${merchantId}/stores/${storeId}/profits`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      query: { per_page: 10 }
    }),

  shifts: (merchantId: string, context: ApiContext) =>
    apiFetch<ApiEnvelope<StaffShift[]>>(`/merchants/${merchantId}/shifts`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      query: { per_page: 10 }
    }),

  startShift: (merchantId: string, context: ApiContext) =>
    apiFetch<ApiEnvelope<StaffShift>>(`/merchants/${merchantId}/shifts/start`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      method: 'POST',
      body: { currency: 'SAR', notes: 'Started from Lammah web console' }
    }),

  closeShift: (merchantId: string, shiftId: string, context: ApiContext) =>
    apiFetch<ApiEnvelope<StaffShift>>(`/merchants/${merchantId}/shifts/${shiftId}/close`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      method: 'PATCH',
      body: { notes: 'Closed from Lammah web console' }
    }),

  priceUpdates: (merchantId: string, storeId: string, context: ApiContext) =>
    apiFetch<ApiEnvelope<PriceUpdateBatch[]>>(`/merchants/${merchantId}/stores/${storeId}/price-updates`, {
      token: context.token,
      baseUrl: context.apiBaseUrl
    }),

  createPriceUpdate: (merchantId: string, storeId: string, context: ApiContext, percent: number) =>
    apiFetch<ApiEnvelope<PriceUpdateBatch>>(`/merchants/${merchantId}/stores/${storeId}/price-updates`, {
      token: context.token,
      baseUrl: context.apiBaseUrl,
      method: 'POST',
      body: {
        mode: 'percent',
        value: percent,
        target_type: 'all',
        target_filters: { status: 'publish' },
        execute_now: true
      }
    })
}
