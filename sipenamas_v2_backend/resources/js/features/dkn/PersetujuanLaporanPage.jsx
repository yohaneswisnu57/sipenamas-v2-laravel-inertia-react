import React, { useState, useEffect } from 'react'
import { AlertCircle, CheckCircle2, FileCheck2 } from 'lucide-react'
import { dekanApi } from '../../services/api/dekanApi'
import { masterDataApi } from '../../services/api/masterDataApi'
import { Card } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'

/**
 * Padanan panel "approvallaporan" legacy: Dekan menyetujui laporan akhir
 * penelitian lolos di fakultasnya. Persetujuan baru bisa diberikan setelah
 * ketua men-set final lembar pengesahan laporan akhir, dan tidak bisa
 * dibatalkan.
 */
export default function PersetujuanLaporanPage() {
  const [periodeList, setPeriodeList] = useState([])
  const [kdperiode, setKdperiode] = useState('')
  const [items, setItems] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [konfirmasiId, setKonfirmasiId] = useState(null)
  const [busyId, setBusyId] = useState(null)

  const loadData = async (kode) => {
    setIsLoading(true)
    try {
      const res = await dekanApi.getLaporanAkhirList(kode)
      setItems(res.data.items)
      setKdperiode(res.data.kdperiode)
    } catch (e) {
      setError(e.message)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    masterDataApi.getPeriodeList().then((res) => setPeriodeList(res.data))
    loadData()
  }, [])

  const setujui = async (id) => {
    setBusyId(id)
    setError('')
    try {
      await dekanApi.approveLaporanAkhir(id)
      setNotice('Laporan akhir disetujui dan ditandai di lembar pengesahan.')
      setTimeout(() => setNotice(''), 4000)
      await loadData(kdperiode)
    } catch (e) {
      setError(e.message)
    } finally {
      setBusyId(null)
      setKonfirmasiId(null)
    }
  }

  const pratinjau = async (id) => {
    setError('')
    try {
      await dekanApi.lihatLembarPengesahanLaporan(id)
    } catch (e) {
      setError(e.message)
    }
  }

  return (
    <div className="space-y-6 text-left">
      <div className="pb-5 border-b border-[#e7e9eb] flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">Persetujuan Laporan Akhir</h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Penelitian lolos di fakultas Anda. Laporan bisa disetujui setelah ketua men-set final lembar pengesahannya.
          </p>
        </div>
        <div className="text-xs">
          <label htmlFor="periode-persetujuan" className="block font-semibold text-slate-700 mb-1">Periode</label>
          <select
            id="periode-persetujuan"
            value={kdperiode}
            onChange={(e) => loadData(e.target.value)}
            className="p-2 bg-white border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-base sm:text-xs"
          >
            {periodeList.map((p) => (
              <option key={p.kodeperiode} value={p.kodeperiode}>{p.tahun}</option>
            ))}
          </select>
        </div>
      </div>

      {notice && (
        <div className="p-3.5 bg-[#10c469]/10 border border-[#10c469]/20 rounded-xl text-xs flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-[#10c469] shrink-0" />
          <span className="font-semibold text-[#0c8a49]">{notice}</span>
        </div>
      )}
      {error && (
        <div className="p-3.5 bg-[#ff5b5b]/10 border border-[#ff5b5b]/20 rounded-xl text-xs flex items-center gap-2">
          <AlertCircle className="w-4 h-4 text-[#ff5b5b] shrink-0" />
          <span className="font-semibold text-[#ff5b5b]">{error}</span>
        </div>
      )}

      {isLoading ? (
        <p className="py-12 text-center text-xs text-slate-500">Memuat data...</p>
      ) : items.length === 0 ? (
        <Card className="p-8 text-center text-xs text-[#98a6ad]">
          <FileCheck2 className="w-8 h-8 mx-auto mb-2" />
          Tidak ada penelitian lolos pada periode ini.
        </Card>
      ) : (
        <div className="space-y-4">
          {items.map((item) => (
            <Card key={item.id} className="p-5">
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div className="space-y-1.5 max-w-2xl text-xs">
                  <div className="flex flex-wrap items-center gap-1.5">
                    {item.skim && <Badge variant="primary" pill>{item.skim}</Badge>}
                    {item.isDisetujuiDekan ? (
                      <Badge variant="success" pill>Disetujui</Badge>
                    ) : item.isLembarPengesahanFinal ? (
                      <Badge variant="warning" pill>Menunggu persetujuan</Badge>
                    ) : (
                      <Badge variant="neutral" pill>Laporan belum final</Badge>
                    )}
                  </div>
                  <h5 className="text-sm font-bold font-heading text-[#313a46] leading-snug">{item.judul}</h5>
                  <p className="text-[#6c757d] text-[11px]">
                    Ketua: <strong className="text-[#313a46]">{item.ketua || '-'}</strong> &bull; Capaian luaran:{' '}
                    <strong className="text-[#313a46]">{item.jumlahRealisasi}/{item.jumlahTarget}</strong>
                  </p>
                </div>

                {item.isLembarPengesahanFinal && (
                  <div className="flex flex-wrap gap-2 shrink-0">
                    <Button variant="secondary" size="sm" onClick={() => pratinjau(item.id)}>
                      Lembar Pengesahan
                    </Button>
                    {!item.isDisetujuiDekan &&
                      (konfirmasiId === item.id ? (
                        <>
                          <Button variant="success" size="sm" isLoading={busyId === item.id} onClick={() => setujui(item.id)}>
                            Ya, Setujui
                          </Button>
                          <Button variant="secondary" size="sm" onClick={() => setKonfirmasiId(null)}>
                            Batal
                          </Button>
                        </>
                      ) : (
                        <Button variant="success" size="sm" iconLeft={CheckCircle2} onClick={() => setKonfirmasiId(item.id)}>
                          Setujui Laporan
                        </Button>
                      ))}
                  </div>
                )}
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
