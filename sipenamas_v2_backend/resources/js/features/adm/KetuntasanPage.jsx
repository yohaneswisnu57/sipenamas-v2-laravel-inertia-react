import React, { useState, useEffect } from 'react'
import { AlertCircle, CheckCircle2, FileCheck2 } from 'lucide-react'
import { adminApi } from '../../services/api/adminApi'
import { masterDataApi } from '../../services/api/masterDataApi'
import { Card } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'

const PILIHAN_STATUS = ['-', 'TUNTAS', 'BELUM TUNTAS', 'TUNTAS BERSYARAT', 'BATAL']

/**
 * Padanan panel "hasilpenelitian" admin legacy: LPPM melihat capaian
 * luaran tiap penelitian dan menetapkan status ketuntasannya.
 */
export default function KetuntasanPage() {
  const [periodeList, setPeriodeList] = useState([])
  const [kdperiode, setKdperiode] = useState('')
  const [items, setItems] = useState([])
  const [pilihan, setPilihan] = useState({})
  const [isLoading, setIsLoading] = useState(true)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [busyId, setBusyId] = useState(null)

  const loadData = async (kode) => {
    setIsLoading(true)
    try {
      const res = await adminApi.getKetuntasanList(kode)
      setItems(res.data.items)
      setKdperiode(res.data.kdperiode)
      setPilihan(Object.fromEntries(res.data.items.map((i) => [i.id, i.statusKetuntasan])))
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

  const simpan = async (id) => {
    setBusyId(id)
    setError('')
    try {
      const res = await adminApi.updateKetuntasan(id, pilihan[id])
      setNotice(res.message || 'Status sudah diupdate.')
      setTimeout(() => setNotice(''), 4000)
      await loadData(kdperiode)
    } catch (e) {
      setError(Object.values(e.errors || {}).flat()[0] || e.message)
    } finally {
      setBusyId(null)
    }
  }

  return (
    <div className="space-y-6 text-left">
      <div className="pb-5 border-b border-[#e7e9eb] flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">Status Ketuntasan Penelitian</h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Tetapkan ketuntasan setelah memeriksa capaian luaran dan persetujuan Dekan atas laporan akhir.
          </p>
        </div>
        <div className="text-xs">
          <label htmlFor="periode-ketuntasan" className="block font-semibold text-slate-700 mb-1">Periode</label>
          <select
            id="periode-ketuntasan"
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
          Tidak ada penelitian pada periode ini.
        </Card>
      ) : (
        <div className="space-y-4">
          {items.map((item) => (
            <Card key={item.id} className="p-5">
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div className="space-y-1.5 max-w-2xl text-xs">
                  <div className="flex flex-wrap items-center gap-1.5">
                    {item.skim && <Badge variant="primary" pill>{item.skim}</Badge>}
                    <Badge variant={item.statusFinalApproval === 'LOLOS' ? 'success' : 'neutral'} pill>
                      {item.statusFinalApproval === 'LOLOS' ? 'Lolos' : 'Belum diputuskan'}
                    </Badge>
                    {item.isDisetujuiDekan && <Badge variant="info" pill>Laporan disetujui Dekan</Badge>}
                  </div>
                  <h5 className="text-sm font-bold font-heading text-[#313a46] leading-snug">{item.judul}</h5>
                  <p className="text-[#6c757d] text-[11px]">
                    Ketua: <strong className="text-[#313a46]">{item.ketua || '-'}</strong>
                    {item.prodi && <> ({item.prodi})</>} &bull; Capaian luaran:{' '}
                    <strong className="text-[#313a46]">{item.jumlahRealisasi}/{item.jumlahTarget}</strong>
                  </p>
                </div>

                <div className="flex items-center gap-2 shrink-0 text-xs">
                  <select
                    aria-label={`Status ketuntasan ${item.judul}`}
                    value={pilihan[item.id] ?? '-'}
                    onChange={(e) => setPilihan((prev) => ({ ...prev, [item.id]: e.target.value }))}
                    className="p-2 bg-white border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-base sm:text-xs"
                  >
                    {PILIHAN_STATUS.map((s) => (
                      <option key={s} value={s}>{s}</option>
                    ))}
                  </select>
                  <Button
                    variant="primary"
                    size="sm"
                    isLoading={busyId === item.id}
                    disabled={pilihan[item.id] === item.statusKetuntasan}
                    onClick={() => simpan(item.id)}
                  >
                    Update Status
                  </Button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
