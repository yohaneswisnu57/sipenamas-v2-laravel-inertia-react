import React, { useMemo } from 'react'
import { Link, useSearchParams } from '@/lib/router'
import { PlusCircle } from 'lucide-react'
import { formatRupiah } from '../../utils/formatters'
import { StatusBadge } from '../../components/common/StatusBadge'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { STATUS_USULAN, STATUS_USULAN_LABELS, TAHAP_USULAN } from '../../utils/constants'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

export default function DaftarPenelitianPage({ proposals = [] }) {
  const isLoading = false
  const [searchParams, setSearchParams] = useSearchParams()

  const search = searchParams.get('q') || ''
  const statusFilter = searchParams.get('status') || 'ALL'
  const skimFilter = searchParams.get('skim') || 'ALL'
  const tahapFilter = searchParams.get('tahap') || 'ALL'

  const updateParam = (key, val) => {
    const next = new URLSearchParams(searchParams)
    if (val && val !== 'ALL') {
      next.set(key, val)
    } else {
      next.delete(key)
    }
    setSearchParams(next, { replace: true })
  }

  const availableSkims = useMemo(() => {
    const s = new Set()
    proposals.forEach((p) => {
      if (p.skimNama) s.add(p.skimNama)
    })
    return Array.from(s)
  }, [proposals])

  const filtered = proposals.filter((p) => {
    const q = search.toLowerCase()
    const matchSearch =
      !search ||
      (p.judul && p.judul.toLowerCase().includes(q)) ||
      (p.kodeUsulan && p.kodeUsulan.toLowerCase().includes(q)) ||
      (p.skimNama && p.skimNama.toLowerCase().includes(q))
    const matchStatus = statusFilter === 'ALL' || p.status === statusFilter
    const matchSkim = skimFilter === 'ALL' || p.skimNama === skimFilter
    const matchTahap =
      tahapFilter === 'ALL' || TAHAP_USULAN[tahapFilter]?.statuses.includes(p.status)
    return matchSearch && matchStatus && matchSkim && matchTahap
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedProposals } =
    usePagination(filtered, 10)

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Daftar Portofolio Penelitian Saya
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Riwayat seluruh kegiatan penelitian internal yang diajukan ke LPPM UKWMS
          </p>
        </div>
        <Link to="/pen/penelitian/baru">
          <Button variant="primary" size="sm" iconLeft={PlusCircle}>
            Ajukan Usulan Baru
          </Button>
        </Link>
      </div>

      {/* Filter toolbar */}
      <TableFilterBar
        searchValue={search}
        onSearchChange={(val) => updateParam('q', val)}
        searchPlaceholder="Cari judul, kode usulan, atau skema..."
        filters={[
          {
            key: 'tahap',
            label: 'Tahap',
            value: tahapFilter,
            onChange: (val) => updateParam('tahap', val),
            options: [
              { value: 'ALL', label: 'Semua Tahap' },
              ...Object.entries(TAHAP_USULAN).map(([val, { label }]) => ({ value: val, label })),
            ],
          },
          {
            key: 'skim',
            label: 'Skema',
            value: skimFilter,
            onChange: (val) => updateParam('skim', val),
            options: [
              { value: 'ALL', label: 'Semua Skema' },
              ...availableSkims.map((s) => ({ value: s, label: s })),
            ],
          },
          {
            key: 'status',
            label: 'Status',
            value: statusFilter,
            onChange: (val) => updateParam('status', val),
            options: [
              { value: 'ALL', label: 'Semua Status' },
              ...Object.entries(STATUS_USULAN_LABELS).map(([val, label]) => ({
                value: val,
                label,
              })),
            ],
          },
        ]}
        onReset={() => {
          setSearchParams({}, { replace: true })
        }}
        totalCount={proposals.length}
        filteredCount={filtered.length}
      />

      {/* Table */}
      <Card>
        <CardHeader
          title="Tabel Portofolio Riset"
          subtitle={`Menampilkan ${filtered.length} kegiatan penelitian`}
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-4 py-3 text-center">Aksi</th>
                <th className="px-4 py-3">Kode & Judul Usulan</th>
                <th className="px-3 py-3">Skema</th>
                <th className="px-3 py-3 text-right">Dana Disetujui</th>
                <th className="px-3 py-3 text-center">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton cols={5} rows={5} />
              ) : filtered.length === 0 ? (
                <TableEmptyState
                  colSpan={5}
                  message={
                    search || statusFilter !== 'ALL' || skimFilter !== 'ALL' || tahapFilter !== 'ALL'
                      ? 'Tidak ada usulan penelitian yang cocok dengan kriteria pencarian.'
                      : 'Belum ada kegiatan penelitian terdaftar.'
                  }
                  onReset={
                    search || statusFilter !== 'ALL' || skimFilter !== 'ALL'
                      ? () => {
                          setSearch('')
                          setStatusFilter('ALL')
                          setSkimFilter('ALL')
                        }
                      : null
                  }
                />
              ) : (
                pagedProposals.map((p) => (
                  <tr key={p.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                    <td className="px-4 py-3.5 text-center whitespace-nowrap">
                      <div className="flex items-center justify-center gap-1.5">
                        <Link to={`/pen/penelitian/${p.id}`}>
                          <Button variant="secondary" size="xs">
                            Detail Usulan
                          </Button>
                        </Link>
                        {p.status === STATUS_USULAN.REVISI && (
                          <Link to={`/pen/revisi/${p.id}`}>
                            <Button variant="soft-warning" size="xs">
                              Unggah Revisi
                            </Button>
                          </Link>
                        )}
                      </div>
                    </td>
                    <td className="px-4 py-3.5 max-w-md">
                      <span className="font-mono text-[11px] text-[#188ae2] font-semibold block">
                        {p.kodeUsulan} &bull; TA {p.tahun}
                      </span>
                      <p className="font-semibold text-[#313a46] line-clamp-2 mt-0.5" title={p.judul}>
                        {p.judul}
                      </p>
                      <span className="text-[10px] text-[#98a6ad] mt-1 block">
                        Fokus: {p.bidangFokus}
                      </span>
                    </td>

                    <td className="px-3.5 py-3.5 whitespace-nowrap font-medium text-[#313a46]">
                      {p.skimNama}
                    </td>

                    <td className="px-3.5 py-3.5 text-right whitespace-nowrap font-tabular font-bold text-[#313a46]">
                      {formatRupiah(p.biayaDisetujui || p.biayaUsulan)}
                    </td>

                    <td className="px-3.5 py-3.5 text-center whitespace-nowrap">
                      <StatusBadge status={p.status} dokumenFinal={p.isDokumenProposalFinal} />
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
