import React, { useState, useEffect } from 'react'
import { useParams, useNavigate, Link } from '@/lib/router'
import {
  ArrowLeft,
  BookMarked,
  CheckCircle2,
  AlertCircle,
  Coins,
  Download,
  FileCheck2,
} from 'lucide-react'
import { reviewerApi } from '../../services/api/reviewerApi'
import { formatRupiah } from '../../utils/formatters'
import { Card, CardHeader, CardContent } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'

const SKOR_MINIMUM = 400
/** Komentar FINAL minimal 100 karakter (legacy rev/app.js). */
const MIN_KARAKTER_CATATAN = 100

const PILIHAN_SKOR = [
  { value: 1, label: '1 (Buruk)' },
  { value: 2, label: '2 (Sangat Kurang)' },
  { value: 3, label: '3 (Kurang)' },
  { value: 5, label: '5 (Cukup)' },
  { value: 6, label: '6 (Baik)' },
  { value: 7, label: '7 (Sangat Baik)' },
]

const BLOK = new Set(['P', 'DIV', 'LI', 'BR', 'TR', 'H1', 'H2', 'H3', 'H4'])

/**
 * Kriteria legacy tersimpan sebagai HTML (salinan Word). Dipecah per blok
 * menjadi baris teks biasa - HTML-nya tidak dirender.
 */
function barisKriteria(html) {
  const baris = ['']
  const jelajah = (node) => {
    if (node.nodeName === 'SCRIPT' || node.nodeName === 'STYLE') return
    if (node.nodeType === Node.TEXT_NODE) {
      baris[baris.length - 1] += node.textContent
      return
    }
    if (BLOK.has(node.nodeName)) baris.push('')
    node.childNodes.forEach(jelajah)
    if (BLOK.has(node.nodeName)) baris.push('')
  }
  jelajah(new DOMParser().parseFromString(html || '', 'text/html').body)
  return baris.map((b) => b.replace(/\s+/g, ' ').trim()).filter(Boolean)
}

