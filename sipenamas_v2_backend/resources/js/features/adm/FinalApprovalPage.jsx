import React, { useState, useEffect } from 'react'
import {
  FileCheck2,
  CheckCircle2,
  XCircle,
  Award,
  FileText,
  DollarSign,
  AlertTriangle,
} from 'lucide-react'
import { adminApi } from '../../services/api/adminApi'
import { masterDataApi } from '../../services/api/masterDataApi'
import { formatRupiah } from '../../utils/formatters'
import { STATUS_USULAN } from '../../utils/constants'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Modal } from '../../components/ui/Modal'
import { Badge } from '../../components/ui/Badge'
import { StatusBadge } from '../../components/common/StatusBadge'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'
import SuratKeputusanPanel from './SuratKeputusanPanel'

export default function FinalApprovalPage() {
  const [proposals, setProposals] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState('ALL')
  // null = periode belum diketahui; legacy default ke periode aktif (glbKDPERIODE).
  const [kdperiode, setKdperiode] = useState(null)
  const [periodeList, setPeriodeList] = useState([])

  // Decision modal state
  const [selectedProposal, setSelectedProposal] = useState(null)
  const [decisionStatus, setDecisionStatus] = useState(STATUS_USULAN.LOLOS)
  const [approvedBudget, setApprovedBudget] = useState(0)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [successMessage, setSuccessMessage] = useState('')
  const [errorMessage, setErrorMessage] = useState('')
  const [selectedIds, setSelectedIds] = useState([])

  const toggleSelected = (id) =>
    setSelectedIds((ids) => (ids.includes(id) ? ids.filter((x) => x !== id) : [...ids, id]))

  const unduhSurat = async (id, jenis) => {
    setErrorMessage('')
    try {
      await adminApi.unduhSurat(id, jenis)
    } catch (e) {
      setErrorMessage(e.message)
    }
  }

  const loadData = async () => {
    setIsLoading(true)
    try {
      const res = await adminApi.getFinalApprovalList(kdperiode)
      setProposals(res.data)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    masterDataApi
      .getPeriodeList()
      .then((res) => {
        setPeriodeList(res.data)
        setKdperiode(res.data.find((p) => p.isaktif === 1)?.kodeperiode ?? 'ALL')
      })
      .catch(() => setKdperiode(''))
  }, [])

  useEffect(() => {
    if (kdperiode !== null) loadData()
  }, [kdperiode])

  const openDecisionModal = (p) => {
    setSelectedProposal(p)
    setDecisionStatus(STATUS_USULAN.LOLOS)
    setApprovedBudget(p.biayaDisetujui || p.rekomendasiDana || p.biayaUsulan)
  }

  const handleDecision = async (e) => {
    e.preventDefault()
    if (!selectedProposal) return
    setIsSubmitting(true)
    setErrorMessage('')

    try {
      await adminApi.submitFinalDecision(selectedProposal.id, {
        status: decisionStatus,
        biayaDisetujui: approvedBudget,
      })
      setSelectedProposal(null)
      setSuccessMessage(`Keputusan sidang LPPM untuk ${selectedProposal.kodeUsulan} berhasil ditetapkan!`)
      setTimeout(() => setSuccessMessage(''), 4000)
      await loadData()
    } catch (err) {
      setErrorMessage(err?.message || 'Gagal menyimpan keputusan. Silakan coba lagi.')
    } finally {
      setIsSubmitting(false)
    }
  }

  const filteredProposals = proposals.filter((p) => {
    const matchSearch =
      !searchQuery ||
      p.judul?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      p.kodeUsulan?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      p.ketuaNama?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (p.suratTugas?.nomor && p.suratTugas.nomor.toLowerCase().includes(searchQuery.toLowerCase()))
    const matchStatus = statusFilter === 'ALL' || p.status === statusFilter
    return matchSearch && matchStatus
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedProposals } =
    usePagination(filteredProposals, 10)

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Sidang Final Approval LPPM & Penetapan SK
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Rekapitulasi skor reviewer, ranking usulan, penetapan dana definitif, dan penerbitan SK Rektor
          </p>
        </div>
      </div>

      {successMessage && (
        <div className="p-3.5 bg-[#10c469]/10 border border-[#10c469]/20 text-[#10c469] rounded-xl text-xs flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-[#10c469] shrink-0" />
          <span className="font-semibold text-[#0c8a49]">{successMessage}</span>
        </div>
      )}

      {errorMessage && (
        <div className="p-3.5 bg-[#ff5b5b]/10 border border-[#ff5b5b]/20 text-[#ff5b5b] rounded-xl text-xs flex items-center gap-2">
          <XCircle className="w-4 h-4 text-[#ff5b5b] shrink-0" />
          <span className="font-semibold text-[#ff5b5b]">{errorMessage}</span>
        </div>
      )}

      {/* Filter Toolbar */}
      <TableFilterBar
        search={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari judul, kode, ketua, nomor SK..."
        filters={[
          {
            key: 'status',
            value: statusFilter,
            onChange: setStatusFilter,
            options: [
              { value: 'ALL', label: 'Semua Status Sidang' },
              { value: STATUS_USULAN.APPROVED, label: 'Lolos / Didanai' },
              { value: STATUS_USULAN.REVISED, label: 'Perlu Revisi' },
              { value: STATUS_USULAN.REJECTED, label: 'Ditolak' },
              { value: STATUS_USULAN.PLOTTED, label: 'Dalam Penelaahan' },
            ],
          },
          {
            key: 'periode',
            value: kdperiode ?? '',
            onChange: setKdperiode,
            options: [
              { value: 'ALL', label: 'Semua Periode' },
              ...periodeList.map((p) => ({ value: p.kodeperiode, label: String(p.tahun) })),
            ],
          },
        ]}
        onReset={() => {
          setSearchQuery('')
          setStatusFilter('ALL')
        }}
        totalResults={filteredProposals.length}
      />

      <SuratKeputusanPanel selectedIds={selectedIds} onDone={loadData} />

      {/* Decision Table */}
      <Card>
        <CardHeader
          title="Tabel Ranking & Keputusan Kelulusan Usulan"
          subtitle="Urutan berdasarkan rata-rata skor gabungan Reviewer 1 & Reviewer 2"
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-3 py-3 w-8">
                  <span className="sr-only">Pilih</span>
                </th>
                <th className="px-4 py-3">Kode & Judul Usulan</th>
                <th className="px-3 py-3">Ketua & Unit</th>
                <th className="px-3 py-3 text-center">Rev 1</th>
                <th className="px-3 py-3 text-center">Rev 2</th>
                <th className="px-3 py-3 text-center">Rerata Skor</th>
                <th className="px-3 py-3 text-right">Dana Disetujui</th>
                <th className="px-3 py-3 text-center">Status Sidang</th>
                <th className="px-3 py-3">Surat</th>
                <th className="px-4 py-3 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton rows={5} cols={10} />
              ) : pagedProposals.length === 0 ? (
                <TableEmptyState
                  colSpan={10}
                  message="Tidak ada usulan yang sesuai filter"
                  submessage="Silakan reset filter pencarian atau ubah status seleksi."
                  onReset={() => {
                    setSearchQuery('')
                    setStatusFilter('ALL')
                  }}
                />
              ) : (
                pagedProposals.map((p) => {
                const s1 = p.skorReviewer1 || 0
                const s2 = p.skorReviewer2 || 0
                const avg = p.skorRataRata || (s1 && s2 ? (s1 + s2) / 2 : s1 || s2 || '-')
                const disparity = s1 && s2 ? Math.abs(s1 - s2) : 0

                return (
                  <tr key={p.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                    <td className="px-3 py-3.5">
                      <input
                        type="checkbox"
                        aria-label={`Pilih ${p.judul}`}
                        checked={selectedIds.includes(p.id)}
                        onChange={() => toggleSelected(p.id)}
                      />
                    </td>
                    <td className="px-4 py-3.5 max-w-sm">
                      <span className="font-mono text-[11px] text-[#188ae2] font-semibold block">
                        {p.kodeUsulan} &bull; {p.skimKode}
                      </span>
                      <p className="font-semibold text-[#313a46] line-clamp-2 mt-0.5" title={p.judul}>
                        {p.judul}
                      </p>
                      {p.suratTugas?.nomor && (
                        <span className="text-[10px] text-blue-700 bg-blue-50 px-1.5 py-0.2 rounded border border-blue-200 font-mono mt-1 inline-block">
                          Surat Tugas: {p.suratTugas.nomor}
                        </span>
                      )}
                    </td>

                    <td className="px-3 py-3.5 whitespace-nowrap">
                      <p className="font-medium text-slate-800">{p.ketuaNama}</p>
                      <span className="text-[11px] text-slate-500">{p.fakultasNama}</span>
                    </td>

                    <td className="px-3 py-3.5 text-center font-tabular font-semibold text-slate-700">
                      {s1 || '-'}
                    </td>

                    <td className="px-3 py-3.5 text-center font-tabular font-semibold text-slate-700">
                      {s2 || '-'}
                    </td>

                    <td className="px-3 py-3.5 text-center whitespace-nowrap">
                      <span className="text-sm font-bold text-slate-900 font-tabular">{avg}</span>
                      {disparity > 10 && (
                        <span className="block text-[10px] text-amber-700 font-medium" title="Disparitas nilai > 10 poin">
                          &Delta; {disparity} (Cek)
                        </span>
                      )}
                    </td>

                    <td className="px-3 py-3.5 text-right whitespace-nowrap font-tabular font-bold text-slate-900">
                      {formatRupiah(p.biayaDisetujui || p.biayaUsulan)}
                    </td>

                    <td className="px-3 py-3.5 text-center whitespace-nowrap">
                      <StatusBadge status={p.status} />
                    </td>

                    <td className="px-3 py-3.5 whitespace-nowrap space-y-1">
                      {[
                        ['tugas', p.statusFinalApproval === 'LOLOS' ? 'ST' : 'STPP', p.suratTugas],
                        ['dana', 'SPD', p.suratDana],
                      ]
                        .filter(([, , surat]) => surat?.nomor)
                        .map(([key, jenis, surat]) => (
                          <button
                            key={key}
                            type="button"
                            onClick={() => unduhSurat(p.id, jenis)}
                            className="flex items-center gap-1 text-[11px] text-[#188ae2] hover:underline"
                          >
                            <FileText className="w-3 h-3" />
                            {jenis} No. {surat.nomor}
                            <Badge variant={surat.isFinal ? 'success' : 'warning'}>
                              {surat.isFinal ? 'FINAL' : 'DRAFT'}
                            </Badge>
                          </button>
                        ))}
                    </td>

                    <td className="px-4 py-3.5 text-center whitespace-nowrap">
                      <Button
                        variant={p.status === STATUS_USULAN.LOLOS ? 'secondary' : 'primary'}
                        size="xs"
                        iconLeft={FileCheck2}
                        onClick={() => openDecisionModal(p)}
                      >
                        {p.status === STATUS_USULAN.LOLOS ? 'Ubah SK' : 'Tetapkan SK'}
                      </Button>
                    </td>
                  </tr>
                )
              }))}
            </tbody>
          </table>
        </div>
        <CardFooter>
          <Pagination
            page={page}
            totalPages={totalPages}
            totalItems={totalItems}
            pageSize={pageSize}
            onPageChange={setPage}
          />
        </CardFooter>
      </Card>

      {/* Modal Penetapan Keputusan Sidang & SK Rektor */}
      <Modal
        isOpen={!!selectedProposal}
        onClose={() => setSelectedProposal(null)}
        title="Penetapan Keputusan Sidang Final LPPM"
        subtitle={selectedProposal ? `${selectedProposal.kodeUsulan} — ${selectedProposal.judul}` : ''}
        maxWidth="max-w-xl"
        footer={
          <>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setSelectedProposal(null)}
              disabled={isSubmitting}
            >
              Batal
            </Button>
            <Button
              variant="primary"
              size="sm"
              onClick={handleDecision}
              isLoading={isSubmitting}
            >
              Simpan Keputusan Final
            </Button>
          </>
        }
      >
        {selectedProposal && (
          <form onSubmit={handleDecision} className="space-y-4 text-xs">
            <div className="p-3 bg-slate-50 rounded-lg border border-slate-200 grid grid-cols-2 gap-2">
              <div>
                <span className="text-slate-500 block">Ketua Pengusul:</span>
                <strong className="text-slate-800">{selectedProposal.ketuaNama}</strong>
              </div>
              <div>
                <span className="text-slate-500 block">Skor Rata-Rata Reviewer:</span>
                <strong className="text-slate-900 text-sm font-bold">
                  {selectedProposal.skorRataRata || selectedProposal.skorReviewer1 || '-'} / 700
                </strong>
              </div>
            </div>

            <div>
              <label className="block font-semibold text-slate-700 mb-1">Keputusan Akhir LPPM</label>
              <div className="grid grid-cols-2 gap-3">
                <button
                  type="button"
                  onClick={() => setDecisionStatus(STATUS_USULAN.LOLOS)}
                  className={`p-3 rounded-lg border text-left cursor-pointer transition ${
                    decisionStatus === STATUS_USULAN.LOLOS
                      ? 'border-emerald-500 bg-emerald-50 text-emerald-900 font-bold ring-2 ring-emerald-200'
                      : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                  }`}
                >
                  <div className="flex items-center gap-1.5">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                    <span>LOLOS PENDANAAN</span>
                  </div>
                  <p className="text-[11px] text-slate-500 mt-1 font-normal">
                    Diberikan hibah dan diterbitkan Surat Keputusan Rektor
                  </p>
                </button>

                <button
                  type="button"
                  onClick={() => setDecisionStatus(STATUS_USULAN.TIDAK_LOLOS)}
                  className={`p-3 rounded-lg border text-left cursor-pointer transition ${
                    decisionStatus === STATUS_USULAN.TIDAK_LOLOS
                      ? 'border-rose-500 bg-rose-50 text-rose-900 font-bold ring-2 ring-rose-200'
                      : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50'
                  }`}
                >
                  <div className="flex items-center gap-1.5">
                    <XCircle className="w-4 h-4 text-rose-600" />
                    <span>TIDAK LOLOS</span>
                  </div>
                  <p className="text-[11px] text-slate-500 mt-1 font-normal">
                    Usulan tidak memenuhi passing grade / kuota pagu
                  </p>
                </button>
              </div>
            </div>

            {decisionStatus === STATUS_USULAN.LOLOS && (
              <>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">
                    Nominal Dana Definitif yang Disetujui (Rp)
                  </label>
                  <input
                    type="number"
                    required
                    value={approvedBudget}
                    onChange={(e) => setApprovedBudget(e.target.value)}
                    className="w-full px-3 py-2 border border-slate-300 rounded-md font-tabular text-sm font-bold text-slate-900"
                  />
                  <span className="text-[11px] text-slate-500 mt-0.5 block">
                    Usulan Awal: {formatRupiah(selectedProposal.biayaUsulan)}
                  </span>
                </div>
              </>
            )}
          </form>
        )}
      </Modal>
    </div>
  )
}
