import { Bot, Play, RefreshCcw, ShieldAlert, Tags } from 'lucide-react'

type ActionPanelProps = {
  onForecast: () => void
  onFraudScan: () => void
  onRfmRefresh: () => void
  onPriceLift: (percent: number) => void
  onStartShift: () => void
  busy: boolean
}

export function ActionPanel({ onForecast, onFraudScan, onRfmRefresh, onPriceLift, onStartShift, busy }: ActionPanelProps) {
  return (
    <section className="glass-panel rounded-md p-4">
      <div className="flex items-center gap-2">
        <Bot className="size-5 text-teal-200" />
        <h2 className="text-sm font-semibold">Command actions</h2>
      </div>

      <div className="mt-4 grid gap-2">
        <button
          className="flex h-11 items-center justify-between rounded-md border border-teal-300/25 bg-teal-300/10 px-3 text-sm text-teal-100 disabled:opacity-50"
          disabled={busy}
          onClick={onForecast}
        >
          Generate forecast
          <RefreshCcw className="size-4" />
        </button>
        <button
          className="flex h-11 items-center justify-between rounded-md border border-rose-300/25 bg-rose-300/10 px-3 text-sm text-rose-100 disabled:opacity-50"
          disabled={busy}
          onClick={onFraudScan}
        >
          Run fraud radar
          <ShieldAlert className="size-4" />
        </button>
        <button
          className="flex h-11 items-center justify-between rounded-md border border-sky-300/25 bg-sky-300/10 px-3 text-sm text-sky-100 disabled:opacity-50"
          disabled={busy}
          onClick={onRfmRefresh}
        >
          Refresh RFM scores
          <RefreshCcw className="size-4" />
        </button>
        <div className="grid grid-cols-2 gap-2">
          {[5, 10].map((percent) => (
            <button
              key={percent}
              className="flex h-11 items-center justify-between rounded-md border border-amber-300/25 bg-amber-300/10 px-3 text-sm text-amber-100 disabled:opacity-50"
              disabled={busy}
              onClick={() => onPriceLift(percent)}
            >
              +{percent}%
              <Tags className="size-4" />
            </button>
          ))}
        </div>
        <button
          className="flex h-11 items-center justify-between rounded-md border border-white/10 bg-white/5 px-3 text-sm text-slate-300 disabled:opacity-50"
          disabled={busy}
          onClick={onStartShift}
        >
          Start support shift
          <Play className="size-4" />
        </button>
      </div>
    </section>
  )
}
