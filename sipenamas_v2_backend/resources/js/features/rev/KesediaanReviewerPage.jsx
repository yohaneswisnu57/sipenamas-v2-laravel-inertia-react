import React, { useState, useEffect } from 'react'
import { CheckSquare, CheckCircle2, XCircle, AlertCircle, ArrowLeft } from 'lucide-react'
import { Link } from '@/lib/router'
import { reviewerApi } from '../../services/api/reviewerApi'
import { Card, CardHeader } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { formatRupiah } from '../../utils/formatters'

export default function KesediaanReviewerPage() {
  const [tasks, setTasks] = useState([])
  const [notice, setNotice] = useState('')

  const loadData = async () => {
    const res = await reviewerApi.getPenugasanList()
    setTasks(res.data)
  }

  useEffect(() => {
    loadData()
  }, [])

  const [error, setError] = useState('')

  const handleConfirm = async (proposalId, bersedia) => {
    setError('')
    try {
      await reviewerApi.confirmKesediaan(proposalId, { bersedia })
      setNotice(bersedia ? 'Konfirmasi kesediaan menilai berhasil dicatat.' : 'Penolakan tugas telah dilaporkan ke LPPM.')
      setTimeout(() => setNotice(''), 4000)
    } catch (e) {
      setError(e.message)
    }
    await loadData()
  }

  return (
    <div className="space-y-6 text-left">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <div className="flex items-center gap-2">
            <Link to="/rev/dashboard" className="text-[#98a6ad] hover:text-[#313a46]">
              <ArrowLeft className="w-4 h-4" />
            </Link>
            <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
              Konfirmasi Kesediaan Penugasan Reviewer
            </h4>
          </div>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Deklarasikan kesediaan menilai atau tolak tugas jika terdapat potensi benturan kepentingan (Conflict of Interest)
          </p>
        </div>
      </div>

      {notice && (
        <div className="p-3.5 bg-[#10c469]/10 border border-[#10c469]/20 text-[#10c469] rounded-xl text-xs flex items-center gap-2">
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

      <div className="space-y-4">
        {tasks.map((t) => (
          <Card key={t.id} className="p-5">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div className="space-y-1.5 max-w-2xl text-xs">
                <div className="flex items-center gap-2">
                  <span className="font-mono font-bold text-[#188ae2]">{t.kodeUsulan}</span>
                  <Badge variant="primary" pill>{t.skimNama}</Badge>
                </div>
                <h5 className="text-sm font-bold font-heading text-[#313a46] leading-snug">{t.judul}</h5>
                <p className="text-[#6c757d] text-[11px]">
                  Ketua: <strong className="text-[#313a46]">{t.ketuaNama}</strong> ({t.prodiNama} &bull; {t.fakultasNama})
                </p>
                <p className="text-[#98a6ad] text-[11px]">
                  Fokus Bidang: {t.bidangFokus} &bull; Usulan Anggaran: <span className="font-tabular font-bold text-[#313a46]">{formatRupiah(t.biayaUsulan)}</span>
                </p>
              </div>

              {t.kesediaanSaya && t.kesediaanSaya !== 'MENUNGGU' ? (
                <div className="shrink-0 text-right text-xs space-y-1">
                  <Badge variant={t.kesediaanSaya === 'BERSEDIA' ? 'success' : 'danger'} pill>
                    {t.kesediaanSaya === 'BERSEDIA' ? 'Bersedia menilai' : 'Menolak tugas'}
                  </Badge>
                  {t.tglKesediaanSaya && <p className="text-[#98a6ad]">Dikonfirmasi {t.tglKesediaanSaya}</p>}
                </div>
              ) : (
                <div className="flex items-center gap-2 shrink-0">
                  <Button
                    variant="secondary"
                    size="sm"
                    iconLeft={XCircle}
                    onClick={() => handleConfirm(t.id, false)}
                  >
                    Tolak Tugas
                  </Button>
                  <Button
                    variant="success"
                    size="sm"
                    iconLeft={CheckCircle2}
                    onClick={() => handleConfirm(t.id, true)}
                  >
                    Bersedia Menilai
                  </Button>
                </div>
              )}
            </div>
          </Card>
        ))}
      </div>
    </div>
  )
}
