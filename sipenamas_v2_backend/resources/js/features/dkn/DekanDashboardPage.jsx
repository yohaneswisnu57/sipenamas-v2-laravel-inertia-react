import React, { useState, useEffect } from 'react'
import { Link } from '@/lib/router'
import {
  Building2,
  CheckSquare,
  BarChart3,
  AlertCircle,
  Coins,
  ArrowRight,
  CheckCircle2,
  XCircle,
  FileText,
  Download,
} from 'lucide-react'
import { dekanApi } from '../../services/api/dekanApi'
import { masterDataApi } from '../../services/api/masterDataApi'
import { formatRupiah } from '../../utils/formatters'
import { StatCard } from '../../components/common/StatCard'
import { StatusBadge } from '../../components/common/StatusBadge'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Modal } from '../../components/ui/Modal'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'
import { TAHAP_USULAN, STATUS_USULAN } from '../../utils/constants'

export default function DekanDashboardPage() {
  const [stats, setStats] = useState(null)
  const [items, setItems] = useState([])
  const [periodeList, setPeriodeList] = useState([])
  const [kdperiode, setKdperiode] = useState('ALL')
  const [isLoading, setIsLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [tahapFilter, setTahapFilter] = useState('ALL')

  // Approval modal state
  const [selectedProposal, setSelectedProposal] = useState(null)
  const [approvalNote, setApprovalNote] = useState('')
  const [isProcessing, setIsProcessing] = useState(false)
  const [notice, setNotice] = useState('')
  const [proposalUrl, setProposalUrl] = useState('')
  const [proposalError, setProposalError] = useState('')
  const [actionError, setActionError] = useState('')

  useEffect(() => {
    masterDataApi.getPeriodeList().then((res) => setPeriodeList(res.data))
  }, [])

  useEffect(() => {
    if (!selectedProposal) return undefined
    let url = ''
    let batal = false
    setProposalUrl('')
    setProposalError('')
    setActionError('')
    dekanApi
      .ambilDokumenProposal(selectedProposal.id)
      .then((u) => {
        url = u
        if (!batal) setProposalUrl(u)
      })
      .catch((e) => {
        if (!batal) setProposalError(e.message)
      })
    return () => {
      batal = true
      if (url) URL.revokeObjectURL(url)
    }
  }, [selectedProposal])

  const loadData = async () => {
    setIsLoading(true)
    try {
      const [s, p] = await Promise.all([
        dekanApi.getDashboardStats(),
        dekanApi.getPengajuanList(kdperiode)
      ])
      setStats(s.data)
      setItems(p.data)
    } catch (e) {
      console.error(e)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [kdperiode])

  const handleApprove = async () => {
    if (!selectedProposal || !approvalNote.trim()) return
    setIsProcessing(true)
    try {
      await dekanApi.approveProposal(selectedProposal.id, { catatan: approvalNote })
      setSelectedProposal(null)
      setNotice(`Usulan ${selectedProposal.kodeUsulan} disetujui tingkat Fakultas!`)
      loadData()
    } catch (e) {
      setActionError(Object.values(e.errors || {}).flat()[0] || e.message)
    } finally {
      setIsProcessing(false)
    }
  }

  const handleReject = async () => {
    if (!selectedProposal || !approvalNote.trim()) return
    setIsProcessing(true)
    try {
      await dekanApi.rejectProposal(selectedProposal.id, { catatan: approvalNote })
      setSelectedProposal(null)
      setNotice(`Usulan ${selectedProposal.kodeUsulan} ditolak.`)
      loadData()
    } catch (e) {
      setActionError(Object.values(e.errors || {}).flat()[0] || e.message)
    } finally {
      setIsProcessing(false)
    }
  }

  const filtered = items.filter((p) => {
    const q = search.toLowerCase()
    const matchSearch =
      !q ||
      p.judul?.toLowerCase().includes(q) ||
      p.ketuaNama?.toLowerCase().includes(q) ||
      p.prodiNama?.toLowerCase().includes(q) ||
      p.skimNama?.toLowerCase().includes(q)
    const matchTahap =
      tahapFilter === 'ALL' || TAHAP_USULAN[tahapFilter]?.statuses.includes(p.status)
    return matchSearch && matchTahap
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems } = usePagination(filtered, 10)

  const reset = () => {
    setSearch('')
    setTahapFilter('ALL')
    setKdperiode('ALL')
  }

  return (
    <div className="space-y-6 text-left">
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-5 border-b border-[#e7e9eb]">
        <div>
          <div className="flex items-center gap-2">
            <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
              Dashboard & Daftar Pengajuan
            </h4>
            <span className="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#5b69bc]/10 text-[#5b69bc] border border-[#5b69bc]/20">
              Portal Dekan
            </span>
          </div>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Persetujuan kelayakan usulan, pengendalian pagu dana fakultas, dan monitoring ketercapaian riset dosen
          </p>
        </div>
      </div>

      {notice && (
        <div className="p-3 bg-[#10c469]/10 border border-[#10c469]/20 text-[#10c469] rounded-lg text-xs flex items-center gap-2 font-medium">
          <CheckCircle2 className="w-4 h-4 text-[#10c469] shrink-0" />
          <span>{notice}</span>
        </div>
      )}

      {/* Stats Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Menunggu Persetujuan Dekan"
          value={stats?.menungguPersetujuan || 0}
          subtext="Perlu verifikasi RIP Fakultas"
          icon={CheckSquare}
          iconColor="text-[#5b69bc] bg-[#5b69bc]/10 border-[#5b69bc]/20"
          trend={{ value: 'Antrean', label: 'perlu disetujui', positive: false }}
          progress={45}
          progressColor="bg-[#5b69bc]"
        />
        <StatCard
          label="Pagu Anggaran Fakultas"
          value={formatRupiah(stats?.paguFakultas || 0)}
          subtext="Total alokasi dana dari Universitas"
          icon={Building2}
          iconColor="text-[#10c469] bg-[#10c469]/10 border-[#10c469]/20"
        />
        <StatCard
          label="Total Dana Disetujui"
          value={formatRupiah(stats?.totalDanaDisetujui || 0)}
          subtext="Telah disetujui oleh LPPM"
          icon={Coins}
          iconColor="text-[#f9c851] bg-[#f9c851]/10 border-[#f9c851]/20"
          progress={
            stats?.paguFakultas > 0
              ? Math.min(100, Math.round(((stats?.totalDanaDisetujui || 0) / stats.paguFakultas) * 100))
              : 0
          }
          progressColor="bg-[#f9c851]"
        />
        <StatCard
          label="Usulan Penelitian Aktif"
          value={stats?.risetAktif || 0}
          subtext="Sedang berjalan di tahun ini"
          icon={AlertCircle}
          iconColor="text-[#ff5b5b] bg-[#ff5b5b]/10 border-[#ff5b5b]/20"
        />
      </div>

      <TableFilterBar
        searchValue={search}
        onSearchChange={setSearch}
        searchPlaceholder="Cari judul, ketua, prodi, atau skema..."
        filters={[
          {
            key: 'tahap',
            label: 'Tahap',
            value: tahapFilter,
            onChange: setTahapFilter,
            options: [
              { value: 'ALL', label: 'Semua Tahap' },
              ...Object.entries(TAHAP_USULAN).map(([value, { label }]) => ({ value, label })),
            ],
          },
          {
            key: 'periode',
            label: 'Periode',
            value: kdperiode,
            onChange: setKdperiode,
            options: [
              { value: 'ALL', label: 'Semua Periode' },
              ...periodeList.map((p) => ({ value: p.kodeperiode, label: String(p.tahun) })),
            ],
          },
        ]}
        onReset={reset}
        totalCount={items.length}
        filteredCount={filtered.length}
      />

      {/* Tabel Utama */}
      <Card>
        <CardHeader
          title="Daftar Pengajuan Fakultas"
          subtitle={`Menampilkan ${filtered.length} usulan penelitian`}
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-5 py-3 font-heading">Kode & Judul Usulan</th>
                <th className="px-3.5 py-3 font-heading">Ketua & Program Studi</th>
                <th className="px-3.5 py-3 font-heading">Skema</th>
                <th className="px-3.5 py-3 text-right font-heading">Biaya Usulan</th>
                <th className="px-3.5 py-3 text-center font-heading">Status</th>
                <th className="px-4 py-3 text-center font-heading">Aksi</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {isLoading ? (
                <TableSkeleton cols={6} rows={5} />
              ) : filtered.length === 0 ? (
                <TableEmptyState
                  colSpan={6}
                  message="Tidak ada usulan yang cocok dengan kriteria pencarian."
                  onReset={reset}
                />
              ) : (
                paginatedItems.map((p) => (
                  <tr key={p.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                    <td className="px-5 py-3.5 max-w-md">
                      <span className="font-mono text-[11px] text-[#188ae2] font-semibold block">
                        {p.kodeUsulan} &bull; TA {p.tahun}
                      </span>
                      <p className="font-semibold text-[#313a46] line-clamp-2 mt-0.5" title={p.judul}>
                        {p.judul}
                      </p>
                    </td>
                    <td className="px-3.5 py-3.5">
                      <span className="font-medium text-[#313a46] block">{p.ketuaNama}</span>
                      <span className="text-[10px] text-[#98a6ad]">{p.prodiNama}</span>
                    </td>
                    <td className="px-3.5 py-3.5 whitespace-nowrap font-medium text-[#313a46]">{p.skimNama}</td>
                    <td className="px-3.5 py-3.5 text-right whitespace-nowrap font-tabular font-bold text-[#313a46]">
                      {formatRupiah(p.biayaUsulan)}
                    </td>
                    <td className="px-3.5 py-3.5 text-center whitespace-nowrap">
                      <StatusBadge status={p.status} dokumenFinal={p.isDokumenProposalFinal} />
                    </td>
                    <td className="px-4 py-3.5 text-center whitespace-nowrap">
                      {p.status === STATUS_USULAN.SUBMITTED && p.isDokumenProposalFinal ? (
                        <Button
                          variant="primary"
                          size="xs"
                          className="bg-purple-700 hover:bg-purple-800"
                          onClick={() => {
                            setSelectedProposal(p)
                            setApprovalNote('')
                          }}
                        >
                          Setujui / Telaah
                        </Button>
                      ) : (
                        <Button
                          variant="secondary"
                          size="xs"
                          onClick={() => {
                            setSelectedProposal(p)
                            setApprovalNote(p.approvalNote || p.rejectionNote || 'Sudah ditelaah.')
                          }}
                        >
                          Lihat PDF
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

      {/* Modal Telaah Dekan */}
      <Modal
        isOpen={!!selectedProposal}
        onClose={() => setSelectedProposal(null)}
        title={selectedProposal?.status === STATUS_USULAN.SUBMITTED && selectedProposal?.isDokumenProposalFinal ? "Telaah & Pengesahan Usulan Tingkat Fakultas" : "Pratinjau Dokumen & Catatan Dekan"}
        subtitle={selectedProposal ? `${selectedProposal.kodeUsulan} — ${selectedProposal.ketuaNama}` : ''}
        maxWidth="max-w-4xl"
        footer={
          <>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setSelectedProposal(null)}
              disabled={isProcessing}
            >
              Tutup
            </Button>
            {selectedProposal?.status === STATUS_USULAN.SUBMITTED && selectedProposal?.isDokumenProposalFinal && (
              <div className="flex gap-2">
                <Button
                  variant="danger"
                  size="sm"
                  iconLeft={XCircle}
                  isLoading={isProcessing}
                  disabled={!approvalNote.trim() || !proposalUrl}
                  onClick={handleReject}
                >
                  Tolak Usulan
                </Button>
                <Button
                  variant="primary"
                  size="sm"
                  className="bg-[#10c469] hover:bg-[#10c469]/90 border-[#10c469]"
                  iconLeft={CheckCircle2}
                  isLoading={isProcessing}
                  disabled={!approvalNote.trim() || !proposalUrl}
                  onClick={handleApprove}
                >
                  Setujui Usulan
                </Button>
              </div>
            )}
          </>
        }
      >
        {selectedProposal && (
          <div className="space-y-4">
            {actionError && (
              <div className="p-3 bg-rose-50 border border-rose-200 text-rose-700 rounded-lg flex items-start gap-2">
                <AlertCircle className="w-5 h-5 shrink-0 mt-0.5" />
                <p className="text-sm font-medium">{actionError}</p>
              </div>
            )}

            <div className="p-3 bg-slate-50 border border-slate-200 rounded-lg space-y-1">
              <p className="font-semibold text-slate-800">{selectedProposal.judul}</p>
              <p className="text-slate-600">Ketua: <strong>{selectedProposal.ketuaNama}</strong> ({selectedProposal.prodiNama})</p>
              <p className="text-slate-600">Usulan Biaya: <strong>{formatRupiah(selectedProposal.biayaUsulan)}</strong></p>
            </div>

            <div className="space-y-2">
              <div className="flex items-center justify-between gap-2">
                <p className="font-semibold text-slate-700">Naskah proposal</p>
                {proposalUrl && (
                  <a
                    href={proposalUrl}
                    download={`Proposal_${selectedProposal.kodeUsulan}.pdf`}
                    className="inline-flex items-center gap-1 text-[#188ae2] font-semibold hover:underline"
                  >
                    <Download className="w-3.5 h-3.5" /> Unduh Proposal (.PDF)
                  </a>
                )}
              </div>
              {proposalError ? (
                <p className="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700">{proposalError}</p>
              ) : proposalUrl ? (
                <iframe
                  title="Naskah proposal"
                  src={proposalUrl}
                  className="w-full h-[55vh] border border-slate-200 rounded-lg bg-white"
                />
              ) : (
                <p className="p-3 text-slate-500">Memuat naskah proposal...</p>
              )}
            </div>

            <div>
              <label className="block font-semibold text-slate-700 mb-1">
                Keterangan / Catatan Dekan:
              </label>
              <textarea
                rows={3}
                value={approvalNote}
                onChange={(e) => setApprovalNote(e.target.value)}
                readOnly={!(selectedProposal?.status === STATUS_USULAN.SUBMITTED && selectedProposal?.isDokumenProposalFinal)}
                placeholder="Usulan perlu diselaraskan dengan Rencana Induk Penelitian Fakultas Teknik..."
                className="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-brand-500/20 focus:border-brand-500 transition-shadow text-sm"
              />
            </div>
          </div>
        )}
      </Modal>
    </div>
  )
}
