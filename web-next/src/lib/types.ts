export type ApiEnvelope<T> = {
  data: T
  links?: Record<string, unknown>
  meta?: Record<string, unknown>
}

export type ConnectionSettings = {
  apiBaseUrl: string
  token: string
  merchantId: string
  storeId: string
}

export type DashboardSummary = {
  period: { days: number; starts_at: string; ends_at: string }
  gross_revenue: number
  net_profit: number
  open_fraud_signals: number
  high_churn_customers: number
  active_shifts: number
  queued_price_updates: number
  latest_forecast?: unknown
}

export type SalesForecast = {
  id: string
  forecast_month: string
  training_months: number
  gross_revenue_forecast: number
  net_profit_forecast: number | null
  order_count_forecast: number | null
  confidence_low: number | null
  confidence_high: number | null
  generated_at: string
}

export type FraudSignal = {
  id: string
  risk_score: number
  severity: 'low' | 'medium' | 'high' | 'critical'
  signal_type: string
  status: 'open' | 'reviewing' | 'dismissed' | 'confirmed'
  evidence: Record<string, unknown>
  last_seen_at: string
}

export type RfmScore = {
  id: string
  customer_id: string
  recency_days: number
  frequency_orders: number
  monetary_value: number
  segment: string
  churn_probability: number | null
  subscription_expires_at: string | null
}

export type ProfitSnapshot = {
  id: string
  order_id: string
  gross_revenue: number
  net_profit: number
  margin_percent: number | null
  currency: string
  calculated_at: string
}

export type PriceUpdateBatch = {
  id: string
  mode: 'flat' | 'percent' | 'set'
  value: number
  target_type: string
  status: string
  items_count?: number
  created_at?: string
}

export type StaffShift = {
  id: string
  merchant_id: string
  user_id: string
  status: 'open' | 'closed' | string
  currency: string
  starts_at: string | null
  ends_at: string | null
  opening_cash: number | null
  closing_cash: number | null
  notes: string | null
}
