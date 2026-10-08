import React, { useState } from 'react'
import { CheckCircle2, AlertCircle, ArrowLeft, Users2 } from 'lucide-react'
import { Link } from '@/lib/router'
import { penelitiApi } from '../../services/api/penelitiApi'
import { Card } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'

/**
 * Padanan pen/myphp/statuskesediaantim.php legacy: anggota dosen
 * menyetujui keanggotaan di usulan orang lain. Legacy tidak punya aksi tolak.
 */
export default function KesediaanTimPage({ items = [] }) {
  const isLoading = false
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [processingId, setProcessingId] = useState(null)

  const handleSetuju = async (timId) => {
    setProcessingId(timId)
    setError('')
    try {
      await penelitiApi.setujuiKesediaanTim(timId)
      setNotice('Keanggotaan penelitian disetujui.')
      setTimeout(() => setNotice(''), 4000)
    } catch (e) {
      setError(Object.values(e.errors || {}).flat()[0] || e.message)
    } finally {
      setProcessingId(null)
    }
  }

  return (
    <div className="space-y-6 text-left">
      <div className="pb-5 border-b border-[#e7e9eb]">
        <div className="flex items-center gap-2">
          <Link to="/pen/dashboard" className="text-[#98a6ad] hover:text-[#313a46]">
            <ArrowLeft className="w-4 h-4" />
          </Link>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">Persetujuan Anggota Tim</h4>
        </div>
        <p className="text-xs text-[#98a6ad] mt-0.5">
          Usulan penelitian yang mencantumkan Anda sebagai anggota. Usulan baru bisa diteruskan ke Dekan setelah semua anggota menyetujui.
        </p>
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
          <Users2 className="w-8 h-8 mx-auto mb-2" />
          Tidak ada permintaan persetujuan keanggotaan.
        </Card>
      ) : (
        <div className="space-y-4">
          {items.map((item) => (
            <Card key={item.timId} className="p-5">
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div className="space-y-1.5 max-w-2xl text-xs">
                  {item.skimNama && <Badge variant="primary" pill>{item.skimNama}</Badge>}
                  <h5 className="text-sm font-bold font-heading text-[#313a46] leading-snug">{item.judul}</h5>
                  <p className="text-[#6c757d] text-[11px]">
                    Ketua: <strong className="text-[#313a46]">{item.ketuaNama}</strong>
                    {item.tugas && <> &bull; Tugas Anda: {item.tugas}</>}
                  </p>
                </div>
                <Button
                  variant="success"
                  size="sm"
                  iconLeft={CheckCircle2}
                  isLoading={processingId === item.timId}
                  onClick={() => handleSetuju(item.timId)}
                >
                  Setujui Keanggotaan
                </Button>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
