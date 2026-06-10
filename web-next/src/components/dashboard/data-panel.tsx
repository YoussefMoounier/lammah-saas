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

export function DataPanel<T>({ title, action, emptyState = 'No records yet', getRowKey, rows, columns }: DataPanelProps<T>) {
  return (
    <section className="glass-panel rounded-md">
      <div className="flex min-h-14 items-center justify-between border-b border-white/10 px-4">
        <h2 className="text-sm font-semibold">{title}</h2>
        {action}
      </div>
      <div className="overflow-x-auto">
        <table className="w-full min-w-[640px] border-collapse text-sm">
          <thead>
            <tr className="text-left text-xs uppercase tracking-[0.12em] text-slate-500">
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
