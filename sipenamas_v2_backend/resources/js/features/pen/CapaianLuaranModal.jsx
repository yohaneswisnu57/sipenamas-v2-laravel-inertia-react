import React, { useState, useEffect } from 'react'
import { AlertCircle, CheckCircle2 } from 'lucide-react'
import { penelitiApi } from '../../services/api/penelitiApi'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Modal } from '../../components/ui/Modal'

const INPUT = 'w-full p-2 bg-white border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-base sm:text-xs'
const STATUS_TAYANG = ['BELUM SUBMIT', 'SUBMITTED', 'ACCEPTED (LOA)']

/**
 * Padanan jendela "TARGET CAPAIAN & LUARAN" legacy (khusus ketua, setelah
 * lembar pengesahan laporan akhir final): unggah bukti tiap target lalu
 * tandai realisasinya. Target ber-insentif memakai label "Published" dan
 * status tayang jurnal.
 */
// `data` adalah prop halaman (null selama dimuat; `{ error }` bila ditolak).
export default function CapaianLuaranModal({ penelitianId, data: dataProp, onClose }) {
  const [errorAksi, setError] = useState('')
  const error = dataProp?.error || errorAksi
  const data = dataProp?.error ? null : dataProp
  const [notice, setNotice] = useState('')
  const [busy, setBusy] = useState('')
  const [draft, setDraft] = useState({})

  // Draft isian per target diisi ulang dari data server setiap kali berubah.
  useEffect(() => {
    if (!data) return
    setDraft(
      Object.fromEntries(
        data.target.map((t) => [
          t.id,
          { realisasi: t.isRealisasi, keterangan: t.keterangan || '', statusTayang: t.statusTayang || '' },
        ])
      )
    )
  }, [data])

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

  const ubah = (id, patch) => setDraft((prev) => ({ ...prev, [id]: { ...prev[id], ...patch } }))

  return (
    <Modal
      isOpen
      onClose={onClose}
      title="Target Capaian & Luaran"
      subtitle={data?.judul || ''}
      maxWidth="max-w-3xl"
      footer={
        <Button variant="secondary" size="sm" onClick={onClose}>
          Tutup
        </Button>
      }
    >
      <div className="space-y-4 text-xs">
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

        {!data
          ? !error && <p className="py-8 text-center text-slate-500">Memuat data...</p>
          : data.target.map((t) => {
              const d = draft[t.id] || {}

              return (
                <div key={t.id} className="p-4 border border-slate-200 rounded-xl space-y-3">
                  <div className="flex flex-wrap items-center gap-1.5">
                    {t.isWajib && <Badge variant="danger" pill>Wajib</Badge>}
                    {t.isAdaInsentif && <Badge variant="info" pill>Insentif</Badge>}
                    {t.isRealisasi && <Badge variant="success" pill>{t.isAdaInsentif ? 'Published' : 'Realisasi'}</Badge>}
                  </div>
                  <p className="font-bold text-[#313a46]">
                    {t.kategori}
                    {t.subkategori ? ` — ${t.subkategori}` : ''}
                  </p>
                  {t.indikator && <p className="text-[#6c757d]">Indikator: {t.indikator}</p>}

                  <div className="flex flex-wrap items-center gap-2">
                    <input
                      type="file"
                      aria-label={`Dokumen ${t.kategori}`}
                      onChange={(e) => {
                        const file = e.target.files[0]
                        if (file) jalankan(`upload-${t.id}`, () => penelitiApi.uploadDokumenLuaran(penelitianId, t.id, file))
                        e.target.value = ''
                      }}
                      className="text-base sm:text-xs"
                    />
                    {t.adaDokumen && (
                      <>
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() => jalankan(`lihat-${t.id}`, () => penelitiApi.lihatDokumenLuaran(penelitianId, t.id))}
                        >
                          Lihat ({t.ekstensi})
                        </Button>
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() => jalankan(`hapus-${t.id}`, () => penelitiApi.deleteDokumenLuaran(penelitianId, t.id))}
                        >
                          Clear
                        </Button>
                      </>
                    )}
                  </div>

                  <label className="flex items-center gap-2 font-semibold text-slate-700">
                    <input type="checkbox" checked={!!d.realisasi} onChange={(e) => ubah(t.id, { realisasi: e.target.checked })} />
                    {t.isAdaInsentif ? 'PUBLISHED' : 'REALISASI'}
                  </label>

                  {t.isAdaInsentif && !d.realisasi && (
                    <div>
                      <label htmlFor={`tayang-${t.id}`} className="block font-semibold text-slate-700 mb-1">Status tayang</label>
                      <select id={`tayang-${t.id}`} value={d.statusTayang || ''} onChange={(e) => ubah(t.id, { statusTayang: e.target.value })} className={INPUT}>
                        <option value="">-</option>
                        {STATUS_TAYANG.map((s) => (
                          <option key={s} value={s}>{s}</option>
                        ))}
                      </select>
                    </div>
                  )}

                  <div>
                    <label htmlFor={`ket-${t.id}`} className="block font-semibold text-slate-700 mb-1">Keterangan hasil</label>
                    <input id={`ket-${t.id}`} value={d.keterangan || ''} onChange={(e) => ubah(t.id, { keterangan: e.target.value })} className={INPUT} />
                  </div>

                  <Button
                    variant="soft-primary"
                    size="sm"
                    isLoading={busy === `simpan-${t.id}`}
                    onClick={() => jalankan(`simpan-${t.id}`, () => penelitiApi.saveCapaianLuaran(penelitianId, t.id, d))}
                  >
                    Simpan
                  </Button>
                </div>
              )
            })}
      </div>
    </Modal>
  )
}
