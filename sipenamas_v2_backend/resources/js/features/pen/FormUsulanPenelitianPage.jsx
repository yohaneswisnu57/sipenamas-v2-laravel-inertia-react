import React, { useEffect, useState, useRef } from 'react'
import { useNavigate } from '@/lib/router'
import {
  FileText,
  Users2,
  Coins,
  Award,
  UploadCloud,
  CheckCircle2,
  Save,
  ArrowLeft,
  ArrowRight,
  Plus,
  Trash2,
  RotateCcw,
  Sparkles,
  AlertCircle,
  Search,
  User,
} from 'lucide-react'
import { useAuthStore } from '../../store/authStore'
import { penelitiApi } from '../../services/api/penelitiApi'
import { masterDataApi } from '../../services/api/masterDataApi'
import { formatRupiah } from '../../utils/formatters'
import { Card, CardHeader, CardContent, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'



function DosenAnggotaCombobox({ onSelect, excludedNpps = [] }) {
  const [query, setQuery] = useState('')
  const [results, setResults] = useState([])
  const [isOpen, setIsOpen] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const wrapperRef = useRef(null)

  useEffect(() => {
    function handleClickOutside(event) {
      if (wrapperRef.current && !wrapperRef.current.contains(event.target)) {
        setIsOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  useEffect(() => {
    let active = true
    setIsLoading(true)
    masterDataApi
      .searchDosen(query)
      .then((res) => {
        if (!active) return
        const list = res.data || []
        const filtered = list.filter(
          (d) =>
            !excludedNpps.includes(d.npp) &&
            !d.npp?.toUpperCase().startsWith('XADM') &&
            !d.npp?.toLowerCase().startsWith('superadmin')
        )
        setResults(filtered)
        setIsLoading(false)
      })
      .catch(() => {
        if (active) {
          setResults([])
          setIsLoading(false)
        }
      })
    return () => {
      active = false
    }
  }, [query, excludedNpps.join(',')])

  return (
    <div ref={wrapperRef} className="relative w-full">
      <div className="relative">
        <Search className="w-4 h-4 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
        <input
          type="text"
          placeholder="Cari Dosen (Nama / NIDN / NIK)..."
          value={query}
          onChange={(e) => {
            setQuery(e.target.value)
            setIsOpen(true)
          }}
          onFocus={() => setIsOpen(true)}
          className="w-full pl-8 pr-3 py-2 text-base sm:text-sm border border-slate-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-[#188ae2]"
        />
      </div>

      {isOpen && (
        <div className="absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-md shadow-lg max-h-56 overflow-y-auto">
          {isLoading ? (
            <div className="p-3 text-xs text-slate-500 text-center">Mencari dosen...</div>
          ) : results.length === 0 ? (
            <div className="p-3 text-xs text-slate-500 text-center">
              {query ? 'Dosen tidak ditemukan' : 'Ketik nama atau NPP untuk mencari'}
            </div>
          ) : (
            results.map((d) => (
              <button
                key={d.npp}
                type="button"
                onClick={() => {
                  onSelect(d)
                  setIsOpen(false)
                  setQuery('')
                }}
                className="w-full text-left px-3 py-2 text-xs hover:bg-blue-50 border-b border-slate-100 last:border-0 flex items-center justify-between"
              >
                <div>
                  <div className="font-semibold text-slate-800">{d.nama}</div>
                  <div className="text-[11px] text-slate-500">{d.prodi || 'Program Studi -'}</div>
                </div>
                <span className="font-mono text-[10px] bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded border border-slate-200 shrink-0 ml-2">
                  {d.npp}
                </span>
              </button>
            ))
          )}
        </div>
      )}
    </div>
  )
}

const HONORARIUM_PERSEN = 30
const KOMPOSISI_FIELDS = [
  { key: 'komposisiBahanPeralatan', label: 'Bahan & Peralatan Penelitian', max: 70 },
  { key: 'komposisiPerjalanan', label: 'Biaya Perjalanan', max: 40 },
  { key: 'komposisiLaporan', label: 'Laporan', max: 5 },
  { key: 'komposisiHonorarium', label: 'Honorarium', max: null },
]

/**
 * Props dari server: opsi master data, periode aktif, dan `usulan` (draft yang
 * diedit, null untuk usulan baru). Sesudah simpan server mengarahkan ke daftar.
 */
export default function FormUsulanPenelitianPage({
  isAbdimas = false,
  usulan = null,
  periodeAktif = null,
  skimOptions = [],
  fakultasOptions = [],
  sumberDanaOptions = [],
}) {
  const navigate = useNavigate()
  const DRAFT_STORAGE_KEY = isAbdimas ? 'sipenamas_draft_abdimas_v1' : 'sipenamas_draft_usulan_v1'
  const editId = usulan?.id
  const isEdit = Boolean(usulan)
  const { user: currentUser } = useAuthStore()
  const [currentStep, setCurrentStep] = useState(1)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [hasDraftNotice, setHasDraftNotice] = useState(false)
  const [submitError, setSubmitError] = useState(null)

  // Wizard state. Mode edit (padanan tombol Edit legacy) mulai dari draft
  // server; usulan baru memakai opsi pertama tiap dropdown.
  const [formData, setFormData] = useState(() => ({
    judul: usulan?.judul || '',
    skimKode: usulan?.skimKode || skimOptions[0]?.kode || '',
    sumberDanaKode: usulan?.sumberDanaKode || sumberDanaOptions[0]?.kode || '',
    fakultasKode: fakultasOptions[0]?.kode || '',
    bidangFokus: usulan?.bidangFokus || '',
    tempatLokasi: usulan?.tempatLokasi || '',
    biayaUsulan: usulan?.biayaUsulan || 0,
    targetLuaran: usulan?.targetLuaran || '',
    ringkasan: usulan?.ringkasan || '',
    anggotaDosen: usulan?.anggotaDosen || [],
    anggotaMahasiswa: usulan?.anggotaMahasiswa || [],
    mitra: usulan?.mitra || [],
    komposisiBahanPeralatan: usulan?.komposisiBahanPeralatan || 0,
    komposisiPerjalanan: usulan?.komposisiPerjalanan || 0,
    komposisiLaporan: usulan?.komposisiLaporan || 0,
  }))

  // Check saved draft on mount
  useEffect(() => {
    if (isEdit) return
    try {
      const saved = localStorage.getItem(DRAFT_STORAGE_KEY)
      if (saved) {
        const parsed = JSON.parse(saved)
        if (parsed && parsed.judul && parsed.judul.trim().length > 0) {
          setHasDraftNotice(true)
        }
      }
    } catch {
      // Ignore storage errors
    }
  }, [])

  // Auto-save draft when formData changes
  useEffect(() => {
    if (!isEdit && formData.judul && formData.judul.trim().length > 0) {
      try {
        localStorage.setItem(DRAFT_STORAGE_KEY, JSON.stringify(formData))
      } catch {
        // Storage full or unavailable
      }
    }
  }, [formData])

  const restoreDraft = () => {
    try {
      const saved = localStorage.getItem(DRAFT_STORAGE_KEY)
      if (saved) {
        setFormData(JSON.parse(saved))
        setHasDraftNotice(false)
      }
    } catch {
      setHasDraftNotice(false)
    }
  }

  const discardDraft = () => {
    try {
      localStorage.removeItem(DRAFT_STORAGE_KEY)
      setHasDraftNotice(false)
    } catch {
      setHasDraftNotice(false)
    }
  }

  // Komposisi dana (padanan legacy doSimpan): batas per kategori, total 100%.
  const selectedSkim = skimOptions.find(s => s.kode === formData.skimKode)
  const maxDana = selectedSkim?.maxDana || 0

  const totalKomposisi =
    HONORARIUM_PERSEN +
    Number(formData.komposisiBahanPeralatan || 0) +
    Number(formData.komposisiPerjalanan || 0) +
    Number(formData.komposisiLaporan || 0)
  const komposisiError = (() => {
    if (maxDana > 0 && Number(formData.biayaUsulan) > maxDana) return `Jumlah Dana melebihi batas maksimal skim (Maks. ${formatRupiah(maxDana)})`
    if (Number(formData.komposisiBahanPeralatan) > 70) return 'Bahan & Peralatan maksimal 70%'
    if (Number(formData.komposisiPerjalanan) > 40) return 'Biaya Perjalanan maksimal 40%'
    if (Number(formData.komposisiLaporan) > 5) return 'Laporan maksimal 5%'
    if (Math.abs(totalKomposisi - 100) > 0.001) return 'Komposisi dana harus berjumlah 100 persen'
    return null
  })()

  // Member helper
  const addDosen = () => {
    setFormData({
      ...formData,
      anggotaDosen: [...formData.anggotaDosen, { nama: '', npp: '', prodi: '', tugas: '' }],
    })
  }

  const removeDosen = (idx) => {
    setFormData({
      ...formData,
      anggotaDosen: formData.anggotaDosen.filter((_, i) => i !== idx),
    })
  }

  // Mitra (anggota eksternal luar institusi) - diisi manual, bukan
  // lookup ke database dosen internal (tidak punya KODEPERSON).
  const addMitra = () => {
    setFormData({
      ...formData,
      mitra: [...formData.mitra, { nama: '', instansi: '', tugas: '' }],
    })
  }

  const removeMitra = (idx) => {
    setFormData({
      ...formData,
      mitra: formData.mitra.filter((_, i) => i !== idx),
    })
  }

  // `ajukan = false` menyimpan draft (padanan checkbox "ajukan" legacy);
  // begitu diajukan usulan tidak bisa diedit lagi.
  const handleSubmit = async (ajukan = true) => {
    if (komposisiError) {
      setSubmitError({ message: komposisiError, details: [] })
      setCurrentStep(3)
      return
    }
    setIsSubmitting(true)
    setSubmitError(null)
    try {
      const payload = {
        ...formData,
        ajukan,
      }
      if (isEdit) {
        await penelitiApi.updatePenelitian(editId, payload)
      } else {
        await penelitiApi.createPenelitian(payload)
      }
      // Server sudah mengarahkan ke daftar usulan.
      try {
        localStorage.removeItem(DRAFT_STORAGE_KEY)
      } catch {
        // Ignore
      }
    } catch (e) {
      setSubmitError({ message: e.message, details: Object.values(e.errors || {}).flat() })
    } finally {
      setIsSubmitting(false)
    }
  }

  const steps = [
    { num: 1, label: '1. Informasi Dasar', icon: FileText },
    { num: 2, label: '2. Tim Peneliti', icon: Users2 },
    { num: 3, label: '3. Komposisi Dana', icon: Coins },
    { num: 4, label: '4. Target Luaran', icon: Award },
    { num: 5, label: '5. Konfirmasi', icon: UploadCloud },
  ]

  return (
    <div className="space-y-6 text-left max-w-4xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            {isEdit ? 'Edit Draft Usulan ' + (isAbdimas ? 'Abdimas' : 'Penelitian') : 'Pengajuan Usulan ' + (isAbdimas ? 'Abdimas' : 'Penelitian') + ' Baru'}
          </h4>
          {periodeAktif && (
            <p className="text-xs text-[#98a6ad] mt-0.5">
              Periode aktif: {periodeAktif.nama || `TA ${periodeAktif.tahun}`}
            </p>
          )}
        </div>
        <Button
          variant="secondary"
          size="sm"
          iconLeft={ArrowLeft}
          onClick={() => navigate('/pen/dashboard')}
        >
          Batal
        </Button>
      </div>

      {/* Auto-save Draft Alert Notice */}
      {hasDraftNotice && (
        <div className="p-3.5 rounded-xl bg-[#188ae2]/10 border border-[#188ae2]/25 text-[#313a46] flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs shadow-2xs">
          <div className="flex items-center gap-2.5">
            <Sparkles className="w-4 h-4 text-[#188ae2] shrink-0" />
            <div>
              <span className="font-semibold text-[#188ae2]">Ditemukan draf usulan belum selesai</span>
              <span className="text-slate-600 ml-1.5 hidden md:inline">
                Sistem mendeteksi isian yang tersimpan otomatis dari sesi Anda sebelumnya.
              </span>
            </div>
          </div>
          <div className="flex items-center gap-2 shrink-0">
            <Button variant="primary" size="xs" onClick={restoreDraft} iconLeft={RotateCcw}>
              Pulihkan Draf
            </Button>
            <Button variant="ghost" size="xs" onClick={discardDraft} className="text-slate-500">
              Abaikan
            </Button>
          </div>
        </div>
      )}

      {/* Step Tabs */}
      <div className="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
        {steps.map((s) => {
          const Icon = s.icon
          const isCurrent = currentStep === s.num
          const isDone = currentStep > s.num
          return (
            <button
              key={s.num}
              type="button"
              onClick={() => setCurrentStep(s.num)}
              className={`p-3 rounded-xl border text-left cursor-pointer transition text-xs flex items-center gap-2 ${
                isCurrent
                  ? 'border-[#188ae2] bg-[#188ae2]/10 text-[#188ae2] font-bold ring-1 ring-[#188ae2]'
                  : isDone
                  ? 'border-[#10c469]/30 bg-[#10c469]/5 text-[#10c469] font-medium'
                  : 'border-[#e7e9eb] bg-white text-[#6c757d] hover:bg-[#f6f7fb]'
              }`}
            >
              <Icon className={`w-4 h-4 shrink-0 ${isCurrent ? 'text-[#188ae2]' : isDone ? 'text-[#10c469]' : 'text-[#98a6ad]'}`} />
              <span className="truncate font-heading">{s.label}</span>
            </button>
          )
        })}
      </div>

      {/* Form Content per Step */}
      <Card>
        <CardContent className="p-6 text-xs space-y-4">
          {/* STEP 1: Informasi Dasar */}
          {currentStep === 1 && (
            <div className="space-y-4">
              <h3 className="text-sm font-bold text-slate-900 pb-2 border-b border-slate-100">
                Langkah 1: Identitas dan Skema Usulan
              </h3>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">
                  {`Judul ${isAbdimas ? "Abdimas" : "Penelitian"} (Bahasa Indonesia) *`}
                </label>
                <textarea
                  rows={2}
                  required
                  value={formData.judul}
                  onChange={(e) => setFormData({ ...formData, judul: e.target.value })}
                  placeholder="Contoh: Pengembangan Prototipe Sensor Pintar Berbasis IoT untuk Monitoring Kualitas Air Tambak"
                  className="w-full p-2.5 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 text-xs"
                />
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">{`Skema ${isAbdimas ? "Abdimas" : "Penelitian"} *`}</label>
                  <select
                    value={formData.skimKode}
                    onChange={(e) => setFormData({ ...formData, skimKode: e.target.value })}
                    className="w-full p-2 border border-slate-300 rounded-md"
                  >
                    {skimOptions.map((s) => (
                      <option key={s.kode} value={s.kode}>
                        {s.nama}
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Fakultas Pengusul *</label>
                  <select
                    value={formData.fakultasKode}
                    onChange={(e) => setFormData({ ...formData, fakultasKode: e.target.value })}
                    className="w-full p-2 border border-slate-300 rounded-md"
                  >
                    {fakultasOptions.map((f) => (
                      <option key={f.kode} value={f.kode}>
                        {f.nama}
                      </option>
                    ))}
                  </select>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Bidang Fokus Riset *</label>
                  <input
                    type="text"
                    value={formData.bidangFokus}
                    onChange={(e) => setFormData({ ...formData, bidangFokus: e.target.value })}
                    className="w-full p-2 border border-slate-300 rounded-md"
                  />
                </div>
                {isAbdimas && (
                  <div>
                    <label className="block font-semibold text-slate-700 mb-1">Tempat/Lokasi Pelaksanaan *</label>
                    <input
                      type="text"
                      value={formData.tempatLokasi}
                      onChange={(e) => setFormData({ ...formData, tempatLokasi: e.target.value })}
                      className="w-full p-2 border border-slate-300 rounded-md"
                      placeholder="Contoh: Desa Suka Makmur"
                    />
                  </div>
                )}
              </div>
            </div>
          )}

          {/* STEP 2: Tim Peneliti */}
          {currentStep === 2 && (
            <div className="space-y-4">
              <div className="flex items-center justify-between pb-2 border-b border-slate-100">
                <h3 className="text-sm font-bold text-slate-900">
                  Langkah 2: Anggota Tim Dosen & Mahasiswa
                </h3>
                <Button variant="secondary" size="xs" iconLeft={Plus} onClick={addDosen}>
                  Tambah Anggota Dosen
                </Button>
              </div>

              <div className="space-y-3">
                <p className="font-semibold text-slate-700">Anggota Dosen Pendamping:</p>
                {formData.anggotaDosen.map((dosen, idx) => (
                  <div key={idx} className="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-3">
                    <div className="flex items-center justify-between">
                      <span className="font-bold text-slate-800 text-xs sm:text-sm">
                        Dosen Anggota #{idx + 1}
                      </span>
                      <button
                        type="button"
                        onClick={() => removeDosen(idx)}
                        className="text-rose-600 hover:text-rose-800 p-1"
                        title="Hapus Anggota"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>

                    {dosen.npp ? (
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 p-2.5 bg-white border border-slate-200 rounded-md">
                        <div className="flex items-center gap-2.5">
                          <div className="w-8 h-8 rounded-full bg-blue-50 text-[#188ae2] flex items-center justify-center font-bold text-xs shrink-0">
                            <User className="w-4 h-4" />
                          </div>
                          <div>
                            <div className="font-semibold text-slate-800 text-xs sm:text-sm">
                              {dosen.nama || dosen.npp}
                            </div>
                            <div className="text-[11px] text-slate-500 flex items-center gap-2 mt-0.5">
                              <span className="font-mono bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200 text-slate-700">
                                {dosen.npp}
                              </span>
                              {dosen.prodi && <span>{dosen.prodi}</span>}
                            </div>
                          </div>
                        </div>
                        <button
                          type="button"
                          onClick={() => {
                            const updated = [...formData.anggotaDosen]
                            updated[idx].npp = ''
                            updated[idx].nama = ''
                            updated[idx].prodi = ''
                            setFormData({ ...formData, anggotaDosen: updated })
                          }}
                          className="text-xs text-[#188ae2] hover:underline self-end sm:self-center font-medium"
                        >
                          Ganti Dosen
                        </button>
                      </div>
                    ) : (
                      <DosenAnggotaCombobox
                        onSelect={(d) => {
                          const updated = [...formData.anggotaDosen]
                          updated[idx].nama = d.nama
                          updated[idx].npp = d.npp
                          updated[idx].prodi = d.prodi || ''
                          setFormData({ ...formData, anggotaDosen: updated })
                        }}
                        excludedNpps={[
                          currentUser?.kodeperson,
                          currentUser?.id,
                          ...formData.anggotaDosen.map((a) => a.npp).filter(Boolean),
                        ].filter(Boolean)}
                      />
                    )}

                    <input
                      type="text"
                      placeholder="Tugas dalam Penelitian"
                      value={dosen.tugas}
                      onChange={(e) => {
                        const updated = [...formData.anggotaDosen]
                        updated[idx].tugas = e.target.value
                        setFormData({ ...formData, anggotaDosen: updated })
                      }}
                      className="w-full p-2 text-base sm:text-sm border border-slate-300 rounded-md bg-white focus:outline-none focus:ring-1 focus:ring-[#188ae2]"
                    />
                  </div>
                ))}
              </div>

              <div className="pt-2 border-t border-slate-100">
                <p className="font-semibold text-slate-700 mb-2">Mahasiswa Pembantu Riset / MBKM:</p>
                {formData.anggotaMahasiswa.map((mhs, idx) => (
                  <div key={idx} className="flex items-center gap-3 p-2 bg-slate-50 border border-slate-200 rounded-md">
                    <span className="font-medium text-slate-800">{mhs.nama}</span>
                    <span className="font-mono text-slate-500">({mhs.nim})</span>
                    <span className="text-slate-600">{mhs.prodi}</span>
                    <span className="text-slate-400 text-[10px] ml-auto">{mhs.peran}</span>
                  </div>
                ))}
              </div>

              <div className="pt-2 border-t border-slate-100">
                <div className="flex items-center justify-between pb-1">
                  <p className="font-semibold text-slate-700">Mitra (Anggota Eksternal Luar Institusi):</p>
                  <Button variant="secondary" size="xs" iconLeft={Plus} onClick={addMitra}>
                    Tambah Mitra
                  </Button>
                </div>
                <div className="space-y-3">
                  {formData.mitra.map((m, idx) => (
                    <div key={idx} className="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-2">
                      <div className="flex items-center justify-between">
                        <span className="font-bold text-slate-800">Mitra #{idx + 1}</span>
                        <button
                          type="button"
                          onClick={() => removeMitra(idx)}
                          className="text-rose-600 hover:text-rose-800"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      </div>
                      <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <input
                          type="text"
                          placeholder="Nama Mitra"
                          value={m.nama}
                          onChange={(e) => {
                            const updated = [...formData.mitra]
                            updated[idx].nama = e.target.value
                            setFormData({ ...formData, mitra: updated })
                          }}
                          className="p-1.5 border border-slate-300 rounded bg-white"
                        />
                        <input
                          type="text"
                          placeholder="Instansi Asal"
                          value={m.instansi}
                          onChange={(e) => {
                            const updated = [...formData.mitra]
                            updated[idx].instansi = e.target.value
                            setFormData({ ...formData, mitra: updated })
                          }}
                          className="p-1.5 border border-slate-300 rounded bg-white"
                        />
                        <input
                          type="text"
                          placeholder="Tugas dalam Penelitian"
                          value={m.tugas}
                          onChange={(e) => {
                            const updated = [...formData.mitra]
                            updated[idx].tugas = e.target.value
                            setFormData({ ...formData, mitra: updated })
                          }}
                          className="p-1.5 border border-slate-300 rounded bg-white"
                        />
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}

          {/* STEP 3: Komposisi Dana (padanan legacy: persen per kategori, total 100%) */}
          {currentStep === 3 && (
            <div className="space-y-4">
              <h3 className="text-sm font-bold text-slate-900 pb-2 border-b border-slate-100">
                Langkah 3: Jumlah Dana & Komposisi Dana
              </h3>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">Sumber Dana *</label>
                <select
                  value={formData.sumberDanaKode}
                  onChange={(e) => setFormData({ ...formData, sumberDanaKode: e.target.value })}
                  className="w-full sm:w-64 p-2 text-base sm:text-sm border border-slate-300 rounded-md bg-white"
                >
                  <option value="" disabled>-- Pilih Sumber Dana --</option>
                  {sumberDanaOptions.map((o) => (
                    <option key={o.kode} value={o.kode}>
                      {o.nama}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">Jumlah Dana (Rp) *</label>
                <input
                  type="text"
                  value={formData.biayaUsulan ? formData.biayaUsulan.toLocaleString('id-ID') : ''}
                  onChange={(e) => {
                    const raw = e.target.value.replace(/\D/g, '')
                    setFormData({ ...formData, biayaUsulan: Number(raw) })
                  }}
                  className={`w-full sm:w-64 p-2 text-base sm:text-sm border rounded-md font-tabular text-right ${maxDana > 0 && formData.biayaUsulan > maxDana ? 'border-red-500' : 'border-slate-300'}`}
                />
                {maxDana > 0 && (
                  <p className={`text-xs mt-1 ${formData.biayaUsulan > maxDana ? 'text-red-500 font-semibold' : 'text-slate-500'}`}>
                    Maksimal dana sesuai skim: {formatRupiah(maxDana)}
                  </p>
                )}
              </div>

              <div className="space-y-2">
                {KOMPOSISI_FIELDS.map((f) => (
                  <div key={f.key} className="flex flex-wrap items-center gap-2">
                    <span className="w-56 font-medium text-slate-700">{f.label}</span>
                    <input
                      type="number"
                      min="0"
                      max={f.max ?? undefined}
                      step="any"
                      value={f.key === 'komposisiHonorarium' ? HONORARIUM_PERSEN : formData[f.key]}
                      readOnly={f.key === 'komposisiHonorarium'}
                      onChange={(e) => setFormData({ ...formData, [f.key]: Number(e.target.value) })}
                      className={`w-24 p-2 text-base sm:text-sm border border-slate-300 rounded-md font-tabular text-right ${
                        f.key === 'komposisiHonorarium' ? 'bg-slate-100 text-slate-500' : 'bg-white'
                      }`}
                    />
                    <span className="text-slate-500">%</span>
                    <span className="text-slate-400 text-[11px]">{f.max ? `(Maks ${f.max}%)` : '(Tetap)'}</span>
                    <span className="ml-auto font-tabular text-slate-700">
                      {formatRupiah(((f.key === 'komposisiHonorarium' ? HONORARIUM_PERSEN : Number(formData[f.key] || 0)) / 100) * Number(formData.biayaUsulan || 0))}
                    </span>
                  </div>
                ))}
              </div>

              <div
                className={`p-3 rounded-lg border text-xs font-semibold ${
                  komposisiError ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-emerald-50 border-emerald-200 text-emerald-800'
                }`}
              >
                Total komposisi: {totalKomposisi}% {komposisiError ? `— ${komposisiError}` : '(sesuai, 100%)'}
              </div>
            </div>
          )}

          {/* STEP 4: Target Luaran */}
          {currentStep === 4 && (
            <div className="space-y-4">
              <h3 className="text-sm font-bold text-slate-900 pb-2 border-b border-slate-100">
                Langkah 4: Ringkasan & Target Publikasi Utama
              </h3>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">
                  Target Publikasi Ilmiah Utama
                </label>
                <input
                  type="text"
                  value={formData.targetLuaran}
                  onChange={(e) => setFormData({ ...formData, targetLuaran: e.target.value })}
                  placeholder="Contoh: Jurnal Terindeks Scopus Q2 atau Jurnal Nasional SINTA 2"
                  className="w-full p-2 border border-slate-300 rounded-md"
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">
                  Ringkasan Eksekutif Penelitian (Abstract)
                </label>
                <textarea
                  rows={4}
                  value={formData.ringkasan}
                  onChange={(e) => setFormData({ ...formData, ringkasan: e.target.value })}
                  placeholder="Tuliskan latar belakang masalah, urgensi, metode penelitian, dan kontribusi kebaruan ilmiah..."
                  className="w-full p-2.5 border border-slate-300 rounded-md"
                />
              </div>
            </div>
          )}

          {/* STEP 5: Berkas Proposal */}
          {currentStep === 5 && (
            <div className="space-y-4">
              <h3 className="text-sm font-bold text-slate-900 pb-2 border-b border-slate-100">
                Langkah 5: Konfirmasi Alur Pengajuan
              </h3>

              <ol className="p-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-900 text-[11px] space-y-1 list-decimal list-inside">
                <li>Usulan dikirim dan sistem memeriksa cekal, kuota ketua, jumlah anggota, dan batas anggaran.</li>
                <li>Setiap anggota dosen menyetujui keanggotaan lewat menu Persetujuan Anggota Tim.</li>
                <li>Setelah semua anggota setuju, ketua mengunggah naskah proposal PDF dari halaman detail usulan.</li>
                <li>Usulan lalu diteruskan ke Dekan Fakultas untuk persetujuan dan lembar pengesahan.</li>
              </ol>

              {submitError && (
                <div className="p-3 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-xs space-y-1">
                  <div className="flex items-center gap-2 font-semibold">
                    <AlertCircle className="w-4 h-4 text-rose-600 shrink-0" />
                    <span>{submitError.message}</span>
                  </div>
                  {submitError.details.length > 0 && (
                    <ul className="list-disc list-inside pl-6">
                      {submitError.details.map((detail) => (
                        <li key={detail}>{detail}</li>
                      ))}
                    </ul>
                  )}
                </div>
              )}
            </div>
          )}
        </CardContent>

        {/* Footer Navigation */}
        <CardFooter className="flex justify-between">
          <Button
            variant="secondary"
            size="sm"
            disabled={currentStep === 1}
            onClick={() => setCurrentStep(currentStep - 1)}
            iconLeft={ArrowLeft}
          >
            Sebelumnya
          </Button>

          {currentStep < 5 ? (
            <Button
              variant="primary"
              size="sm"
              onClick={() => setCurrentStep(currentStep + 1)}
              iconRight={ArrowRight}
            >
              Selanjutnya
            </Button>
          ) : (
            <div className="flex gap-2">
              <Button variant="secondary" size="sm" onClick={() => handleSubmit(false)} disabled={isSubmitting} iconLeft={Save}>
                Simpan Draft
              </Button>
              <Button
                variant="success"
                size="sm"
                onClick={() => handleSubmit(true)}
                isLoading={isSubmitting}
                iconLeft={CheckCircle2}
              >
                Kirim Usulan
              </Button>
            </div>
          )}
        </CardFooter>
      </Card>
    </div>
  )
}
