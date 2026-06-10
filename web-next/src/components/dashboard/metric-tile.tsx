import type { LucideIcon } from 'lucide-react'
import clsx from 'clsx'

type MetricTileProps = {
  label: string
  value: string
  detail: string
  tone: 'teal' | 'amber' | 'rose' | 'sky'
  icon: LucideIcon
}

const toneMap = {
  teal: 'border-teal-300/25 bg-teal-300/10 text-teal-100',
  amber: 'border-amber-300/25 bg-amber-300/10 text-amber-100',
  rose: 'border-rose-300/25 bg-rose-300/10 text-rose-100',
  sky: 'border-sky-300/25 bg-sky-300/10 text-sky-100'
}

export function MetricTile({ label, value, detail, tone, icon: Icon }: MetricTileProps) {
  return (
    <article className="glass-panel rounded-md p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-xs uppercase tracking-[0.14em] text-slate-500">{label}</p>
          <p className="mt-3 text-2xl font-semibold">{value}</p>
        </div>
        <div className={clsx('grid size-10 place-items-center rounded-md border', toneMap[tone])}>
          <Icon className="size-5" />
        </div>
      </div>
      <p className="mt-3 text-sm text-slate-400">{detail}</p>
    </article>
  )
}
