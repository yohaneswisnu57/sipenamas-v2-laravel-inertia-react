import React from 'react'
import { STATUS_METADATA, STATUS_USULAN } from '../../utils/constants'

export function StatusBadge({ status, className = '', dokumenFinal }) {
  const base = STATUS_METADATA[status] || {
    label: status || 'Unknown',
    badge: 'bg-slate-100 text-slate-700 border-slate-200',
  }
  // Usulan diajukan tapi ketua belum memfinalkan dokumen: belum masuk antrean Dekan.
  const meta =
    status === STATUS_USULAN.SUBMITTED && dokumenFinal === false
      ? { ...base, label: 'Melengkapi Dokumen Pengajuan' }
      : base

  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium border ${meta.badge} ${className}`}
    >
      <span className="w-1.5 h-1.5 rounded-full bg-current opacity-70" />
      {meta.label}
    </span>
  )
}
