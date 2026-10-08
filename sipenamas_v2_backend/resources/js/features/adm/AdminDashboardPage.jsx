import React, { useEffect, useState } from 'react'
import { Link } from '@/lib/router'
import {
  Coins,
  FileText,
  Users2,
  FileCheck2,
  CalendarDays,
  ArrowUpRight,
  TrendingUp,
  Clock,
  CheckCircle2,
  AlertTriangle,
  FileSpreadsheet,
} from 'lucide-react'
import { adminApi } from '../../services/api/adminApi'
import { formatRupiah, formatDate } from '../../utils/formatters'
import { StatCard } from '../../components/common/StatCard'
import { StatusBadge } from '../../components/common/StatusBadge'
import { Card, CardHeader, CardContent } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { ROLES, STATUS_USULAN, TAHAP_USULAN } from '../../utils/constants'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'
import { Filter } from 'lucide-react'

export default function AdminDashboardPage() {
  const [stats, setStats] = useState(null)
  const [allProposals, setAllProposals] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [tableSearch, setTableSearch] = useState('')
  const [tableStatus, setTableStatus] = useState('ALL')
  const [tableTahap, setTableTahap] = useState('ALL')

  useEffect(() => {
    async function loadData() {
      setIsLoading(true)
      try {
        const resStats = await adminApi.getDashboardStats()
        const resProps = await adminApi.getPlottingList()
        setStats(resStats.data)
        setAllProposals(resProps.data || [])
      } finally {
        setIsLoading(false)
      }
    }
    loadData()
  }, [])

  const filteredProposals = allProposals.filter((p) => {
    const q = tableSearch.toLowerCase().trim()
    const matchSearch =
      !q ||
      p.judul?.toLowerCase().includes(q) ||
      p.kodeUsulan?.toLowerCase().includes(q) ||
      p.ketuaNama?.toLowerCase().includes(q) ||
      p.skimNama?.toLowerCase().includes(q) ||
      p.prodiNama?.toLowerCase().includes(q) ||
      p.fakultasKode?.toLowerCase().includes(q)
    const matchStatus = tableStatus === 'ALL' || p.status === tableStatus
    const matchTahap =
      tableTahap === 'ALL' || TAHAP_USULAN[tableTahap]?.statuses.includes(p.status)
    return matchSearch && matchStatus && matchTahap
  })

  const displayedProposals = filteredProposals.slice(0, 10)

  if (isLoading) {
    return (
      <div className="py-12 text-center text-xs text-slate-500">
        <div className="w-6 h-6 border-2 border-blue-600 border-t-transparent rounded-full animate-spin mx-auto mb-2" />
        Memuat dasbor LPPM...
      </div>
    )
  }

  return (
    <div className="space-y-6 text-left">
      {/* Page Title & Operational Header (Adminto Style) */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <div className="flex items-center gap-2.5">
            <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
              Pusat Kendali LPPM UKWMS
            </h4>
            <span className="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#ff5b5b]/10 text-[#ff5b5b] border border-[#ff5b5b]/20">
              Portal Admin
            </span>
          </div>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Tahun Anggaran {stats?.activePeriode?.tahun || '2026'} &bull; Gelombang Aktif:{' '}
            <strong className="text-[#313a46] font-medium">{stats?.activePeriode?.kodeperiode}</strong>
          </p>
        </div>

        <div className="flex items-center gap-2.5">
          <Link to="/adm/periode">
            <Button variant="secondary" size="sm" iconLeft={CalendarDays}>
              Kelola Periode
            </Button>
          </Link>
          <Link to="/adm/sinta">
            <Button variant="primary" size="sm" iconLeft={FileSpreadsheet}>
              Ekspor SINTA
            </Button>
          </Link>
        </div>
      </div>

      {/* KPI Stats Grid (Adminto Style) */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <StatCard
          label="Total Usulan Masuk"
          value={stats?.totalProposal || 0}
          subtext="Proposal periode aktif"
          icon={FileText}
          iconColor="text-[#188ae2] bg-[#188ae2]/10 border-[#188ae2]/20"
          trend={{ value: '+12%', label: 'vs periode lalu', positive: true }}
          progress={75}
          progressColor="bg-[#188ae2]"
        />
        <StatCard
          label="Total Dana Usulan"
          value={formatRupiah(stats?.totalDanaUsulan)}
          subtext="Akumulasi permohonan"
          icon={Coins}
          iconColor="text-[#5b69bc] bg-[#5b69bc]/10 border-[#5b69bc]/20"
          trend={{ value: '88%', label: 'pengajuan lengkap', positive: true }}
          progress={65}
          progressColor="bg-[#5b69bc]"
        />
        <StatCard
          label="Dana Disetujui (SK)"
          value={formatRupiah(stats?.totalDanaDisetujui)}
          subtext={`Serapan pagu: ${stats?.paguTerserapPersen}%`}
          icon={TrendingUp}
          iconColor="text-[#10c469] bg-[#10c469]/10 border-[#10c469]/20"
          trend={{ value: `${stats?.paguTerserapPersen}%`, label: 'terserap', positive: true }}
          progress={stats?.paguTerserapPersen || 45}
          progressColor="bg-[#10c469]"
        />
        <StatCard
          label="Pagu Anggaran LPPM"
          value={formatRupiah(stats?.activePeriode?.totalPagu)}
          subtext={`Kuota: ${stats?.activePeriode?.kuotaProposal} usulan`}
          icon={CalendarDays}
          iconColor="text-[#f9c851] bg-[#f9c851]/15 border-[#f9c851]/30"
          trend={{ value: 'Kuota', label: `${stats?.activePeriode?.kuotaProposal || 50} usulan`, positive: true }}
          progress={50}
          progressColor="bg-[#f9c851]"
        />
      </div>

      {/* Actionable Pipeline Queue Bar */}
      <div className="bg-white border border-[#e7e9eb] rounded-xl p-4 shadow-[0_1px_3px_rgba(0,0,0,0.03)]">
        <div className="flex items-center justify-between mb-3">
          <p className="text-[11px] font-bold text-[#8a969c] uppercase tracking-wider font-heading">
            Antrean Tindakan LPPM Segera
          </p>
          <span className="text-xs text-[#8a969c]">Perlu tindak lanjut admin</span>
        </div>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
          <Link
            to="/adm/plotting"
            className="p-3.5 rounded-xl border border-[#5b69bc]/20 bg-[#5b69bc]/5 hover:bg-[#5b69bc]/10 transition flex items-center justify-between group"
          >
            <div>
              <p className="text-[11px] text-[#5b69bc] font-semibold">Perlu Plotting Reviewer</p>
              <p className="text-xl font-bold text-[#313a46] font-heading font-tabular mt-0.5">
                {stats?.queueCounts?.menungguPlotting || 0}
              </p>
            </div>
            <div className="w-8 h-8 rounded-lg bg-white border border-[#5b69bc]/20 flex items-center justify-center text-[#5b69bc] group-hover:scale-105 transition-transform">
              <ArrowUpRight className="w-4 h-4" />
            </div>
          </Link>

          <Link
            to="/adm/plotting"
            className="p-3.5 rounded-xl border border-[#f9c851]/40 bg-[#f9c851]/10 hover:bg-[#f9c851]/20 transition flex items-center justify-between group"
          >
            <div>
              <p className="text-[11px] text-[#a16f03] font-semibold">Dalam Proses Review</p>
              <p className="text-xl font-bold text-[#313a46] font-heading font-tabular mt-0.5">
                {stats?.queueCounts?.dalamReview || 0}
              </p>
            </div>
            <div className="w-8 h-8 rounded-lg bg-white border border-[#f9c851]/30 flex items-center justify-center text-[#a16f03] group-hover:scale-105 transition-transform">
              <ArrowUpRight className="w-4 h-4" />
            </div>
          </Link>

          <Link
            to="/adm/final-approval"
            className="p-3.5 rounded-xl border border-[#188ae2]/20 bg-[#188ae2]/5 hover:bg-[#188ae2]/10 transition flex items-center justify-between group"
          >
            <div>
              <p className="text-[11px] text-[#188ae2] font-semibold">Sidang Final & SK</p>
              <p className="text-xl font-bold text-[#313a46] font-heading font-tabular mt-0.5">
                {stats?.queueCounts?.sidangFinal || 0}
              </p>
            </div>
            <div className="w-8 h-8 rounded-lg bg-white border border-[#188ae2]/20 flex items-center justify-center text-[#188ae2] group-hover:scale-105 transition-transform">
              <ArrowUpRight className="w-4 h-4" />
            </div>
          </Link>

        </div>
      </div>

      {/* Main Content: Proposals Table & Recent Activities */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Left: Latest Proposals Table */}
        <div className="lg:col-span-8">
          <Card>
            <CardHeader
              title="Proposal Terkini dalam Siklus Seleksi"
              subtitle="Memantau status usulan lintas fakultas secara real-time"
              action={
                <Link to="/adm/plotting">
                  <Button variant="ghost" size="xs" iconRight={ArrowUpRight}>
                    Lihat Semua
                  </Button>
                </Link>
              }
            />
            {/* Table Quick Filter Bar */}
            <div className="p-3 border-b border-[#e7e9eb] bg-[#f6f7fb]/30">
              <TableFilterBar
                searchValue={tableSearch}
                onSearchChange={setTableSearch}
                searchPlaceholder="Cari judul, ketua, kode usulan, skema..."
                filters={[
                  {
                    key: 'tahap',
                    value: tableTahap,
                    onChange: setTableTahap,
                    options: [
                      { value: 'ALL', label: 'Semua Tahap' },
                      ...Object.entries(TAHAP_USULAN).map(([value, { label }]) => ({ value, label })),
                    ],
                  },
                  {
                    key: 'status',
                    value: tableStatus,
                    onChange: setTableStatus,
                    options: [
                      { value: 'ALL', label: 'Semua Status Usulan' },
                      { value: STATUS_USULAN.DISETUJUI_DEKAN, label: 'Menunggu Plotting' },
                      { value: STATUS_USULAN.PLOTTED, label: 'Dalam Review' },
                      { value: STATUS_USULAN.REVISED, label: 'Revisi Naskah' },
                      { value: STATUS_USULAN.APPROVED, label: 'Lolos / Disetujui' },
                    ],
                  },
                ]}
                onReset={() => {
                  setTableSearch('')
                  setTableStatus('ALL')
                  setTableTahap('ALL')
                }}
                totalCount={allProposals.length}
                filteredCount={filteredProposals.length}
              />
            </div>

            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs">
                <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
                  <tr>
                    <th className="px-5 py-3 font-heading">Kode & Judul</th>
                    <th className="px-3.5 py-3 font-heading">Ketua & Unit</th>
                    <th className="px-3.5 py-3 text-right font-heading">Biaya Usulan</th>
                    <th className="px-4 py-3 text-center font-heading">Status Usulan</th>
                    <th className="px-4 py-3 text-center font-heading">Aksi</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-[#f1f4f8]">
                  {isLoading ? (
                    <TableSkeleton rows={5} cols={5} />
                  ) : filteredProposals.length === 0 ? (
                    <TableEmptyState
                      colSpan={5}
                      message="Tidak ada proposal ditemukan"
                      submessage="Coba ubah filter atau kata kunci pencarian."
                      onReset={() => {
                        setTableSearch('')
                        setTableStatus('ALL')
                      }}
                    />
                  ) : (
                    displayedProposals.map((p) => (
                      <tr key={p.id} className="hover:bg-[#f8fafc] transition-colors">
                        <td className="px-5 py-3.5 max-w-xs">
                          <span className="font-mono text-[11px] text-[#188ae2] font-semibold block">
                            {p.kodeUsulan}
                          </span>
                          <p className="font-semibold text-[#313a46] truncate mt-0.5" title={p.judul}>
                            {p.judul}
                          </p>
                          <span className="text-[10px] text-[#8a969c]">{p.skimNama}</span>
                        </td>
                        <td className="px-3.5 py-3.5 whitespace-nowrap">
                          <p className="font-medium text-[#313a46]">{p.ketuaNama}</p>
                          <span className="text-[11px] text-[#8a969c]">
                            {p.prodiNama} &bull; {p.fakultasKode}
                          </span>
                        </td>
                        <td className="px-3.5 py-3.5 text-right whitespace-nowrap font-tabular font-semibold text-[#313a46]">
                          {formatRupiah(p.biayaUsulan)}
                        </td>
                        <td className="px-4 py-3.5 text-center whitespace-nowrap">
                          <StatusBadge status={p.status} />
                        </td>
                        <td className="px-4 py-3.5 text-center whitespace-nowrap">
                          <Link to={`/adm/plotting`}>
                            <Button variant="soft-primary" size="xs">
                              Kelola
                            </Button>
                          </Link>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </Card>
        </div>

        {/* Right: Operational Activity Feed & Timeline */}
        <div className="lg:col-span-4 space-y-4">
          <Card>
            <CardHeader title="Aktivitas Sistem LPPM" subtitle="Catatan aksi terbaru di server" />
            <CardContent className="p-4 space-y-3.5 text-xs">
              {stats?.recentActivities?.map((act) => (
                <div key={act.id} className="flex items-start gap-3">
                  <div className="w-7 h-7 rounded-lg bg-[#188ae2]/10 text-[#188ae2] flex items-center justify-center shrink-0 mt-0.5 border border-[#188ae2]/20">
                    <Clock className="w-3.5 h-3.5" />
                  </div>
                  <div className="min-w-0 flex-1">
                    <p className="text-[#313a46] font-medium leading-snug">{act.title}</p>
                    <span className="text-[10px] text-[#8a969c]">{act.time}</span>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>

          {/* Quick Schedule Notice (Adminto Dark Card Style) */}
          <Card className="bg-[#252631] text-white border-[#373847] shadow-md">
            <CardContent className="p-4.5 space-y-2.5">
              <div className="flex items-center gap-2 text-[#f9c851] font-semibold text-xs font-heading">
                <AlertTriangle className="w-4 h-4" />
                <span>Pengingat Deadline Review</span>
              </div>
              <p className="text-xs text-[#aab9ca] leading-relaxed">
                Penilaian reviewer untuk Gelombang I ditutup pada{' '}
                <strong className="text-white">{formatDate(stats?.activePeriode?.tglBatasReview)}</strong>. Kirim notifikasi WA massal kepada reviewer yang belum menyelesaikan input skor.
              </p>
              <div className="pt-2">
                <Link to="/adm/whatsapp">
                  <Button variant="brand" size="xs" className="w-full">
                    Buka WhatsApp Broadcast
                  </Button>
                </Link>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}