function KriteriaTeks({ nomor, html }) {
  const [judul, ...rincian] = barisKriteria(html)
  return (
    <div className="space-y-1">
      <p className="font-bold text-[#313a46]">{nomor}. {judul}</p>
      {rincian.length > 0 && (
        <ul className="space-y-0.5 text-[#6c757d]">
          {rincian.map((b, i) => (
            <li key={i} className={b.startsWith('-') ? 'pl-3' : ''}>
              {b.startsWith('-') ? `• ${b.replace(/^-\s*/, '')}` : b}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}

export default function PenilaianProposalPage() {
  const { id } = useParams()
  const navigate = useNavigate()

  const [proposal, setProposal] = useState(null)
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [successNotice, setSuccessNotice] = useState('')

  // Borang per skim dari backend (legacy soalpenilaianproposal): nilai
  // per kriteria = skor x bobot%, total maksimal 700.
  const [borang, setBorang] = useState([])
  const [skor, setSkor] = useState({})
  const [submitError, setSubmitError] = useState('')

  const [catatan, setCatatan] = useState('')
  const [rekomendasiStatus, setRekomendasiStatus] = useState('REVISI') // 'LOLOS' | 'REVISI'
  const [rekomendasiDana, setRekomendasiDana] = useState('')
  // Komentar revisi per butir (legacy rev/penilaianproposalrevisi.php) -
  // peneliti membalas tiap komentar satu per satu.
  const [komentarRevisi, setKomentarRevisi] = useState([])

  const [proposalUrl, setProposalUrl] = useState('')
  const [proposalError, setProposalError] = useState('')

  // Revisi judul oleh reviewer (legacy tombol "REVISI JUDUL").
  const [editJudul, setEditJudul] = useState(false)
  const [judulBaru, setJudulBaru] = useState('')
  const [judulError, setJudulError] = useState('')
  const [isSavingJudul, setIsSavingJudul] = useState(false)
  const [panduanError, setPanduanError] = useState('')

  // Naskah proposal yang dinilai (legacy cekFeldokumenproposal) - reviewer
  // harus bisa membacanya sebelum mengisi borang.
  useEffect(() => {
    let url = ''
    let batal = false
    setProposalUrl('')
    setProposalError('')
    reviewerApi
      .ambilDokumenProposal(id)
      .then((u) => {
        url = u
        if (batal) URL.revokeObjectURL(u)
        else setProposalUrl(u)
      })
      .catch((e) => !batal && setProposalError(e.message))
    return () => {
      batal = true
      if (url) URL.revokeObjectURL(url)
    }
  }, [id])

  useEffect(() => {
    async function load() {
      setIsLoading(true)
      try {
        const [res, borangRes] = await Promise.all([
          reviewerApi.getPenugasanDetail(id),
          reviewerApi.getBorang(id),
        ])
        setProposal(res.data)
        setBorang(borangRes.data)
        setSkor(Object.fromEntries(borangRes.data.filter((k) => k.skor).map((k) => [k.nomor, k.skor])))
        setJudulBaru(res.data?.judul || '')
        // Isian tersimpan (FINAL maupun DRAFT) dimuat kembali ke form.
        const tersimpan = res.data?.penilaianSaya
        if (tersimpan?.isFinal || tersimpan?.hasil) {
          setCatatan(tersimpan.catatan || '')
          setRekomendasiDana(tersimpan.rekomendasiDana ?? '')
          setRekomendasiStatus(tersimpan.hasil === 'LOLOS' ? 'LOLOS' : 'REVISI')
          setKomentarRevisi(tersimpan.komentarRevisi || [])
        } else if (res.data?.biayaUsulan) {
          setRekomendasiDana(res.data.biayaUsulan)
        }
      } finally {
        setIsLoading(false)
      }
    }
    load()
  }, [id])

  const sudahDikirim = Boolean(proposal?.penilaianSaya?.isFinal)
  const totalScore = borang.reduce((sum, k) => sum + (skor[k.nomor] || 0) * k.bobot, 0)
  const semuaTerisi = borang.length > 0 && borang.every((k) => skor[k.nomor])
  const otomatisTolak = semuaTerisi && totalScore < SKOR_MINIMUM

  /** DRAFT = simpan sementara (legacy Status Penilaian DRAFT); FINAL = kunci. */
  const simpanPenilaian = async (status) => {
    setIsSubmitting(true)
    setSubmitError('')
    setSuccessNotice('')
    try {
      await reviewerApi.submitNilaiRubrik(proposal.id, {
        skor: borang.filter((k) => skor[k.nomor]).map((k) => ({ nomor: k.nomor, skor: skor[k.nomor] })),
        catatan,
        rekomendasiStatus,
        rekomendasiDana,
        komentarRevisi: komentarRevisi.map((k) => k.trim()).filter(Boolean),
        status,
      })
      if (status === 'DRAFT') {
        setSuccessNotice('Draf penilaian tersimpan. Anda masih dapat mengubahnya sebelum dikirim FINAL.')
        return
      }
      setSuccessNotice('Lembar penilaian rubrik skor dan rekomendasi berhasil disimpan ke LPPM!')
      setTimeout(() => navigate('/rev/dashboard'), 1500)
    } catch (err) {
      setSubmitError(Object.values(err.errors || {}).flat()[0] || err.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    // Syarat FINAL legacy rev/app.js onPenilaianproposal_btnSimpanClick; server tetap memvalidasi.
    if (!Number(rekomendasiDana)) {
      setSubmitError('Mohon diisi rekomendasi biayanya.')
      return
    }
    if (catatan.length < MIN_KARAKTER_CATATAN) {
      setSubmitError('Komentar minimal berisi 100 karakter.')
      return
    }
    if (!window.confirm('Kirim penilaian sebagai FINAL? Setelah dikirim, penilaian tidak dapat diubah lagi.')) return
    simpanPenilaian('FINAL')
  }

  const simpanJudul = async () => {
    setIsSavingJudul(true)
    setJudulError('')
    try {
      const res = await reviewerApi.revisiJudul(proposal.id, judulBaru.trim())
      setProposal((p) => ({ ...p, judul: res.data.judulBaru }))
      setEditJudul(false)
    } catch (err) {
      setJudulError(Object.values(err.errors || {}).flat()[0] || err.message)
    } finally {
      setIsSavingJudul(false)
    }
  }

  const unduhPanduan = async () => {
    setPanduanError('')
    try {
      await reviewerApi.unduhPanduanPenilaian()
    } catch (err) {
      setPanduanError(err.message)
    }
  }

  return (
    <div className="space-y-6 text-left max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <div className="flex items-center gap-2">
            <Link to="/rev/dashboard" className="text-[#98a6ad] hover:text-[#313a46]">
              <ArrowLeft className="w-4 h-4" />
            </Link>
            <span className="font-mono text-xs font-bold text-[#188ae2]">
              {proposal?.kodeUsulan || 'PR-2026-FT-003'}
            </span>
          </div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight mt-1">
            Lembar Penilaian Substantif Proposal Penelitian
          </h4>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button variant="secondary" size="sm" iconLeft={BookMarked} onClick={unduhPanduan}>
            Panduan Penilaian
          </Button>
          {proposalUrl && (
            <a href={proposalUrl} download={`Proposal_${proposal?.kodeUsulan || id}.pdf`}>
              <Button variant="secondary" size="sm" iconLeft={Download}>
                Unduh Naskah Proposal PDF
              </Button>
            </a>
          )}
        </div>
      </div>

      {panduanError && (
        <p className="flex items-center gap-1.5 text-xs text-[#ff5b5b]">
          <AlertCircle className="w-4 h-4 shrink-0" /> {panduanError}
        </p>
      )}

      {successNotice && (
        <div className="p-3.5 bg-[#10c469]/10 border border-[#10c469]/20 text-[#10c469] rounded-xl text-xs flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-[#10c469] shrink-0" />
          <span className="font-semibold text-[#0c8a49]">{successNotice}</span>
        </div>
      )}

      {/* Info Proposal Card */}
      <Card className="p-4 text-xs space-y-1">
        {editJudul ? (
          <div className="space-y-2">
            <label htmlFor="judul-baru" className="block font-semibold text-[#313a46]">Judul baru</label>
            <textarea
              id="judul-baru"
              rows={2}
              value={judulBaru}
              onChange={(e) => setJudulBaru(e.target.value)}
              className="w-full p-2.5 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg text-base sm:text-sm text-[#313a46] focus:bg-white focus:border-[#188ae2] focus:outline-none"
            />
            {judulError && <p className="text-[#ff5b5b]">{judulError}</p>}
            <div className="flex gap-2">
              <Button size="xs" variant="primary" isLoading={isSavingJudul} disabled={!judulBaru.trim()} onClick={simpanJudul}>
                Simpan Judul
              </Button>
              <Button size="xs" variant="secondary" onClick={() => { setEditJudul(false); setJudulBaru(proposal?.judul || ''); setJudulError('') }}>
                Batal
              </Button>
            </div>
          </div>
        ) : (
          <div className="flex items-start justify-between gap-3">
            <h5 className="font-bold font-heading text-sm text-[#313a46]">{proposal?.judul}</h5>
            {proposal && (
              <Button size="xs" variant="secondary" onClick={() => setEditJudul(true)}>
                Revisi Judul
              </Button>
            )}
          </div>
        )}
        <p className="text-[#6c757d]">
          Ketua: <strong className="text-[#313a46]">{proposal?.ketuaNama}</strong> &bull; {proposal?.prodiNama} ({proposal?.fakultasNama})
        </p>
        <p className="text-[#98a6ad]">
          Skema: <strong className="text-[#313a46]">{proposal?.skimNama}</strong> &bull; Usulan Biaya: <span className="font-tabular font-bold text-[#313a46]">{formatRupiah(proposal?.biayaUsulan)}</span>
        </p>
      </Card>

      {/* Naskah Proposal - wajib dibaca reviewer sebelum mengisi borang */}
      <Card>
        <CardHeader title="Naskah Proposal" subtitle="Baca naskah ini sebelum mengisi skor di bawah" />
        <CardContent className="text-xs">
          {proposalError ? (
            <p className="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700">{proposalError}</p>
          ) : proposalUrl ? (
            <iframe
              title="Naskah proposal"
              src={proposalUrl}
              className="w-full h-[60vh] border border-slate-200 rounded-lg bg-white"
            />
          ) : (
            <p className="p-3 text-slate-500">Memuat naskah proposal...</p>
          )}
        </CardContent>
      </Card>

      {sudahDikirim && (
        <div className="p-3.5 bg-[#188ae2]/10 border border-[#188ae2]/20 rounded-xl text-xs flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-[#188ae2] shrink-0" />
          <span className="font-semibold text-[#188ae2]">
            Penilaian sudah dikirim (hasil: {proposal.penilaianSaya.hasil}, total {proposal.penilaianSaya.totalSkor}) dan tidak dapat diubah.
          </span>
        </div>
      )}

      {/* Rubric Form */}
      <form onSubmit={handleSubmit} className="space-y-6">
        <fieldset disabled={sudahDikirim} className="space-y-6 min-w-0">
        <Card>
          <CardHeader
            title="Borang Penilaian Proposal"
            subtitle="Pilih skor 1 - 7 untuk setiap kriteria; nilai = skor x bobot"
            action={
              <div className="text-right">
                <span className="text-xs text-[#98a6ad] block font-semibold uppercase tracking-wider">Total Nilai:</span>
                <span className="text-2xl font-bold font-heading text-[#188ae2] font-tabular">
                  {totalScore} <span className="text-xs text-[#98a6ad] font-normal">/ 700</span>
                </span>
              </div>
            }
          />
          <CardContent className="space-y-4 text-xs">
            {borang.length === 0 && !isLoading && (
              <p className="text-[#ff5b5b]">Borang penilaian untuk skim ini belum dikonfigurasi. Hubungi admin LPPM.</p>
            )}
            {borang.map((k) => (
              <div key={k.nomor} className="p-3 rounded-xl border border-[#e7e9eb] bg-[#f6f7fb]/50">
                <div className="flex items-center justify-between gap-4">
                  <div>
                    <KriteriaTeks nomor={k.nomor} html={k.kriteria} />
                    <p className="text-[#6c757d] text-[11px] mt-1">Bobot {k.bobot}%</p>
                  </div>
                  <select
                    required
                    aria-label={`Skor kriteria ${k.nomor}`}
                    value={skor[k.nomor] || ''}
                    onChange={(e) => setSkor({ ...skor, [k.nomor]: Number(e.target.value) })}
                    className="w-40 p-1.5 border border-[#e7e9eb] bg-white rounded-lg font-bold text-sm text-[#313a46] focus:border-[#188ae2] focus:outline-none"
                  >
                    <option value="">Pilih skor</option>
                    {PILIHAN_SKOR.map((opt) => (
                      <option key={opt.value} value={opt.value}>{opt.label}</option>
                    ))}
                  </select>
                </div>
              </div>
            ))}
          </CardContent>
        </Card>

        {/* Catatan & Rekomendasi Keputusan */}
        <Card>
          <CardHeader title="Rekomendasi Keputusan Reviewer & Catatan Kualitatif" />
          <CardContent className="space-y-4 text-xs">
            <div>
              <label className="block font-semibold text-[#313a46] mb-1.5">
                Rekomendasi Kelayakan Usulan *
              </label>
              {otomatisTolak && (
                <p className="mb-2 p-2.5 rounded-lg bg-[#ff5b5b]/10 text-[#ff5b5b] font-semibold">
                  Total nilai di bawah {SKOR_MINIMUM}: rekomendasi otomatis DITOLAK.
                </p>
              )}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <button
                  type="button"
                  onClick={() => setRekomendasiStatus('LOLOS')}
                  className={`p-3 rounded-xl border text-left cursor-pointer transition ${
                    rekomendasiStatus === 'LOLOS'
                      ? 'border-[#10c469] bg-[#10c469]/10 text-[#10c469] font-bold ring-1 ring-[#10c469]'
                      : 'border-[#e7e9eb] bg-white text-[#313a46] hover:bg-[#f6f7fb]'
                  }`}
                >
                  <div className="font-bold font-heading">DITERIMA (LOLOS)</div>
                  <p className="text-[11px] text-[#6c757d] font-normal mt-0.5">Tanpa revisi substantif</p>
                </button>

                <button
                  type="button"
                  onClick={() => setRekomendasiStatus('REVISI')}
                  className={`p-3 rounded-xl border text-left cursor-pointer transition ${
                    rekomendasiStatus === 'REVISI'
                      ? 'border-[#f9c851] bg-[#f9c851]/10 text-[#b28400] font-bold ring-1 ring-[#f9c851]'
                      : 'border-[#e7e9eb] bg-white text-[#313a46] hover:bg-[#f6f7fb]'
                  }`}
                >
                  <div className="font-bold font-heading">PERLU REVISI</div>
                  <p className="text-[11px] text-[#6c757d] font-normal mt-0.5">Peneliti wajib mengunggah naskah perbaikan</p>
                </button>

              </div>
            </div>

            <div>
              <label htmlFor="rekomendasi-dana" className="block font-semibold text-[#313a46] mb-1">
                Rekomendasi Penyesuaian Anggaran Dana (Rp) *
              </label>
              <input
                id="rekomendasi-dana"
                type="number"
                value={rekomendasiDana}
                onChange={(e) => setRekomendasiDana(e.target.value)}
                className="w-full p-2 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg font-tabular font-bold text-[#313a46] focus:bg-white focus:border-[#188ae2] focus:outline-none"
              />
            </div>

            <div>
              <label htmlFor="catatan-evaluasi" className="block font-semibold text-[#313a46] mb-1">
                Catatan Evaluasi / Ulasan Kritis untuk Peneliti *
              </label>
              <textarea
                id="catatan-evaluasi"
                rows={5}
                required
                value={catatan}
                onChange={(e) => setCatatan(e.target.value)}
                placeholder="Tulis ulasan Anda atas proposal ini..."
                className="w-full p-3 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg font-sans leading-relaxed text-[#313a46] focus:bg-white focus:border-[#188ae2] focus:outline-none"
              />
              <p className={`text-[11px] mt-0.5 ${catatan.length < MIN_KARAKTER_CATATAN ? 'text-[#ab7405]' : 'text-[#0c8a49]'}`}>
                {catatan.length} karakter (minimal {MIN_KARAKTER_CATATAN} untuk kirim FINAL)
              </p>
            </div>

            <div className="space-y-2">
              <p className="font-semibold text-[#313a46]">Komentar Revisi (dibalas peneliti satu per satu)</p>
              {komentarRevisi.map((teks, idx) => (
                <div key={idx} className="flex gap-2">
                  <textarea
                    aria-label={`Komentar revisi ${idx + 1}`}
                    rows={2}
                    value={teks}
                    onChange={(e) => setKomentarRevisi((prev) => prev.map((k, i) => (i === idx ? e.target.value : k)))}
                    className="w-full p-2.5 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-base sm:text-xs"
                  />
                  <Button variant="secondary" size="xs" onClick={() => setKomentarRevisi((prev) => prev.filter((_, i) => i !== idx))}>
                    Hapus
                  </Button>
                </div>
              ))}
              <Button variant="secondary" size="xs" onClick={() => setKomentarRevisi((prev) => [...prev, ''])}>
                Tambah Komentar Revisi
              </Button>
            </div>

            {submitError && (
              <p className="flex items-center gap-1.5 text-[#ff5b5b]">
                <AlertCircle className="w-4 h-4 shrink-0" /> {submitError}
              </p>
            )}

            {!sudahDikirim && (
            <div className="pt-2 flex justify-end gap-2">
              <Button
                variant="secondary"
                size="md"
                onClick={() => navigate('/rev/dashboard')}
              >
                Batal
              </Button>
              <Button
                variant="secondary"
                size="md"
                disabled={isSubmitting}
                onClick={() => simpanPenilaian('DRAFT')}
              >
                Simpan Draf
              </Button>
              <Button
                type="submit"
                variant="primary"
                size="md"
                isLoading={isSubmitting}
                iconLeft={FileCheck2}
              >
                Kirim FINAL
              </Button>
            </div>
            )}
          </CardContent>
        </Card>
        </fieldset>
      </form>
    </div>
  )
}
