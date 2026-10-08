import React, { useState, useEffect, useMemo } from 'react'
import { rektoratApi } from '../../services/api/rektoratApi'
import { formatRupiah } from '../../utils/formatters'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { StatusBadge } from '../../components/common/StatusBadge'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

export default function ApprovalStrategisPage() {
  const [list, setList] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [fakultasFilter, setFakultasFilter] = useState('ALL')

  useEffect(() => {
    async function load() {
      setIsLoading(true)
      try {
        const res = await rektoratApi.getApprovalStrategisList()
        setList(res.data)
      } finally {
        setIsLoading(false)
      }
    }
    load()
  }, [])

  const availableFakultas = useMemo(() => {
    return Array.from(new Set(list.map((p) => p.fakultasNama).filter(Boolean)))
  }, [list])

  const filteredList = list.filter((p) => {
    const q = searchQuery.toLowerCase()
    const matchSearch =
      !searchQuery ||
      (p.kodeUsulan && p.kodeUsulan.toLowerCase().includes(q)) ||
      (p.judul && p.judul.toLowerCase().includes(q)) ||
      (p.ketuaNama && p.ketuaNama.toLowerCase().includes(q)) ||
      (p.skimNama && p.skimNama.toLowerCase().includes(q))
    const matchFakultas = fakultasFilter === 'ALL' || p.fakultasNama === fakultasFilter
    return matchSearch && matchFakultas
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedList } =
    usePagination(filteredList, 10)

  return (
    <div className="space-y-6 text-left">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Monitoring Riset Strategis Unggulan Universitas
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Pemantauan pimpinan universitas atas usulan penelitian dengan nilai anggaran khusus dan berdampak strategis nasional
          </p>
        </div>
      </div>

      {/* Filter toolbar */}
      <TableFilterBar
        searchValue={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari kode usulan, judul riset, ketua, atau skema..."
        filters={[
          {
            key: 'fakultas',
            label: 'Fakultas',
            value: fakultasFilter,
            onChange: setFakultasFilter,
            options: [
              { value: 'ALL', label: 'Semua Fakultas' },
              ...availableFakultas.map((f) => ({ value: f, label: f })),
            ],
          },
        ]}
        onReset={() => {
          setSearchQuery('')
          setFakultasFilter('ALL')
        }}
        totalCount={list.length}
        filteredCount={filteredList.length}
      />

      <Card>
        <CardHeader
          title="Daftar Usulan Penelitian Unggulan (PU)"
          subtitle="Penelitian dengan pendanaan besar (> Rp 50 Juta) atau riset kolaboratif"
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-4 py-3">Kode & Judul Usulan</th>
                <th className="px-3 py-3">Ketua Peneliti</th>
                <th className="px-3 py-3">Fakultas</th>
                <th className="px-3 py-3 text-right">Alokasi Anggaran</th>
                <th className="px-3 py-3 text-center">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton cols={5} rows={5} />
              ) : filteredList.length === 0 ? (
                <TableEmptyState
                  colSpan={5}
                  message={
                    searchQuery || fakultasFilter !== 'ALL'
                      ? 'Tidak ada usulan riset strategis yang cocok dengan kriteria pencarian.'
                      : 'Belum ada usulan penelitian unggulan terdaftar.'
                  }
                  onReset={
                    searchQuery || fakultasFilter !== 'ALL'
                      ? () => {
                          setSearchQuery('')
                          setFakultasFilter('ALL')
                        }
                      : null
                  }
                />
              ) : (
                pagedList.map((p) => (
                  <tr key={p.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                    <td className="px-4 py-3.5 max-w-sm">
                      <span className="font-mono text-[11px] text-[#188ae2] font-semibold block">
                        {p.kodeUsulan} &bull; {p.skimNama}
                      </span>
                      <p className="font-semibold text-[#313a46] line-clamp-2 mt-0.5" title={p.judul}>
                        {p.judul}
                      </p>
                    </td>

                    <td className="px-3 py-3.5 whitespace-nowrap font-medium text-[#313a46]">
                      {p.ketuaNama}
                    </td>

                    <td className="px-3 py-3.5 whitespace-nowrap text-[#6c757d]">
                      {p.fakultasNama}
                    </td>

                    <td className="px-3 py-3.5 text-right whitespace-nowrap font-tabular font-bold text-[#313a46] text-sm">
                      {formatRupiah(p.biayaDisetujui || p.biayaUsulan)}
                    </td>

                    <td className="px-3 py-3.5 text-center whitespace-nowrap">
                      <StatusBadge status={p.status} />
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <CardFooter>
          <Pagination
            page={page}
            totalPages={totalPages}
            totalItems={totalItems}
            pageSize={pageSize}
            onPageChange={setPage}
          />
        </CardFooter>
      </Card>
    </div>
  )
}
