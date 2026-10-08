import React, { useState, useEffect } from 'react'
import { CheckCircle2, AlertCircle, ArrowLeft, ClipboardCheck, PencilLine } from 'lucide-react'
import { Link } from '@/lib/router'
import { penelitiApi } from '../../services/api/penelitiApi'
import { muatJendela } from '../../lib/inertiaRequest'
import { Card } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Modal } from '../../components/ui/Modal'

/**
 * Padanan pen/myphp/monevhasilpenelitian.php legacy: reviewer monev
 * tunjukan Dekan mengisi borang monev (8 soal pilihan) dan kesimpulan.
 */
// `borang` (prop) terisi saat URL memuat `?borang={id}`; salinan lokalnya
// menampung jawaban yang baru dipilih.
export default function MonevHasilPage({ items = [], borang: borangProp = null }) {
  const isLoading = false
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [borang, setBorang] = useState(null)
  const [kesimpulan, setKesimpulan] = useState('')
  const [isFinal, setIsFinal] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)

  // Salin borang dari server saat borang lain dibuka; jawaban yang baru
  // dipilih tetap di state lokal walau props dimuat ulang sesudah simpan.
  useEffect(() => {
    if (borangProp) {
      setBorang(borangProp)
      setKesimpulan(borangProp.kesimpulan || '')
      setIsFinal(borangProp.isFinal)
    }
  }, [borangProp?.id])

  const openBorang = (id) => {
    setError('')
    muatJendela({ borang: id }, ['borang'])
  }

  const tutupBorang = () => {
    setBorang(null)
    muatJendela({ borang: null }, ['borang'])
  }

  const handleJawab = async (nomor, jawaban) => {
    if (!jawaban) return
    setError('')
    try {
      await penelitiApi.saveMonevJawaban(borang.id, { nomor, jawaban })
      setBorang((prev) => ({
        ...prev,
        soal: prev.soal.map((s) => (s.nomor === nomor ? { ...s, jawaban } : s)),
      }))
    } catch (e) {
      setError(Object.values(e.errors || {}).flat()[0] || e.message)
    }
  }

  const handleSimpan = async () => {
    setIsSubmitting(true)
    setError('')
    try {
      const res = await penelitiApi.saveMonevKesimpulan(borang.id, { kesimpulan, isFinal })
      setBorang(null)
      setNotice(res.message || 'Data sudah disimpan.')
      setTimeout(() => setNotice(''), 5000)
    } catch (e) {
      setError(Object.values(e.errors || {}).flat()[0] || e.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="space-y-6 text-left">
      <div className="pb-5 border-b border-[#e7e9eb]">
        <div className="flex items-center gap-2">
          <Link to="/pen/dashboard" className="text-[#98a6ad] hover:text-[#313a46]">
            <ArrowLeft className="w-4 h-4" />
          </Link>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">Monev Hasil Penelitian</h4>
        </div>
        <p className="text-xs text-[#98a6ad] mt-0.5">
          Penelitian yang ditugaskan Dekan kepada Anda sebagai reviewer monev.
        </p>
      </div>

      {notice && (
        <div className="p-3.5 bg-[#10c469]/10 border border-[#10c469]/20 rounded-xl text-xs flex items-center gap-2">
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

      {isLoading ? (
        <p className="py-12 text-center text-xs text-slate-500">Memuat data...</p>
      ) : items.length === 0 ? (
        <Card className="p-8 text-center text-xs text-[#98a6ad]">
          <ClipboardCheck className="w-8 h-8 mx-auto mb-2" />
          Tidak ada penugasan monev.
        </Card>
      ) : (
        <div className="space-y-4">
          {items.map((item) => (
            <Card key={item.id} className="p-5">
              <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div className="space-y-1.5 max-w-2xl text-xs">
                  <div className="flex flex-wrap items-center gap-1.5">
                    {item.skim && <Badge variant="primary" pill>{item.skim}</Badge>}
                    {item.tahun && <Badge variant="neutral" pill>{item.tahun}</Badge>}
                    <Badge variant={item.isFinal ? 'success' : 'warning'} pill>
                      {item.isFinal ? 'Final' : 'Belum Final'}
                    </Badge>
                  </div>
                  <h5 className="text-sm font-bold font-heading text-[#313a46] leading-snug">{item.judul}</h5>
                  {item.ketua && (
                    <p className="text-[#6c757d] text-[11px]">
                      Ketua: <strong className="text-[#313a46]">{item.ketua}</strong>
                    </p>
                  )}
                </div>
                <Button variant="soft-primary" size="sm" iconLeft={PencilLine} onClick={() => openBorang(item.id)}>
                  Isi Borang Monev
                </Button>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal
        isOpen={!!borang}
        onClose={tutupBorang}
        title="Borang Monev Hasil Penelitian"
        subtitle={borang?.judul || ''}
        maxWidth="max-w-3xl"
        footer={
          <>
            <Button variant="secondary" size="sm" onClick={tutupBorang} disabled={isSubmitting}>
              Tutup
            </Button>
            <Button variant="primary" size="sm" onClick={handleSimpan} isLoading={isSubmitting}>
              Simpan
            </Button>
          </>
        }
      >
        {borang && (
          <div className="space-y-4 text-xs">
            {borang.soal.map((soal) => (
              <div key={soal.nomor}>
                <label htmlFor={`soal-${soal.nomor}`} className="block font-semibold text-slate-700 mb-1">
                  {soal.nomor}. {soal.aspek}
                </label>
                <select
                  id={`soal-${soal.nomor}`}
                  value={soal.jawaban || ''}
                  onChange={(e) => handleJawab(soal.nomor, e.target.value)}
                  className="w-full p-2 bg-white border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-base sm:text-xs"
                >
                  <option value="">Pilih jawaban...</option>
                  {soal.pilihan.map((p) => (
                    <option key={p.kode} value={p.kode}>
                      {p.kode}. {p.label}
                    </option>
                  ))}
                </select>
              </div>
            ))}

            <div>
              <label htmlFor="kesimpulan-monev" className="block font-semibold text-slate-700 mb-1">
                Kesimpulan
              </label>
              <textarea
                id="kesimpulan-monev"
                rows={4}
                value={kesimpulan}
                onChange={(e) => setKesimpulan(e.target.value)}
                className="w-full p-2.5 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-base sm:text-xs"
              />
            </div>

            <label className="flex items-center gap-2 font-semibold text-slate-700">
              <input type="checkbox" checked={isFinal} onChange={(e) => setIsFinal(e.target.checked)} />
              Final (semua soal wajib terjawab)
            </label>
          </div>
        )}
      </Modal>
    </div>
  )
}
