import React, { useRef, useState } from 'react'
import { AlertCircle, CheckCircle2, Clock, Eye, Save, UploadCloud } from 'lucide-react'
import { penelitiApi } from '../../services/api/penelitiApi'
import { Card, CardHeader, CardContent } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Modal } from '../../components/ui/Modal'

const MAX_UKURAN_PDF = 10 * 1024 * 1024

const INPUT = 'w-full p-2 bg-white border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-base sm:text-xs'

/**
 * Langkah sebelum usulan masuk antrean Dekan, urutan legacy
 * (statuskesediaantim, rencanatargetpenelitian, permohonanpenelitian
 * "Lembar Pengesahan", dokumenproposalpenelitian): semua anggota setuju ->
 * ketua isi dana penyertaan, generate & set final lembar pengesahan ->
 * unggah proposal PDF -> set dokumen final.
 */
// Sesudah tiap aksi server mengarahkan kembali ke halaman detail, jadi
// `proposal` dan `targets` (props halaman) selalu terbaru.
export function KelengkapanPengajuanCard({ proposal, targets = [] }) {
  const fileInputRef = useRef(null)
  const [dipilih, setDipilih] = useState(() => targets.filter((t) => t.isDipilih).map((t) => t.id))
  const [isSavingTarget, setIsSavingTarget] = useState(false)
  const [isUploading, setIsUploading] = useState(false)
  const [error, setError] = useState('')
  const [notice, setNotice] = useState('')
  const [danaMitra, setDanaMitra] = useState(proposal.danaMitra ?? '')
  const [danaInkind, setDanaInkind] = useState(proposal.danaInkind ?? '')
  const [busy, setBusy] = useState('')
  const [konfirmasi, setKonfirmasi] = useState('')
  const [previewUrl, setPreviewUrl] = useState(null)
  const [previewTitle, setPreviewTitle] = useState('')
  const anggota = proposal.anggotaDosen || []
  const belumSetuju = anggota.filter((a) => !a.isApproved)
  const bolehUnggah =
    proposal.isKetua && belumSetuju.length === 0 && proposal.isLembarPengesahanFinal && !proposal.isDokumenProposalFinal

  const pratinjau = async () => {
    setError('')
    setBusy('pratinjau')
    try {
      const url = await penelitiApi.pratinjauDokumenProposal(proposal.id)
      setPreviewTitle('Pratinjau Proposal')
      setPreviewUrl(url)
    } catch (e) {
      setError(e.message)
    } finally {
      setBusy('')
    }
  }

  const jalankan = async (kunci, aksi) => {
    setBusy(kunci)
    setError('')
    setNotice('')
    try {
      const res = await aksi()
      if (res?.message) setNotice(res.message)
    } catch (e) {
      setError(Object.values(e.errors || {}).flat()[0] || e.message)
    } finally {
      setBusy('')
      setKonfirmasi('')
    }
  }

  const toggleTarget = (id) => {
    setDipilih((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]))
  }

  const simpanTarget = async () => {
    setIsSavingTarget(true)
    setError('')
    try {
      await penelitiApi.updateRencanaTarget(proposal.id, dipilih)
      setNotice('Rencana target luaran disimpan.')
    } catch (e) {
      setError(e.message)
    } finally {
      setIsSavingTarget(false)
    }
  }

  const handleFile = async (e) => {
    const file = e.target.files?.[0]
    e.target.value = ''
    setError('')
    setNotice('')
    if (!file) return

    if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
      setError('Berkas harus dalam format PDF.')
      return
    }
    if (file.size > MAX_UKURAN_PDF) {
      setError('Ukuran berkas melebihi batas maksimal 10 MB.')
      return
    }

    setIsUploading(true)
    try {
      const res = await penelitiApi.uploadDokumenProposal(proposal.id, file)
      setNotice(res.message)
    } catch (err) {
      setError(Object.values(err.errors || {}).flat()[0] || err.message)
    } finally {
      setIsUploading(false)
    }
  }

  return (
    <Card>
      <CardHeader title="Kelengkapan Pengajuan" />
      <CardContent className="space-y-4 text-xs">
        {/* 1. Persetujuan anggota */}
        <div className="space-y-2">
          <p className="font-semibold text-[#313a46]">1. Persetujuan anggota dosen</p>
          {anggota.length === 0 ? (
            <p className="text-[#98a6ad]">Tidak ada anggota dosen.</p>
          ) : (
            anggota.map((a) => (
              <div key={a.npp} className="flex items-center justify-between p-2.5 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg">
                <span className="text-[#313a46]">{a.nama || a.npp}</span>
                {a.isApproved ? (
                  <Badge variant="success" pill>Sudah setuju</Badge>
                ) : (
                  <Badge variant="warning" pill>Menunggu</Badge>
                )}
              </div>
            ))
          )}
        </div>

        {/* 2. Rencana target capaian dan luaran */}
        {targets.length > 0 && (
          <div className="space-y-2 pt-3 border-t border-[#e7e9eb]">
            <p className="font-semibold text-[#313a46]">2. Rencana target capaian dan luaran</p>
            {targets.map((t) => (
              <label key={t.id} className="flex items-start gap-2 text-[#6c757d]">
                <input
                  type="checkbox"
                  className="mt-0.5"
                  checked={t.isWajib || dipilih.includes(t.id)}
                  disabled={t.isWajib || !proposal.isKetua || proposal.isDokumenProposalFinal}
                  onChange={() => toggleTarget(t.id)}
                />
                <span>
                  {t.kategori} - {t.subkategori}
                  {t.isWajib && <span className="text-[#ff5b5b]"> (wajib)</span>}
                </span>
              </label>
            ))}
            {proposal.isKetua && !proposal.isDokumenProposalFinal && (
              <Button variant="secondary" size="xs" iconLeft={Save} isLoading={isSavingTarget} onClick={simpanTarget}>
                Simpan Target
              </Button>
            )}
          </div>
        )}

        {/* 3. Lembar pengesahan */}
        {proposal.isKetua && (
          <div className="space-y-2 pt-3 border-t border-[#e7e9eb]">
            <div className="flex items-center gap-2">
              <p className="font-semibold text-[#313a46]">3. Lembar pengesahan</p>
              {proposal.isLembarPengesahanFinal ? (
                <Badge variant="success" pill>Final</Badge>
              ) : (
                proposal.adaLembarPengesahan && <Badge variant="warning" pill>Belum final</Badge>
              )}
            </div>

            {!proposal.isLembarPengesahanFinal && (
              <>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  <div>
                    <label htmlFor="dana-mitra-proposal" className="block text-[#6c757d] mb-1">Penyertaan dana mitra (Rp)</label>
                    <input
                      id="dana-mitra-proposal"
                      type="text"
                      value={danaMitra !== '' ? Number(danaMitra).toLocaleString('id-ID') : ''}
                      onChange={(e) => setDanaMitra(e.target.value.replace(/\D/g, ''))}
                      className={INPUT}
                    />
                  </div>
                  <div>
                    <label htmlFor="dana-inkind-proposal" className="block text-[#6c757d] mb-1">Penyertaan dana in-kind (Rp)</label>
                    <input
                      id="dana-inkind-proposal"
                      type="text"
                      value={danaInkind !== '' ? Number(danaInkind).toLocaleString('id-ID') : ''}
                      onChange={(e) => setDanaInkind(e.target.value.replace(/\D/g, ''))}
                      className={INPUT}
                    />
                  </div>
                </div>
                <div className="flex flex-wrap gap-2">
                  <Button
                    variant="secondary"
                    size="xs"
                    iconLeft={Save}
                    isLoading={busy === 'dana'}
                    disabled={danaMitra === '' || danaInkind === ''}
                    onClick={() => jalankan('dana', () => penelitiApi.saveDanaPenyertaanProposal(proposal.id, { danaMitra, danaInkind }))}
                  >
                    Simpan Dana Penyertaan
                  </Button>
                  <Button
                    variant="secondary"
                    size="xs"
                    isLoading={busy === 'generate'}
                    disabled={proposal.danaMitra === null || proposal.danaInkind === null}
                    onClick={() => jalankan('generate', () => penelitiApi.generateLembarPengesahanProposal(proposal.id))}
                  >
                    Generate Dokumen
                  </Button>
                  <Button variant="secondary" size="xs" disabled={!proposal.adaLembarPengesahan} onClick={() => setKonfirmasi('lembar')}>
                    Set Final
                  </Button>
                </div>
              </>
            )}

            {proposal.adaLembarPengesahan && (
              <Button
                variant="secondary"
                size="xs"
                isLoading={busy === 'preview'}
                onClick={() => jalankan('preview', async () => {
                  const url = await penelitiApi.unduhPengesahan(proposal.id, { format: 'pdf', preview: true })
                  setPreviewTitle('Pratinjau Lembar Pengesahan')
                  setPreviewUrl(url)
                })}
              >
                Pratinjau Lembar
              </Button>
            )}

            {konfirmasi === 'lembar' && (
              <div className="p-3 bg-[#f9c851]/20 border border-[#f9c851]/40 rounded-lg space-y-2">
                <p className="font-semibold text-[#ab7405]">
                  Proses ini membuat lembar pengesahan berstatus FINAL dan tidak dapat diedit lagi. Lanjut?
                </p>
                <div className="flex gap-2">
                  <Button variant="primary" size="xs" isLoading={busy === 'final-lembar'} onClick={() => jalankan('final-lembar', () => penelitiApi.finalLembarPengesahanProposal(proposal.id))}>
                    Ya, Set Final
                  </Button>
                  <Button variant="secondary" size="xs" onClick={() => setKonfirmasi('')}>Batal</Button>
                </div>
              </div>
            )}
          </div>
        )}

        {/* 4. Dokumen proposal */}
        <div className="space-y-2 pt-3 border-t border-[#e7e9eb]">
          <p className="font-semibold text-[#313a46]">4. Naskah proposal (PDF)</p>
          {proposal.isDokumenProposalFinal ? (
            <p className="flex items-center gap-1.5 text-[#0c8a49]">
              <CheckCircle2 className="w-4 h-4" /> Proposal sudah final dan diteruskan ke Dekan.
            </p>
          ) : !bolehUnggah ? (
            <p className="flex items-center gap-1.5 text-[#98a6ad]">
              <Clock className="w-4 h-4" />
              {!proposal.isKetua
                ? 'Ketua akan mengunggah proposal setelah semua anggota setuju.'
                : belumSetuju.length > 0
                  ? `Menunggu persetujuan ${belumSetuju.length} anggota sebelum proposal bisa diunggah.`
                  : 'Set final lembar pengesahan terlebih dulu sebelum mengunggah proposal.'}
            </p>
          ) : null}

          {bolehUnggah && (
            <>
              <input type="file" ref={fileInputRef} accept=".pdf,application/pdf" className="hidden" onChange={handleFile} />
              <div className="flex flex-wrap gap-2">
                <Button
                  variant={proposal.adaDokumenProposal ? 'secondary' : 'primary'}
                  size="sm"
                  iconLeft={UploadCloud}
                  isLoading={isUploading}
                  onClick={() => fileInputRef.current?.click()}
                >
                  {proposal.adaDokumenProposal ? 'Ganti Proposal PDF' : 'Unggah Proposal PDF (maks. 10 MB)'}
                </Button>
                {proposal.adaDokumenProposal && (
                  <>
                    <Button variant="secondary" size="sm" iconLeft={Eye} isLoading={busy === 'pratinjau'} onClick={pratinjau}>
                      Pratinjau Proposal
                    </Button>
                    <Button variant="primary" size="sm" onClick={() => setKonfirmasi('dokumen')}>
                      Set Dokumen Final
                    </Button>
                  </>
                )}
              </div>
              {proposal.adaDokumenProposal && (
                <p className="text-[#98a6ad]">
                  Proposal tersimpan sebagai draft. Periksa lewat pratinjau, ganti bila perlu, lalu set dokumen final.
                </p>
              )}
              {konfirmasi === 'dokumen' && (
                <div className="p-3 bg-[#f9c851]/20 border border-[#f9c851]/40 rounded-lg space-y-2">
                  <p className="font-semibold text-[#ab7405]">
                    Dokumen proposal akan diset sebagai FINAL dan tidak bisa diedit kembali. Lanjut?
                  </p>
                  <div className="flex gap-2">
                    <Button variant="primary" size="xs" isLoading={busy === 'final-dokumen'} onClick={() => jalankan('final-dokumen', () => penelitiApi.finalDokumenProposal(proposal.id))}>
                      Ya, Set Final
                    </Button>
                    <Button variant="secondary" size="xs" onClick={() => setKonfirmasi('')}>Batal</Button>
                  </div>
                </div>
              )}
            </>
          )}
        </div>

        {notice && <p className="text-[#0c8a49]">{notice}</p>}
        {error && (
          <p className="flex items-center gap-1.5 text-[#ff5b5b]">
            <AlertCircle className="w-4 h-4 shrink-0" /> {error}
          </p>
        )}
      </CardContent>

      <Modal
        isOpen={!!previewUrl}
        onClose={() => {
          if (previewUrl) URL.revokeObjectURL(previewUrl)
          setPreviewUrl(null)
          setPreviewTitle('')
        }}
        title={previewTitle}
        size="4xl"
      >
        <div className="w-full h-[75vh]">
          {previewUrl && (
            <iframe
              src={previewUrl}
              className="w-full h-full rounded border-0"
              title="Pratinjau PDF"
            />
          )}
        </div>
      </Modal>
    </Card>
  )
}
