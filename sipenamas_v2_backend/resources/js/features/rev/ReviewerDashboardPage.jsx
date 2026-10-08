import React, { useState, useEffect } from 'react'
import { Link } from '@/lib/router'
import {
  BookMarked,
  CheckSquare,
  Clock,
  CheckCircle2,
  FileText,
  ArrowRight,
  AlertCircle,
} from 'lucide-react'
import { reviewerApi } from '../../services/api/reviewerApi'
import { useAuthStore } from '../../store/authStore'
import { StatCard } from '../../components/common/StatCard'
import { StatusBadge } from '../../components/common/StatusBadge'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { STATUS_USULAN, STATUS_USULAN_LABELS } from '../../utils/constants'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

export default function ReviewerDashboardPage() {
  const { user } = useAuthStore()
  const [tasks, setTasks] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [skimFilter, setSkimFilter] = useState('ALL')
  const [statusFilter, setStatusFilter] = useState('ALL')

  useEffect(() => {
    async function load() {
      setIsLoading(true)
      try {
        const res = await reviewerApi.getPenugasanList()
        setTasks(res.data)
      } finally {
        setIsLoading(false)
      }
    }
    load()
  }, [user])

  const pendingConfirmation = tasks.filter(
    (t) =>
      (t.reviewer1?.id === user?.id && t.reviewer1?.statusKesediaan === 'MENUNGGU') ||
      (t.reviewer2?.id === user?.id && t.reviewer2?.statusKesediaan === 'MENUNGGU')
  ).length

  const pendingScoring = tasks.filter(
    (t) =>
      !t.skorReviewer1 ||
      !t.skorReviewer2 ||
      t.status === STATUS_USULAN.PLOTTED ||
      t.status === STATUS_USULAN.REVIEW
  ).length

  const availableSkims = Array.from(new Set(tasks.map((t) => t.skimNama).filter(Boolean)))

  const filteredTasks = tasks.filter((t) => {
    const q = searchQuery.toLowerCase()
    const matchSearch =
      !searchQuery ||
      (t.kodeUsulan && t.kodeUsulan.toLowerCase().includes(q)) ||
      (t.judul && t.judul.toLowerCase().includes(q)) ||
      (t.ketuaNama && t.ketuaNama.toLowerCase().includes(q))
    const matchSkim = skimFilter === 'ALL' || t.skimNama === skimFilter
    const matchStatus = statusFilter === 'ALL' || t.status === statusFilter
    return matchSearch && matchSkim && matchStatus
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedTasks } =
    usePagination(filteredTasks, 10)

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <div className="flex items-center gap-2.5">
            <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
              Workspace Reviewer Penilai
            </h4>
            <span className="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#f9c851]/20 text-[#ab7405] border border-[#f9c851]/30">
              Portal Reviewer
            </span>
          </div>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Evaluasi substantif proposal penelitian, konfirmasi penugasan, dan pengisian rubrik skor terbobot
          </p>
        </div>

        <Link to="/rev/kesediaan">
          <Button variant="primary" size="sm" iconLeft={CheckSquare}>
            Konfirmasi Tugas Baru
          </Button>
        </Link>
      </div>

      {/* KPI Cards (Adminto Style) */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <StatCard
          label="Total Usulan Ditugaskan"
          value={tasks.length}
          subtext="Periode aktif LPPM"
          icon={FileText}
          iconColor="text-[#188ae2] bg-[#188ae2]/10 border-[#188ae2]/20"
          trend={{ value: 'Ditugaskan', label: 'oleh LPPM', positive: true }}
          progress={100}
          progressColor="bg-[#188ae2]"
        />
        <StatCard
          label="Perlu Konfirmasi Kesediaan"
          value={pendingConfirmation}
          subtext="Deklarasi bebas konflik kepentingan"
          icon={AlertCircle}
          iconColor="text-[#5b69bc] bg-[#5b69bc]/10 border-[#5b69bc]/20"
          trend={{ value: 'Pending', label: 'kesediaan', positive: pendingConfirmation === 0 }}
          progress={pendingConfirmation > 0 ? 30 : 100}
          progressColor="bg-[#5b69bc]"
        />
        <StatCard
          label="Menunggu Pengisian Skor"
          value={pendingScoring}
          subtext="Batas telaah 15 April 2026"
          icon={Clock}
          iconColor="text-[#f9c851] bg-[#f9c851]/15 border-[#f9c851]/30"
          trend={{ value: 'Antrean', label: 'evaluasi skor', positive: pendingScoring === 0 }}
          progress={pendingScoring > 0 ? 50 : 100}
          progressColor="bg-[#f9c851]"
        />
      </div>

      {/* Filter toolbar */}
      <TableFilterBar
        searchValue={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari kode usulan, judul penelitian, atau ketua..."
        filters={[
          {
            key: 'skim',
            label: 'Skema',
            value: skimFilter,
            onChange: setSkimFilter,
            options: [
              { value: 'ALL', label: 'Semua Skema' },
              ...availableSkims.map((s) => ({ value: s, label: s })),
            ],
          },
          {
            key: 'status',
            label: 'Status Usulan',
            value: statusFilter,
            onChange: setStatusFilter,
            options: [
              { value: 'ALL', label: 'Semua Status' },
              ...Object.entries(STATUS_USULAN_LABELS).map(([val, label]) => ({
                value: val,
                label,
              })),
            ],
          },
        ]}
        onReset={() => {
          setSearchQuery('')
          setSkimFilter('ALL')
          setStatusFilter('ALL')
        }}
        totalCount={tasks.length}
        filteredCount={filteredTasks.length}
      />

      {/* Tasks Table */}
      <Card>
        <CardHeader
          title="Daftar Usulan Penelitian yang Ditugaskan"
          subtitle="Nilai secara objektif berdasarkan rubrik kriteria baku LPPM UKWMS"
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-5 py-3 font-heading">Kode & Judul Usulan</th>
                <th className="px-3.5 py-3 font-heading">Ketua Peneliti</th>
                <th className="px-3.5 py-3 font-heading">Skema</th>
                <th className="px-3.5 py-3 text-center font-heading">Skor Anda</th>
                <th className="px-3.5 py-3 text-center font-heading">Status Usulan</th>
                <th className="px-4 py-3 text-center font-heading">Aksi Telaah</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {isLoading ? (
                <TableSkeleton cols={6} rows={5} />
              ) : filteredTasks.length === 0 ? (
                <TableEmptyState
                  colSpan={6}
                  message={
                    searchQuery || skimFilter !== 'ALL' || statusFilter !== 'ALL'
                      ? 'Tidak ada usulan penugasan yang cocok dengan kriteria pencarian.'
                      : 'Belum ada usulan penelitian yang ditugaskan ke akun Anda.'
                  }
                  onReset={
                    searchQuery || skimFilter !== 'ALL' || statusFilter !== 'ALL'
                      ? () => {
                          setSearchQuery('')
                          setSkimFilter('ALL')
                          setStatusFilter('ALL')
                        }
                      : null
                  }
                />
              ) : (
                pagedTasks.map((p) => (
                  <tr key={p.id} className="hover:bg-slate-50/80 transition">
                    <td className="px-5 py-3.5 max-w-sm">
                      <span className="font-mono text-[11px] text-slate-500 font-medium block">
                        {p.kodeUsulan} &bull; TA {p.tahun}
                      </span>
                      <p className="font-semibold text-slate-900 line-clamp-2 mt-0.5" title={p.judul}>
                        {p.judul}
                      </p>
                      <span className="text-[10px] text-slate-500 mt-0.5 block">
                        Fokus: {p.bidangFokus}
                      </span>
                    </td>

                    <td className="px-3.5 py-3.5 whitespace-nowrap">
                      <p className="font-medium text-slate-800">{p.ketuaNama}</p>
                      <span className="text-[11px] text-slate-500">{p.fakultasNama}</span>
                    </td>

                    <td className="px-3.5 py-3.5 whitespace-nowrap font-medium text-slate-700">
                      {p.skimNama}
                    </td>

                    <td className="px-3.5 py-3.5 text-center whitespace-nowrap font-mono font-bold text-[#188ae2]">
                      {p.skorReviewer1 || p.skorReviewer2 || '-'}
                    </td>

                    <td className="px-3.5 py-3.5 text-center whitespace-nowrap">
                      <StatusBadge status={p.status} />
                    </td>

                    <td className="px-4 py-3.5 text-center whitespace-nowrap">
                      <Link to={`/rev/penilaian/${p.id}`}>
                        <Button variant="primary" size="xs" className="bg-amber-700 hover:bg-amber-800">
                          Isi Rubrik Skor
                        </Button>
                      </Link>
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
    </div>
  )
}
