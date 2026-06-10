import type { Metadata } from 'next'
import './globals.css'

export const metadata: Metadata = {
  title: 'Lammah SaaS',
  description: 'Headless dashboard for WooCommerce IPTV and digital product merchants'
}

export default function RootLayout({ children }: Readonly<{ children: React.ReactNode }>) {
  return (
    <html lang="en">
      <body>{children}</body>
    </html>
  )
}
