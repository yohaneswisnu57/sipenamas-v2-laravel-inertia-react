import React, { useState } from 'react'
import { Link } from '@/lib/router'
import {
  FileText,
  PlusCircle,
  Coins,
  Clock,
  AlertTriangle,
  ArrowRight,
  Download,
  Eye,
  Calendar,
  Sparkles,
} from 'lucide-react'
import { penelitiApi } from '../../services/api/penelitiApi'
import { formatRupiah, formatDate } from '../../utils/formatters'
import { useAuthStore } from '../../store/authStore'
import { StatCard } from '../../components/common/StatCard'
import { StatusBadge } from '../../components/common/StatusBadge'
import { Card, CardHeader, CardContent } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { STATUS_USULAN, STATUS_USULAN_LABELS } from '../../utils/constants'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

// `stats` dan `proposals` adalah deferred props: undefined sampai dimuat.
export default function PenelitiDashboardPage({ stats, proposals: deferredProposals, templates = [] }) {
  const { user } = useAuthStore()
  const proposals = deferredProposals || []
  const isLoading = deferredProposals === undefined
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState('ALL')

  const filteredProposals = proposals.filter((p) => {
    const q = searchQuery.toLowerCase()
    const matchSearch =
      !searchQuery ||
      (p.kodeUsulan && p.kodeUsulan.toLowerCase().includes(q)) ||
      (p.judul && p.judul.toLowerCase().includes(q)) ||
      (p.skimNama && p.skimNama.toLowerCase().includes(q))
    const matchStatus = statusFilter === 'ALL' || p.status === statusFilter
    return matchSearch && matchStatus
  })

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <div className="flex items-center gap-2.5">
            <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
              Workspace Dosen Peneliti & Pengabdi
            </h4>
            <span className="px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-[#188ae2]/10 text-[#188ae2] border border-[#188ae2]/20">
              Portal Peneliti
            </span>
          </div>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Selamat datang, <strong className="text-[#313a46] font-medium">{user?.name}</strong> &bull; NIDN:{' '}
            <span className="font-mono text-[#313a46]">{user?.nidn || '-'}</span> &bull; {user?.prodi}
          </p>
        </div>

        <Link to="/pen/penelitian/baru">
          <Button variant="primary" size="sm" iconLeft={PlusCircle}>
            Ajukan Usulan Baru
          </Button>
        </Link>
      </div>

      {/* Pending Action Banner if any revision / monev is required */}
      {stats?.pendingAction && (
        <div className="p-4 rounded-xl bg-[#f9c851]/15 border border-[#f9c851]/35 text-[#313a46] flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs shadow-2xs">
          <div className="flex items-start gap-3">
            <AlertTriangle className="w-5 h-5 text-[#b57a04] shrink-0 mt-0.5" />
            <div>
              <p className="font-bold text-[#b57a04] font-heading">
                Tindakan Diperlukan: {stats.pendingAction.kodeUsulan} Memerlukan Perbaikan
              </p>
              <p className="text-[11.5px] text-[#6c757d] mt-0.5">
                Reviewer telah mengunggah catatan evaluasi.
                {stats.batasRevisi && (
                  <>
                    {' '}Batas akhir perbaikan revisi: <strong className="text-[#313a46]">{formatDate(stats.batasRevisi)}</strong>.
                  </>
                )}
              </p>
            </div>
          </div>
          <Link to={`/pen/revisi/${stats.pendingAction.id}`}>
            <Button variant="soft-warning" size="xs" className="shrink-0 font-semibold">
              Unggah Revisi
            </Button>
          </Link>
        </div>
      )}

      {/* KPI Stats (Adminto Style) */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <StatCard
          label="Total Usulan Saya"
          value={stats?.totalUsulan || 0}
          subtext="Histori riset terdaftar"
          icon={FileText}
          iconColor="text-[#188ae2] bg-[#188ae2]/10 border-[#188ae2]/20"
        />
        <StatCard
          label="Usulan Aktif Berjalan"
          value={stats?.usulanAktif || 0}
          subtext="Sedang dalam siklus seleksi / kontrak"
          icon={Clock}
          iconColor="text-[#5b69bc] bg-[#5b69bc]/10 border-[#5b69bc]/20"
        />
        <StatCard
          label="Total Dana Disetujui"
          value={formatRupiah(stats?.totalDanaDisetujui)}
          subtext="Akumulasi hibah didanai"
          icon={Coins}
          iconColor="text-[#10c469] bg-[#10c469]/10 border-[#10c469]/20"
        />
      </div>

      {/* Proposals List */}
      <div className="space-y-3">
        <TableFilterBar
          searchValue={searchQuery}
          onSearchChange={setSearchQuery}
          searchPlaceholder="Cari kode usulan, judul penelitian, skema..."
          filters={[
            {
              key: 'status',
              label: 'Status',
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
            setStatusFilter('ALL')
          }}
          totalCount={proposals.length}
          filteredCount={filteredProposals.length}
        />

        <Card>
          <CardHeader
            title="Daftar Usulan Penelitian Saya"
            subtitle="Pantau alur persetujuan Dekan, catatan reviewer, dan tahapan pendanaan"
            action={
              <Link to="/pen/penelitian">
                <Button variant="ghost" size="xs">
                  Lihat Lengkap
                </Button>
              </Link>
            }
          />
          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
                <tr>
                  <th className="px-5 py-3 font-heading">Kode & Judul Usulan</th>
                  <th className="px-3.5 py-3 font-heading">Skema</th>
                  <th className="px-3.5 py-3 text-right font-heading">Biaya Disetujui</th>
                  <th className="px-3.5 py-3 text-center font-heading">Status Usulan</th>
                  <th className="px-4 py-3 text-center font-heading">Aksi</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-[#f1f4f8]">
                {isLoading ? (
                  <TableSkeleton cols={5} rows={4} />
                ) : filteredProposals.length === 0 ? (
                  <TableEmptyState
                    colSpan={5}
                    message={
                      searchQuery || statusFilter !== 'ALL'
                        ? 'Tidak ada usulan yang cocok dengan kriteria pencarian.'
                        : 'Belum ada usulan penelitian terdaftar.'
                    }
                    onReset={
                      searchQuery || statusFilter !== 'ALL'
                        ? () => {
                            setSearchQuery('')
                            setStatusFilter('ALL')
                          }
                        : null
                    }
                  />
                ) : (
                  filteredProposals.map((p) => (
                    <tr key={p.id} className="hover:bg-[#f8fafc] transition-colors">
                      <td className="px-5 py-3.5 max-w-md">
                        <span className="font-mono text-[11px] text-[#188ae2] font-semibold block">
                          {p.kodeUsulan} &bull; TA {p.tahun}
                        </span>
                        <p className="font-semibold text-[#313a46] line-clamp-2 mt-0.5" title={p.judul}>
                          {p.judul}
                        </p>
                        <span className="text-[10px] text-[#8a969c] mt-1 block">
                          Fokus: {p.bidangFokus}
                        </span>
                      </td>

                      <td className="px-3.5 py-3.5 whitespace-nowrap">
                        <span className="font-medium text-[#313a46]">{p.skimNama}</span>
                      </td>

                      <td className="px-3.5 py-3.5 text-right whitespace-nowrap font-tabular font-bold text-[#313a46]">
                        {formatRupiah(p.biayaDisetujui || p.biayaUsulan)}
                      </td>

                      <td className="px-3.5 py-3.5 text-center whitespace-nowrap">
                        <StatusBadge status={p.status} dokumenFinal={p.isDokumenProposalFinal} />
                      </td>

                      <td className="px-4 py-3.5 text-center whitespace-nowrap">
                        <div className="flex items-center justify-center gap-1.5">
                          <Link to={`/pen/penelitian/${p.id}`}>
                            <Button variant="soft-primary" size="xs">
                              Detail Usulan
                            </Button>
                          </Link>
                          {p.status === STATUS_USULAN.REVISI && (
                            <Link to={`/pen/revisi/${p.id}`}>
                              <Button variant="primary" size="xs" className="bg-amber-600 hover:bg-amber-700">
                                Revisi
                              </Button>
                            </Link>
                          )}
                        </div>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      {/* Templates Section */}
      <Card>
        <CardHeader 
          title="Panduan & Template Unduhan" 
          subtitle="Dokumen format resmi dari LPPM untuk pedoman pengusulan dan penyusunan laporan" 
        />
        <CardContent className="p-5">
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {Object.entries(
              // Panduan (readOnly) disembunyikan dulu atas permintaan pengguna.
              templates.filter(tpl => !tpl.readOnly).reduce((acc, tpl) => {
                ;(acc[tpl.skim] ||= []).push(tpl)
                return acc
              }, {})
            ).map(([skim, items]) => (
              <div key={skim} className="p-3.5 border border-[#e7e9eb] rounded-lg">
                <div className="flex items-center gap-2.5 mb-2.5">
                  <FileText className="w-4 h-4 text-[#6c757d] shrink-0" />
                  <h5 className="font-heading font-semibold text-[#313a46] text-[13px] truncate">{skim}</h5>
                </div>
                <div className="flex flex-wrap gap-2">
                  {items.map(tpl => (
                    <Button
                      key={tpl.id}
                      variant="ghost"
                      size="sm"
                      onClick={() => (tpl.readOnly ? penelitiApi.lihatTemplate(tpl.id) : penelitiApi.unduhTemplate(tpl.id, tpl.name))}
                      className="text-[#188ae2] hover:bg-[#188ae2]/10 capitalize"
                    >
                      {tpl.readOnly ? <Eye className="w-4 h-4" /> : <Download className="w-4 h-4" />}
                      {tpl.jenis}
                    </Button>
                  ))}
                </div>
              </div>
            ))}
            {templates.length === 0 && (
              <div className="col-span-full py-4 text-center text-sm text-slate-500">
                Memuat template...
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
