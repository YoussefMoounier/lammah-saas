import type { ReactNode } from 'react'

type Column<T> = {
  header: string
  render: (row: T) => ReactNode
}

type DataPanelProps<T> = {
  title: string
  action?: ReactNode
  emptyState?: string
  getRowKey?: (row: T, index: number) => string | number
  rows: T[]
  columns: Column<T>[]
}

export function DataPanel<T>({ title, action, emptyState = 'لا توجد بيانات بعد', getRowKey, rows, columns }: DataPanelProps<T>) {
  return (
    <section className="glass-panel max-w-full overflow-hidden rounded-md">
      <div className="flex min-h-14 items-center justify-between gap-3 border-b border-white/10 px-4">
        <h2 className="text-sm font-semibold">{title}</h2>
        {action}
      </div>
      <div className="max-w-full overflow-x-auto">
        <table className="w-full min-w-[560px] border-collapse text-sm sm:min-w-[640px]">
          <thead>
            <tr className="text-right text-xs uppercase tracking-[0.12em] text-slate-500">
              {columns.map((column) => (
                <th key={column.header} className="px-4 py-3 font-medium">
                  {column.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 ? (
              <tr className="border-t border-white/10 text-slate-500">
                <td className="px-4 py-6 text-center" colSpan={columns.length}>
                  {emptyState}
                </td>
              </tr>
            ) : (
              rows.map((row, rowIndex) => (
                <tr key={getRowKey ? getRowKey(row, rowIndex) : rowIndex} className="border-t border-white/10 text-slate-300">
                  {columns.map((column) => (
                    <td key={column.header} className="px-4 py-3">
                      {column.render(row)}
                    </td>
                  ))}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>
    </section>
  )
}
