import React, { useState, useEffect } from 'react'
import { CheckCircle2, AlertCircle, ClipboardCheck, UserPlus } from 'lucide-react'
import { dekanApi } from '../../services/api/dekanApi'
import { Card } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Modal } from '../../components/ui/Modal'

/**
 * Padanan dkn/myphp/monevpenelitianpenunjukan.php legacy: Dekan menunjuk
 * reviewer monev untuk penelitian LOLOS yang sudah tuntas di fakultasnya.
 */
export default function PenunjukanMonevPage() {
  const [items, setItems] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [notice, setNotice] = useState('')
  const [error, setError] = useState('')
  const [selected, setSelected] = useState(null)
  const [kandidat, setKandidat] = useState([])
  const [kodeperson, setKodeperson] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)

  const loadData = async () => {
    setIsLoading(true)
    try {
      const res = await dekanApi.getMonevList()
      setItems(res.data)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [])

  const openPenunjukan = async (item) => {
    setError('')
    setSelected(item)
    setKodeperson(item.reviewerMonev?.kodeperson || '')
    setKandidat([])
    try {
      const res = await dekanApi.getMonevKandidat(item.id)
      setKandidat(res.data)
    } catch (e) {
      setError(e.message)
    }
  }

  const handleSimpan = async () => {
    setIsSubmitting(true)
    setError('')
    try {
      await dekanApi.assignMonev(selected.id, { kodeperson })
      setSelected(null)
      setNotice('Data penunjukan sudah disimpan.')
      setTimeout(() => setNotice(''), 4000)
      await loadData()
    } catch (e) {
      setError(Object.values(e.errors || {}).flat()[0] || e.message)
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <div className="space-y-6 text-left">
      <div className="pb-5 border-b border-[#e7e9eb]">
        <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">Penunjukan Reviewer Monev</h4>
        <p className="text-xs text-[#98a6ad] mt-0.5">
          Penelitian lolos yang sudah tuntas di fakultas Anda. Reviewer monev dipilih dari dosen GJM fakultas di luar tim peneliti.
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
          Belum ada penelitian tuntas yang perlu dimonev.
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
                    {item.isFinal && <Badge variant="success" pill>Monev Final</Badge>}
                  </div>
                  <h5 className="text-sm font-bold font-heading text-[#313a46] leading-snug">{item.judul}</h5>
                  <p className="text-[#6c757d] text-[11px]">
                    Reviewer monev:{' '}
                    <strong className="text-[#313a46]">
                      {item.reviewerMonev
                        ? `${item.reviewerMonev.nama}${item.reviewerMonev.prodi ? ` (${item.reviewerMonev.prodi})` : ''}`
                        : 'Belum ditunjuk'}
                    </strong>
                  </p>
                </div>
                <Button variant="soft-primary" size="sm" iconLeft={UserPlus} onClick={() => openPenunjukan(item)}>
                  {item.reviewerMonev ? 'Ganti Reviewer' : 'Tunjuk Reviewer'}
                </Button>
              </div>
            </Card>
          ))}
        </div>
      )}

      <Modal
        isOpen={!!selected}
        onClose={() => setSelected(null)}
        title="Penunjukan Reviewer Monev"
        subtitle={selected?.judul || ''}
        maxWidth="max-w-md"
        footer={
          <>
            <Button variant="secondary" size="sm" onClick={() => setSelected(null)} disabled={isSubmitting}>
              Batal
            </Button>
            <Button variant="primary" size="sm" onClick={handleSimpan} isLoading={isSubmitting}>
              Simpan
            </Button>
          </>
        }
      >
        <div className="space-y-3 text-xs">
          <label htmlFor="reviewer-monev" className="block font-semibold text-slate-700 mb-1">
            Reviewer Monev
          </label>
          <select
            id="reviewer-monev"
            value={kodeperson}
            onChange={(e) => setKodeperson(e.target.value)}
            className="w-full p-2 bg-white border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-base sm:text-xs"
          >
            <option value="">— Belum ditunjuk —</option>
            {kandidat.map((k) => (
              <option key={k.kodeperson} value={k.kodeperson}>
                {k.nama}{k.prodi ? ` (${k.prodi})` : ''}
              </option>
            ))}
          </select>
        </div>
      </Modal>
    </div>
  )
}
