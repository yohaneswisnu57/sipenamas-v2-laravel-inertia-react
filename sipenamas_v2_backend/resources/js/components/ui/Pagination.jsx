import React from 'react'
import { ChevronLeft, ChevronRight } from 'lucide-react'

export function Pagination({ page, totalPages, totalItems, pageSize, onPageChange }) {
  if (totalItems === 0) return null

  const startItem = (page - 1) * pageSize + 1
  const endItem = Math.min(page * pageSize, totalItems)

  const goTo = (p) => {
    const next = Math.min(Math.max(p, 1), totalPages)
    if (next !== page) onPageChange(next)
  }

  return (
    <>
      <span>
        Menampilkan <strong className="text-slate-700 font-semibold">{startItem}</strong>-
        <strong className="text-slate-700 font-semibold">{endItem}</strong> dari{' '}
        <strong className="text-slate-700 font-semibold">{totalItems}</strong> data
      </span>
      <div className="flex items-center gap-1">
        <button
          type="button"
          onClick={() => goTo(page - 1)}
          disabled={page <= 1}
          className="p-1.5 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent transition"
          aria-label="Halaman sebelumnya"
        >
          <ChevronLeft className="w-3.5 h-3.5" />
        </button>
        <span className="px-2 font-medium text-slate-700 whitespace-nowrap">
          Hal {page} / {totalPages}
        </span>
        <button
          type="button"
          onClick={() => goTo(page + 1)}
          disabled={page >= totalPages}
          className="p-1.5 rounded-md border border-slate-200 text-slate-600 hover:bg-slate-100 disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent transition"
          aria-label="Halaman berikutnya"
        >
          <ChevronRight className="w-3.5 h-3.5" />
        </button>
      </div>
    </>
  )
}
