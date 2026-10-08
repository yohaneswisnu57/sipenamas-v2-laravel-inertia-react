import React, { useEffect } from 'react'
import { useParams } from '@/lib/router'
import { API_BASE_URL } from '../../services/api/apiClient'

/**
 * Tujuan QR surat (padanan appz/dox/{JENIS}/{KODE} legacy). Halaman publik
 * tanpa login yang langsung membuka PDF surat dari backend, sehingga browser
 * ponsel memakai penampil PDF bawaannya.
 */
export function urlDokumenQr(jenis, kode) {
  return `${API_BASE_URL}/dox/${encodeURIComponent(jenis)}/${encodeURIComponent(kode)}`
}

export default function DokumenQrPage() {
  const { jenis, kode } = useParams()

  useEffect(() => {
    window.location.replace(urlDokumenQr(jenis, kode))
  }, [jenis, kode])

  return (
    <div className="min-h-screen bg-[#f6f7fb] flex items-center justify-center p-6">
      <p className="text-sm text-[#98a6ad]">Membuka dokumen…</p>
    </div>
  )
}
