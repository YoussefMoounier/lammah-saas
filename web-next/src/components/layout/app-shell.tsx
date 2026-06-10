'use client'

import type { ReactNode } from 'react'
import { Bell, Bot, ChartNoAxesCombined, CircleDollarSign, ShieldAlert, Store, Tags, Users } from 'lucide-react'
import clsx from 'clsx'

export type ConsoleSection = 'overview' | 'fraud' | 'pricing' | 'shifts'

const navItems: Array<{ id: ConsoleSection; label: string; icon: typeof ChartNoAxesCombined }> = [
  { id: 'overview', label: 'Overview', icon: ChartNoAxesCombined },
  { id: 'fraud', label: 'Fraud', icon: ShieldAlert },
  { id: 'pricing', label: 'Pricing', icon: Tags },
  { id: 'shifts', label: 'Shifts', icon: Users }
]

type AppShellProps = {
  activeSection: ConsoleSection
  children: ReactNode
  liveMode: boolean
  onSectionChange: (section: ConsoleSection) => void
}

export function AppShell({ activeSection, children, liveMode, onSectionChange }: AppShellProps) {
  return (
    <main className="min-h-screen bg-[#07090d] text-slate-100">
      <div className="neon-line h-1 w-full" />
      <aside className="fixed left-0 top-1 hidden h-[calc(100vh-4px)] w-64 border-r border-white/10 bg-[#090d13]/95 px-4 py-5 lg:block">
        <div className="flex items-center gap-3 px-2">
          <div className="grid size-10 place-items-center rounded-md border border-teal-300/40 bg-teal-300/10">
            <Store className="size-5 text-teal-200" />
          </div>
          <div>
            <p className="text-sm font-semibold">Lammah SaaS</p>
            <p className="text-xs text-slate-400">Merchant command</p>
          </div>
        </div>

        <nav className="mt-8 space-y-1">
          {navItems.map((item) => {
            const Icon = item.icon

            return (
              <button
                key={item.label}
                onClick={() => onSectionChange(item.id)}
                className={clsx(
                  'flex h-11 w-full items-center gap-3 rounded-md px-3 text-sm transition',
                  activeSection === item.id ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white'
                )}
              >
                <Icon className="size-4" />
                {item.label}
              </button>
            )
          })}
        </nav>

        <div className="absolute bottom-5 left-4 right-4 rounded-md border border-amber-300/20 bg-amber-300/10 p-3">
          <div className="flex items-center gap-2 text-sm font-medium text-amber-100">
            <Bot className="size-4" />
            AI native mode
          </div>
          <p className="mt-2 text-xs leading-5 text-slate-400">Laravel analytics active. FastAPI can attach later.</p>
        </div>
      </aside>

      <section className="lg:pl-64">
        <header className="sticky top-0 z-20 flex h-16 items-center justify-between border-b border-white/10 bg-[#07090d]/85 px-4 backdrop-blur md:px-6">
          <div>
            <p className="text-xs uppercase tracking-[0.18em] text-teal-200">Live operations</p>
            <h1 className="text-base font-semibold md:text-xl">IPTV and digital products dashboard</h1>
          </div>
          <div className="flex items-center gap-2">
            <button className="grid size-10 place-items-center rounded-md border border-white/10 bg-white/5 text-slate-300 hover:text-white" title="Notifications">
              <Bell className="size-4" />
            </button>
            <button className="hidden h-10 items-center gap-2 rounded-md border border-teal-300/30 bg-teal-300/10 px-3 text-sm text-teal-100 md:flex">
              <CircleDollarSign className="size-4" />
              {liveMode ? 'Live API' : 'Demo mode'}
            </button>
          </div>
        </header>

        <div className="px-4 pb-24 pt-5 md:px-6 lg:pb-8">{children}</div>
      </section>

      <nav className="fixed bottom-0 left-0 right-0 z-30 grid grid-cols-4 border-t border-white/10 bg-[#090d13]/95 px-2 py-2 backdrop-blur lg:hidden">
        {navItems.map((item) => {
          const Icon = item.icon

          return (
            <button
              key={item.label}
              onClick={() => onSectionChange(item.id)}
              className={clsx('grid justify-items-center gap-1 rounded-md py-2 text-xs', activeSection === item.id ? 'text-teal-200' : 'text-slate-500')}
            >
              <Icon className="size-5" />
              {item.label}
            </button>
          )
        })}
      </nav>
    </main>
  )
}
