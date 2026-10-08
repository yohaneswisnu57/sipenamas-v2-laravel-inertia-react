import React, { useState, useEffect, useRef } from 'react'
import {
  Users2,
  Search,
  Filter,
  UserCheck,
  AlertCircle,
  CheckCircle2,
  XCircle,
  Clock,
  ArrowRight,
} from 'lucide-react'
import { adminApi } from '../../services/api/adminApi'
import { masterDataApi } from '../../services/api/masterDataApi'
import { formatRupiah } from '../../utils/formatters'
import { STATUS_USULAN, TAHAP_USULAN } from '../../utils/constants'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Modal } from '../../components/ui/Modal'
import { StatusBadge } from '../../components/common/StatusBadge'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'

// Padanan combo cmbreviewer legacy: dicari di server per usulan (idpen),
// sehingga ketua & anggota tim usulan tidak pernah muncul sebagai opsi.
function ReviewerCombobox({ proposalId, value, onChange, excludeIds = [], placeholder }) {
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
    if (!isOpen) return
    let active = true
    setIsLoading(true)
    const timer = setTimeout(() => {
      masterDataApi
        .searchReviewer(query, proposalId)
        .then((res) => {
          if (active) setResults(res.data || [])
        })
        .catch(() => {
          if (active) setResults([])
        })
        .finally(() => {
          if (active) setIsLoading(false)
        })
    }, 250)
    return () => {
      active = false
      clearTimeout(timer)
    }
  }, [query, proposalId, isOpen])

  const options = results.filter((rev) => !excludeIds.includes(rev.id))

  return (
    <div ref={wrapperRef} className="relative w-full">
      {value && !isOpen ? (
        <div className="flex items-center gap-2 w-full px-3 py-2 border border-slate-300 rounded-md bg-white">
          <button
            type="button"
            onClick={() => setIsOpen(true)}
            className="flex-1 min-w-0 text-left text-base sm:text-sm text-slate-800 truncate"
          >
            {value.nama}
            <span className="ml-1 font-mono text-[10px] text-slate-500">{value.id}</span>
          </button>
          <button
            type="button"
            onClick={() => onChange(null)}
            className="p-1 text-slate-400 hover:text-rose-600 shrink-0"
            aria-label="Hapus pilihan reviewer"
          >
            <XCircle className="w-4 h-4" />
          </button>
        </div>
      ) : (
        <div className="relative">
          <Search className="w-4 h-4 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            type="text"
            placeholder={placeholder || 'Cari reviewer (nama / kode)...'}
            value={query}
            autoFocus={!!value}
            onChange={(e) => {
              setQuery(e.target.value)
              setIsOpen(true)
            }}
            onFocus={() => setIsOpen(true)}
            className="w-full pl-8 pr-3 py-2 text-base sm:text-sm border border-slate-300 rounded-md bg-white focus:outline-none focus:ring-2 focus:ring-[#188ae2]"
          />
        </div>
      )}

      {isOpen && (
        <div className="absolute z-20 mt-1 w-full bg-white border border-slate-200 rounded-md shadow-lg max-h-56 overflow-y-auto">
          {isLoading ? (
            <div className="p-3 text-xs text-slate-500 text-center">Mencari reviewer...</div>
          ) : options.length === 0 ? (
            <div className="p-3 text-xs text-slate-500 text-center">Reviewer tidak ditemukan</div>
          ) : (
            options.map((rev) => (
              <button
                key={rev.id}
                type="button"
                onClick={() => {
                  onChange({ id: rev.id, nama: rev.name })
                  setIsOpen(false)
                  setQuery('')
                }}
                className="w-full text-left px-3 py-2 text-xs hover:bg-blue-50 border-b border-slate-100 last:border-0 flex items-center justify-between gap-2"
              >
                <div className="min-w-0">
                  <div className="font-semibold text-slate-800 truncate">{rev.name}</div>
                  <div className="text-[11px] text-slate-500 truncate">
                    {rev.prodi || 'Program Studi -'} &bull; {rev.isExternal ? 'Mitra Eksternal' : 'Internal UKWMS'}
                  </div>
                </div>
                <span className="font-mono text-[10px] bg-slate-100 text-slate-700 px-1.5 py-0.5 rounded border border-slate-200 shrink-0">
                  {rev.id}
                </span>
              </button>
            ))
          )}
        </div>
      )}
    </div>
  )
}

