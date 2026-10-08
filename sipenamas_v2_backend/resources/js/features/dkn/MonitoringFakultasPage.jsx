import React, { useState, useEffect, useMemo } from 'react'
import { dekanApi } from '../../services/api/dekanApi'
import { masterDataApi } from '../../services/api/masterDataApi'
import { formatRupiah } from '../../utils/formatters'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Badge } from '../../components/ui/Badge'
import { StatusBadge } from '../../components/common/StatusBadge'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

/**
 * Padanan legacy dkn/myphp/penelitianbelumtuntas.php: penelitian fakultas
 * dekan yang STATUSFINALAPPROVAL = LOLOS tetapi STATUSKETUNTASANPENELITIAN
 * belum TUNTAS maupun TUNTAS BERSYARAT. Legacy murni daftar pengawasan,
 * tanpa aksi tulis (pengingat dikirim terpisah lewat antrian notifikasi).
 */
export default function MonitoringFakultasPage() {
  const [items, setItems] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [prodiFilter, setProdiFilter] = useState('ALL')
  const [periodeList, setPeriodeList] = useState([])
  const [kdperiode, setKdperiode] = useState('ALL')

  useEffect(() => {
    masterDataApi.getPeriodeList().then((res) => setPeriodeList(res.data))
  }, [])

  useEffect(() => {
    async function load() {
      setIsLoading(true)
      try {
        const res = await dekanApi.getMonitoringBelumTuntas({ kdperiode })
        setItems(res.data)
      } finally {
        setIsLoading(false)
      }
    }
    load()
  }, [kdperiode])

  const availableProdis = useMemo(
    () => Array.from(new Set(items.map((p) => p.prodiNama).filter(Boolean))),
    [items]
  )

  const filteredItems = items.filter((p) => {
    const q = searchQuery.toLowerCase()
    const matchSearch =
      !searchQuery ||
      (p.kodeUsulan && p.kodeUsulan.toLowerCase().includes(q)) ||
      (p.judul && p.judul.toLowerCase().includes(q)) ||
      (p.ketuaNama && p.ketuaNama.toLowerCase().includes(q))
    const matchProdi = prodiFilter === 'ALL' || p.prodiNama === prodiFilter
    return matchSearch && matchProdi
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedItems } =
    usePagination(filteredItems, 10)

  const resetFilter = () => {
    setSearchQuery('')
    setProdiFilter('ALL')
  }

  return (
    <div className="space-y-6 text-left">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Monitoring Penelitian Belum Tuntas
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Penelitian yang sudah lolos pendanaan namun status ketuntasannya belum TUNTAS atau TUNTAS BERSYARAT
          </p>
        </div>
      </div>

      <TableFilterBar
        searchValue={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari kode usulan, judul, atau ketua peneliti..."
        filters={[
          {
            key: 'prodi',
            label: 'Program Studi',
            value: prodiFilter,
            onChange: setProdiFilter,
            options: [
              { value: 'ALL', label: 'Semua Prodi' },
              ...availableProdis.map((p) => ({ value: p, label: p })),
            ],
          },
          {
            key: 'periode',
            label: 'Periode',
            value: kdperiode,
            onChange: setKdperiode,
            options: [
              { value: 'ALL', label: 'Semua Periode' },
              ...periodeList.map((p) => ({ value: p.kodeperiode, label: String(p.tahun) })),
            ],
          },
        ]}
        onReset={resetFilter}
        totalCount={items.length}
        filteredCount={filteredItems.length}
      />

      <Card>
        <CardHeader
          title="Daftar Penelitian Belum Tuntas"
          subtitle="Status ketuntasan ditetapkan Admin/LPPM setelah laporan akhir disetujui Dekan"
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-4 py-3">Kode & Judul Usulan</th>
                <th className="px-3 py-3">Ketua Peneliti</th>
                <th className="px-3 py-3">Tahap</th>
                <th className="px-3 py-3">Status Ketuntasan</th>
                <th className="px-3 py-3">Laporan Akhir</th>
                <th className="px-4 py-3 text-right">Dana Disetujui</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton cols={6} rows={4} />
              ) : filteredItems.length === 0 ? (
                <TableEmptyState
                  colSpan={6}
                  message={
                    searchQuery || prodiFilter !== 'ALL'
                      ? 'Tidak ada penelitian yang cocok dengan kriteria pencarian.'
                      : 'Semua penelitian fakultas sudah tuntas.'
                  }
                  onReset={searchQuery || prodiFilter !== 'ALL' ? resetFilter : null}
                />
              ) : (
                pagedItems.map((p) => (
                  <tr key={p.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                    <td className="px-4 py-3.5 max-w-sm">
                      <span className="font-mono text-[11px] text-[#6c757d] font-medium block">
                        {p.kodeUsulan} &bull; TA {p.tahun}
                      </span>
                      <p className="font-semibold text-[#313a46] line-clamp-2 mt-0.5" title={p.judul}>
                        {p.judul}
                      </p>
                    </td>

                    <td className="px-3 py-3.5 whitespace-nowrap">
                      <p className="font-medium text-[#313a46]">{p.ketuaNama}</p>
                      <span className="text-[11px] text-[#6c757d]">{p.prodiNama}</span>
                    </td>

                    <td className="px-3 py-3.5 whitespace-nowrap">
                      <StatusBadge status={p.status} />
                    </td>

                    <td className="px-3 py-3.5 whitespace-nowrap">
                      <Badge variant="warning" size="sm">
                        {p.statusKetuntasan}
                      </Badge>
                    </td>

                    <td className="px-3 py-3.5 whitespace-nowrap text-[11px] font-medium">
                      {p.isDisetujuiDekan ? (
                        <span className="text-[#10c469]">Disetujui Dekan</span>
                      ) : p.adaDokumenHasil ? (
                        <span className="text-[#f9c851]">Menunggu persetujuan Dekan</span>
                      ) : (
                        <span className="text-[#ff5b5b]">Dokumen hasil belum diunggah</span>
                      )}
                    </td>

                    <td className="px-4 py-3.5 text-right font-tabular font-bold text-[#5b69bc] whitespace-nowrap">
                      {formatRupiah(p.biayaDisetujui)}
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
