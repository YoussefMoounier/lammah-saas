'use client'

import type { ReactNode } from 'react'
import { Bell, Bot, ChartNoAxesCombined, PlugZap, ShieldAlert, Store, Tags, Users } from 'lucide-react'
import clsx from 'clsx'

export type ConsoleSection = 'overview' | 'stores' | 'fraud' | 'pricing' | 'shifts'

const navItems: Array<{ id: ConsoleSection; label: string; icon: typeof ChartNoAxesCombined }> = [
  { id: 'overview', label: 'الرئيسية', icon: ChartNoAxesCombined },
  { id: 'stores', label: 'المتاجر', icon: PlugZap },
  { id: 'fraud', label: 'الحماية', icon: ShieldAlert },
  { id: 'pricing', label: 'الأسعار', icon: Tags },
  { id: 'shifts', label: 'الشفتات', icon: Users }
]

type AppShellProps = {
  activeSection: ConsoleSection
  children: ReactNode
  liveMode: boolean
  onSectionChange: (section: ConsoleSection) => void
}

export function AppShell({ activeSection, children, liveMode, onSectionChange }: AppShellProps) {
  return (
    <main className="min-h-screen w-full overflow-x-hidden bg-[#07090d] text-slate-100">
      <div className="neon-line h-1 w-full" />
      <aside className="fixed right-0 top-1 hidden h-[calc(100vh-4px)] w-72 border-l border-white/10 bg-[#090d13]/95 px-4 py-5 lg:block">
        <div className="flex items-center gap-3 px-2">
          <div className="grid size-11 place-items-center rounded-md border border-teal-300/40 bg-teal-300/10">
            <Store className="size-5 text-teal-200" />
          </div>
          <div>
            <p className="text-base font-semibold">لمّة SaaS</p>
            <p className="text-xs text-slate-400">لوحة تحكم التجار</p>
          </div>
        </div>

        <nav className="mt-8 space-y-1">
          {navItems.map((item) => {
            const Icon = item.icon

            return (
              <button
                key={item.id}
                onClick={() => onSectionChange(item.id)}
                className={clsx(
                  'flex h-12 w-full items-center gap-3 rounded-md px-3 text-sm transition',
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
            وضع الذكاء التشغيلي
          </div>
          <p className="mt-2 text-xs leading-6 text-slate-400">تحليلات Laravel تعمل الآن. يمكن إضافة FastAPI لاحقًا.</p>
        </div>
      </aside>

      <section className="min-w-0 lg:pr-72">
        <header className="sticky top-0 z-20 flex min-h-16 items-center justify-between gap-3 border-b border-white/10 bg-[#07090d]/88 px-4 py-3 backdrop-blur md:px-6">
          <div className="min-w-0">
            <p className="text-xs font-semibold text-teal-200">إدارة WooCommerce و IPTV</p>
            <h1 className="mt-1 text-lg font-semibold leading-tight md:text-2xl">لوحة لمّة للتجارة الرقمية</h1>
          </div>
          <div className="flex shrink-0 items-center gap-2">
            <button className="grid size-10 place-items-center rounded-md border border-white/10 bg-white/5 text-slate-300 hover:text-white" title="الإشعارات">
              <Bell className="size-4" />
            </button>
            <div className="hidden h-10 items-center gap-2 rounded-md border border-teal-300/30 bg-teal-300/10 px-3 text-sm text-teal-100 sm:flex">
              <span className="size-2 rounded-full bg-teal-300" />
              {liveMode ? 'متصل مباشر' : 'تجربة'}
            </div>
          </div>
        </header>

        <div className="mx-auto w-full max-w-[1600px] overflow-x-hidden px-4 pb-24 pt-5 md:px-6 lg:pb-8">{children}</div>
      </section>

      <nav className="fixed bottom-0 left-0 right-0 z-30 grid grid-cols-5 border-t border-white/10 bg-[#090d13]/95 px-1 py-2 backdrop-blur lg:hidden">
        {navItems.map((item) => {
          const Icon = item.icon

          return (
            <button
              key={item.id}
              onClick={() => onSectionChange(item.id)}
              className={clsx('grid min-w-0 justify-items-center gap-1 rounded-md py-2 text-[11px]', activeSection === item.id ? 'text-teal-200' : 'text-slate-500')}
            >
              <Icon className="size-5" />
              <span className="truncate">{item.label}</span>
            </button>
          )
        })}
      </nav>
    </main>
  )
}
