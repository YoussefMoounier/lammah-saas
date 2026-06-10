import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'لمّة SaaS',
  description: 'لوحة تحكم عربية لتجار WooCommerce و IPTV والمنتجات الرقمية'
}

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="ar" dir="rtl">
      <body>{children}</body>
    </html>
  )
}
