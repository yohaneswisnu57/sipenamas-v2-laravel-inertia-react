import React, { useState } from 'react'
import { AlertCircle } from 'lucide-react'
import { penelitiApi } from '../../services/api/penelitiApi'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Modal } from '../../components/ui/Modal'

/**
 * Padanan jendela "KUESIONER PENELITIAN" legacy: satu pengisian per orang
 * per periode. Jawaban disimpan begitu dipilih.
 */
// `data` adalah prop halaman (null selama dimuat; `{ error }` bila gagal).
export default function KuesionerPenelitianModal({ kdperiode, data: dataProp, onClose }) {
  const [errorAksi, setError] = useState('')
  const error = dataProp?.error || errorAksi
  const data = dataProp?.error ? null : dataProp

  const handleJawab = async (detailId, jawab) => {
    setError('')
    try {
      await penelitiApi.saveKuesionerJawaban(detailId, jawab)
    } catch (e) {
      setError(Object.values(e.errors || {}).flat()[0] || e.message)
    }
  }

  return (
    <Modal
      isOpen
      onClose={onClose}
      title="Kuesioner Penelitian"
      subtitle={`Periode ${kdperiode}`}
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

        {!data ? (
          !error && <p className="py-8 text-center text-slate-500">Memuat kuesioner...</p>
        ) : (
          <>
            <div className="flex items-center justify-between gap-2">
              <p className="text-[#6c757d]">Pilih satu jawaban untuk setiap pernyataan.</p>
              <Badge variant={data.isDone ? 'success' : 'warning'} pill>
                {data.isDone ? 'Lengkap' : 'Belum Lengkap'}
              </Badge>
            </div>

            {['A', 'B', 'C'].map((kelompok) => {
              const pertanyaan = data.pertanyaan.filter((p) => p.kelompok === kelompok)
              if (pertanyaan.length === 0) return null

              return (
                <fieldset key={kelompok} className="space-y-3">
                  <legend className="font-bold text-[#313a46]">
                    {kelompok}. {data.kelompok[kelompok]}
                  </legend>
                  {pertanyaan.map((p) => (
                    <div key={p.id}>
                      <label htmlFor={`kuesioner-${p.id}`} className="block font-semibold text-slate-700 mb-1">
                        {p.nomor}. {p.uraian}
                      </label>
                      <select
                        id={`kuesioner-${p.id}`}
                        value={p.jawab || ''}
                        onChange={(e) => e.target.value && handleJawab(p.id, e.target.value)}
                        className="w-full p-2 bg-white border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-base sm:text-xs"
                      >
                        <option value="">Pilih jawaban...</option>
                        {data.pilihan.map((pilihan) => (
                          <option key={pilihan.kode} value={pilihan.kode}>
                            {pilihan.label}
                          </option>
                        ))}
                      </select>
                    </div>
                  ))}
                </fieldset>
              )
            })}
          </>
        )}
      </div>
    </Modal>
  )
}
