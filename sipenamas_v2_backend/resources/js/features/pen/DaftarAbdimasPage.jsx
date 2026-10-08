import React, { useState, useMemo } from 'react'
import { Link } from '@/lib/router'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { StatusBadge } from '../../components/common/StatusBadge'
import { formatRupiah } from '../../utils/formatters'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { PlusCircle } from 'lucide-react'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

/**
 * Padanan legacy pen/myphp/permohonanabdimas.php LST: usulan JENIS_PA =
 * ABDIMAS tempat login menjadi anggota tim. Pengajuan Abdimas baru belum
 * diport ke V2, jadi halaman ini hanya daftar baca + tautan detail.
 */
export default function DaftarAbdimasPage({ abdimasList = [] }) {
  const isLoading = false
  const [search, setSearch] = useState('')
  const [skemaFilter, setSkemaFilter] = useState('ALL')

  const availableSkemas = useMemo(
    () => Array.from(new Set(abdimasList.map((a) => a.skimNama).filter(Boolean))),
    [abdimasList]
  )

  const filtered = abdimasList.filter((a) => {
    const q = search.toLowerCase()
    const matchSearch =
      !search ||
      a.judul?.toLowerCase().includes(q) ||
      a.kodeUsulan?.toLowerCase().includes(q) ||
      a.tempatLokasi?.toLowerCase().includes(q)
    const matchSkema = skemaFilter === 'ALL' || a.skimNama === skemaFilter
    return matchSearch && matchSkema
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedAbdimas } =
    usePagination(filtered, 10)

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Daftar Pengabdian kepada Masyarakat (Abdimas)
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Manajemen kegiatan penerapan iptek dan pendampingan kemitraan masyarakat
          </p>
        </div>
        <Link to="/pen/abdimas/baru">
          <Button variant="primary" size="sm" iconLeft={PlusCircle}>
            Ajukan Usulan Baru
          </Button>
        </Link>
      </div>

      {/* Filter toolbar */}
      <TableFilterBar
        searchValue={search}
        onSearchChange={setSearch}
        searchPlaceholder="Cari judul kegiatan, kode, atau lokasi..."
        filters={[
          {
            key: 'skema',
            label: 'Skema Abdimas',
            value: skemaFilter,
            onChange: setSkemaFilter,
            options: [
              { value: 'ALL', label: 'Semua Skema' },
              ...availableSkemas.map((s) => ({ value: s, label: s })),
            ],
          },
        ]}
        onReset={() => {
          setSearch('')
          setSkemaFilter('ALL')
        }}
        totalCount={abdimasList.length}
        filteredCount={filtered.length}
      />

      <Card>
        <CardHeader
          title="Portofolio Kegiatan Abdimas Saya"
          subtitle="Memantau keterlibatan mitra sasaran dan realisasi program kemitraan"
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-4 py-3 text-center">Aksi</th>
                <th className="px-4 py-3">Kode & Judul Kegiatan</th>
                <th className="px-3 py-3">Skema Abdimas</th>
                <th className="px-3 py-3">Tempat / Lokasi</th>
                <th className="px-3 py-3 text-right">Dana Disetujui</th>
                <th className="px-3 py-3 text-center">Status</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton cols={6} rows={4} />
              ) : filtered.length === 0 ? (
                <TableEmptyState
                  colSpan={6}
                  message={
                    search || skemaFilter !== 'ALL'
                      ? 'Tidak ada kegiatan abdimas yang cocok dengan kriteria pencarian.'
                      : 'Belum ada kegiatan abdimas terdaftar.'
                  }
                  onReset={
                    search || skemaFilter !== 'ALL'
                      ? () => {
                          setSearch('')
                          setSkemaFilter('ALL')
                        }
                      : null
                  }
                />
              ) : (
                pagedAbdimas.map((a) => (
                <tr key={a.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                  <td className="px-4 py-3.5 text-center whitespace-nowrap">
                    <Link to={`/pen/penelitian/${a.id}`}>
                      <Button variant="secondary" size="xs">
                        Detail
                      </Button>
                    </Link>
                  </td>
                  <td className="px-4 py-3.5 max-w-sm">
                    <span className="font-mono text-[11px] text-[#188ae2] font-semibold block">
                      {a.kodeUsulan}
                    </span>
                    <p className="font-semibold text-[#313a46] line-clamp-2 mt-0.5" title={a.judul}>
                      {a.judul}
                    </p>
                  </td>

                  <td className="px-3 py-3.5 whitespace-nowrap font-medium text-[#313a46]">
                    {a.skimNama}
                  </td>

                  <td className="px-3 py-3.5 whitespace-nowrap text-[#6c757d]">
                    {a.tempatLokasi || '-'}
                  </td>

                  <td className="px-3 py-3.5 text-right whitespace-nowrap font-tabular font-bold text-[#313a46]">
                    {a.biayaDisetujui != null ? formatRupiah(a.biayaDisetujui) : '-'}
                  </td>

                  <td className="px-3 py-3.5 text-center whitespace-nowrap">
                    <StatusBadge status={a.status} />
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
