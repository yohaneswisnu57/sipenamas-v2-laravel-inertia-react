import React, { useState, useRef } from 'react'
import { Link } from '@/lib/router'
import { ArrowLeft, UploadCloud, AlertTriangle, CheckCircle2, MessageSquare, CornerDownRight, Send, Eye } from 'lucide-react'
import { penelitiApi } from '../../services/api/penelitiApi'
import { Card, CardHeader, CardContent } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Modal } from '../../components/ui/Modal'
import { formatDateTime } from '../../utils/formatters'

const MAX_UKURAN_PDF = 10 * 1024 * 1024

/**
 * Padanan jendela revisi proposal legacy (hasilreviewpenelitian.php):
 * komentar revisi tiap reviewer ditampilkan sebagai utas, ketua membalas
 * tiap komentar (KOMENRESPON), mengunggah naskah revisi sebagai draft yang
 * boleh diganti, lalu menandainya final. Setelah final, balasan dan naskah
 * terkunci.
 */
// `revisi` dari server; sesudah tiap aksi halaman dimuat ulang lewat redirect.
export default function RevisiProposalPage({ revisi: data }) {
  const id = data?.id
  const fileInputRef = useRef(null)
  const [draft, setDraft] = useState({})
  const [sedangBalas, setSedangBalas] = useState(null)
  const [file, setFile] = useState(null)
  const [busy, setBusy] = useState('')
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [konfirmasi, setKonfirmasi] = useState(false)
  const [previewUrl, setPreviewUrl] = useState(null)

  const terkunci = !data || data.isFinal || Boolean(data.alasanTertutup)

  const kirimBalasan = async (komentar) => {
    setBusy(`balas-${komentar.id}`)
    setError('')
    try {
      await penelitiApi.saveResponRevisi(id, komentar.id, draft[komentar.id] ?? '')
      setSedangBalas(null)
    } catch (e) {
      setError(Object.values(e.errors || {}).flat()[0] || e.message)
    } finally {
      setBusy('')
    }
  }

  const pilihFile = (e) => {
    const dipilih = e.target.files?.[0]
    e.target.value = ''
    setError('')
    if (!dipilih) return
    if (dipilih.type !== 'application/pdf' && !dipilih.name.toLowerCase().endsWith('.pdf')) {
      setError('Berkas harus dalam format PDF.')
      return
    }
    if (dipilih.size > MAX_UKURAN_PDF) {
      setError('Ukuran berkas melebihi batas maksimal 10 MB.')
      return
    }
    setFile(dipilih)
  }

  const unggahDraft = async () => {
    setBusy('unggah')
    setError('')
    try {
      const res = await penelitiApi.uploadDokumenRevisi(id, file)
      setNotice(res.message || 'Draft naskah revisi disimpan')
      setFile(null)
    } catch (e) {
      setError(Object.values(e.errors || {}).flat()[0] || e.message)
    } finally {
      setBusy('')
    }
  }

  const pratinjau = async () => {
    setBusy('pratinjau')
    setError('')
    try {
      setPreviewUrl(await penelitiApi.pratinjauDokumenRevisi(id))
    } catch (e) {
      setError(e.message)
    } finally {
      setBusy('')
    }
  }

  const setFinal = async () => {
    setBusy('final')
    setError('')
    try {
      const res = await penelitiApi.finalRevisi(id)
      setNotice(res.message || 'Revisi proposal final dan dikirim ke reviewer')
    } catch (e) {
      setError(Object.values(e.errors || {}).flat()[0] || e.message)
    } finally {
      setBusy('')
      setKonfirmasi(false)
    }
  }

  return (
    <div className="space-y-6 text-left max-w-4xl mx-auto">
      <div className="pb-5 border-b border-[#e7e9eb]">
        <div className="flex items-center gap-2">
          <Link to={`/pen/penelitian/${id}`} className="text-[#98a6ad] hover:text-[#313a46]">
            <ArrowLeft className="w-4 h-4" />
          </Link>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">Revisi Proposal</h4>
          {data?.isFinal && <Badge variant="success" pill>FINAL</Badge>}
          {data && !data.isFinal && data.adaDokumenRevisi && <Badge variant="warning" pill>DRAFT</Badge>}
        </div>
        {data && <p className="text-xs text-[#98a6ad] mt-0.5">{data.judul}</p>}
      </div>

      {notice && (
        <div className="p-3.5 bg-[#10c469]/10 border border-[#10c469]/20 rounded-xl text-xs flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-[#10c469] shrink-0" />
          <span className="font-semibold text-[#0c8a49]">{notice}</span>
        </div>
      )}
      {error && (
        <div className="p-3.5 bg-[#ff5b5b]/10 border border-[#ff5b5b]/20 rounded-xl text-xs flex items-center gap-2">
          <AlertTriangle className="w-4 h-4 text-[#ff5b5b] shrink-0" />
          <span className="font-semibold text-[#ff5b5b]">{error}</span>
        </div>
      )}
      {data?.alasanTertutup && (
        <div className="p-3.5 bg-[#f9c851]/10 border border-[#f9c851]/20 text-[#b28400] rounded-xl text-xs flex items-center gap-2">
          <AlertTriangle className="w-4 h-4 shrink-0" />
          <span className="font-semibold">{data.alasanTertutup}</span>
        </div>
      )}
      {data && !data.adaVerifikator && !data.alasanTertutup && (
        <div className="p-3.5 bg-[#f9c851]/10 border border-[#f9c851]/20 text-[#b28400] rounded-xl text-xs flex items-center gap-2">
          <AlertTriangle className="w-4 h-4 shrink-0" />
          <span>Menunggu LPPM menunjuk reviewer verifikasi sebelum revisi bisa dikirim.</span>
        </div>
      )}

      {!data ? (
        !error && <p className="py-12 text-center text-xs text-slate-500">Memuat data...</p>
      ) : (
        <>
          <Card>
            <CardHeader title="Komentar Reviewer" subtitle="Balas tiap komentar untuk menjelaskan perbaikan yang sudah dilakukan" />
            <CardContent className="space-y-4 text-xs">
              {data.komentar.length === 0 && <p className="text-[#98a6ad]">Tidak ada komentar revisi.</p>}

              {data.komentar.map((k) => (
                <article key={k.id} className="space-y-2">
                  <div className="flex gap-2.5">
                    <div className="w-8 h-8 shrink-0 rounded-full bg-[#188ae2]/10 text-[#188ae2] flex items-center justify-center">
                      <MessageSquare className="w-4 h-4" />
                    </div>
                    <div className="flex-1 p-3 bg-[#f6f7fb] border border-[#e7e9eb] rounded-xl">
                      <p className="font-bold text-[#313a46]">Reviewer {k.reviewerKe}</p>
                      <p className="text-[#313a46] leading-relaxed whitespace-pre-line mt-0.5">{k.komentar}</p>
                    </div>
                  </div>

                  <div className="ml-10 space-y-2">
                    {k.respon && sedangBalas !== k.id && (
                      <div className="flex gap-2">
                        <CornerDownRight className="w-4 h-4 mt-2 text-[#98a6ad] shrink-0" />
                        <div className="flex-1 p-3 bg-[#10c469]/10 border border-[#10c469]/20 rounded-xl">
                          <p className="font-bold text-[#0c8a49]">Anda</p>
                          <p className="text-[#313a46] leading-relaxed whitespace-pre-line mt-0.5">{k.respon}</p>
                        </div>
                      </div>
                    )}

                    {!terkunci &&
                      (sedangBalas === k.id ? (
                        <div className="space-y-2">
                          <textarea
                            aria-label={`Balasan untuk komentar Reviewer ${k.reviewerKe}`}
                            rows={3}
                            value={draft[k.id] ?? k.respon ?? ''}
                            onChange={(e) => setDraft((prev) => ({ ...prev, [k.id]: e.target.value }))}
                            placeholder="Tulis balasan..."
                            className="w-full p-2.5 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-base sm:text-xs"
                          />
                          <div className="flex gap-2">
                            <Button variant="primary" size="xs" iconLeft={Send} isLoading={busy === `balas-${k.id}`} onClick={() => kirimBalasan(k)}>
                              Kirim Balasan
                            </Button>
                            <Button variant="secondary" size="xs" onClick={() => setSedangBalas(null)}>
                              Batal
                            </Button>
                          </div>
                        </div>
                      ) : (
                        <button
                          type="button"
                          className="font-semibold text-[#188ae2] hover:underline"
                          onClick={() => {
                            setDraft((prev) => ({ ...prev, [k.id]: k.respon ?? '' }))
                            setSedangBalas(k.id)
                          }}
                        >
                          {k.respon ? 'Ubah balasan' : 'Balas'}
                        </button>
                      ))}
                  </div>
                </article>
              ))}

              {data.catatanVerifikator && (
                <div className="p-3 bg-[#f9c851]/10 border border-[#f9c851]/20 rounded-xl">
                  <p className="font-bold text-[#ab7405]">Catatan verifikasi revisi</p>
                  <p className="text-[#313a46] whitespace-pre-line mt-0.5">{data.catatanVerifikator}</p>
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader title="Naskah Proposal Hasil Revisi" subtitle="PDF, maksimal 10 MB" />
            <CardContent className="space-y-3 text-xs">
              {data.adaDokumenRevisi ? (
                <div className="flex flex-wrap items-center gap-2">
                  <p className="flex items-center gap-1.5 text-[#0c8a49]">
                    <CheckCircle2 className="w-4 h-4" />
                    {data.isFinal ? 'Naskah revisi final' : 'Draft naskah revisi'} diunggah {formatDateTime(data.tsUploadRevisi)}.
                  </p>
                  <Button variant="secondary" size="xs" iconLeft={Eye} isLoading={busy === 'pratinjau'} onClick={pratinjau}>
                    Pratinjau
                  </Button>
                </div>
              ) : (
                <p className="text-[#98a6ad]">Belum ada naskah revisi yang diunggah.</p>
              )}

              {!terkunci && (
                <>
                  <input type="file" ref={fileInputRef} accept=".pdf,application/pdf" className="hidden" onChange={pilihFile} />
                  <div className="flex flex-wrap items-center gap-2">
                    <Button variant="secondary" size="sm" iconLeft={UploadCloud} onClick={() => fileInputRef.current?.click()}>
                      {data.adaDokumenRevisi ? 'Ganti Naskah' : 'Pilih Naskah Revisi'}
                    </Button>
                    {file && (
                      <>
                        <span className="font-mono text-slate-600">{file.name}</span>
                        <Button variant="primary" size="sm" isLoading={busy === 'unggah'} onClick={unggahDraft}>
                          Unggah Draft
                        </Button>
                      </>
                    )}
                  </div>

                  {!data.adaVerifikator && (
                    <p className="text-[#ab7405]">Revisi bisa di-set final setelah LPPM menunjuk verifikator revisi.</p>
                  )}

                  {konfirmasi ? (
                    <div className="p-3 bg-[#f9c851]/20 border border-[#f9c851]/40 rounded-lg space-y-2">
                      <p className="font-semibold text-[#ab7405]">
                        Setelah final, balasan dan naskah revisi tidak bisa diubah lagi. Lanjut?
                      </p>
                      <div className="flex gap-2">
                        <Button variant="primary" size="xs" isLoading={busy === 'final'} onClick={setFinal}>
                          Ya, Set Final
                        </Button>
                        <Button variant="secondary" size="xs" onClick={() => setKonfirmasi(false)}>
                          Batal
                        </Button>
                      </div>
                    </div>
                  ) : (
                    <Button
                      variant="primary"
                      size="sm"
                      iconLeft={Send}
                      disabled={!data.adaVerifikator || !data.adaDokumenRevisi}
                      onClick={() => setKonfirmasi(true)}
                    >
                      Set Final &amp; Kirim ke Reviewer
                    </Button>
                  )}
                </>
              )}
            </CardContent>
          </Card>
        </>
      )}

      <Modal
        isOpen={!!previewUrl}
        onClose={() => {
          if (previewUrl) URL.revokeObjectURL(previewUrl)
          setPreviewUrl(null)
        }}
        title="Pratinjau Naskah Revisi"
        size="4xl"
      >
        <div className="w-full h-[75vh]">
          {previewUrl && <iframe src={previewUrl} className="w-full h-full rounded border-0" title="Pratinjau PDF" />}
        </div>
      </Modal>
    </div>
  )
}
