import React, { useState } from 'react'
import { FileSpreadsheet, Download, CheckCircle2, Table } from 'lucide-react'
import { akreditasiApi } from '../../services/api/akreditasiApi'
import { Card, CardHeader, CardContent } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'

export default function ExportLkpsPage() {
  const [isExporting, setIsExporting] = useState(false)
  const [notice, setNotice] = useState('')

  const handleExport = async (tableName) => {
    setIsExporting(true)
    try {
      const res = await akreditasiApi.generateLkpsExcel({ table: tableName })
      setNotice(`${tableName} berhasil di-generate dan diunduh ke format Excel!`)
      setTimeout(() => setNotice(''), 4000)
    } finally {
      setIsExporting(false)
    }
  }

  return (
    <div className="space-y-6 text-left max-w-4xl mx-auto">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Generator Tabel LKPS Format Excel (LAM & BAN-PT)
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Ekspor otomatis format tabel standar Laporan Kinerja Program Studi siap unggah ke sistem akreditasi
          </p>
        </div>
      </div>

      {notice && (
        <div className="p-3.5 bg-[#10c469]/10 border border-[#10c469]/20 text-[#10c469] rounded-xl text-xs flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-[#10c469] shrink-0" />
          <span className="font-semibold text-[#0c8a49]">{notice}</span>
        </div>
      )}

      <div className="space-y-4">
        {/* Table 3.b.1 */}
        <Card className="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="space-y-1">
            <span className="text-xs font-bold text-[#188ae2] bg-[#188ae2]/10 px-2 py-0.5 rounded border border-[#188ae2]/20">
              Tabel 3.b.1 LKPS
            </span>
            <h5 className="text-sm font-bold font-heading text-[#313a46] mt-1">
              Penelitian Dosen Tetap Perguruan Tinggi (DTPS)
            </h5>
            <p className="text-xs text-[#6c757d] leading-relaxed">
              Memuat data judul penelitian, sumber pembiayaan (Internal PT, Mandiri, Nasional Dikti, Internasional), dan tahun pelaksanaan TS-2 s/d TS.
            </p>
          </div>
          <Button
            variant="primary"
            size="sm"
            iconLeft={Download}
            className="shrink-0"
            onClick={() => handleExport('Tabel_3b1_Penelitian_DTPS.xlsx')}
            isLoading={isExporting}
          >
            Unduh Excel
          </Button>
        </Card>

        {/* Table 3.b.2 */}
        <Card className="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="space-y-1">
            <span className="text-xs font-bold text-[#188ae2] bg-[#188ae2]/10 px-2 py-0.5 rounded border border-[#188ae2]/20">
              Tabel 3.b.2 LKPS
            </span>
            <h5 className="text-sm font-bold font-heading text-[#313a46] mt-1">
              Penelitian DTPS yang Melibatkan Mahasiswa
            </h5>
            <p className="text-xs text-[#6c757d] leading-relaxed">
              Memetakan topik riset dosen yang terintegrasi dengan skripsi/tugas akhir dan program MBKM asisten peneliti.
            </p>
          </div>
          <Button
            variant="secondary"
            size="sm"
            iconLeft={Download}
            className="shrink-0"
            onClick={() => handleExport('Tabel_3b2_Riset_Mahasiswa.xlsx')}
            isLoading={isExporting}
          >
            Unduh Excel
          </Button>
        </Card>

        {/* Table 3.b.4 */}
        <Card className="p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div className="space-y-1">
            <span className="text-xs font-bold text-[#188ae2] bg-[#188ae2]/10 px-2 py-0.5 rounded border border-[#188ae2]/20">
              Tabel 3.b.4 LKPS
            </span>
            <h5 className="text-sm font-bold font-heading text-[#313a46] mt-1">
              Publikasi Ilmiah DTPS & Mahasiswa
            </h5>
            <p className="text-xs text-[#6c757d] leading-relaxed">
              Rekapitulasi artikel pada jurnal nasional tidak terakreditasi, SINTA 1-6, dan jurnal internasional bereputasi Scopus/WoS.
            </p>
          </div>
          <Button
            variant="secondary"
            size="sm"
            iconLeft={Download}
            className="shrink-0"
            onClick={() => handleExport('Tabel_3b4_Publikasi_Ilmiah.xlsx')}
            isLoading={isExporting}
          >
            Unduh Excel
          </Button>
        </Card>
      </div>
    </div>
  )
}
