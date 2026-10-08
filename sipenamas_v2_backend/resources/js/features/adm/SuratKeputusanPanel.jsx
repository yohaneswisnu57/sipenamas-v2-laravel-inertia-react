import React, { useState } from 'react'
import { FileText, Stamp } from 'lucide-react'
import { adminApi } from '../../services/api/adminApi'
import { Card, CardHeader } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'

/**
 * Proses massal surat (legacy adm/finalapproval.php GENFILESURAT + SETDOKFINAL):
 * usulan LOLOS mendapat Surat Tugas + Surat Pencairan Dana (2 nomor berurutan),
 * usulan tidak lolos mendapat Surat Tugas Penulisan Proposal (1 nomor).
 */
export default function SuratKeputusanPanel({ selectedIds, onDone }) {
  const [nomor, setNomor] = useState('')
  const [tanggal, setTanggal] = useState(new Date().toISOString().slice(0, 10))
  const [busy, setBusy] = useState('')
  const [pesan, setPesan] = useState({ ok: '', error: '' })
  const [konfirmasi, setKonfirmasi] = useState(null)

  const jalankan = async (aksi, fn) => {
    setBusy(aksi)
    setKonfirmasi(null)
    setPesan({ ok: '', error: '' })
    try {
      const res = await fn()
      setPesan({ ok: res?.message || 'Berhasil', error: '' })
      await onDone()
    } catch (e) {
      if (e.status === 409) {
        setKonfirmasi({
          pesan: `${e.message} Proses dilanjutkan?`,
          lanjut: () => jalankan('generate', () => generate(true)),
        })
      } else {
        setPesan({ ok: '', error: e.message })
      }
    } finally {
      setBusy('')
    }
  }

  const generate = (abaikanDuplikasi = false) =>
    adminApi.generateSurat({ ids: selectedIds, nomor: Number(nomor), tanggal, abaikanDuplikasi })

  const kosong = selectedIds.length === 0

  return (
    <Card>
      <CardHeader
        title="Generate Surat Tugas & Surat Pencairan Dana"
        subtitle={`${selectedIds.length} usulan dipilih. Lolos: Surat Tugas + SPD (2 nomor berurutan). Tidak lolos: Surat Tugas Penulisan Proposal.`}
      />
      <div className="p-4 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs items-end">
        <label className="block">
          <span className="block font-semibold text-slate-700 mb-1">Nomor awal</span>
          <input
            type="number"
            min="1"
            value={nomor}
            onChange={(e) => setNomor(e.target.value)}
            className="w-full px-3 py-2 border border-slate-300 rounded-md text-base sm:text-xs"
          />
        </label>
        <label className="block">
          <span className="block font-semibold text-slate-700 mb-1">Tanggal surat</span>
          <input
            type="date"
            value={tanggal}
            onChange={(e) => setTanggal(e.target.value)}
            className="w-full px-3 py-2 border border-slate-300 rounded-md text-base sm:text-xs"
          />
        </label>
        <div className="flex gap-2">
          <Button
            variant="primary"
            size="sm"
            iconLeft={FileText}
            disabled={kosong || !nomor || !tanggal}
            isLoading={busy === 'generate'}
            onClick={() => jalankan('generate', () => generate())}
          >
            Generate
          </Button>
          <Button
            variant="secondary"
            size="sm"
            iconLeft={Stamp}
            disabled={kosong}
            isLoading={busy === 'final'}
            onClick={() =>
              setKonfirmasi({
                pesan: 'Proses ini akan membuat status dokumen surat FINAL (permanen). Lanjut?',
                lanjut: () => jalankan('final', () => adminApi.finalSurat(selectedIds)),
              })
            }
          >
            Set Final
          </Button>
        </div>
      </div>
      {konfirmasi && (
        <div className="mx-4 mb-4 p-3 rounded-lg border border-amber-300 bg-amber-50 text-xs flex flex-col sm:flex-row sm:items-center gap-2">
          <span className="flex-1 font-semibold text-amber-800">{konfirmasi.pesan}</span>
          <div className="flex gap-2">
            <Button variant="primary" size="xs" onClick={konfirmasi.lanjut}>
              Ya, lanjutkan
            </Button>
            <Button variant="secondary" size="xs" onClick={() => setKonfirmasi(null)}>
              Batal
            </Button>
          </div>
        </div>
      )}
      {(pesan.ok || pesan.error) && (
        <p className={`px-4 pb-4 text-xs font-semibold ${pesan.error ? 'text-[#ff5b5b]' : 'text-[#0c8a49]'}`}>
          {pesan.error || pesan.ok}
        </p>
      )}
    </Card>
  )
}
