import React, { useState, useEffect } from 'react'
import { rektoratApi } from '../../services/api/rektoratApi'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Badge } from '../../components/ui/Badge'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

/**
 * Padanan legacy rkt/myphp/mbkm.php, mbkmbyprodi.php, mbkmbelum.php dan
 * mbkmdatahasil*.php.
 *
 * Modul MBKM legacy adalah survei berhadiah: mahasiswa mengisi kuesioner
 * lalu menerima voucher. Tidak ada SKS terkonversi maupun dosen pembimbing
 * di skema legacy, jadi keduanya tidak ditampilkan di sini.
 */
const TAB_LIST = [
  { key: 'pengisian', label: 'Daftar Pengisian' },
  { key: 'rekap', label: 'Rekap per Prodi' },
  { key: 'belum', label: 'Belum Mengisi' },
  { key: 'dosen', label: 'Kuesioner Dosen' },
  { key: 'mahasiswa', label: 'Kuesioner Mahasiswa' },
  { key: 'tendik', label: 'Kuesioner Tendik' },
]

export default function MonitoringMbkmPage() {
  const [tab, setTab] = useState('pengisian')
  const [rows, setRows] = useState([])
  const [rekap, setRekap] = useState(null)
  const [kampus, setKampus] = useState('SURABAYA')
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')

  useEffect(() => {
    async function load() {
      setIsLoading(true)
      try {
        if (tab === 'rekap') {
          const res = await rektoratApi.getMbkmRekapProdi({ kampus })
          setRekap(res.data)
          setRows(res.data.prodi || [])
        } else if (tab === 'pengisian') {
          const res = await rektoratApi.getMbkmPengisian()
          setRows(res.data)
        } else if (tab === 'belum') {
          const res = await rektoratApi.getMbkmBelumMengisi()
          setRows(res.data)
        } else {
          const res = await rektoratApi.getMbkmDataHasil(tab)
          setRows(res.data)
        }
      } finally {
        setIsLoading(false)
      }
    }
    load()
  }, [tab, kampus])

  const filteredRows = rows.filter((row) => {
    if (!searchQuery.trim()) return true
    const q = searchQuery.toLowerCase()
    return ['nim', 'nama', 'namaProdi', 'identitas', 'kodeProdi']
      .some((field) => row[field] && String(row[field]).toLowerCase().includes(q))
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedRows } =
    usePagination(filteredRows, 15)

  const kolom = {
    pengisian: ['NIM', 'Nama Mahasiswa', 'Program Studi', 'Kontak', 'Status Survei', 'Voucher'],
    rekap: ['Program Studi', 'Fakultas', 'Pengisi', 'Total Mahasiswa', 'Persentase'],
    belum: ['NIM', 'Nama Mahasiswa', 'Program Studi', 'Status'],
    kuesioner: ['Identitas', 'Nama', 'Program Studi', 'Semester', 'Pertanyaan', 'Jawaban'],
  }
  const header = kolom[tab] || kolom.kuesioner

  return (
    <div className="space-y-6 text-left">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Survei MBKM & Rekap Pengisian
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Pemantauan pengisian kuesioner MBKM beserta pemberian voucher; prodi ditentukan dari awalan NIM
          </p>
        </div>
      </div>

      {tab === 'rekap' && rekap && (
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
          <Card className="p-5">
            <p className="text-xs font-semibold text-[#98a6ad] uppercase tracking-wider">Kampus</p>
            <p className="text-2xl font-bold font-heading text-[#313a46] mt-1.5">{rekap.kampus}</p>
          </Card>
          <Card className="p-5">
            <p className="text-xs font-semibold text-[#98a6ad] uppercase tracking-wider">Total Pengisi</p>
            <p className="text-2xl font-bold font-heading text-[#5b69bc] font-tabular mt-1.5">
              {rekap.totalPengisi}
            </p>
          </Card>
          <Card className="p-5">
            <p className="text-xs font-semibold text-[#98a6ad] uppercase tracking-wider">Total Mahasiswa</p>
            <p className="text-2xl font-bold font-heading text-[#6c757d] font-tabular mt-1.5">
              {rekap.totalMahasiswa}
            </p>
          </Card>
        </div>
      )}

      <div className="flex flex-wrap gap-2 border-b border-[#e7e9eb] pb-3">
        {TAB_LIST.map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => {
              setTab(t.key)
              setSearchQuery('')
              setPage(1)
            }}
            className={`px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors ${
              tab === t.key
                ? 'bg-[#5b69bc] text-white'
                : 'bg-[#f6f7fb] text-[#6c757d] hover:bg-[#e7e9eb]'
            }`}
          >
            {t.label}
          </button>
        ))}
      </div>

      <TableFilterBar
        searchValue={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari NIM, nama, atau program studi..."
        filters={
          tab === 'rekap'
            ? [
                {
                  key: 'kampus',
                  label: 'Kampus',
                  value: kampus,
                  onChange: setKampus,
                  options: [
                    { value: 'SURABAYA', label: 'Surabaya' },
                    { value: 'MADIUN', label: 'Madiun' },
                  ],
                },
              ]
            : []
        }
        onReset={() => setSearchQuery('')}
        totalCount={rows.length}
        filteredCount={filteredRows.length}
      />

      <Card>
        <CardHeader
          title={TAB_LIST.find((t) => t.key === tab)?.label}
          subtitle="Data diambil langsung dari tabel MBKM legacy, tanpa agregasi tambahan"
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                {header.map((h) => (
                  <th key={h} className="px-4 py-3">
                    {h}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton cols={header.length} rows={5} />
              ) : filteredRows.length === 0 ? (
                <TableEmptyState
                  colSpan={header.length}
                  message={
                    searchQuery
                      ? 'Tidak ada data yang cocok dengan pencarian.'
                      : 'Belum ada data MBKM pada tabel ini.'
                  }
                  onReset={searchQuery ? () => setSearchQuery('') : null}
                />
              ) : (
                pagedRows.map((row) => (
                  <tr key={row.id || row.kodeProdi} className="hover:bg-[#f6f7fb]/60 transition-colors">
                    {tab === 'pengisian' && (
                      <>
                        <td className="px-4 py-3.5 font-mono text-[11px] text-[#6c757d]">{row.nim}</td>
                        <td className="px-4 py-3.5 font-semibold text-[#313a46]">{row.nama}</td>
                        <td className="px-4 py-3.5 text-[#6c757d]">{row.namaProdi}</td>
                        <td className="px-4 py-3.5 text-[#6c757d]">
                          <span className="block">{row.hp || '-'}</span>
                          <span className="text-[11px]">{row.email || '-'}</span>
                        </td>
                        <td className="px-4 py-3.5">
                          <Badge variant={row.statusSurvey ? 'success' : 'warning'} size="sm">
                            {row.statusSurvey || 'BELUM'}
                          </Badge>
                        </td>
                        <td className="px-4 py-3.5 font-tabular text-[#6c757d]">
                          {row.reward.rwd100k || row.reward.rwd50k || row.reward.rwd20k
                            ? [
                                row.reward.rwd100k ? '100K' : null,
                                row.reward.rwd50k ? '50K' : null,
                                row.reward.rwd20k ? '20K' : null,
                              ]
                                .filter(Boolean)
                                .join(' + ')
                            : '-'}
                        </td>
                      </>
                    )}

                    {tab === 'rekap' && (
                      <>
                        <td className="px-4 py-3.5">
                          <p className="font-semibold text-[#313a46]">{row.namaProdi}</p>
                          <span className="font-mono text-[11px] text-[#6c757d]">{row.kodeProdi}</span>
                        </td>
                        <td className="px-4 py-3.5 text-[#6c757d]">{row.namaFakultas || '-'}</td>
                        <td className="px-4 py-3.5 font-tabular font-bold text-[#5b69bc]">
                          {row.jumlahPengisi}
                        </td>
                        <td className="px-4 py-3.5 font-tabular text-[#6c757d]">{row.totalMahasiswa}</td>
                        <td className="px-4 py-3.5">
                          <div className="flex items-center gap-3">
                            <div className="flex-1 h-2 bg-[#e7e9eb] rounded-full overflow-hidden">
                              <div
                                className="h-full bg-[#5b69bc] rounded-full transition-all duration-500"
                                style={{ width: `${Math.min(row.persen, 100)}%` }}
                              />
                            </div>
                            <span className="font-mono text-xs font-bold text-[#313a46] w-14 text-right">
                              {row.persen}%
                            </span>
                          </div>
                        </td>
                      </>
                    )}

                    {tab === 'belum' && (
                      <>
                        <td className="px-4 py-3.5 font-mono text-[11px] text-[#6c757d]">{row.nim}</td>
                        <td className="px-4 py-3.5 font-semibold text-[#313a46]">{row.nama}</td>
                        <td className="px-4 py-3.5 text-[#6c757d]">{row.namaProdi}</td>
                        <td className="px-4 py-3.5">
                          <Badge variant="neutral" size="sm">
                            {row.status || '-'}
                          </Badge>
                        </td>
                      </>
                    )}

                    {['dosen', 'mahasiswa', 'tendik'].includes(tab) && (
                      <>
                        <td className="px-4 py-3.5 font-mono text-[11px] text-[#6c757d]">{row.identitas}</td>
                        <td className="px-4 py-3.5 font-semibold text-[#313a46]">{row.nama}</td>
                        <td className="px-4 py-3.5 text-[#6c757d]">{row.programStudi || '-'}</td>
                        <td className="px-4 py-3.5 text-[#6c757d]">{row.semester || '-'}</td>
                        <td className="px-4 py-3.5 text-[#6c757d] max-w-xs">{row.pertanyaan}</td>
                        <td className="px-4 py-3.5 text-[#313a46] font-medium max-w-xs">{row.jawaban}</td>
                      </>
                    )}
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
