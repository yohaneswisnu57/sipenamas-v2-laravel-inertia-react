import React, { useState, useEffect } from 'react'
import { dekanApi } from '../../services/api/dekanApi'
import { masterDataApi } from '../../services/api/masterDataApi'
import { formatRupiah } from '../../utils/formatters'
import { Card, CardHeader } from '../../components/ui/Card'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

/**
 * Padanan legacy dkn/myphp/anggaranpenelitian.php: pagu disimpan per PRODI
 * (`prodi_anggaran`), dan sisa pagu = alokasi - proposal disetujui. Dana
 * LPPM ditampilkan terpisah karena tidak mengurangi pagu prodi. Menu legacy
 * read-only (editData() dikomentari), jadi halaman ini tanpa aksi tulis.
 */
export default function PaguAnggaranPage() {
  const [pagu, setPagu] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [periodeList, setPeriodeList] = useState([])
  const [kdperiode, setKdperiode] = useState('')

  useEffect(() => {
    masterDataApi.getPeriodeList().then((res) => setPeriodeList(res.data))
  }, [])

  useEffect(() => {
    async function load() {
      setIsLoading(true)
      try {
        const res = await dekanApi.getPaguAnggaran({ kdperiode: kdperiode || undefined })
        setPagu(res.data)
      } finally {
        setIsLoading(false)
      }
    }
    load()
  }, [kdperiode])

  const prodiList = pagu?.prodi || []

  const filteredProdis = prodiList.filter((prodi) => {
    if (!searchQuery.trim()) return true
    const q = searchQuery.toLowerCase()
    return (
      (prodi.namaProdi && prodi.namaProdi.toLowerCase().includes(q)) ||
      (prodi.kodeProdi && prodi.kodeProdi.toLowerCase().includes(q))
    )
  })

  return (
    <div className="space-y-6 text-left">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Alokasi & Serapan Pagu Anggaran per Program Studi
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Pagu penelitian disimpan per program studi dan periode; dana LPPM dicatat terpisah dari pagu prodi
          </p>
        </div>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <Card className="p-5">
          <p className="text-xs font-semibold text-[#98a6ad] uppercase tracking-wider">Total Alokasi Pagu</p>
          <p className="text-2xl font-bold font-heading text-[#313a46] font-tabular mt-1.5">
            {formatRupiah(pagu?.totalAlokasi)}
          </p>
        </Card>
        <Card className="p-5">
          <p className="text-xs font-semibold text-[#98a6ad] uppercase tracking-wider">Proposal Disetujui</p>
          <p className="text-2xl font-bold font-heading text-[#5b69bc] font-tabular mt-1.5">
            {formatRupiah(pagu?.totalDisetujui)}
          </p>
        </Card>
        <Card className="p-5">
          <p className="text-xs font-semibold text-[#98a6ad] uppercase tracking-wider">Total Pengajuan</p>
          <p className="text-2xl font-bold font-heading text-[#6c757d] font-tabular mt-1.5">
            {formatRupiah(pagu?.totalPengajuan)}
          </p>
        </Card>
        <Card className="p-5">
          <p className="text-xs font-semibold text-[#98a6ad] uppercase tracking-wider">Sisa Pagu Tersedia</p>
          <p className="text-2xl font-bold font-heading text-[#10c469] font-tabular mt-1.5">
            {formatRupiah(pagu?.totalSisa)}
          </p>
        </Card>
      </div>

      <TableFilterBar
        searchValue={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari kode atau nama program studi..."
        filters={[
          {
            key: 'periode',
            label: 'Periode',
            value: kdperiode,
            onChange: setKdperiode,
            options: [
              { value: '', label: 'Periode Aktif' },
              ...periodeList.map((p) => ({ value: p.kodeperiode, label: String(p.tahun) })),
            ],
          },
        ]}
        onReset={() => {
          setSearchQuery('')
          setKdperiode('')
        }}
        totalCount={prodiList.length}
        filteredCount={filteredProdis.length}
      />

      <Card>
        <CardHeader
          title="Distribusi Pagu per Program Studi"
          subtitle="Sisa pagu dihitung dari alokasi dikurangi proposal yang disetujui, sama seperti grid legacy"
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-4 py-3">Program Studi</th>
                <th className="px-3 py-3 text-right">Alokasi Pagu</th>
                <th className="px-3 py-3 text-right">Pengajuan</th>
                <th className="px-3 py-3 text-right">Disetujui</th>
                <th className="px-3 py-3 text-right">Dana LPPM</th>
                <th className="px-3 py-3 text-right">Sisa Pagu</th>
                <th className="px-4 py-3">Serapan</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton cols={7} rows={4} />
              ) : filteredProdis.length === 0 ? (
                <TableEmptyState
                  colSpan={7}
                  message={
                    searchQuery
                      ? 'Tidak ada program studi yang cocok dengan pencarian.'
                      : 'Belum ada program studi di fakultas ini.'
                  }
                  onReset={searchQuery ? () => setSearchQuery('') : null}
                />
              ) : (
                filteredProdis.map((prodi) => {
                  const pct =
                    prodi.alokasiAnggaran > 0
                      ? Math.round((prodi.proposalDisetujui / prodi.alokasiAnggaran) * 100)
                      : 0
                  return (
                    <tr key={prodi.kodeProdi} className="hover:bg-[#f6f7fb]/60 transition-colors">
                      <td className="px-4 py-3.5">
                        <p className="font-semibold text-[#313a46]">{prodi.namaProdi}</p>
                        <span className="font-mono text-[11px] text-[#6c757d]">{prodi.kodeProdi}</span>
                      </td>
                      <td className="px-3 py-3.5 text-right font-tabular font-medium text-[#6c757d]">
                        {formatRupiah(prodi.alokasiAnggaran)}
                      </td>
                      <td className="px-3 py-3.5 text-right font-tabular text-[#6c757d]">
                        {formatRupiah(prodi.proposalPengajuan)}
                      </td>
                      <td className="px-3 py-3.5 text-right font-tabular font-bold text-[#5b69bc]">
                        {formatRupiah(prodi.proposalDisetujui)}
                      </td>
                      <td className="px-3 py-3.5 text-right font-tabular text-[#ff5b5b] italic">
                        {formatRupiah(prodi.danaLppm)}
                      </td>
                      <td className="px-3 py-3.5 text-right font-tabular font-bold text-[#10c469]">
                        {formatRupiah(prodi.sisaAnggaran)}
                      </td>
                      <td className="px-4 py-3.5">
                        <div className="flex items-center gap-3">
                          <div className="flex-1 h-2 bg-[#e7e9eb] rounded-full overflow-hidden">
                            <div
                              className="h-full bg-[#5b69bc] rounded-full transition-all duration-500"
                              style={{ width: `${Math.min(pct, 100)}%` }}
                            />
                          </div>
                          <span className="font-mono text-xs font-bold text-[#313a46] w-10 text-right">
                            {pct}%
                          </span>
                        </div>
                      </td>
                    </tr>
                  )
                })
              )}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  )
}
