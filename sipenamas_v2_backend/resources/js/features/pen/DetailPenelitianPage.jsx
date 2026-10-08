import React, { useState } from 'react'
import { Link } from '@/lib/router'
import { ArrowLeft, Download } from 'lucide-react'
import { penelitiApi } from '../../services/api/penelitiApi'
import { formatRupiah } from '../../utils/formatters'
import { StatusBadge } from '../../components/common/StatusBadge'
import { StepIndicator } from '../../components/common/StepIndicator'
import { Card, CardHeader, CardContent } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { STATUS_USULAN } from '../../utils/constants'
import { KelengkapanPengajuanCard } from './KelengkapanPengajuanCard'

function PengesahanQr({ label, signer, emptyText = 'Belum tersedia' }) {
  return (
    <div className="space-y-1">
      <div className="w-full aspect-square bg-white border border-slate-200 rounded-xl p-1.5 flex items-center justify-center shadow-xs">
        {signer?.qr ? (
          <img src={signer.qr} alt={`QR Pengesahan ${label}`} className="w-full h-full" />
        ) : (
          <span className="text-[10px] text-slate-400 px-1 leading-tight">{emptyText}</span>
        )}
      </div>
      <p className="text-[10px] font-semibold text-slate-700">{label}</p>
      {signer && (
        <p className="text-[10px] text-slate-500 leading-tight">
          {signer.nama}
          <br />
          <span className="font-mono">{signer.nik}</span>
        </p>
      )}
    </div>
  )
}

