import React, { useState, useEffect } from 'react'
import { ArrowLeft, CheckCircle2, XCircle, FileText, Loader2 } from 'lucide-react'
import { Link } from '@/lib/router'
import { reviewerApi } from '../../services/api/reviewerApi'
import { Card, CardHeader } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Modal } from '../../components/ui/Modal'
import { formatRupiah } from '../../utils/formatters'

export default function VerifikasiRevisiPage() {
  const [proposals, setProposals] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [notice, setNotice] = useState('')

  const [selected, setSelected] = useState(null)
  const [decision, setDecision] = useState(null)
  const [catatan, setCatatan] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errorNotice, setErrorNotice] = useState('')

  const [threads, setThreads] = useState({})

  // Naskah awal & revisi per usulan - dibuka saat diklik (bukan
  // sekaligus dimuat semua), supaya daftar panjang tetap ringan.
  const [docBusy, setDocBusy] = useState('')
  const [docError, setDocError] = useState('')

  const lihatNaskah = async (proposalId, versi) => {
    setDocError('')
    setDocBusy(`${proposalId}-${versi}`)
    try {
      const url = await (versi === 'awal'
        ? reviewerApi.ambilDokumenProposal(proposalId)
        : reviewerApi.ambilDokumenProposalRevisi(proposalId))
      window.open(url, '_blank', 'noopener')
    } catch (e) {
      setDocError(e.message)
    } finally {
      setDocBusy('')
    }
  }

  const loadData = async () => {
    setIsLoading(true)
    try {
      const res = await reviewerApi.getRevisiVerifikasiQueue()
      setProposals(res.data)
      // Utas komentar revisi + balasan peneliti tiap usulan.
      const entries = await Promise.all(
        res.data.map(async (p) => [p.id, (await reviewerApi.getKomentarRevisi(p.id).catch(() => ({ data: [] }))).data])
      )
      setThreads(Object.fromEntries(entries))
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [])

  const openDecisionModal = (proposal, decisionValue) => {
    setSelected(proposal)
    setDecision(decisionValue)
    setCatatan('')
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    if (!selected || !decision || !catatan.trim()) return
    setIsSubmitting(true)
    setErrorNotice('')
    try {
      await reviewerApi.verifikasiRevisi(selected.id, { status: decision, catatan })
      setSelected(null)
      setNotice(
        decision === 'DISETUJUI'
          ? `Revisi ${selected.kodeUsulan} disetujui.`
          : `Revisi ${selected.kodeUsulan} dikembalikan untuk diperbaiki lagi.`
      )
      setTimeout(() => setNotice(''), 4000)
      await loadData()
    } catch (err) {
      setErrorNotice(err?.message || 'Gagal menyimpan verifikasi. Silakan coba lagi.')
    } finally {
      setIsSubmitting(false)
    }
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
              Verifikasi Hasil Revisi
            </h4>
          </div>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Proposal yang sudah diajukan ulang oleh peneliti dan menunggu Anda periksa sebagai verifikator yang ditunjuk LPPM
          </p>
        </div>
      </div>

      {notice && (
        <div className="p-3.5 bg-[#10c469]/10 border border-[#10c469]/20 text-[#10c469] rounded-xl text-xs flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-[#10c469] shrink-0" />
          <span className="font-semibold text-[#0c8a49]">{notice}</span>
        </div>
      )}

      {errorNotice && (
        <div className="p-3.5 bg-[#ff5b5b]/10 border border-[#ff5b5b]/20 text-[#ff5b5b] rounded-xl text-xs flex items-center gap-2">
          <XCircle className="w-4 h-4 text-[#ff5b5b] shrink-0" />
          <span className="font-semibold text-[#ff5b5b]">{errorNotice}</span>
        </div>
      )}

      {docError && (
        <div className="p-3.5 bg-[#ff5b5b]/10 border border-[#ff5b5b]/20 text-[#ff5b5b] rounded-xl text-xs flex items-center gap-2">
          <XCircle className="w-4 h-4 text-[#ff5b5b] shrink-0" />
          <span className="font-semibold text-[#ff5b5b]">{docError}</span>
        </div>
      )}

      {!isLoading && proposals.length === 0 && (
        <Card className="p-8 text-center text-xs text-[#98a6ad]">
          Tidak ada revisi yang menunggu verifikasi Anda saat ini.
        </Card>
      )}

      <div className="space-y-4">
        {proposals.map((p) => (
          <Card key={p.id} className="p-5">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
              <div className="space-y-1.5 max-w-2xl text-xs">
                <div className="flex items-center gap-2">
                  <span className="font-mono font-bold text-[#188ae2]">{p.kodeUsulan}</span>
                  <Badge variant="primary" pill>{p.skimNama}</Badge>
                </div>
                <h5 className="text-sm font-bold font-heading text-[#313a46] leading-snug">{p.judul}</h5>
                <p className="text-[#6c757d] text-[11px]">
                  Ketua: <strong className="text-[#313a46]">{p.ketuaNama}</strong> ({p.prodiNama})
                </p>
                <p className="text-[#98a6ad] text-[11px]">
                  Usulan Anggaran: <span className="font-tabular font-bold text-[#313a46]">{formatRupiah(p.biayaUsulan)}</span>
                </p>
                <div className="flex flex-wrap gap-2">
                  <Button
                    variant="secondary"
                    size="xs"
                    iconLeft={docBusy === `${p.id}-awal` ? Loader2 : FileText}
                    disabled={docBusy === `${p.id}-awal`}
                    onClick={() => lihatNaskah(p.id, 'awal')}
                  >
                    Lihat Naskah Awal
                  </Button>
                  <Button
                    variant="secondary"
                    size="xs"
                    iconLeft={docBusy === `${p.id}-revisi` ? Loader2 : FileText}
                    disabled={docBusy === `${p.id}-revisi`}
                    onClick={() => lihatNaskah(p.id, 'revisi')}
                  >
                    Lihat Naskah Revisi
                  </Button>
                </div>
                {(threads[p.id] || []).map((k) => (
                  <div key={k.id} className="text-[11px] bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg p-2.5 mt-1 space-y-1">
                    <p className="text-[#313a46]">
                      <strong>Reviewer {k.reviewerKe}:</strong> {k.komentar}
                    </p>
                    <p className="text-[#6c757d] pl-3 border-l-2 border-[#10c469]/40">
                      <strong className="text-[#0c8a49]">Peneliti:</strong> {k.respon || <em>belum dibalas</em>}
                    </p>
                  </div>
                ))}
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <Button
                  variant="secondary"
                  size="sm"
                  iconLeft={XCircle}
                  onClick={() => openDecisionModal(p, 'DITOLAK')}
                >
                  Kembalikan
                </Button>
                <Button
                  variant="success"
                  size="sm"
                  iconLeft={CheckCircle2}
                  onClick={() => openDecisionModal(p, 'DISETUJUI')}
                >
                  Setujui
                </Button>
              </div>
            </div>
          </Card>
        ))}
      </div>

      <Modal
        isOpen={!!selected}
        onClose={() => setSelected(null)}
        title={decision === 'DISETUJUI' ? 'Setujui Hasil Revisi' : 'Kembalikan untuk Diperbaiki'}
        subtitle={selected ? `${selected.kodeUsulan} — ${selected.judul}` : ''}
        maxWidth="max-w-md"
        footer={
          <>
            <Button variant="secondary" size="sm" onClick={() => setSelected(null)} disabled={isSubmitting}>
              Batal
            </Button>
            <Button
              variant={decision === 'DISETUJUI' ? 'success' : 'danger'}
              size="sm"
              onClick={handleSubmit}
              isLoading={isSubmitting}
              disabled={!catatan.trim()}
            >
              {decision === 'DISETUJUI' ? 'Setujui' : 'Kembalikan'}
            </Button>
          </>
        }
      >
        {selected && (
          <form onSubmit={handleSubmit} className="space-y-3 text-xs">
            <label className="block font-semibold text-slate-700 mb-1">
              Catatan (wajib diisi):
            </label>
            <textarea
              rows={4}
              value={catatan}
              onChange={(e) => setCatatan(e.target.value)}
              placeholder="Jelaskan alasan keputusan Anda..."
              className="w-full p-2.5 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500"
            />
          </form>
        )}
      </Modal>
    </div>
  )
}
