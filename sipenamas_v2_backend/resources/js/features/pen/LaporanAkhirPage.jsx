import React, { useState } from 'react'
import { AlertCircle, ArrowLeft, ClipboardList, FileCheck2, Target } from 'lucide-react'
import { Link } from '@/lib/router'
import { muatJendela } from '../../lib/inertiaRequest'
import { Card } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import KuesionerPenelitianModal from './KuesionerPenelitianModal'
import KelengkapanLaporanModal from './KelengkapanLaporanModal'
import CapaianLuaranModal from './CapaianLuaranModal'

/**
 * Padanan panel "hasilpenelitian" legacy: daftar penelitian LOLOS milik tim
 * per periode, dengan tiga aksi berurutan - KUESIONER, KELENGKAPAN LAPORAN
 * (ketua, setelah kuesioner lengkap), CAPAIAN DAN LUARAN (ketua, setelah
 * lembar pengesahan final).
 *
 * Isi jendela adalah props halaman (`kuesioner`, `kelengkapan`, `capaian`)
 * yang dimuat lewat query string, jadi sesudah aksi isinya ikut segar.
 */
const JENDELA_KOSONG = { kuesioner: null, kelengkapan: null, capaian: null }

export default function LaporanAkhirPage({ periodeList = [], laporan: data, kuesioner, kelengkapan, capaian }) {
  const kdperiode = data?.kdperiode || ''
  const isLoading = false
  const [error, setError] = useState('')
  const [modal, setModal] = useState(null)

  const bukaJendela = (jenis, nilai) => {
    setModal({ jenis, id: nilai })
    muatJendela({ ...JENDELA_KOSONG, [jenis]: nilai }, [jenis])
  }

  const closeModal = () => {
    setModal(null)
    muatJendela(JENDELA_KOSONG)
  }

  const gantiPeriode = (kode) => {
    setError('')
    muatJendela({ ...JENDELA_KOSONG, kdperiode: kode })
  }

  const bukaKelengkapan = (item) => {
    if (!data.isKuesionerSelesai) {
      setError('Silahkan melengkapi kuesioner terlebih dulu.')
    } else if (item.peran !== 'KETUA') {
      setError('Maaf, fungsi ini hanya untuk KETUA.')
    } else {
      setError('')
      bukaJendela('kelengkapan', item.id)
    }
  }

  const bukaCapaian = (item) => {
    if (item.peran !== 'KETUA') {
      setError('Maaf, fungsi ini hanya untuk KETUA.')
    } else if (!item.isLembarPengesahanFinal) {
      setError('Maaf, Laporan belum lengkap/Final.')
    } else {
      setError('')
      bukaJendela('capaian', item.id)
    }
  }

  return (
    <div className="space-y-6 text-left">
      <div className="pb-5 border-b border-[#e7e9eb] flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <Link to="/pen/dashboard" className="text-[#98a6ad] hover:text-[#313a46]">
              <ArrowLeft className="w-4 h-4" />
            </Link>
            <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">Laporan Akhir Penelitian</h4>
          </div>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Isi kuesioner, lengkapi laporan akhir, lalu unggah capaian dan luaran penelitian yang lolos.
          </p>
        </div>
        <div className="flex items-end gap-2 text-xs">
          <div>
            <label htmlFor="periode-laporan" className="block font-semibold text-slate-700 mb-1">Periode</label>
            <select
              id="periode-laporan"
              value={kdperiode}
              onChange={(e) => gantiPeriode(e.target.value)}
              className="p-2 bg-white border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-base sm:text-xs"
            >
              {periodeList.map((p) => (
                <option key={p.kodeperiode} value={p.kodeperiode}>{p.tahun}</option>
              ))}
            </select>
          </div>
          <Button variant="soft-primary" size="sm" iconLeft={ClipboardList} disabled={!kdperiode} onClick={() => bukaJendela('kuesioner', 1)}>
            Kuesioner
          </Button>
        </div>
      </div>

      {error && (
        <div className="p-3.5 bg-[#ff5b5b]/10 border border-[#ff5b5b]/20 rounded-xl text-xs flex items-center gap-2">
          <AlertCircle className="w-4 h-4 text-[#ff5b5b] shrink-0" />
          <span className="font-semibold text-[#ff5b5b]">{error}</span>
        </div>
      )}

      {data && (
        <p className="text-xs text-[#6c757d]">
          Kuesioner periode ini:{' '}
          <Badge variant={data.isKuesionerSelesai ? 'success' : 'warning'} pill>
            {data.isKuesionerSelesai ? 'Lengkap' : 'Belum Lengkap'}
          </Badge>
        </p>
      )}

      {isLoading ? (
        <p className="py-12 text-center text-xs text-slate-500">Memuat data...</p>
      ) : !data || data.items.length === 0 ? (
        <Card className="p-8 text-center text-xs text-[#98a6ad]">
          <FileCheck2 className="w-8 h-8 mx-auto mb-2" />
          Tidak ada penelitian lolos pada periode ini.
        </Card>
      ) : (
        <div className="space-y-4">
          {data.items.map((item) => (
            <Card key={item.id} className="p-5">
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div className="space-y-1.5 max-w-2xl text-xs">
                  <div className="flex flex-wrap items-center gap-1.5">
                    {item.skim && <Badge variant="primary" pill>{item.skim}</Badge>}
                    <Badge variant="neutral" pill>{item.peran}</Badge>
                    {item.isLembarPengesahanFinal && <Badge variant="success" pill>Lembar Pengesahan Final</Badge>}
                    {item.isDisetujuiDekan && <Badge variant="primary" pill>Disetujui Dekan</Badge>}
                    {item.statusKetuntasan && item.statusKetuntasan !== '-' && (
                      <Badge variant="dark" pill>{item.statusKetuntasan}</Badge>
                    )}
                  </div>
                  <h5 className="text-sm font-bold font-heading text-[#313a46] leading-snug">{item.judul}</h5>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button variant="soft-primary" size="sm" iconLeft={FileCheck2} onClick={() => bukaKelengkapan(item)}>
                    Kelengkapan Laporan
                  </Button>
                  <Button variant="soft-primary" size="sm" iconLeft={Target} onClick={() => bukaCapaian(item)}>
                    Capaian dan Luaran
                  </Button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}

      {modal?.jenis === 'kuesioner' && <KuesionerPenelitianModal kdperiode={kdperiode} data={kuesioner} onClose={closeModal} />}
      {modal?.jenis === 'kelengkapan' && (
        <KelengkapanLaporanModal penelitianId={modal.id} data={kelengkapan?.id === modal.id || kelengkapan?.error ? kelengkapan : null} onClose={closeModal} />
      )}
      {modal?.jenis === 'capaian' && (
        <CapaianLuaranModal penelitianId={modal.id} data={capaian?.id === modal.id || capaian?.error ? capaian : null} onClose={closeModal} />
      )}
    </div>
  )
}