function ReviewerSlot({ reviewer, skor }) {
  if (!reviewer) {
    return <span className="text-[#98a6ad] italic">Belum diplot</span>
  }

  return (
    <div>
      <p className="font-medium text-[#313a46]">{reviewer.nama}</p>
      <span
        className={`text-[10px] px-2 py-0.5 rounded-full border font-semibold ${
          reviewer.statusKesediaan === 'MENOLAK'
            ? 'text-[#ff5b5b] bg-[#ff5b5b]/10 border-[#ff5b5b]/20'
            : 'text-[#5b69bc] bg-[#5b69bc]/10 border-[#5b69bc]/20'
        }`}
      >
        {reviewer.statusKesediaan || 'Ditugaskan'}
      </span>
      {skor && (
        <span className="text-[10px] font-bold text-[#313a46] block mt-0.5">
          Skor: {skor}
        </span>
      )}
    </div>
  )
}

export default function PlottingReviewerPage() {
  const [proposals, setProposals] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [fakultasFilter, setFakultasFilter] = useState('')
  const [fakultasOptions, setFakultasOptions] = useState([])
  const [tahapFilter, setTahapFilter] = useState('ALL')
  // null = periode belum diketahui; legacy default ke periode aktif (glbKDPERIODE).
  const [kdperiode, setKdperiode] = useState(null)
  const [periodeList, setPeriodeList] = useState([])

  // Modal plotting state
  const [selectedProposal, setSelectedProposal] = useState(null)
  const [reviewer1, setReviewer1] = useState(null)
  const [reviewer2, setReviewer2] = useState(null)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [successNotice, setSuccessNotice] = useState('')
  const [errorNotice, setErrorNotice] = useState('')
  const [isFinalizing, setIsFinalizing] = useState(null)

  // Tunjuk Reviewer Ke-3 (tambahan saat salah satu reviewer 1/2 menolak) modal state
  const [tambahTarget, setTambahTarget] = useState(null)
  const [reviewerBaru, setReviewerBaru] = useState(null)
  const [isTambah, setIsTambah] = useState(false)

  // Tunjuk Verifikator Revisi modal state
  const [verifikatorTarget, setVerifikatorTarget] = useState(null)
  const [verifikatorId, setVerifikatorId] = useState('')
  const [isAssigningVerifikator, setIsAssigningVerifikator] = useState(false)

  const loadData = async () => {
    setIsLoading(true)
    try {
      const res = await adminApi.getPlottingList({
        search: searchQuery,
        fakultas: fakultasFilter,
        kdperiode,
      })
      setProposals(res.data)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    if (kdperiode !== null) loadData()
  }, [searchQuery, fakultasFilter, kdperiode])

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
    masterDataApi.getFakultasList().then((res) => setFakultasOptions(res.data))
  }, [])

  const toPilihan = (rev) => (rev ? { id: rev.id, nama: rev.nama } : null)

  const openPlottingModal = (proposal) => {
    setErrorNotice('')
    setSelectedProposal(proposal)
    setReviewer1(toPilihan(proposal.reviewer1))
    setReviewer2(toPilihan(proposal.reviewer2))
  }

  // Bentuk yang berulang di 4 aksi admin di halaman ini: set loading, jalankan
  // request, tutup modal (opsional), tampilkan notice sukses, muat ulang list.
  const runAction = async (action, { setLoading, start, end = false, closeModal, successMessage }) => {
    setLoading(start)
    setErrorNotice('')
    try {
      await action()
      closeModal?.()
      setSuccessNotice(successMessage)
      setTimeout(() => setSuccessNotice(''), 4000)
      await loadData()
    } catch (err) {
      setErrorNotice(err?.message || 'Gagal menyimpan. Silakan coba lagi.')
    } finally {
      setLoading(end)
    }
  }

  const handleAssign = (e) => {
    e.preventDefault()
    if (!selectedProposal || !reviewer1 || !reviewer2) return

    return runAction(
      () => adminApi.assignReviewers(selectedProposal.id, { reviewer1Id: reviewer1.id, reviewer2Id: reviewer2.id }),
      {
        setLoading: setIsSubmitting,
        start: true,
        closeModal: () => setSelectedProposal(null),
        successMessage: `Reviewer untuk ${selectedProposal.kodeUsulan} berhasil ditugaskan!`,
      }
    )
  }

  const handleFinalize = (proposal) => runAction(
    () => adminApi.finalizePlotting(proposal.id),
    {
      setLoading: setIsFinalizing,
      start: proposal.id,
      end: null,
      successMessage: `Plotting ${proposal.kodeUsulan} difinalisasi — reviewer sekarang bisa mengonfirmasi kesediaan.`,
    }
  )

  const openTambahReviewerModal = (proposal) => {
    setErrorNotice('')
    setTambahTarget(proposal)
    setReviewerBaru(null)
  }

  const handleTambahReviewer = (e) => {
    e.preventDefault()
    if (!tambahTarget || !reviewerBaru) return

    return runAction(
      () => adminApi.tambahReviewerKe3(tambahTarget.id, { reviewerBaruId: reviewerBaru.id }),
      {
        setLoading: setIsTambah,
        start: true,
        closeModal: () => setTambahTarget(null),
        successMessage: `Reviewer ke-3 untuk ${tambahTarget.kodeUsulan} berhasil ditambahkan.`,
      }
    )
  }

  const openVerifikatorModal = (proposal) => {
    setErrorNotice('')
    setVerifikatorTarget(proposal)
    setVerifikatorId(proposal.revisiVerifikator?.nik || '')
  }

  const handleAssignVerifikator = (e) => {
    e.preventDefault()
    if (!verifikatorTarget || !verifikatorId) return

    return runAction(
      () => adminApi.assignRevisiVerifikator(verifikatorTarget.id, { reviewerId: verifikatorId }),
      {
        setLoading: setIsAssigningVerifikator,
        start: true,
        closeModal: () => setVerifikatorTarget(null),
        successMessage: `Verifikator revisi untuk ${verifikatorTarget.kodeUsulan} berhasil ditunjuk.`,
      }
    )
  }

  const visibleProposals = proposals.filter(
    (p) => tahapFilter === 'ALL' || TAHAP_USULAN[tahapFilter]?.statuses.includes(p.status)
  )

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedProposals } =
    usePagination(visibleProposals, 10)

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Plotting & Penugasan Reviewer
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Menugaskan 2 orang reviewer (internal / eksternal) untuk proposal yang telah disetujui Dekan
          </p>
        </div>
      </div>

      {successNotice && (
        <div className="p-3.5 bg-[#10c469]/10 border border-[#10c469]/20 text-[#10c469] rounded-xl text-xs flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-[#10c469] shrink-0" />
          <span className="font-semibold text-[#0c8a49]">{successNotice}</span>
        </div>
      )}

      {/* Filter Toolbar */}
      <TableFilterBar
        searchValue={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari judul, ketua, kode usulan, atau prodi..."
        filters={[
          {
            key: 'tahap',
            value: tahapFilter,
            onChange: setTahapFilter,
            options: [
              { value: 'ALL', label: 'Semua Tahap' },
              ...Object.entries(TAHAP_USULAN).map(([value, { label }]) => ({ value, label })),
            ],
          },
          {
            key: 'fakultas',
            value: fakultasFilter,
            onChange: setFakultasFilter,
            placeholder: 'Semua Fakultas',
            options: fakultasOptions.map((f) => ({ value: f.kode, label: f.nama })),
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
          setFakultasFilter('')
          setTahapFilter('ALL')
        }}
        totalResults={visibleProposals.length}
      />

      {/* Proposals List Table */}
      <Card>
        <CardHeader
          title="Daftar Usulan Penelitian untuk Evaluasi Reviewer"
          subtitle="Periksa kesesuaian bidang kepakaran dan pastikan tidak ada konflik kepentingan"
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-4 py-3">Kode & Judul Usulan</th>
                <th className="px-3 py-3">Ketua Pengusul</th>
                <th className="px-3 py-3">Reviewer 1</th>
                <th className="px-3 py-3">Reviewer 2</th>
                <th className="px-3 py-3">Reviewer 3</th>
                <th className="px-3 py-3 text-center">Status</th>
                <th className="px-4 py-3 text-center">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton rows={6} cols={7} />
              ) : pagedProposals.length === 0 ? (
                <TableEmptyState
                  colSpan={7}
                  message="Tidak ada usulan penelitian ditemukan"
                  submessage="Coba ubah kata kunci pencarian atau filter fakultas."
                  onReset={() => {
                    setSearchQuery('')
                    setFakultasFilter('')
                    setTahapFilter('ALL')
                  }}
                />
              ) : (
                pagedProposals.map((p) => (
                <tr key={p.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                  <td className="px-4 py-3.5 max-w-sm">
                    <span className="font-mono text-[11px] text-[#188ae2] font-semibold block">
                      {p.kodeUsulan} &bull; {p.skimKode}
                    </span>
                    <p className="font-semibold text-[#313a46] line-clamp-2 mt-0.5" title={p.judul}>
                      {p.judul}
                    </p>
                    <span className="text-[10px] text-[#98a6ad] mt-1 block">
                      Fokus: {p.bidangFokus}
                    </span>
                  </td>

                  <td className="px-3 py-3.5 whitespace-nowrap">
                    <p className="font-medium text-[#313a46]">{p.ketuaNama}</p>
                    <span className="text-[11px] text-[#6c757d]">
                      {p.prodiNama} &bull; {p.fakultasKode}
                    </span>
                  </td>

                  <td className="px-3 py-3.5 whitespace-nowrap">
                    <ReviewerSlot reviewer={p.reviewer1} skor={p.skorReviewer1} />
                  </td>

                  <td className="px-3 py-3.5 whitespace-nowrap">
                    <ReviewerSlot reviewer={p.reviewer2} skor={p.skorReviewer2} />
                  </td>

                  <td className="px-3 py-3.5 whitespace-nowrap">
                    <ReviewerSlot reviewer={p.reviewer3} skor={p.skorReviewer3} />
                  </td>

                  <td className="px-3 py-3.5 text-center whitespace-nowrap">
                    <StatusBadge status={p.status} />
                    {p.reviewer1 && p.reviewer2 && p.statusPenunjukanReviewer === 'DRAFT' && (
                      <span className="block text-[10px] text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded border border-amber-200 mt-1">
                        Draft — Reviewer Belum Diberi Tahu
                      </span>
                    )}
                    {p.isButuhReviewerKetiga && !p.reviewer3 && (
                      <span className="block text-[10px] text-rose-700 bg-rose-50 px-1.5 py-0.5 rounded border border-rose-200 mt-1">
                        Selisih skor reviewer ≥ 200 — butuh reviewer ke-3
                      </span>
                    )}
                  </td>

                  <td className="px-4 py-3.5 text-center whitespace-nowrap space-y-1">
                    {!p.isApprovedByDekan ? (
                      <span className="text-[10px] text-slate-500">Menunggu persetujuan Dekan</span>
                    ) : p.statusPenunjukanReviewer === 'FINAL' ? (
                      <span className="block text-[10px] text-slate-500">Plotting final</span>
                    ) : (
                      <Button
                        variant={p.reviewer1 && p.reviewer2 ? 'secondary' : 'primary'}
                        size="xs"
                        iconLeft={Users2}
                        onClick={() => openPlottingModal(p)}
                      >
                        {p.reviewer1 && p.reviewer2 ? 'Pilih Ulang' : 'Plot Reviewer'}
                      </Button>
                    )}
                    {(p.reviewer1?.statusKesediaan === 'MENOLAK' || p.reviewer2?.statusKesediaan === 'MENOLAK' || p.isButuhReviewerKetiga) && !p.reviewer3 && (
                      <Button
                        variant="danger"
                        size="xs"
                        className="block"
                        onClick={() => openTambahReviewerModal(p)}
                      >
                        Tunjuk Reviewer Ke-3
                      </Button>
                    )}
                    {p.reviewer1 && p.reviewer2 && p.statusPenunjukanReviewer === 'DRAFT' && (
                      <Button
                        variant="success"
                        size="xs"
                        className="block"
                        iconLeft={CheckCircle2}
                        onClick={() => handleFinalize(p)}
                        isLoading={isFinalizing === p.id}
                      >
                        Finalisasi & Kirim ke Reviewer
                      </Button>
                    )}
                    {(p.status === STATUS_USULAN.REVISI || p.status === STATUS_USULAN.MENUNGGU_VERIFIKASI_REVISI) && (
                      <Button
                        variant="secondary"
                        size="xs"
                        className="block"
                        onClick={() => openVerifikatorModal(p)}
                      >
                        {p.revisiVerifikator ? 'Ganti Verifikator Revisi' : 'Tunjuk Verifikator Revisi'}
                      </Button>
                    )}
                  </td>
                </tr>
              ))
            )}
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

      {/* Modal Plotting Reviewer */}
      <Modal
        isOpen={!!selectedProposal}
        onClose={() => setSelectedProposal(null)}
        title="Penugasan Reviewer Usulan"
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
              onClick={handleAssign}
              isLoading={isSubmitting}
              disabled={!reviewer1 || !reviewer2}
            >
              Simpan & Kirim Undangan
            </Button>
          </>
        }
      >
        {selectedProposal && (
          <form onSubmit={handleAssign} className="space-y-4 text-xs">
            {errorNotice && (
              <p className="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 font-semibold flex items-start gap-2">
                <XCircle className="w-4 h-4 shrink-0 mt-0.5" /> {errorNotice}
              </p>
            )}
            <div className="p-3 bg-slate-50 rounded-lg border border-slate-200 space-y-1">
              <p className="font-semibold text-slate-800">Detail Proposal:</p>
              <p className="text-slate-600">Ketua: <strong>{selectedProposal.ketuaNama}</strong> ({selectedProposal.prodiNama} - {selectedProposal.fakultasNama})</p>
              <p className="text-slate-600">Bidang Fokus: <strong>{selectedProposal.bidangFokus}</strong></p>
              <p className="text-slate-600">Usulan Anggaran: <strong>{formatRupiah(selectedProposal.biayaUsulan)}</strong></p>
            </div>

            <div className="space-y-3">
              <div>
                <label className="block font-semibold text-slate-700 mb-1">
                  Reviewer 1 (Internal / Eksternal)
                </label>
                <ReviewerCombobox
                  proposalId={selectedProposal.id}
                  value={reviewer1}
                  onChange={setReviewer1}
                  excludeIds={reviewer2 ? [reviewer2.id] : []}
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">
                  Reviewer 2 (Mitra / Kepakaran Spesifik)
                </label>
                <ReviewerCombobox
                  proposalId={selectedProposal.id}
                  value={reviewer2}
                  onChange={setReviewer2}
                  excludeIds={reviewer1 ? [reviewer1.id] : []}
                />
              </div>
            </div>

            <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-[11px] flex items-start gap-2">
              <AlertCircle className="w-4 h-4 shrink-0 mt-0.5 text-amber-600" />
              <p>
                <strong>Aturan Bebas Konflik:</strong> Ketua dan anggota tim penelitian ini tidak ditampilkan sebagai opsi reviewer.
              </p>
            </div>
          </form>
        )}
      </Modal>

      {/* Modal Tunjuk Reviewer Ke-3 (tambahan, bukan pengganti, saat reviewer 1/2 menolak) */}
      <Modal
        isOpen={!!tambahTarget}
        onClose={() => setTambahTarget(null)}
        title="Tunjuk Reviewer Ke-3"
        subtitle={tambahTarget ? `${tambahTarget.kodeUsulan} — ${tambahTarget.judul}` : ''}
        maxWidth="max-w-md"
        footer={
          <>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setTambahTarget(null)}
              disabled={isTambah}
            >
              Batal
            </Button>
            <Button
              variant="primary"
              size="sm"
              onClick={handleTambahReviewer}
              isLoading={isTambah}
              disabled={!reviewerBaru}
            >
              Tambahkan
            </Button>
          </>
        }
      >
        {tambahTarget && (
          <form onSubmit={handleTambahReviewer} className="space-y-3 text-xs">
            {errorNotice && (
              <p className="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 font-semibold flex items-start gap-2">
                <XCircle className="w-4 h-4 shrink-0 mt-0.5" /> {errorNotice}
              </p>
            )}
            <div className="p-3 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-[11px] flex items-start gap-2">
              <AlertCircle className="w-4 h-4 shrink-0 mt-0.5 text-amber-600" />
              <p>Reviewer yang menolak TETAP tercatat - reviewer ke-3 ini ditambahkan berdampingan, bukan menggantikan.</p>
            </div>
            <label className="block font-semibold text-slate-700 mb-1">
              Reviewer Ke-3
            </label>
            <ReviewerCombobox
              proposalId={tambahTarget.id}
              value={reviewerBaru}
              onChange={setReviewerBaru}
              excludeIds={[tambahTarget.reviewer1, tambahTarget.reviewer2, tambahTarget.reviewer3, tambahTarget.reviewerPembanding]
                .filter(Boolean)
                .map((rev) => rev.id)}
            />
          </form>
        )}
      </Modal>

      {/* Modal Tunjuk Verifikator Revisi (dari reviewer usulan, seperti legacy) */}
      <Modal
        isOpen={!!verifikatorTarget}
        onClose={() => setVerifikatorTarget(null)}
        title="Tunjuk Verifikator Revisi"
        subtitle={verifikatorTarget ? `${verifikatorTarget.kodeUsulan} — ${verifikatorTarget.judul}` : ''}
        maxWidth="max-w-md"
        footer={
          <>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setVerifikatorTarget(null)}
              disabled={isAssigningVerifikator}
            >
              Batal
            </Button>
            <Button
              variant="primary"
              size="sm"
              onClick={handleAssignVerifikator}
              isLoading={isAssigningVerifikator}
              disabled={!verifikatorId}
            >
              Tunjuk Verifikator
            </Button>
          </>
        }
      >
        {verifikatorTarget && (
          <form onSubmit={handleAssignVerifikator} className="space-y-3 text-xs">
            {errorNotice && (
              <p className="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 font-semibold flex items-start gap-2">
                <XCircle className="w-4 h-4 shrink-0 mt-0.5" /> {errorNotice}
              </p>
            )}
            <label className="block font-semibold text-slate-700 mb-1">
              Reviewer Verifikator (salah satu reviewer usulan ini)
            </label>
            <select
              value={verifikatorId}
              onChange={(e) => setVerifikatorId(e.target.value)}
              className="w-full p-2 bg-white border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500"
            >
              <option value="">Pilih reviewer...</option>
              {[verifikatorTarget.reviewer1, verifikatorTarget.reviewer2, verifikatorTarget.reviewer3]
                .filter(Boolean)
                .map((rev) => (
                  <option key={rev.id} value={rev.id} disabled={rev.sudahVerifikasiRevisi}>
                    {rev.nama}
                    {rev.sudahVerifikasiRevisi ? ' (sudah pernah verifikasi, tidak bisa dipilih lagi)' : ''}
                  </option>
                ))}
            </select>
          </form>
        )}
      </Modal>
    </div>
  )
}
