'use client'

import { useEffect, useMemo, useState } from 'react'
import { Activity, AlertTriangle, ChartNoAxesCombined, CircleDollarSign, PlugZap, RefreshCcw, ShieldAlert, Store, Tags, Users } from 'lucide-react'
import { lammahApi, readConnectionSettings, saveConnectionSettings } from '@/lib/api'
import type {
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
  WooCommerceStorePayload
} from '@/lib/types'
import { AppShell, type ConsoleSection } from '@/components/layout/app-shell'
import { MetricTile } from './metric-tile'
import { DataPanel } from './data-panel'

const emptySummary: DashboardSummary = {
  period: { days: 30, starts_at: '', ends_at: '' },
  gross_revenue: 0,
  net_profit: 0,
  open_fraud_signals: 0,
  high_churn_customers: 0,
  active_shifts: 0,
  queued_price_updates: 0
}

const emptyStoreForm: WooCommerceStorePayload = {
  name: '',
  base_url: '',
  consumer_key: '',
  consumer_secret: '',
  currency: 'SAR',
  timezone: 'Asia/Riyadh',
  test_connection: false,
  sync_now: true
}

export function DashboardExperience() {
  const [activeSection, setActiveSection] = useState<ConsoleSection>('overview')
  const [connection, setConnection] = useState<ConnectionSettings>(() => readConnectionSettings())
  const [stores, setStores] = useState<WooCommerceStore[]>([])
  const [storeForm, setStoreForm] = useState<WooCommerceStorePayload>(emptyStoreForm)
  const [summary, setSummary] = useState<DashboardSummary>(emptySummary)
  const [forecasts, setForecasts] = useState<SalesForecast[]>([])
  const [fraud, setFraud] = useState<FraudSignal[]>([])
  const [rfm, setRfm] = useState<RfmScore[]>([])
  const [profits, setProfits] = useState<ProfitSnapshot[]>([])
  const [priceUpdates, setPriceUpdates] = useState<PriceUpdateBatch[]>([])
  const [shifts, setShifts] = useState<StaffShift[]>([])
  const [busy, setBusy] = useState(false)
  const [liveMode, setLiveMode] = useState(false)
  const [connectionResult, setConnectionResult] = useState<WooCommerceConnectionResult | null>(null)
  const [connectorToken, setConnectorToken] = useState<{ storeId: string; value: string } | null>(null)
  const [status, setStatus] = useState('اكتب رابط API والتوكن ومعرف التاجر، ثم اضغط اتصال')

  const apiContext = useMemo(() => ({ apiBaseUrl: connection.apiBaseUrl, token: connection.token }), [connection.apiBaseUrl, connection.token])
  const selectedStore = stores.find((store) => store.id === connection.storeId) ?? null

  useEffect(() => {
    const stored = readConnectionSettings()
    if (hasMerchantConnection(stored)) {
      void loadWorkspace(stored)
    }
    // Initial browser hydration only.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  async function loadWorkspace(settings = connection) {
    if (!hasMerchantConnection(settings)) {
      setLiveMode(false)
      setStatus('أكمل بيانات الاتصال أولًا')
      return
    }

    setBusy(true)
    try {
      const context = toApiContext(settings)
      const storesResponse = await lammahApi.stores(settings.merchantId, context)
      const nextStores = storesResponse.data
      const nextStoreId = settings.storeId || nextStores[0]?.id || ''
      const normalized = { ...settings, storeId: nextStoreId }

      setStores(nextStores)
      setConnection(normalized)
      saveConnectionSettings(normalized)

      if (nextStoreId) {
        await loadStoreData(normalized)
      } else {
        clearStoreData()
        setLiveMode(true)
        setStatus('متصل. أضف أول متجر WooCommerce من تبويب المتاجر')
      }
    } catch (error) {
      setLiveMode(false)
      setStatus(readableError(error))
    } finally {
      setBusy(false)
    }
  }

  async function loadStoreData(settings = connection) {
    if (!hasSelectedStore(settings)) return

    setStatus('جاري تحميل بيانات المتجر والتحليلات...')
    const context = toApiContext(settings)
    const [summaryResponse, forecastResponse, fraudResponse, rfmResponse, profitResponse, priceResponse, shiftResponse] = await Promise.allSettled([
      lammahApi.dashboard(settings.merchantId, settings.storeId, context),
      lammahApi.forecasts(settings.merchantId, settings.storeId, context),
      lammahApi.fraudSignals(settings.merchantId, settings.storeId, context),
      lammahApi.rfmScores(settings.merchantId, settings.storeId, context),
      lammahApi.profits(settings.merchantId, settings.storeId, context),
      lammahApi.priceUpdates(settings.merchantId, settings.storeId, context),
      lammahApi.shifts(settings.merchantId, context)
    ])

    const errors: string[] = []

    if (summaryResponse.status === 'fulfilled') setSummary(summaryResponse.value.data)
    else errors.push(`ملخص المبيعات: ${readableError(summaryResponse.reason)}`)

    if (forecastResponse.status === 'fulfilled') setForecasts(forecastResponse.value.data)
    else {
      setForecasts([])
      errors.push(`توقعات المبيعات: ${readableError(forecastResponse.reason)}`)
    }

    if (fraudResponse.status === 'fulfilled') setFraud(fraudResponse.value.data)
    else {
      setFraud([])
      errors.push(`تنبيهات الاحتيال: ${readableError(fraudResponse.reason)}`)
    }

    if (rfmResponse.status === 'fulfilled') setRfm(rfmResponse.value.data)
    else {
      setRfm([])
      errors.push(`شرائح العملاء: ${readableError(rfmResponse.reason)}`)
    }

    if (profitResponse.status === 'fulfilled') setProfits(profitResponse.value.data)
    else {
      setProfits([])
      errors.push(`صافي الربح: ${readableError(profitResponse.reason)}`)
    }

    if (priceResponse.status === 'fulfilled') setPriceUpdates(priceResponse.value.data)
    else {
      setPriceUpdates([])
      errors.push(`تحديثات الأسعار: ${readableError(priceResponse.reason)}`)
    }

    if (shiftResponse.status === 'fulfilled') setShifts(shiftResponse.value.data)
    else {
      setShifts([])
      errors.push(`الشفتات: ${readableError(shiftResponse.reason)}`)
    }

    setLiveMode(true)
    setStatus(errors.length ? `تم فتح المتجر. بعض البيانات لم تُحدّث بعد: ${errors[0]}` : 'تم تحديث بيانات المتجر')
  }

  function clearStoreData() {
    setSummary(emptySummary)
    setForecasts([])
    setFraud([])
    setRfm([])
    setProfits([])
    setPriceUpdates([])
    setShifts([])
  }

  function updateStoreInState(nextStore: WooCommerceStore) {
    setStores((current) => {
      const exists = current.some((store) => store.id === nextStore.id)

      return exists ? current.map((store) => (store.id === nextStore.id ? nextStore : store)) : [nextStore, ...current]
    })
  }

  function updateConnection(key: keyof ConnectionSettings, value: string) {
    setConnection((current) => ({ ...current, [key]: value }))
  }

  function updateStoreForm(key: keyof WooCommerceStorePayload, value: string | boolean) {
    setStoreForm((current) => ({ ...current, [key]: value }))
  }

  function saveAndConnect() {
    const normalized = normalizeConnection(connection)
    setConnection(normalized)
    saveConnectionSettings(normalized)
    void loadWorkspace(normalized)
  }

  function selectStore(storeId: string) {
    const normalized = { ...connection, storeId }
    setConnection(normalized)
    saveConnectionSettings(normalized)
    setActiveSection('overview')
    setStatus('تم اختيار المتجر. جاري تحميل آخر بيانات متاحة...')
    void loadStoreData(normalized).catch((error) => setStatus(readableError(error)))
  }

  async function syncStore(storeId: string) {
    if (!hasMerchantConnection(connection)) {
      setStatus('أكمل بيانات الاتصال قبل المزامنة')
      return
    }

    const normalized = { ...connection, storeId }
    setConnection(normalized)
    saveConnectionSettings(normalized)
    setBusy(true)

    try {
      setStatus('جاري إرسال طلب المزامنة إلى Railway...')
      const response = await lammahApi.syncStore(normalized.merchantId, storeId, toApiContext(normalized))

      if (response.data) {
        updateStoreInState(response.data)
      }

      setStatus(response.queued ? 'تم وضع المزامنة في الطابور. سيبدأ العامل خلال لحظات، اضغط تحديث لمتابعة الحالة.' : 'تم تنفيذ المزامنة.')
    } catch (error) {
      setStatus(readableError(error))
    } finally {
      setBusy(false)
    }
  }

  async function generateConnectorToken(storeId: string) {
    if (!hasMerchantConnection(connection)) {
      setStatus('أكمل بيانات الاتصال قبل توليد توكن الإضافة')
      return
    }

    setBusy(true)

    try {
      setStatus('جاري توليد توكن WordPress Connector...')
      const response = await lammahApi.generateConnectorToken(connection.merchantId, storeId, toApiContext(connection))
      updateStoreInState(response.data)
      setConnectorToken({ storeId, value: response.connector_token })
      setStatus('تم توليد التوكن. انسخه الآن داخل إضافة WordPress، لن يظهر مرة أخرى.')
    } catch (error) {
      setStatus(readableError(error))
    } finally {
      setBusy(false)
    }
  }

  async function addStore() {
    if (!hasMerchantConnection(connection)) {
      setStatus('أكمل بيانات الاتصال قبل إضافة متجر')
      return
    }

    if (!storeForm.name || !storeForm.base_url || !storeForm.consumer_key || !storeForm.consumer_secret) {
      setStatus('اكتب اسم المتجر والرابط و Consumer Key و Consumer Secret')
      return
    }

    setBusy(true)
    try {
      const response = await lammahApi.createStore(connection.merchantId, apiContext, storeForm)
      const nextStore = response.data
      const normalized = { ...connection, storeId: nextStore.id }

      setConnectionResult(response.connection ?? null)
      setStoreForm(emptyStoreForm)
      setConnection(normalized)
      updateStoreInState(nextStore)
      saveConnectionSettings(normalized)
      setActiveSection('overview')
      setStatus(response.sync_queued ? 'تم حفظ المتجر بسرعة ووضع المزامنة في الطابور.' : response.connection?.error ?? 'تم حفظ المتجر')
    } catch (error) {
      setStatus(readableError(error))
    } finally {
      setBusy(false)
    }
  }

  async function runAction(action: (settings: ConnectionSettings) => Promise<unknown>, label: string) {
    if (!hasSelectedStore(connection)) {
      setStatus('اختار متجرًا أولًا')
      setActiveSection('stores')
      return
    }

    setBusy(true)
    try {
      await action(connection)
      setStatus(label)
      try {
        await loadStoreData(connection)
      } catch (reloadError) {
        setStatus(`${label}. لكن تحديث العرض فشل: ${readableError(reloadError)}`)
      }
    } catch (error) {
      setStatus(readableError(error))
    } finally {
      setBusy(false)
    }
  }

  return (
    <AppShell activeSection={activeSection} liveMode={liveMode} onSectionChange={setActiveSection}>
      <ConnectionPanel busy={busy} connection={connection} status={status} onChange={updateConnection} onConnect={saveAndConnect} onRefresh={() => void loadWorkspace(connection)} />

      <section className="dashboard-grid mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <MetricTile label="إجمالي المبيعات" value={formatMoney(summary.gross_revenue)} detail="من طلبات WooCommerce المتزامنة" tone="teal" icon={CircleDollarSign} />
        <MetricTile label="صافي الربح" value={formatMoney(summary.net_profit)} detail="بعد التكلفة والرسوم" tone="sky" icon={ChartNoAxesCombined} />
        <MetricTile label="تنبيهات الاحتيال" value={String(summary.open_fraud_signals)} detail="حسابات تجريبية أو نشاط مشبوه" tone="rose" icon={ShieldAlert} />
        <MetricTile label="خطر عدم التجديد" value={String(summary.high_churn_customers)} detail="عملاء يحتاجون متابعة" tone="amber" icon={Users} />
      </section>

      {activeSection === 'overview' && (
        <section className="dashboard-grid mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(280px,360px)]">
          <div className="min-w-0 grid gap-4">
            {!selectedStore && <EmptyStorePrompt onClick={() => setActiveSection('stores')} />}
            <StoreSummaryCard store={selectedStore} busy={busy} connectorToken={connectorToken} onGenerateToken={generateConnectorToken} onSync={() => syncStore(connection.storeId)} />
            <ForecastsPanel forecasts={forecasts} />
            <ProfitsPanel profits={profits} />
          </div>

          <div className="min-w-0 grid content-start gap-4">
            <CommandPanel
              busy={busy}
              onForecast={() => runAction((settings) => lammahApi.generateForecast(settings.merchantId, settings.storeId, toApiContext(settings)), 'تم طلب التوقعات')}
              onFraudScan={() => runAction((settings) => lammahApi.scanFraud(settings.merchantId, settings.storeId, toApiContext(settings)), 'تم تشغيل فحص الاحتيال')}
              onRfmRefresh={() => runAction((settings) => lammahApi.refreshRfmScores(settings.merchantId, settings.storeId, toApiContext(settings)), 'تم تحديث RFM')}
              onPriceLift={(percent) => runAction((settings) => lammahApi.createPriceUpdate(settings.merchantId, settings.storeId, toApiContext(settings), percent), `تم طلب زيادة ${percent}%`)}
              onStartShift={() => runAction((settings) => lammahApi.startShift(settings.merchantId, toApiContext(settings)), 'تم بدء الشفت')}
            />
            <RfmPanel rfm={rfm} />
            <ProcessCard />
          </div>
        </section>
      )}

      {activeSection === 'stores' && (
        <section className="dashboard-grid mt-4 grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(300px,420px)]">
          <StoreList stores={stores} selectedStoreId={connection.storeId} busy={busy} connectorToken={connectorToken} onGenerateToken={generateConnectorToken} onSelect={selectStore} onSync={syncStore} />
          <StoreFormPanel busy={busy} form={storeForm} result={connectionResult} onChange={updateStoreForm} onSubmit={addStore} />
        </section>
      )}

      {activeSection === 'fraud' && <FraudPanel fraud={fraud} busy={busy} liveMode={liveMode} onReview={(signalId) => runAction((settings) => lammahApi.updateFraudSignal(settings.merchantId, signalId, 'reviewing', toApiContext(settings)), 'تم وضع التنبيه قيد المراجعة')} />}

      {activeSection === 'pricing' && (
          <section className="dashboard-grid mt-4 grid gap-4 xl:grid-cols-[minmax(280px,360px)_minmax(0,1fr)]">
          <PricingActions busy={busy} onPriceLift={(percent) => runAction((settings) => lammahApi.createPriceUpdate(settings.merchantId, settings.storeId, toApiContext(settings), percent), `تم طلب زيادة ${percent}%`)} />
          <PriceUpdatesPanel priceUpdates={priceUpdates} />
        </section>
      )}

      {activeSection === 'shifts' && <ShiftsPanel shifts={shifts} busy={busy} liveMode={liveMode} onStart={() => runAction((settings) => lammahApi.startShift(settings.merchantId, toApiContext(settings)), 'تم بدء الشفت')} onClose={(shiftId) => runAction((settings) => lammahApi.closeShift(settings.merchantId, shiftId, toApiContext(settings)), 'تم إغلاق الشفت')} />}
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
  const isConfigured = hasMerchantConnection(connection)

  return (
    <section className="glass-panel grid gap-4 rounded-md p-4">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div className="min-w-0">
          <h2 className="text-base font-semibold">{isConfigured ? 'حالة حساب العميل' : 'تجهيز حساب العميل'}</h2>
          <p className="mt-1 text-sm leading-6 text-slate-400">
            {isConfigured ? 'الربط التقني محفوظ. العميل يرى المتاجر والتحليلات فقط، وإعدادات المطور مخفية هنا عند الحاجة.' : 'أدخل بيانات الحساب مرة واحدة فقط. بعد تسجيل الدخول لاحقًا لن يحتاج العميل رؤية هذه البيانات.'}
          </p>
        </div>
        <div className="flex shrink-0 gap-2">
          <button className="h-10 rounded-md border border-teal-300/25 bg-teal-300/10 px-4 text-sm text-teal-100 disabled:opacity-50" disabled={busy} onClick={isConfigured ? onRefresh : onConnect}>
            {isConfigured ? 'تحديث' : 'اتصال'}
          </button>
          {isConfigured && (
            <button className="grid h-10 w-11 place-items-center rounded-md border border-white/10 bg-white/5 text-slate-300 disabled:opacity-50" disabled={busy} onClick={onRefresh} title="تحديث">
              <RefreshCcw className="size-4" />
            </button>
          )}
        </div>
      </div>

      <div className="flex min-w-0 items-start gap-2 rounded-md border border-white/10 bg-white/5 p-3 text-sm text-slate-300">
        <Activity className="mt-0.5 size-4 shrink-0 text-teal-200" />
        <span className="min-w-0 break-words">{status}</span>
      </div>

      <details className="group rounded-md border border-white/10 bg-black/10 p-3">
        <summary className="cursor-pointer select-none text-sm font-medium text-slate-300 group-open:text-white">إعدادات المطور</summary>
        <div className="dashboard-grid mt-3 grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_minmax(0,1fr)]">
          <LabeledInput label="رابط API على Railway" value={connection.apiBaseUrl} onChange={(value) => onChange('apiBaseUrl', value)} placeholder="https://your-api.up.railway.app" />
          <LabeledInput label="توكن Sanctum" type="password" value={connection.token} onChange={(value) => onChange('token', value)} placeholder="01...|..." />
          <LabeledInput label="معرف التاجر" value={connection.merchantId} onChange={(value) => onChange('merchantId', value)} placeholder="Merchant ULID" />
          <button className="h-12 rounded-md border border-teal-300/25 bg-teal-300/10 px-4 text-sm text-teal-100 disabled:opacity-50 md:col-span-2 xl:col-span-3" disabled={busy} onClick={onConnect}>
            حفظ إعدادات الاتصال
          </button>
        </div>
      </details>
    </section>
  )
}

function LabeledInput({ label, value, onChange, placeholder, type = 'text' }: { label: string; value: string; placeholder?: string; type?: string; onChange: (value: string) => void }) {
  return (
    <label className="grid gap-1.5">
      <span className="text-xs font-medium text-slate-400">{label}</span>
      <input
        className="h-12 w-full rounded-md border border-white/10 bg-white/5 px-3 text-sm text-slate-100 outline-none transition placeholder:text-slate-600 focus:border-teal-300/50"
        type={type}
        value={value}
        placeholder={placeholder}
        onChange={(event) => onChange(event.target.value)}
      />
    </label>
  )
}

function StoreFormPanel({ busy, form, result, onChange, onSubmit }: { busy: boolean; form: WooCommerceStorePayload; result: WooCommerceConnectionResult | null; onChange: (key: keyof WooCommerceStorePayload, value: string | boolean) => void; onSubmit: () => void }) {
  return (
    <section className="glass-panel rounded-md p-4">
      <div className="flex items-center gap-2">
        <PlugZap className="size-5 text-teal-200" />
        <h2 className="text-base font-semibold">ربط متجر WooCommerce</h2>
      </div>
      <p className="mt-2 text-sm leading-6 text-slate-400">من WordPress أنشئ REST API Key بصلاحية Read/Write، ثم الصق البيانات هنا. لا تحتاج Railway أو Tinker.</p>

      <div className="mt-4 grid gap-3">
        <LabeledInput label="اسم المتجر" value={form.name} onChange={(value) => onChange('name', value)} placeholder="متجر سمارت جينز" />
        <LabeledInput label="رابط WordPress" value={form.base_url} onChange={(value) => onChange('base_url', value)} placeholder="https://smartjenz.com" />
        <LabeledInput label="Consumer Key" value={form.consumer_key} onChange={(value) => onChange('consumer_key', value)} placeholder="ck_..." />
        <LabeledInput label="Consumer Secret" type="password" value={form.consumer_secret} onChange={(value) => onChange('consumer_secret', value)} placeholder="cs_..." />
        <div className="grid gap-3 sm:grid-cols-2">
          <LabeledInput label="العملة" value={form.currency} onChange={(value) => onChange('currency', value.toUpperCase())} placeholder="SAR" />
          <LabeledInput label="المنطقة الزمنية" value={form.timezone} onChange={(value) => onChange('timezone', value)} placeholder="Asia/Riyadh" />
        </div>
        <label className="flex items-center gap-2 rounded-md border border-white/10 bg-white/5 p-3 text-sm text-slate-300">
          <input className="size-4 accent-teal-300" type="checkbox" checked={form.sync_now} onChange={(event) => onChange('sync_now', event.target.checked)} />
          شغل مزامنة المنتجات والطلبات بعد الحفظ
        </label>
        <button className="h-12 rounded-md bg-teal-300 px-4 text-sm font-semibold text-slate-950 disabled:opacity-50" disabled={busy} onClick={onSubmit}>
          حفظ وربط المتجر
        </button>
      </div>

      {result && (
        <div className={`mt-4 rounded-md border p-3 text-sm ${result.ok ? 'border-teal-300/25 bg-teal-300/10 text-teal-100' : 'border-rose-300/25 bg-rose-300/10 text-rose-100'}`}>
          {result.ok ? `الاتصال ناجح. عينة المنتجات: ${result.sample_count ?? 0}` : result.error ?? 'فشل اختبار الاتصال'}
        </div>
      )}
    </section>
  )
}

type ConnectorTokenState = { storeId: string; value: string } | null

function StoreList({
  stores,
  selectedStoreId,
  busy,
  connectorToken,
  onGenerateToken,
  onSelect,
  onSync
}: {
  stores: WooCommerceStore[]
  selectedStoreId: string
  busy: boolean
  connectorToken: ConnectorTokenState
  onGenerateToken: (storeId: string) => void
  onSelect: (storeId: string) => void
  onSync: (storeId: string) => void
}) {
  return (
    <section className="glass-panel rounded-md p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold">المتاجر المربوطة</h2>
          <p className="mt-1 text-sm text-slate-400">اختار متجرًا لعرض تحليلاته أو شغل مزامنته.</p>
        </div>
        <span className="rounded-md border border-white/10 bg-white/5 px-3 py-1 text-sm text-slate-300">{stores.length} متجر</span>
      </div>

      <div className="mt-4 grid gap-3">
        {stores.length === 0 && <div className="rounded-md border border-dashed border-white/15 p-6 text-center text-sm text-slate-400">لا يوجد متاجر بعد. أضف أول متجر من النموذج.</div>}
        {stores.map((store) => (
          <article key={store.id} className={`rounded-md border p-4 ${selectedStoreId === store.id ? 'border-teal-300/45 bg-teal-300/10' : 'border-white/10 bg-white/5'}`}>
            {(() => {
              const syncState = store.sync_settings?.sync_state

              return (
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="min-w-0">
                <h3 className="font-semibold text-white">{store.name}</h3>
                <p className="mt-1 break-all text-sm text-slate-400">{store.base_url}</p>
                <p className="mt-1 text-xs text-slate-500">WordPress Connector: {translateConnectorStatus(store.connector_status)}</p>
                {syncState && <p className="mt-1 text-xs text-slate-500">حالة المزامنة: {translateSyncState(syncState)}</p>}
              </div>
              <StatusBadge value={store.status} />
            </div>
              )
            })()}
            {connectorToken?.storeId === store.id && <ConnectorTokenBox token={connectorToken.value} />}
            {store.last_error && <p className="mt-3 rounded-md border border-rose-300/20 bg-rose-300/10 p-2 text-xs text-rose-100">{store.last_error}</p>}
            {store.connector_last_error && <p className="mt-3 rounded-md border border-amber-300/20 bg-amber-300/10 p-2 text-xs text-amber-100">{store.connector_last_error}</p>}
            <div className="mt-4 flex flex-wrap gap-2">
              <button className="rounded-md border border-teal-300/25 bg-teal-300/10 px-3 py-2 text-sm text-teal-100" onClick={() => onSelect(store.id)}>
                اختيار المتجر
              </button>
              <button className="rounded-md border border-sky-300/25 bg-sky-300/10 px-3 py-2 text-sm text-sky-100 disabled:opacity-50" disabled={busy} onClick={() => onGenerateToken(store.id)}>
                {store.has_connector_token ? 'تدوير توكن الإضافة' : 'توليد توكن الإضافة'}
              </button>
              <button className="rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 disabled:opacity-50" disabled={busy || store.sync_settings?.sync_state === 'queued' || store.sync_settings?.sync_state === 'running'} onClick={() => onSync(store.id)}>
                {store.sync_settings?.sync_state === 'queued' || store.sync_settings?.sync_state === 'running' ? 'قيد المزامنة' : 'مزامنة الآن'}
              </button>
            </div>
          </article>
        ))}
      </div>
    </section>
  )
}

function StoreSummaryCard({
  store,
  busy,
  connectorToken,
  onGenerateToken,
  onSync
}: {
  store: WooCommerceStore | null
  busy: boolean
  connectorToken: ConnectorTokenState
  onGenerateToken: (storeId: string) => void
  onSync: () => void
}) {
  if (!store) return null

  const syncState = store.sync_settings?.sync_state

  return (
    <section className="glass-panel rounded-md p-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="grid size-11 place-items-center rounded-md border border-teal-300/25 bg-teal-300/10">
            <Store className="size-5 text-teal-100" />
          </div>
          <div>
            <h2 className="text-base font-semibold">{store.name}</h2>
            <p className="break-all text-sm text-slate-400">{store.base_url}</p>
            <p className="mt-1 text-xs text-slate-500">WordPress Connector: {translateConnectorStatus(store.connector_status)}</p>
            {syncState && <p className="mt-1 text-xs text-slate-500">حالة المزامنة: {translateSyncState(syncState)}</p>}
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          <button className="rounded-md border border-sky-300/25 bg-sky-300/10 px-3 py-2 text-sm text-sky-100 disabled:opacity-50" disabled={busy} onClick={() => onGenerateToken(store.id)}>
            {store.has_connector_token ? 'تدوير توكن الإضافة' : 'توليد توكن الإضافة'}
          </button>
          <button className="rounded-md border border-white/10 bg-white/5 px-3 py-2 text-sm text-slate-200 disabled:opacity-50" disabled={busy || syncState === 'queued' || syncState === 'running'} onClick={onSync}>
            {syncState === 'queued' || syncState === 'running' ? 'المزامنة تعمل' : 'مزامنة REST احتياطية'}
          </button>
        </div>
      </div>
      {connectorToken?.storeId === store.id && <ConnectorTokenBox token={connectorToken.value} />}
    </section>
  )
}

function ConnectorTokenBox({ token }: { token: string }) {
  return (
    <div className="mt-4 rounded-md border border-sky-300/25 bg-sky-300/10 p-3">
      <p className="text-sm font-medium text-sky-100">انسخ هذا التوكن الآن داخل WordPress Connector</p>
      <code className="mt-2 block break-all rounded-md border border-white/10 bg-black/30 p-3 text-xs text-slate-100">{token}</code>
      <p className="mt-2 text-xs text-slate-400">لأمان الحساب، التوكن يظهر مرة واحدة فقط. لو ضاع، ولّد توكن جديد.</p>
    </div>
  )
}

function EmptyStorePrompt({ onClick }: { onClick: () => void }) {
  return (
    <section className="glass-panel rounded-md p-6 text-center">
      <PlugZap className="mx-auto size-9 text-teal-200" />
      <h2 className="mt-3 text-lg font-semibold">ابدأ بربط متجر WooCommerce</h2>
      <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-slate-400">بعد الربط، لمّة تسحب المنتجات والطلبات تلقائيًا وتظهر المبيعات والتحليلات هنا.</p>
      <button className="mt-4 rounded-md bg-teal-300 px-4 py-2 text-sm font-semibold text-slate-950" onClick={onClick}>
        إضافة متجر
      </button>
    </section>
  )
}

function CommandPanel({ busy, onForecast, onFraudScan, onRfmRefresh, onPriceLift, onStartShift }: { busy: boolean; onForecast: () => void; onFraudScan: () => void; onRfmRefresh: () => void; onPriceLift: (percent: number) => void; onStartShift: () => void }) {
  return (
    <section className="glass-panel rounded-md p-4">
      <h2 className="text-base font-semibold">أوامر سريعة</h2>
      <div className="mt-4 grid gap-2">
        <ActionButton disabled={busy} label="توليد توقع المبيعات" tone="teal" onClick={onForecast} />
        <ActionButton disabled={busy} label="فحص الحسابات المشبوهة" tone="rose" onClick={onFraudScan} />
        <ActionButton disabled={busy} label="تحديث شرائح العملاء RFM" tone="sky" onClick={onRfmRefresh} />
        <div className="grid grid-cols-2 gap-2">
          <ActionButton disabled={busy} label="+5%" tone="amber" onClick={() => onPriceLift(5)} />
          <ActionButton disabled={busy} label="+10%" tone="amber" onClick={() => onPriceLift(10)} />
        </div>
        <ActionButton disabled={busy} label="بدء شفت دعم" tone="neutral" onClick={onStartShift} />
      </div>
    </section>
  )
}

function ActionButton({ label, tone, disabled, onClick }: { label: string; tone: 'teal' | 'rose' | 'sky' | 'amber' | 'neutral'; disabled: boolean; onClick: () => void }) {
  const tones = {
    teal: 'border-teal-300/25 bg-teal-300/10 text-teal-100',
    rose: 'border-rose-300/25 bg-rose-300/10 text-rose-100',
    sky: 'border-sky-300/25 bg-sky-300/10 text-sky-100',
    amber: 'border-amber-300/25 bg-amber-300/10 text-amber-100',
    neutral: 'border-white/10 bg-white/5 text-slate-200'
  }

  return (
    <button className={`h-11 rounded-md border px-3 text-sm disabled:opacity-50 ${tones[tone]}`} disabled={disabled} onClick={onClick}>
      {label}
    </button>
  )
}

function ForecastsPanel({ forecasts }: { forecasts: SalesForecast[] }) {
  return (
    <DataPanel
      title="توقعات المبيعات"
      emptyState="لا توجد توقعات بعد"
      getRowKey={(row) => row.id}
      rows={forecasts}
      columns={[
        { header: 'الشهر', render: (row) => formatDate(row.forecast_month) },
        { header: 'المبيعات', render: (row) => formatMoney(row.gross_revenue_forecast) },
        { header: 'الربح', render: (row) => (row.net_profit_forecast === null ? '-' : formatMoney(row.net_profit_forecast)) },
        { header: 'الطلبات', render: (row) => row.order_count_forecast ?? '-' },
        { header: 'النطاق', render: (row) => `${formatMoney(row.confidence_low)} - ${formatMoney(row.confidence_high)}` }
      ]}
    />
  )
}

function ProfitsPanel({ profits }: { profits: ProfitSnapshot[] }) {
  return (
    <DataPanel
      title="صافي الربح حسب الطلبات"
      emptyState="لا توجد طلبات محسوبة بعد"
      getRowKey={(row) => row.id}
      rows={profits}
      columns={[
        { header: 'الطلب', render: (row) => shortId(row.order_id) },
        { header: 'الإجمالي', render: (row) => formatMoney(row.gross_revenue, row.currency) },
        { header: 'الصافي', render: (row) => formatMoney(row.net_profit, row.currency) },
        { header: 'الهامش', render: (row) => (row.margin_percent === null ? '-' : `${row.margin_percent}%`) }
      ]}
    />
  )
}

function RfmPanel({ rfm }: { rfm: RfmScore[] }) {
  return (
    <DataPanel
      title="عملاء يحتاجون متابعة"
      emptyState="لا يوجد عملاء بخطر مرتفع"
      getRowKey={(row) => row.id}
      rows={rfm}
      columns={[
        { header: 'العميل', render: (row) => shortId(row.customer_id) },
        { header: 'الشريحة', render: (row) => row.segment },
        { header: 'خطر عدم التجديد', render: (row) => formatPercent(row.churn_probability) }
      ]}
    />
  )
}

function ProcessCard() {
  return (
    <section className="glass-panel rounded-md p-4 text-sm text-slate-400">
      <div className="flex items-center gap-2 text-amber-100">
        <AlertTriangle className="size-4" />
        طريقة تشغيل العميل
      </div>
      <ol className="mt-3 list-decimal space-y-2 pr-5 leading-6">
        <li>تدخل WooCommerce keys في تبويب المتاجر.</li>
        <li>لمّة تختبر الاتصال وتشغل المزامنة.</li>
        <li>العميل يستلم رابط الداشبورد وحساب دخول، وليس Railway أو Supabase.</li>
      </ol>
    </section>
  )
}

function FraudPanel({ fraud, busy, liveMode, onReview }: { fraud: FraudSignal[]; busy: boolean; liveMode: boolean; onReview: (signalId: string) => void }) {
  return (
    <section className="mt-4">
      <DataPanel
        title="رادار الاحتيال والحسابات التجريبية"
        emptyState="لا توجد تنبيهات مفتوحة"
        getRowKey={(row) => row.id}
        rows={fraud}
        columns={[
          { header: 'الخطورة', render: (row) => <SeverityBadge value={row.severity} /> },
          { header: 'الدرجة', render: (row) => Math.round(row.risk_score) },
          { header: 'الإشارة', render: (row) => row.signal_type },
          { header: 'الحالة', render: (row) => row.status },
          {
            header: 'إجراء',
            render: (row) => (
              <button className="rounded-md border border-white/10 bg-white/5 px-2 py-1 text-xs text-slate-200 hover:bg-white/10 disabled:opacity-50" disabled={busy || !liveMode} onClick={() => onReview(row.id)}>
                مراجعة
              </button>
            )
          }
        ]}
      />
    </section>
  )
}

function PricingActions({ busy, onPriceLift }: { busy: boolean; onPriceLift: (percent: number) => void }) {
  return (
    <section className="glass-panel rounded-md p-4">
      <div className="flex items-center gap-2">
        <Tags className="size-5 text-amber-100" />
        <h2 className="text-base font-semibold">تعديل الأسعار</h2>
      </div>
      <p className="mt-2 text-sm leading-6 text-slate-400">استخدمها أثناء الضغط العالي أو المواسم. كل دفعة محفوظة وقابلة للمراجعة.</p>
      <div className="mt-4 grid grid-cols-2 gap-2">
        <ActionButton disabled={busy} label="+5%" tone="amber" onClick={() => onPriceLift(5)} />
        <ActionButton disabled={busy} label="+10%" tone="amber" onClick={() => onPriceLift(10)} />
      </div>
    </section>
  )
}

function PriceUpdatesPanel({ priceUpdates }: { priceUpdates: PriceUpdateBatch[] }) {
  return (
    <DataPanel
      title="دفعات تعديل الأسعار"
      emptyState="لا توجد دفعات بعد"
      getRowKey={(row) => row.id}
      rows={priceUpdates}
      columns={[
        { header: 'الدفعة', render: (row) => shortId(row.id) },
        { header: 'النوع', render: (row) => row.mode },
        { header: 'القيمة', render: (row) => (row.mode === 'percent' ? `${row.value}%` : row.value) },
        { header: 'الحالة', render: (row) => <StatusBadge value={row.status} /> }
      ]}
    />
  )
}

function ShiftsPanel({ shifts, busy, liveMode, onStart, onClose }: { shifts: StaffShift[]; busy: boolean; liveMode: boolean; onStart: () => void; onClose: (shiftId: string) => void }) {
  return (
    <section className="mt-4">
      <DataPanel
        title="شفتات فريق الدعم"
        emptyState="لا توجد شفتات"
        getRowKey={(row) => row.id}
        action={
          <button className="rounded-md border border-teal-300/25 bg-teal-300/10 px-3 py-1.5 text-xs text-teal-100 disabled:opacity-50" disabled={busy || !liveMode} onClick={onStart}>
            بدء شفت
          </button>
        }
        rows={shifts}
        columns={[
          { header: 'الشفت', render: (row) => shortId(row.id) },
          { header: 'الموظف', render: (row) => shortId(row.user_id) },
          { header: 'الحالة', render: (row) => <StatusBadge value={row.status} /> },
          { header: 'بدأ', render: (row) => formatDateTime(row.starts_at) },
          {
            header: 'إجراء',
            render: (row) =>
              row.status === 'open' ? (
                <button className="rounded-md border border-white/10 bg-white/5 px-2 py-1 text-xs text-slate-200 hover:bg-white/10 disabled:opacity-50" disabled={busy || !liveMode} onClick={() => onClose(row.id)}>
                  إغلاق
                </button>
              ) : (
                '-'
              )
          }
        ]}
      />
    </section>
  )
}

function SeverityBadge({ value }: { value: FraudSignal['severity'] }) {
  const tone = value === 'critical' || value === 'high' ? 'border-rose-300/30 bg-rose-300/10 text-rose-100' : 'border-amber-300/30 bg-amber-300/10 text-amber-100'

  return <span className={`inline-flex rounded-md border px-2 py-1 text-xs ${tone}`}>{translateSeverity(value)}</span>
}

function StatusBadge({ value }: { value: string }) {
  const tone = ['active', 'open', 'queued', 'processing'].includes(value) ? 'border-teal-300/30 bg-teal-300/10 text-teal-100' : value === 'error' ? 'border-rose-300/30 bg-rose-300/10 text-rose-100' : 'border-white/10 bg-white/5 text-slate-300'

  return <span className={`inline-flex rounded-md border px-2 py-1 text-xs ${tone}`}>{translateStatus(value)}</span>
}

function toApiContext(settings: ConnectionSettings) {
  return { apiBaseUrl: settings.apiBaseUrl, token: settings.token }
}

function hasMerchantConnection(settings: ConnectionSettings) {
  return Boolean(settings.apiBaseUrl && settings.token && settings.merchantId)
}

function hasSelectedStore(settings: ConnectionSettings) {
  return Boolean(settings.apiBaseUrl && settings.token && settings.merchantId && settings.storeId)
}

function normalizeConnection(settings: ConnectionSettings): ConnectionSettings {
  return {
    apiBaseUrl: settings.apiBaseUrl.trim().replace(/\/$/, ''),
    token: settings.token.trim(),
    merchantId: settings.merchantId.trim(),
    storeId: settings.storeId.trim()
  }
}

function readableError(error: unknown) {
  return error instanceof Error ? error.message : 'حدث خطأ غير متوقع'
}

function formatMoney(value: number | null | undefined, currency = 'SAR') {
  return `${currency} ${Number(value ?? 0).toLocaleString('ar-EG', { maximumFractionDigits: 2 })}`
}

function formatPercent(value: number | null | undefined) {
  if (value === null || value === undefined) return '-'

  return `${Math.round(value * 100)}%`
}

function formatDate(value: string | null | undefined) {
  if (!value) return '-'

  return new Intl.DateTimeFormat('ar-EG', { month: 'short', year: 'numeric' }).format(new Date(value))
}

function formatDateTime(value: string | null | undefined) {
  if (!value) return '-'

  return new Intl.DateTimeFormat('ar-EG', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value))
}

function shortId(value: string) {
  return value.length <= 12 ? value : `${value.slice(0, 6)}...${value.slice(-4)}`
}

function translateStatus(value: string) {
  const map: Record<string, string> = {
    active: 'نشط',
    disabled: 'متوقف',
    error: 'خطأ',
    open: 'مفتوح',
    closed: 'مغلق',
    queued: 'بالانتظار',
    running: 'قيد التشغيل',
    processing: 'قيد المعالجة',
    completed: 'مكتمل',
    failed: 'فشل'
  }

  return map[value] ?? value
}

function translateSyncState(value: string) {
  const map: Record<string, string> = {
    queued: 'في الطابور',
    running: 'جاري السحب من WooCommerce',
    succeeded: 'اكتملت بنجاح',
    failed: 'فشلت وتحتاج مراجعة'
  }

  return map[value] ?? value
}

function translateConnectorStatus(value: string) {
  const map: Record<string, string> = {
    not_configured: 'لم يتم توليد التوكن بعد',
    token_issued: 'تم توليد التوكن وينتظر الربط',
    connected: 'متصل',
    warning: 'متصل مع تحذير',
    error: 'خطأ في الربط'
  }

  return map[value] ?? value
}

function translateSeverity(value: string) {
  const map: Record<string, string> = {
    low: 'منخفض',
    medium: 'متوسط',
    high: 'مرتفع',
    critical: 'حرج'
  }

  return map[value] ?? value
}
