import React, { useState, useEffect } from 'react'
import {
  FileSpreadsheet,
  Download,
  CheckCircle2,
  ExternalLink,
  Table,
} from 'lucide-react'
import { adminApi } from '../../services/api/adminApi'
import { formatRupiah } from '../../utils/formatters'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

export default function ExportSintaPage() {
  const [rows, setRows] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [isExporting, setIsExporting] = useState(false)
  const [successNotice, setSuccessNotice] = useState('')
  const [searchQuery, setSearchQuery] = useState('')
  const [tahunFilter, setTahunFilter] = useState('ALL')
  const [skemaFilter, setSkemaFilter] = useState('ALL')

  useEffect(() => {
    async function load() {
      setIsLoading(true)
      try {
        const res = await adminApi.getSintaExportData()
        setRows(res.data)
      } finally {
        setIsLoading(false)
      }
    }
    load()
  }, [])

  const handleDownloadCsv = () => {
    setIsExporting(true)
    setTimeout(() => {
      // Build CSV content
      const headers = [
        'No',
        'Kode PT',
        'Nama PT',
        'Tahun',
        'NIDN Ketua',
        'Nama Ketua',
        'Judul Penelitian',
        'Bidang Fokus',
        'Skema',
        'Lama Kegiatan',
        'Dana Usulan (Rp)',
        'Dana Disetujui (Rp)',
        'Target TKT',
        'Status',
        'Nomor SK',
      ]

      const csvRows = rows.map((r) => [
        r.no,
        r.kode_pt,
        `"${r.nama_pt}"`,
        r.tahun,
        r.nidn_ketua,
        `"${r.nama_ketua}"`,
        `"${r.judul}"`,
        `"${r.bidang_fokus}"`,
        `"${r.skema}"`,
        r.lama_kegiatan,
        r.dana_usulan,
        r.dana_disetujui,
        r.target_tkt,
        r.status,
        `"${r.nomor_sk}"`,
      ])

      const csvContent =
        'data:text/csv;charset=utf-8,' +
        [headers.join(','), ...csvRows.map((e) => e.join(','))].join('\n')

      const encodedUri = encodeURI(csvContent)
      const link = document.createElement('a')
      link.setAttribute('href', encodedUri)
      link.setAttribute('download', `SINTA_Ekspor_Penelitian_UKWMS_${new Date().getFullYear()}.csv`)
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)

      setIsExporting(false)
      setSuccessNotice('Berkas CSV format SINTA Kemendikbud berhasil diunduh!')
      setTimeout(() => setSuccessNotice(''), 4000)
    }, 400)
  }

  const filteredRows = rows.filter((r) => {
    const matchSearch =
      !searchQuery ||
      r.judul?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      r.nama_ketua?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      r.nidn_ketua?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      r.skema?.toLowerCase().includes(searchQuery.toLowerCase())
    const matchTahun = tahunFilter === 'ALL' || String(r.tahun) === String(tahunFilter)
    const matchSkema = skemaFilter === 'ALL' || r.skema === skemaFilter
    return matchSearch && matchTahun && matchSkema
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedRows } =
    usePagination(filteredRows, 10)

  // Extract unique skema for filter
  const skemaOptions = Array.from(new Set(rows.map((r) => r.skema).filter(Boolean))).map((s) => ({
    value: s,
    label: s,
  }))

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Ekspor Pangkalan Data Nasional (SINTA / BIMA)
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Menghasilkan format tabel terstandarisasi untuk sinkronisasi portofolio hibah ke Kemendikbudristek
          </p>
        </div>
        <Button
          variant="success"
          size="sm"
          iconLeft={Download}
          onClick={handleDownloadCsv}
          isLoading={isExporting}
        >
          Unduh File CSV / Excel SINTA
        </Button>
      </div>

      {successNotice && (
        <div className="p-3.5 bg-[#10c469]/10 border border-[#10c469]/20 text-[#10c469] rounded-xl text-xs flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-[#10c469] shrink-0" />
          <span className="font-semibold text-[#0c8a49]">{successNotice}</span>
        </div>
      )}

      {/* Filter Toolbar */}
      <TableFilterBar
        search={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari nama ketua, NIDN, judul, skema..."
        filters={[
          {
            key: 'tahun',
            value: tahunFilter,
            onChange: setTahunFilter,
            options: [
              { value: 'ALL', label: 'Semua Tahun' },
              { value: '2026', label: 'Tahun 2026' },
              { value: '2025', label: 'Tahun 2025' },
              { value: '2024', label: 'Tahun 2024' },
            ],
          },
          {
            key: 'skema',
            value: skemaFilter,
            onChange: setSkemaFilter,
            options: [{ value: 'ALL', label: 'Semua Skema' }, ...skemaOptions],
          },
        ]}
        onReset={() => {
          setSearchQuery('')
          setTahunFilter('ALL')
          setSkemaFilter('ALL')
        }}
        totalResults={filteredRows.length}
      />

      {/* SINTA Columns Preview Table */}
      <Card>
        <CardHeader
          title="Pratinjau Data Sinkronisasi SINTA"
          subtitle="Kolom disesuaikan dengan template resmi impor pangkalan data SINTA"
          action={
            <span className="text-xs text-[#98a6ad]">
              Total: <strong className="text-[#313a46]">{filteredRows.length}</strong> record usulan
            </span>
          }
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-3 py-3 text-center">No</th>
                <th className="px-3 py-3">Tahun</th>
                <th className="px-3 py-3">NIDN Ketua</th>
                <th className="px-4 py-3">Nama Ketua</th>
                <th className="px-4 py-3">Judul Kegiatan Penelitian</th>
                <th className="px-3 py-3">Skema</th>
                <th className="px-3 py-3 text-right">Dana Disetujui</th>
                <th className="px-3 py-3 text-center">Status</th>
                <th className="px-4 py-3">Nomor SK</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb] font-mono text-[11px]">
              {isLoading ? (
                <TableSkeleton rows={5} cols={9} />
              ) : pagedRows.length === 0 ? (
                <TableEmptyState
                  colSpan={9}
                  message="Tidak ada data ekspor SINTA yang sesuai"
                  submessage="Coba ubah kata kunci atau filter tahun/skema."
                  onReset={() => {
                    setSearchQuery('')
                    setTahunFilter('ALL')
                    setSkemaFilter('ALL')
                  }}
                />
              ) : (
                pagedRows.map((r) => (
                <tr key={r.no} className="hover:bg-[#f6f7fb]/60 transition-colors">
                  <td className="px-3 py-3 text-center text-[#98a6ad]">{r.no}</td>
                  <td className="px-3 py-3 text-[#6c757d]">{r.tahun}</td>
                  <td className="px-3 py-3 font-semibold text-[#188ae2]">{r.nidn_ketua}</td>
                  <td className="px-4 py-3 font-sans font-medium text-[#313a46] whitespace-nowrap">
                    {r.nama_ketua}
                  </td>
                  <td className="px-4 py-3 font-sans text-slate-800 max-w-sm">
                    <p className="line-clamp-2" title={r.judul}>{r.judul}</p>
                  </td>
                  <td className="px-3 py-3 font-sans text-slate-600 whitespace-nowrap">{r.skema}</td>
                  <td className="px-3 py-3 text-right font-bold text-slate-900 whitespace-nowrap">
                    {formatRupiah(r.dana_disetujui)}
                  </td>
                  <td className="px-3 py-3 text-center whitespace-nowrap">
                    <span
                      className={`px-2 py-0.5 rounded text-[10px] font-sans font-semibold border ${
                        r.status === 'Didanai'
                          ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                          : 'bg-slate-100 text-slate-600 border-slate-200'
                      }`}
                    >
                      {r.status}
                    </span>
                  </td>
                  <td className="px-4 py-3 text-slate-600 whitespace-nowrap">{r.nomor_sk}</td>
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
