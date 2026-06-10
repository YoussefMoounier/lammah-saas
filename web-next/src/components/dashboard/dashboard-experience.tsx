'use client'

import { useEffect, useState } from 'react'
import {
  Activity,
  AlertTriangle,
  ChartNoAxesCombined,
  CircleDollarSign,
  RefreshCcw,
  ShieldAlert,
  Store,
  Tags,
  Users
} from 'lucide-react'
import { lammahApi, readConnectionSettings, saveConnectionSettings } from '@/lib/api'
import type {
  ConnectionSettings,
  DashboardSummary,
  FraudSignal,
  PriceUpdateBatch,
  ProfitSnapshot,
  RfmScore,
  SalesForecast,
  StaffShift
} from '@/lib/types'
import { AppShell, type ConsoleSection } from '@/components/layout/app-shell'
import { MetricTile } from './metric-tile'
import { DataPanel } from './data-panel'
import { ActionPanel } from './action-panel'

const demoSummary: DashboardSummary = {
  period: { days: 30, starts_at: '', ends_at: '' },
  gross_revenue: 148920,
  net_profit: 93440,
  open_fraud_signals: 7,
  high_churn_customers: 18,
  active_shifts: 3,
  queued_price_updates: 1
}

const demoForecasts: SalesForecast[] = [
  {
    id: 'f1',
    forecast_month: '2026-07-01',
    training_months: 6,
    gross_revenue_forecast: 164500,
    net_profit_forecast: 101700,
    order_count_forecast: 892,
    confidence_low: 150000,
    confidence_high: 178000,
    generated_at: new Date().toISOString()
  }
]

const demoFraud: FraudSignal[] = [
  {
    id: 'r1',
    risk_score: 88,
    severity: 'critical',
    signal_type: 'trial_abuse_cluster',
    status: 'open',
    evidence: { ip_order_count_24h: 11 },
    last_seen_at: new Date().toISOString()
  },
  {
    id: 'r2',
    risk_score: 64,
    severity: 'high',
    signal_type: 'email_pattern_anomaly',
    status: 'open',
    evidence: { suspicious_email_pattern: 'test+231' },
    last_seen_at: new Date().toISOString()
  }
]

const demoRfm: RfmScore[] = [
  { id: 'c1', customer_id: 'cust_01', recency_days: 4, frequency_orders: 12, monetary_value: 1840, segment: 'vip', churn_probability: 0.11, subscription_expires_at: null },
  { id: 'c2', customer_id: 'cust_02', recency_days: 80, frequency_orders: 5, monetary_value: 920, segment: 'vip_at_risk', churn_probability: 0.72, subscription_expires_at: new Date().toISOString() }
]

const demoProfits: ProfitSnapshot[] = [
  { id: 'p1', order_id: 'ord_01', gross_revenue: 320, net_profit: 218.25, margin_percent: 68.2, currency: 'SAR', calculated_at: new Date().toISOString() },
  { id: 'p2', order_id: 'ord_02', gross_revenue: 180, net_profit: 101.3, margin_percent: 56.3, currency: 'SAR', calculated_at: new Date().toISOString() }
]

const demoPriceUpdates: PriceUpdateBatch[] = [
  { id: 'b1', mode: 'percent', value: 5, target_type: 'all', status: 'queued', items_count: 18, created_at: new Date().toISOString() }
]

const demoShifts: StaffShift[] = [
  {
    id: 's1',
    merchant_id: 'merchant_demo',
    user_id: 'agent_01',
    status: 'open',
    currency: 'SAR',
    starts_at: new Date().toISOString(),
    ends_at: null,
    opening_cash: 0,
    closing_cash: null,
    notes: 'Demo shift'
  }
]

