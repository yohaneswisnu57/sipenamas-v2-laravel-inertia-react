import React, { useState, useEffect, useMemo } from 'react'
import { Search, Filter, Download, FileSpreadsheet } from 'lucide-react'
import { akreditasiApi } from '../../services/api/akreditasiApi'
import { formatRupiah } from '../../utils/formatters'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

export default function DataMiningBorangPage() {
  const [rows, setRows] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [prodiFilter, setProdiFilter] = useState('ALL')
  const [tahunFilter, setTahunFilter] = useState('ALL')

  const load = async () => {
    setIsLoading(true)
    try {
      const res = await akreditasiApi.getDataMiningBorang({
        prodi: prodiFilter === 'ALL' ? '' : prodiFilter,
        tahun: tahunFilter === 'ALL' ? '' : tahunFilter,
      })
      setRows(res.data)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [prodiFilter, tahunFilter])

  const availableProdis = useMemo(() => {
    return Array.from(new Set(rows.map((r) => r.prodi).filter(Boolean)))
  }, [rows])

  const filteredRows = rows.filter((r) => {
    if (!searchQuery.trim()) return true
    const q = searchQuery.toLowerCase()
    return (
      (r.namaDosen && r.namaDosen.toLowerCase().includes(q)) ||
      (r.nidn && r.nidn.toLowerCase().includes(q)) ||
      (r.judulPenelitian && r.judulPenelitian.toLowerCase().includes(q)) ||
      (r.skema && r.skema.toLowerCase().includes(q))
    )
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedRows } =
    usePagination(filteredRows, 10)

  return (
    <div className="space-y-6 text-left">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Data Mining Borang Penelitian Dosen
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Penyaringan data portofolio riset multi-variabel untuk penyusunan Laporan Evaluasi Diri (LED) & LKPS
          </p>
        </div>
      </div>

      {/* Filter toolbar */}
      <TableFilterBar
        searchValue={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari nama dosen, NIDN, judul riset, atau skema..."
        filters={[
          {
            key: 'tahun',
            label: 'Tahun Akademik',
            value: tahunFilter,
            onChange: setTahunFilter,
            options: [
              { value: 'ALL', label: 'Semua Tahun' },
              { value: '2026', label: '2026' },
              { value: '2025', label: '2025' },
              { value: '2024', label: '2024' },
            ],
          },
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
        ]}
        onReset={() => {
          setSearchQuery('')
          setProdiFilter('ALL')
          setTahunFilter('ALL')
        }}
        totalCount={rows.length}
        filteredCount={filteredRows.length}
      />

      <Card>
        <CardHeader
          title="Tabel Hasil Ekstraksi Portofolio Riset"
          subtitle={`Ditemukan ${rows.length} rekam data`}
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-3 py-3">Tahun</th>
                <th className="px-4 py-3">Nama Dosen & NIDN</th>
                <th className="px-3 py-3">Program Studi</th>
                <th className="px-4 py-3">Judul Penelitian</th>
                <th className="px-3 py-3">Skema</th>
                <th className="px-3 py-3 text-right">Jumlah Dana</th>
                <th className="px-4 py-3">Target Luaran</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb] font-sans text-xs">
              {isLoading ? (
                <TableSkeleton cols={7} rows={5} />
              ) : filteredRows.length === 0 ? (
                <TableEmptyState
                  colSpan={7}
                  message={
                    searchQuery || prodiFilter !== 'ALL' || tahunFilter !== 'ALL'
                      ? 'Tidak ada data mining yang cocok dengan kriteria pencarian.'
                      : 'Belum ada data portofolio riset yang diekstraksi.'
                  }
                  onReset={
                    searchQuery || prodiFilter !== 'ALL' || tahunFilter !== 'ALL'
                      ? () => {
                          setSearchQuery('')
                          setProdiFilter('ALL')
                          setTahunFilter('ALL')
                        }
                      : null
                  }
                />
              ) : (
                pagedRows.map((r, idx) => (
                  <tr key={idx} className="hover:bg-[#f6f7fb]/60 transition-colors">
                    <td className="px-3 py-3 font-mono font-medium text-[#6c757d]">{r.tahunAkademik}</td>
                    <td className="px-4 py-3 whitespace-nowrap">
                      <p className="font-semibold text-[#313a46]">{r.namaDosen}</p>
                      <span className="font-mono text-[10px] text-[#98a6ad]">NIDN: {r.nidn}</span>
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap text-[#6c757d]">{r.prodi}</td>
                    <td className="px-4 py-3 max-w-sm">
                      <p className="line-clamp-2 text-[#313a46]" title={r.judulPenelitian}>
                        {r.judulPenelitian}
                      </p>
                    </td>
                    <td className="px-3 py-3 whitespace-nowrap text-[#6c757d]">{r.skema}</td>
                    <td className="px-3 py-3 text-right whitespace-nowrap font-tabular font-bold text-[#313a46]">
                      {formatRupiah(r.jumlahDana)}
                    </td>
                    <td className="px-4 py-3 text-[#6c757d] max-w-xs truncate" title={r.luaranArtikel}>
                      {r.luaranArtikel}
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
