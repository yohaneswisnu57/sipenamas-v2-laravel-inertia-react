import React, { useState, useEffect } from 'react'
import { AlertCircle, CheckCircle2, Trash2 } from 'lucide-react'
import { penelitiApi } from '../../services/api/penelitiApi'
import { masterDataApi } from '../../services/api/masterDataApi'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Modal } from '../../components/ui/Modal'

const INPUT = 'w-full p-2 bg-white border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-base sm:text-xs'

/**
 * Padanan jendela "KELENGKAPAN LAPORAN AKHIR PENELITIAN" legacy (khusus
 * ketua): dana penyertaan, mahasiswa terlibat, lalu lembar pengesahan
 * laporan akhir - generate, set final, unduh setelah disetujui Dekan.
 */
// `data` adalah prop halaman (null selama dimuat; `{ error }` bila ditolak).
export default function KelengkapanLaporanModal({ penelitianId, data: dataProp, onClose }) {
  const [errorAksi, setError] = useState('')
  const error = dataProp?.error || errorAksi
  const data = dataProp?.error ? null : dataProp
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState('')
  const [danaMitra, setDanaMitra] = useState('')
  const [danaInkind, setDanaInkind] = useState('')
  const [cariMhs, setCariMhs] = useState('')
  const [hasilMhs, setHasilMhs] = useState([])
  const [konfirmasiFinal, setKonfirmasiFinal] = useState(false)

  // Isi ulang input dana saat data (dari server) berganti.
  useEffect(() => {
    if (data) {
      setDanaMitra(data.danaMitra)
      setDanaInkind(data.danaInkind)
    }
  }, [data?.id, data?.danaMitra, data?.danaInkind])

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
    }
  }

  const handleCariMhs = async () => {
    if (!cariMhs.trim()) return
    const res = await masterDataApi.searchMahasiswa(cariMhs)
    setHasilMhs(res.data)
  }

  const isFinal = data?.isLembarPengesahanFinal

  return (
    <Modal
      isOpen
      onClose={onClose}
      title="Kelengkapan Laporan Akhir Penelitian"
      subtitle={data?.judul || ''}
      maxWidth="max-w-3xl"
      footer={
        <Button variant="secondary" size="sm" onClick={onClose}>
          Tutup
        </Button>
      }
    >
      <div className="space-y-5 text-xs">
        {error && (
          <div className="p-3 bg-[#ff5b5b]/10 border border-[#ff5b5b]/20 rounded-xl flex items-center gap-2">
            <AlertCircle className="w-4 h-4 text-[#ff5b5b] shrink-0" />
            <span className="font-semibold text-[#ff5b5b]">{error}</span>
          </div>
        )}
        {notice && (
          <div className="p-3 bg-[#10c469]/10 border border-[#10c469]/20 rounded-xl flex items-center gap-2">
            <CheckCircle2 className="w-4 h-4 text-[#10c469] shrink-0" />
            <span className="font-semibold text-[#0c8a49]">{notice}</span>
          </div>
        )}

        {!data ? (
          !error && <p className="py-8 text-center text-slate-500">Memuat data...</p>
        ) : (
          <>
            <section className="space-y-2">
              <h5 className="font-bold text-[#313a46]">Dana Penyertaan</h5>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label htmlFor="dana-mitra" className="block font-semibold text-slate-700 mb-1">Dana mitra (Rp)</label>
                  <input id="dana-mitra" type="number" min="0" value={danaMitra} onChange={(e) => setDanaMitra(e.target.value)} className={INPUT} />
                </div>
                <div>
                  <label htmlFor="dana-inkind" className="block font-semibold text-slate-700 mb-1">Dana inkind (Rp)</label>
                  <input id="dana-inkind" type="number" min="0" value={danaInkind} onChange={(e) => setDanaInkind(e.target.value)} className={INPUT} />
                </div>
              </div>
              <Button
                variant="soft-primary"
                size="sm"
                isLoading={busy === 'dana'}
                onClick={() => jalankan('dana', () => penelitiApi.saveDanaPenyertaanLaporan(penelitianId, { danaMitra, danaInkind }))}
              >
                Simpan Dana Penyertaan
              </Button>
            </section>

            <section className="space-y-2">
              <h5 className="font-bold text-[#313a46]">Mahasiswa Terlibat</h5>
              {data.mahasiswa.length === 0 ? (
                <p className="text-[#98a6ad]">Belum ada mahasiswa.</p>
              ) : (
                <ul className="divide-y divide-slate-200 border border-slate-200 rounded-md">
                  {data.mahasiswa.map((m) => (
                    <li key={m.id} className="flex items-center justify-between gap-2 px-3 py-2">
                      <span>
                        {m.nama || '-'} ({m.nim})
                      </span>
                      {!isFinal && (
                        <button
                          type="button"
                          aria-label={`Hapus ${m.nim}`}
                          className="text-[#ff5b5b] hover:text-[#d94848]"
                          onClick={() => jalankan('mhs', () => penelitiApi.deleteMahasiswaLaporan(penelitianId, m.id))}
                        >
                          <Trash2 className="w-4 h-4" />
                        </button>
                      )}
                    </li>
                  ))}
                </ul>
              )}
              {!isFinal && (
                <>
                  <div className="flex gap-2">
                    <input
                      aria-label="Cari mahasiswa"
                      placeholder="Cari nama atau NIM..."
                      value={cariMhs}
                      onChange={(e) => setCariMhs(e.target.value)}
                      onKeyDown={(e) => e.key === 'Enter' && handleCariMhs()}
                      className={INPUT}
                    />
                    <Button variant="secondary" size="sm" onClick={handleCariMhs}>
                      Cari
                    </Button>
                  </div>
                  {hasilMhs.length > 0 && (
                    <ul className="divide-y divide-slate-200 border border-slate-200 rounded-md max-h-40 overflow-y-auto">
                      {hasilMhs.map((m) => (
                        <li key={m.nim} className="flex items-center justify-between gap-2 px-3 py-2">
                          <span>
                            {m.nama} ({m.nim})
                          </span>
                          <Button
                            variant="soft-primary"
                            size="sm"
                            onClick={() => jalankan('mhs', () => penelitiApi.addMahasiswaLaporan(penelitianId, m.nim))}
                          >
                            Tambah
                          </Button>
                        </li>
                      ))}
                    </ul>
                  )}
                </>
              )}
            </section>

            <section className="space-y-2">
              <div className="flex items-center gap-2">
                <h5 className="font-bold text-[#313a46]">Lembar Pengesahan Laporan Akhir</h5>
                {isFinal ? (
                  <Badge variant="success" pill>Final</Badge>
                ) : (
                  data.adaLembarPengesahan && <Badge variant="warning" pill>Belum Final</Badge>
                )}
                {data.isDisetujuiDekan && <Badge variant="primary" pill>Disetujui Dekan</Badge>}
              </div>

              {konfirmasiFinal ? (
                <div className="p-3 bg-[#f9c851]/20 border border-[#f9c851]/40 rounded-xl space-y-2">
                  <p className="font-semibold text-[#ab7405]">
                    Proses ini membuat lembar pengesahan berstatus FINAL dan tidak dapat diedit lagi. Lanjut?
                  </p>
                  <div className="flex gap-2">
                    <Button
                      variant="primary"
                      size="sm"
                      isLoading={busy === 'final'}
                      onClick={() =>
                        jalankan('final', () => penelitiApi.finalLembarPengesahanLaporan(penelitianId)).then(() => setKonfirmasiFinal(false))
                      }
                    >
                      Ya, Set Final
                    </Button>
                    <Button variant="secondary" size="sm" onClick={() => setKonfirmasiFinal(false)}>
                      Batal
                    </Button>
                  </div>
                </div>
              ) : (
                <div className="flex flex-wrap gap-2">
                  <Button
                    variant="soft-primary"
                    size="sm"
                    disabled={isFinal}
                    isLoading={busy === 'generate'}
                    onClick={() => jalankan('generate', () => penelitiApi.generateLembarPengesahanLaporan(penelitianId))}
                  >
                    Generate Dokumen
                  </Button>
                  <Button variant="soft-primary" size="sm" disabled={isFinal || !data.adaLembarPengesahan} onClick={() => setKonfirmasiFinal(true)}>
                    Set Final
                  </Button>
                  <Button
                    variant="secondary"
                    size="sm"
                    disabled={!data.adaLembarPengesahan}
                    isLoading={busy === 'preview'}
                    onClick={() => jalankan('preview', () => penelitiApi.unduhLembarPengesahanLaporan(penelitianId, { preview: true }))}
                  >
                    Pratinjau
                  </Button>
                  <Button
                    variant="secondary"
                    size="sm"
                    disabled={!data.adaLembarPengesahan}
                    isLoading={busy === 'unduh'}
                    onClick={() => jalankan('unduh', () => penelitiApi.unduhLembarPengesahanLaporan(penelitianId))}
                  >
                    Unduh Dokumen
                  </Button>
                </div>
              )}
              <p className="text-[#98a6ad]">Dokumen baru bisa diunduh setelah disetujui Dekan.</p>
            </section>
          </>
        )}
      </div>
    </Modal>
  )
}
