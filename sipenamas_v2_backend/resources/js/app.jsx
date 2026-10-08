import './index.css'
import { createInertiaApp } from '@inertiajs/react'
import { createRoot } from 'react-dom/client'
import { AppLayout } from './components/layout/AppLayout'

const pages = import.meta.glob(['./features/**/*.jsx', '!./features/dev/**'])

createInertiaApp({
  title: (title) => (title ? `${title} — SIPENAMAS V2` : 'SIPENAMAS V2 — LPPM UKWMS'),
  resolve: (name) => {
    const page = pages[`./features/${name}.jsx`]
    if (!page) {
      throw new Error(`Halaman Inertia tidak ditemukan: ${name}`)
    }
    return page()
  },
  // Halaman auth/* (login, unauthorized, QR publik) tampil tanpa sidebar.
  layout: (name) => (name.startsWith('auth/') ? null : AppLayout),
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />)
  },
  progress: { color: '#188ae2' },
})