export default function DetailPenelitianPage({ proposal, rencanaTarget = [] }) {
  const id = proposal?.id
  const [konfirmasiHapus, setKonfirmasiHapus] = useState(false)
  const [hapusError, setHapusError] = useState('')

  // Padanan tombol Del legacy: hanya ketua, selama belum disetujui Dekan.
  // Server mengarahkan ke daftar usulan sesudah hapus.
  const hapus = async () => {
    setHapusError('')
    try {
      await penelitiApi.deletePenelitian(id)
    } catch (e) {
      setHapusError(e.message)
      setKonfirmasiHapus(false)
    }
  }
  const [unduhError, setUnduhError] = useState('')

  const unduh = async (opts) => {
    setUnduhError('')
    try {
      await penelitiApi.unduhPengesahan(id, opts)
    } catch (e) {
      setUnduhError(e.message)
    }
  }

  const unduhSurat = async () => {
    setUnduhError('')
    try {
      await penelitiApi.unduhSuratTugas(id)
    } catch (e) {
      setUnduhError(e.message)
    }
  }

  if (!proposal) {
    return (
      <div className="text-center py-12">
        <p className="text-slate-600 text-sm">Proposal tidak ditemukan</p>
        <Link to="/pen/penelitian">
          <Button variant="secondary" size="xs" className="mt-3">
            Kembali ke Daftar
          </Button>
        </Link>
      </div>
    )
  }

  return (
    <div className="space-y-6 text-left max-w-5xl mx-auto">
      {/* Top Bar Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <div className="flex items-center gap-2">
            <Link to={proposal.jenis === 'ABDIMAS' ? '/pen/abdimas' : '/pen/penelitian'} className="text-[#98a6ad] hover:text-[#313a46]">
              <ArrowLeft className="w-4 h-4" />
            </Link>
            <span className="font-mono text-xs font-bold text-[#188ae2]">{proposal.kodeUsulan}</span>
            <StatusBadge status={proposal.status} dokumenFinal={proposal.isDokumenProposalFinal} />
          </div>
          <h4 className="text-lg sm:text-xl font-bold font-heading text-[#313a46] tracking-tight mt-1">
            {proposal.judul}
          </h4>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {proposal.isKetua && !proposal.isPengajuanFinal && (
            <Link to={proposal.jenis === 'ABDIMAS' ? `/pen/abdimas/${id}/edit` : `/pen/penelitian/${id}/edit`}>
              <Button variant="soft-primary" size="sm">
                Edit Draft
              </Button>
            </Link>
          )}
          {proposal.isKetua && proposal.catatanDekan?.keputusan !== 'SETUJU' &&
            (konfirmasiHapus ? (
              <>
                <Button variant="danger" size="sm" onClick={hapus}>
                  Ya, Hapus
                </Button>
                <Button variant="secondary" size="sm" onClick={() => setKonfirmasiHapus(false)}>
                  Batal
                </Button>
              </>
            ) : (
              <Button variant="secondary" size="sm" onClick={() => setKonfirmasiHapus(true)}>
                Hapus Usulan
              </Button>
            ))}
        </div>
      </div>

      {hapusError && <p className="text-xs font-semibold text-[#ff5b5b]">{hapusError}</p>}

      {/* Lifecycle Progress Stepper */}
      <Card className="p-4">
        <p className="text-[11px] font-semibold text-[#98a6ad] uppercase tracking-wider mb-2">
          Tahapan Siklus Usulan Penelitian
        </p>
        <StepIndicator currentStatus={proposal.status} />
      </Card>

      {/* Grid: 2 Columns */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left Column: Metadata & Ringkasan */}
        <div className="lg:col-span-8 space-y-5">
          {/* Metadata Card */}
          <Card>
            <CardHeader title="Informasi Usulan Penelitian" />
            <CardContent className="space-y-3 text-xs">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <span className="text-[#98a6ad] block">Skema Penelitian:</span>
                  <strong className="text-[#313a46]">{proposal.skimNama}</strong>
                </div>
                <div>
                  <span className="text-[#98a6ad] block">Tahun Anggaran & Periode:</span>
                  <strong className="text-[#313a46]">TA {proposal.tahun} ({proposal.kodeperiode})</strong>
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4 pt-2 border-t border-[#e7e9eb]">
                <div>
                  <span className="text-[#98a6ad] block">Bidang Fokus:</span>
                  <strong className="text-[#313a46]">{proposal.bidangFokus}</strong>
                </div>
                <div>
                  <span className="text-[#98a6ad] block">Target Luaran:</span>
                  <strong className="text-[#313a46]">{proposal.targetLuaran}</strong>
                </div>
              </div>
              
              {proposal.jenis === 'ABDIMAS' && (
                <div className="pt-2 border-t border-[#e7e9eb]">
                  <span className="text-[#98a6ad] block">Lokasi Abdimas:</span>
                  <strong className="text-[#313a46]">{proposal.tempatLokasi || '-'}</strong>
                </div>
              )}

              <div className="pt-2 border-t border-[#e7e9eb]">
                <span className="text-[#98a6ad] block mb-1">Ringkasan Eksekutif (Abstrak):</span>
                <p className="text-[#6c757d] leading-relaxed bg-[#f6f7fb] p-3 rounded-lg border border-[#e7e9eb]">
                  {proposal.ringkasan || 'Tidak ada ringkasan.'}
                </p>
              </div>
            </CardContent>
          </Card>

          {/* Tim Peneliti */}
          <Card>
            <CardHeader title="Tim Peneliti & Pelaksana" />
            <CardContent className="space-y-3 text-xs">
              {/* Ketua */}
              <div className="p-3 bg-[#188ae2]/10 border border-[#188ae2]/20 rounded-xl flex items-center justify-between">
                <div>
                  <span className="text-[10px] font-bold text-[#188ae2] uppercase tracking-wider block">
                    Ketua Peneliti
                  </span>
                  <p className="text-sm font-bold font-heading text-[#313a46] mt-0.5">{proposal.ketuaNama}</p>
                  <p className="text-[11px] text-[#6c757d] font-mono">
                    NPP: {proposal.ketuaNpp} &bull; {proposal.prodiNama} ({proposal.fakultasNama})
                  </p>
                </div>
                <Badge variant="primary" pill>Ketua Pengusul</Badge>
              </div>

              {/* Anggota Dosen */}
              {proposal.anggotaDosen?.map((dosen, idx) => (
                <div key={idx} className="p-3 bg-[#f6f7fb] border border-[#e7e9eb] rounded-xl flex items-center justify-between">
                  <div>
                    <span className="text-[10px] font-semibold text-[#98a6ad] uppercase tracking-wider block">
                      Anggota Dosen #{idx + 1}
                    </span>
                    <p className="font-semibold text-[#313a46]">{dosen.nama}</p>
                    <p className="text-[11px] text-[#6c757d] font-mono">
                      NPP: {dosen.npp} &bull; {dosen.prodi}
                    </p>
                  </div>
                  <span className="text-[11px] text-[#6c757d]">{dosen.tugas}</span>
                </div>
              ))}

              {/* Anggota Mahasiswa */}
              {proposal.anggotaMahasiswa?.map((mhs, idx) => (
                <div key={idx} className="p-2.5 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg text-[11px] flex items-center justify-between">
                  <div>
                    <span className="text-[#98a6ad] font-medium">Mahasiswa: </span>
                    <strong className="text-[#313a46]">{mhs.nama}</strong> ({mhs.nim}) - {mhs.prodi}
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

          {proposal.catatanDekan?.keputusan === 'TOLAK' && (
            <div className="p-3 rounded-xl bg-[#ff5b5b]/10 border border-[#ff5b5b]/30 text-xs space-y-1">
              <p className="font-semibold text-[#ff5b5b]">Usulan ditolak Dekan</p>
              {proposal.catatanDekan.catatan && <p className="text-[#6c757d]">{proposal.catatanDekan.catatan}</p>}
              {proposal.isKetua && (
                <p className="text-[#6c757d]">Lembar pengesahan direset. Isi dana penyertaan, generate, dan set final ulang.</p>
              )}
            </div>
          )}

          {/* Padanan tombol "Lembar Pengesahan" legacy: aktif selama usulan sudah diajukan, termasuk setelah ditolak Dekan. */}
          {[STATUS_USULAN.SUBMITTED, STATUS_USULAN.DITOLAK_DEKAN].includes(proposal.status) && (
            <KelengkapanPengajuanCard proposal={proposal} targets={rencanaTarget} />
          )}
        </div>

        {/* Right Column: Keuangan, SK & Pengesahan */}
        <div className="lg:col-span-4 space-y-5">
          {/* Anggaran Card */}
          <Card>
            <CardHeader title="Informasi Anggaran Hibah" />
            <CardContent className="space-y-3 text-xs">
              <div className="flex justify-between">
                <span className="text-[#98a6ad]">Usulan Biaya:</span>
                <strong className="font-tabular text-[#313a46]">{formatRupiah(proposal.biayaUsulan)}</strong>
              </div>
              <div className="flex justify-between">
                <span className="text-[#98a6ad]">Dana Disetujui (SK):</span>
                <strong className="font-tabular text-[#10c469] font-bold text-sm">
                  {proposal.biayaDisetujui != null ? formatRupiah(proposal.biayaDisetujui) : '-'}
                </strong>
              </div>

            </CardContent>
          </Card>

          {/* Lembar Pengesahan QR Code */}
          <Card className="text-center p-5 space-y-3">
            <h5 className="text-xs font-bold font-heading text-[#313a46]">Lembar Pengesahan Digital</h5>

            <div className="grid grid-cols-2 gap-3">
              <PengesahanQr
                label="Ketua Pengusul"
                signer={proposal.isLembarPengesahanFinal ? proposal.pengesahan?.ketua : null}
                emptyText="Menunggu lembar pengesahan final"
              />
              <PengesahanQr
                label="Dekan Fakultas"
                signer={proposal.pengesahan?.dekan}
                emptyText="Menunggu keputusan Dekan"
              />
            </div>

            <div className="space-y-1.5 pt-2">
              {/* Legacy UNDUH: khusus ketua, PDF, hanya setelah disetujui Dekan. */}
              {proposal.isKetua &&
                (proposal.catatanDekan?.keputusan === 'SETUJU' ? (
                  <>
                    <Button variant="secondary" size="xs" iconLeft={Download} className="w-full" onClick={() => unduh({ format: 'pdf' })}>
                      Unduh Lembar Pengesahan (.PDF)
                    </Button>
                    {proposal.adaDokumenProposal && (
                      <Button variant="primary" size="xs" iconLeft={Download} className="w-full" onClick={() => unduh({ format: 'pdf', gabung: true })}>
                        Proposal Lengkap + Pengesahan (.PDF)
                      </Button>
                    )}
                  </>
                ) : (
                  <p className="text-[10px] text-[#98a6ad]">Lembar pengesahan dapat diunduh setelah disetujui Dekan.</p>
                ))}
              {proposal.suratTugas?.isFinal && (
                <Button
                  variant="secondary"
                  size="xs"
                  iconLeft={Download}
                  className="w-full"
                  onClick={() => unduhSurat()}
                >
                  Unduh Surat Tugas No. {proposal.suratTugas.nomor} (.DOCX)
                </Button>
              )}
              {unduhError && <p className="text-[10px] text-[#ff5b5b]">{unduhError}</p>}
            </div>
          </Card>
        </div>
      </div>
    </div>
  )
}