export function DashboardExperience() {
  const [activeSection, setActiveSection] = useState<ConsoleSection>('overview')
  const [connection, setConnection] = useState<ConnectionSettings>(() => readConnectionSettings())
  const [shouldAutoLoad] = useState(() => isReady(readConnectionSettings()))
  const [summary, setSummary] = useState<DashboardSummary>(demoSummary)
  const [forecasts, setForecasts] = useState<SalesForecast[]>(demoForecasts)
  const [fraud, setFraud] = useState<FraudSignal[]>(demoFraud)
  const [rfm, setRfm] = useState<RfmScore[]>(demoRfm)
  const [profits, setProfits] = useState<ProfitSnapshot[]>(demoProfits)
  const [priceUpdates, setPriceUpdates] = useState<PriceUpdateBatch[]>(demoPriceUpdates)
  const [shifts, setShifts] = useState<StaffShift[]>(demoShifts)
  const [busy, setBusy] = useState(false)
  const [liveMode, setLiveMode] = useState(false)
  const [status, setStatus] = useState('Demo data loaded')

  useEffect(() => {
    if (shouldAutoLoad) {
      void loadDashboard(connection)
    }
    // Stored browser settings are only used for one initial live hydration.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function loadDashboard(settings = connection) {
    if (!isReady(settings)) {
      setLiveMode(false)
      setStatus('Demo data loaded')
      return
    }

    setBusy(true)
    try {
      const context = toApiContext(settings)
      const [summaryResponse, forecastResponse, fraudResponse, rfmResponse, profitResponse, priceResponse, shiftResponse] = await Promise.all([
        lammahApi.dashboard(settings.merchantId, settings.storeId, context),
        lammahApi.forecasts(settings.merchantId, settings.storeId, context),
        lammahApi.fraudSignals(settings.merchantId, settings.storeId, context),
        lammahApi.rfmScores(settings.merchantId, settings.storeId, context),
        lammahApi.profits(settings.merchantId, settings.storeId, context),
        lammahApi.priceUpdates(settings.merchantId, settings.storeId, context),
        lammahApi.shifts(settings.merchantId, context)
      ])

      setSummary(summaryResponse.data)
      setForecasts(forecastResponse.data)
      setFraud(fraudResponse.data)
      setRfm(rfmResponse.data)
      setProfits(profitResponse.data)
      setPriceUpdates(priceResponse.data)
      setShifts(shiftResponse.data)
      setLiveMode(true)
      setStatus('Live API connected')
    } catch (error) {
      setLiveMode(false)
      setStatus(error instanceof Error ? error.message : 'API request failed')
    } finally {
      setBusy(false)
    }
  }

  function updateConnection(key: keyof ConnectionSettings, value: string) {
    setConnection((current) => ({ ...current, [key]: value }))
  }

  function saveAndConnect() {
    const normalized = {
      ...connection,
      apiBaseUrl: connection.apiBaseUrl.trim().replace(/\/$/, ''),
      token: connection.token.trim(),
      merchantId: connection.merchantId.trim(),
      storeId: connection.storeId.trim()
    }

    setConnection(normalized)
    saveConnectionSettings(normalized)
    void loadDashboard(normalized)
  }

  async function runAction(action: (settings: ConnectionSettings) => Promise<unknown>, label: string) {
    if (!isReady(connection)) {
      setStatus('Live connection required')
      return
    }

    setBusy(true)
    try {
      await action(connection)
      setStatus(`${label} queued`)
      await loadDashboard(connection)
    } catch (error) {
      setStatus(error instanceof Error ? error.message : 'Action failed')
    } finally {
      setBusy(false)
    }
  }

  return (
    <AppShell activeSection={activeSection} liveMode={liveMode} onSectionChange={setActiveSection}>
      <ConnectionPanel
        busy={busy}
        connection={connection}
        status={status}
        onChange={updateConnection}
        onConnect={saveAndConnect}
        onRefresh={() => void loadDashboard(connection)}
      />

      <section className="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <MetricTile label="Gross revenue" value={formatMoney(summary.gross_revenue)} detail="Synced WooCommerce revenue" tone="teal" icon={CircleDollarSign} />
        <MetricTile label="Net profit" value={formatMoney(summary.net_profit)} detail="After product cost and fees" tone="sky" icon={ChartNoAxesCombined} />
        <MetricTile label="Fraud signals" value={String(summary.open_fraud_signals)} detail="Open or reviewing signals" tone="rose" icon={ShieldAlert} />
        <MetricTile label="Churn risk" value={String(summary.high_churn_customers)} detail="Customers above risk threshold" tone="amber" icon={Users} />
      </section>

      {activeSection === 'overview' && (
        <section className="mt-4 grid gap-4 xl:grid-cols-[1fr_340px]">
          <div className="grid gap-4">
            <ForecastsPanel forecasts={forecasts} />
            <ProfitsPanel profits={profits} />
          </div>

          <div className="grid content-start gap-4">
            <ActionPanel
              busy={busy}
              onForecast={() => runAction((settings) => lammahApi.generateForecast(settings.merchantId, settings.storeId, toApiContext(settings)), 'Forecast')}
              onFraudScan={() => runAction((settings) => lammahApi.scanFraud(settings.merchantId, settings.storeId, toApiContext(settings)), 'Fraud scan')}
              onRfmRefresh={() => runAction((settings) => lammahApi.refreshRfmScores(settings.merchantId, settings.storeId, toApiContext(settings)), 'RFM refresh')}
              onPriceLift={(percent) => runAction((settings) => lammahApi.createPriceUpdate(settings.merchantId, settings.storeId, toApiContext(settings), percent), `+${percent}% price update`)}
              onStartShift={() => runAction((settings) => lammahApi.startShift(settings.merchantId, toApiContext(settings)), 'Shift start')}
            />
            <RfmPanel rfm={rfm} />
            <SurgePanel queuedPriceUpdates={summary.queued_price_updates} />
          </div>
        </section>
      )}

      {activeSection === 'fraud' && (
        <section className="mt-4">
          <DataPanel
            title="Fraud and test-account radar"
            emptyState="No open fraud signals"
            getRowKey={(row) => row.id}
            rows={fraud}
            columns={[
              { header: 'Severity', render: (row) => <SeverityBadge value={row.severity} /> },
              { header: 'Score', render: (row) => Math.round(row.risk_score) },
              { header: 'Signal', render: (row) => row.signal_type },
              { header: 'Evidence', render: (row) => compactEvidence(row.evidence) },
              { header: 'Status', render: (row) => row.status },
              {
                header: 'Action',
                render: (row) => (
                  <button
                    className="rounded-md border border-white/10 bg-white/5 px-2 py-1 text-xs text-slate-200 hover:bg-white/10 disabled:opacity-50"
                    disabled={busy || !liveMode}
                    onClick={() => runAction((settings) => lammahApi.updateFraudSignal(settings.merchantId, row.id, 'reviewing', toApiContext(settings)), 'Fraud review')}
                  >
                    Review
                  </button>
                )
              }
            ]}
          />
        </section>
      )}

      {activeSection === 'pricing' && (
        <section className="mt-4 grid gap-4 xl:grid-cols-[340px_1fr]">
          <ActionPanel
            busy={busy}
            onForecast={() => runAction((settings) => lammahApi.generateForecast(settings.merchantId, settings.storeId, toApiContext(settings)), 'Forecast')}
            onFraudScan={() => runAction((settings) => lammahApi.scanFraud(settings.merchantId, settings.storeId, toApiContext(settings)), 'Fraud scan')}
            onRfmRefresh={() => runAction((settings) => lammahApi.refreshRfmScores(settings.merchantId, settings.storeId, toApiContext(settings)), 'RFM refresh')}
            onPriceLift={(percent) => runAction((settings) => lammahApi.createPriceUpdate(settings.merchantId, settings.storeId, toApiContext(settings), percent), `+${percent}% price update`)}
            onStartShift={() => runAction((settings) => lammahApi.startShift(settings.merchantId, toApiContext(settings)), 'Shift start')}
          />
          <DataPanel
            title="Bulk price update batches"
            emptyState="No price updates yet"
            getRowKey={(row) => row.id}
            rows={priceUpdates}
            columns={[
              { header: 'Batch', render: (row) => shortId(row.id) },
              { header: 'Mode', render: (row) => row.mode },
              { header: 'Value', render: (row) => (row.mode === 'percent' ? `${row.value}%` : row.value) },
              { header: 'Target', render: (row) => row.target_type },
              { header: 'Items', render: (row) => row.items_count ?? '-' },
              { header: 'Status', render: (row) => <StatusBadge value={row.status} /> }
            ]}
          />
        </section>
      )}

      {activeSection === 'shifts' && (
        <section className="mt-4">
          <DataPanel
            title="Staff shifts"
            emptyState="No shifts found"
            getRowKey={(row) => row.id}
            action={
              <button
                className="rounded-md border border-teal-300/25 bg-teal-300/10 px-3 py-1.5 text-xs text-teal-100 disabled:opacity-50"
                disabled={busy || !liveMode}
                onClick={() => runAction((settings) => lammahApi.startShift(settings.merchantId, toApiContext(settings)), 'Shift start')}
              >
                Start shift
              </button>
            }
            rows={shifts}
            columns={[
              { header: 'Shift', render: (row) => shortId(row.id) },
              { header: 'Agent', render: (row) => shortId(row.user_id) },
              { header: 'Status', render: (row) => <StatusBadge value={row.status} /> },
              { header: 'Started', render: (row) => formatDateTime(row.starts_at) },
              { header: 'Closed', render: (row) => formatDateTime(row.ends_at) },
              {
                header: 'Action',
                render: (row) =>
                  row.status === 'open' ? (
                    <button
                      className="rounded-md border border-white/10 bg-white/5 px-2 py-1 text-xs text-slate-200 hover:bg-white/10 disabled:opacity-50"
                      disabled={busy || !liveMode}
                      onClick={() => runAction((settings) => lammahApi.closeShift(settings.merchantId, row.id, toApiContext(settings)), 'Shift close')}
                    >
                      Close
                    </button>
                  ) : (
                    '-'
                  )
              }
            ]}
          />
        </section>
      )}
    </AppShell>
  )
}

type ConnectionPanelProps = {
  busy: boolean
  connection: ConnectionSettings
  status: string
  onChange: (key: keyof ConnectionSettings, value: string) => void
  onConnect: () => void
  onRefresh: () => void
}

function ConnectionPanel({ busy, connection, status, onChange, onConnect, onRefresh }: ConnectionPanelProps) {
  return (
    <section className="glass-panel grid gap-3 rounded-md p-4 xl:grid-cols-[1.4fr_1fr_1fr_1fr_auto]">
      <LabeledInput label="API base" value={connection.apiBaseUrl} onChange={(value) => onChange('apiBaseUrl', value)} />
      <LabeledInput label="Sanctum token" type="password" value={connection.token} onChange={(value) => onChange('token', value)} />
      <LabeledInput label="Merchant ULID" value={connection.merchantId} onChange={(value) => onChange('merchantId', value)} />
      <LabeledInput label="Store ULID" value={connection.storeId} onChange={(value) => onChange('storeId', value)} />
      <div className="grid gap-2 sm:grid-cols-[1fr_auto] xl:grid-cols-1">
        <button className="h-11 rounded-md border border-teal-300/25 bg-teal-300/10 px-3 text-sm text-teal-100 disabled:opacity-50" disabled={busy} onClick={onConnect}>
          Connect
        </button>
        <button className="grid h-11 place-items-center rounded-md border border-white/10 bg-white/5 px-3 text-slate-300 disabled:opacity-50" disabled={busy} onClick={onRefresh} title="Refresh">
          <RefreshCcw className="size-4" />
        </button>
      </div>
      <div className="flex items-center gap-2 text-sm text-slate-400 xl:col-span-5">
        <Activity className="size-4 text-teal-200" />
        <span className="line-clamp-1">{status}</span>
      </div>
    </section>
  )
}

function LabeledInput({ label, value, onChange, type = 'text' }: { label: string; value: string; type?: string; onChange: (value: string) => void }) {
  return (
    <label className="grid gap-1.5">
      <span className="text-xs uppercase tracking-[0.12em] text-slate-500">{label}</span>
      <input
        className="h-11 rounded-md border border-white/10 bg-white/5 px-3 text-sm text-slate-100 outline-none focus:border-teal-300/50"
        type={type}
        value={value}
        onChange={(event) => onChange(event.target.value)}
      />
    </label>
  )
}

function ForecastsPanel({ forecasts }: { forecasts: SalesForecast[] }) {
  return (
    <DataPanel
      title="Sales forecasts"
      getRowKey={(row) => row.id}
      rows={forecasts}
      columns={[
        { header: 'Month', render: (row) => formatDate(row.forecast_month) },
        { header: 'Gross', render: (row) => formatMoney(row.gross_revenue_forecast) },
        { header: 'Net', render: (row) => (row.net_profit_forecast === null ? '-' : formatMoney(row.net_profit_forecast)) },
        { header: 'Orders', render: (row) => row.order_count_forecast ?? '-' },
        { header: 'Band', render: (row) => `${formatMoney(row.confidence_low)} - ${formatMoney(row.confidence_high)}` }
      ]}
    />
  )
}

function ProfitsPanel({ profits }: { profits: ProfitSnapshot[] }) {
  return (
    <DataPanel
      title="Net profit snapshots"
      getRowKey={(row) => row.id}
      rows={profits}
      columns={[
        { header: 'Order', render: (row) => shortId(row.order_id) },
        { header: 'Gross', render: (row) => formatMoney(row.gross_revenue, row.currency) },
        { header: 'Net', render: (row) => formatMoney(row.net_profit, row.currency) },
        { header: 'Margin', render: (row) => (row.margin_percent === null ? '-' : `${row.margin_percent}%`) },
        { header: 'Calculated', render: (row) => formatDateTime(row.calculated_at) }
      ]}
    />
  )
}

function RfmPanel({ rfm }: { rfm: RfmScore[] }) {
  return (
    <DataPanel
      title="RFM churn watch"
      emptyState="No customers above churn threshold"
      getRowKey={(row) => row.id}
      rows={rfm}
      columns={[
        { header: 'Customer', render: (row) => shortId(row.customer_id) },
        { header: 'Segment', render: (row) => row.segment },
        { header: 'Churn', render: (row) => formatPercent(row.churn_probability) }
      ]}
    />
  )
}

function SurgePanel({ queuedPriceUpdates }: { queuedPriceUpdates: number }) {
  return (
    <div className="glass-panel rounded-md p-4 text-sm text-slate-400">
      <div className="flex items-center gap-2 text-amber-100">
        <AlertTriangle className="size-4" />
        Surge pricing
      </div>
      <div className="mt-3 grid grid-cols-[auto_1fr] items-center gap-3">
        <div className="grid size-11 place-items-center rounded-md border border-amber-300/25 bg-amber-300/10 text-lg font-semibold text-amber-100">{queuedPriceUpdates}</div>
        <p className="leading-6">Queued bulk price actions waiting for the worker.</p>
      </div>
    </div>
  )
}

function SeverityBadge({ value }: { value: FraudSignal['severity'] }) {
  const tone = value === 'critical' || value === 'high' ? 'border-rose-300/30 bg-rose-300/10 text-rose-100' : 'border-amber-300/30 bg-amber-300/10 text-amber-100'

  return <span className={`inline-flex rounded-md border px-2 py-1 text-xs ${tone}`}>{value}</span>
}

function StatusBadge({ value }: { value: string }) {
  const tone = value === 'open' || value === 'queued' || value === 'processing' ? 'border-teal-300/30 bg-teal-300/10 text-teal-100' : 'border-white/10 bg-white/5 text-slate-300'

  return <span className={`inline-flex rounded-md border px-2 py-1 text-xs ${tone}`}>{value}</span>
}

function toApiContext(settings: ConnectionSettings) {
  return { apiBaseUrl: settings.apiBaseUrl, token: settings.token }
}

function isReady(settings: ConnectionSettings) {
  return Boolean(settings.apiBaseUrl && settings.token && settings.merchantId && settings.storeId)
}

function compactEvidence(evidence: Record<string, unknown>) {
  const firstEntries = Object.entries(evidence).slice(0, 2)
  if (firstEntries.length === 0) return '-'

  return firstEntries.map(([key, value]) => `${key}: ${String(value)}`).join(', ')
}

function formatMoney(value: number | null | undefined, currency = 'SAR') {
  return `${currency} ${Number(value ?? 0).toLocaleString(undefined, { maximumFractionDigits: 2 })}`
}

function formatPercent(value: number | null | undefined) {
  if (value === null || value === undefined) return '-'

  return `${Math.round(value * 100)}%`
}

function formatDate(value: string | null | undefined) {
  if (!value) return '-'

  return new Intl.DateTimeFormat(undefined, { month: 'short', year: 'numeric' }).format(new Date(value))
}

function formatDateTime(value: string | null | undefined) {
  if (!value) return '-'

  return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function shortId(value: string) {
  return value.length <= 12 ? value : `${value.slice(0, 6)}...${value.slice(-4)}`
}
