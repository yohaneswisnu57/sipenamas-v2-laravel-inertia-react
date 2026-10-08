import React, { useState, useEffect } from 'react'
import {
  CalendarDays,
  Plus,
  CheckCircle,
  XCircle,
  Calendar,
  DollarSign,
  AlertCircle,
  Clock,
  Pencil,
} from 'lucide-react'
import { adminApi } from '../../services/api/adminApi'
import { formatRupiah, formatDate } from '../../utils/formatters'
import { Card, CardHeader, CardContent, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Modal } from '../../components/ui/Modal'
import { Badge } from '../../components/ui/Badge'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

const DEFAULT_FORM = {
  kodeperiode: '2026-G2',
  tahun: '2026',
  nama: 'Tahun Anggaran 2026 (Gelombang II)',
  tglBukaUsulan: '2026-06-01',
  tglTutupUsulan: '2026-07-31',
  tglBatasReview: '2026-08-15',
  tglBatasRevisi: '2026-08-30',
  tglMonev: '2026-10-15',
  tglLaporanAkhir: '2026-12-15',
  tglPelaksanaanMulai: '',
  tglPelaksanaanSelesai: '',
  totalPagu: 1000000000,
  kuotaProposal: 50,
  isaktif: false,
}

// Kolom tanggal legacy bisa berupa datetime atau '0000-00-00'; input date butuh YYYY-MM-DD.
const toDateInput = (value) => {
  const tgl = value ? String(value).slice(0, 10) : ''
  return tgl.startsWith('0000') ? '' : tgl
}

function StatusPeriodeBadge({ isaktif }) {
  return isaktif === 1 ? (
    <Badge variant="success" size="sm">
      <CheckCircle className="w-3 h-3" />
      Aktif
    </Badge>
  ) : (
    <Badge variant="neutral" size="sm">
      Nonaktif / Tutup
    </Badge>
  )
}

// Dipakai di tabel (desktop) dan kartu (mobile). Di desktop kedua tombol
// ditumpuk dengan lebar sama supaya kolom aksi sempit dan rata antar baris;
// di mobile tombol melebar penuh dan lebih tinggi supaya mudah disentuh.
function PeriodeActions({ periode, togglingId, onEdit, onToggle, isMobile = false }) {
  return (
    <div className={isMobile ? 'grid grid-cols-2 gap-2' : 'mx-auto flex w-28 flex-col gap-1.5'}>
      <Button
        variant="secondary"
        size={isMobile ? 'sm' : 'xs'}
        iconLeft={Pencil}
        onClick={() => onEdit(periode)}
        disabled={togglingId !== null}
      >
        Edit
      </Button>
      <Button
        variant={periode.isaktif === 1 ? 'subtle' : 'secondary'}
        size={isMobile ? 'sm' : 'xs'}
        onClick={() => onToggle(periode.id)}
        isLoading={togglingId === periode.id}
        disabled={togglingId !== null && togglingId !== periode.id}
      >
        {periode.isaktif === 1 ? 'Tutup Periode' : 'Aktifkan'}
      </Button>
    </div>
  )
}

function JadwalItem({ label, children }) {
  return (
    <div>
      <dt className="text-[10px] uppercase tracking-wider text-[#98a6ad]">{label}</dt>
      <dd className="text-[#313a46] font-medium">{children}</dd>
    </div>
  )
}

export default function MasterPeriodePage() {
  const [periodeList, setPeriodeList] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const [statusFilter, setStatusFilter] = useState('ALL')
  const [togglingId, setTogglingId] = useState(null)
  const [errorNotice, setErrorNotice] = useState('')
  const [formError, setFormError] = useState('')
  // null = mode tambah periode baru, selain itu id periode yang sedang diedit
  const [editingId, setEditingId] = useState(null)
  const [formData, setFormData] = useState(DEFAULT_FORM)
  const isEditing = editingId !== null

  const loadData = async () => {
    setIsLoading(true)
    try {
      const res = await adminApi.getPeriodeList()
      setPeriodeList(res.data)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [])

  const handleToggle = async (id) => {
    setTogglingId(id)
    setErrorNotice('')
    try {
      await adminApi.togglePeriodeStatus(id)
      await loadData()
    } catch (err) {
      setErrorNotice(err?.message || 'Gagal mengubah status periode. Silakan coba lagi.')
    } finally {
      setTogglingId(null)
    }
  }

  const openCreateModal = () => {
    setEditingId(null)
    setFormData(DEFAULT_FORM)
    setFormError('')
    setIsModalOpen(true)
  }

  const openEditModal = (p) => {
    setEditingId(p.id)
    setFormData({
      ...DEFAULT_FORM,
      kodeperiode: p.kodeperiode ?? '',
      tahun: p.tahun ?? '',
      nama: p.nama ?? '',
      tglBukaUsulan: toDateInput(p.tglBukaUsulan),
      tglTutupUsulan: toDateInput(p.tglTutupUsulan),
      tglBatasReview: toDateInput(p.tglBatasReview),
      tglBatasRevisi: toDateInput(p.tglBatasRevisi),
      tglMonev: toDateInput(p.tglMonev),
      tglLaporanAkhir: toDateInput(p.tglLaporanAkhir),
      tglPelaksanaanMulai: toDateInput(p.tglPelaksanaanMulai),
      tglPelaksanaanSelesai: toDateInput(p.tglPelaksanaanSelesai),
      isaktif: p.isaktif === 1,
    })
    setFormError('')
    setIsModalOpen(true)
  }

  const closeModal = () => {
    setIsModalOpen(false)
    setFormError('')
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setIsSubmitting(true)
    setFormError('')
    try {
      if (isEditing) {
        await adminApi.updatePeriode(editingId, formData)
      } else {
        await adminApi.createPeriode(formData)
      }
      setIsModalOpen(false)
      await loadData()
    } catch (err) {
      setFormError(err?.message || 'Gagal menyimpan periode. Silakan coba lagi.')
    } finally {
      setIsSubmitting(false)
    }
  }

  const filteredPeriode = periodeList.filter((p) => {
    const matchSearch =
      !searchQuery ||
      p.kodeperiode?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      p.nama?.toLowerCase().includes(searchQuery.toLowerCase()) ||
      String(p.tahun)?.includes(searchQuery)
    const matchStatus =
      statusFilter === 'ALL' ||
      (statusFilter === 'AKTIF' && p.isaktif) ||
      (statusFilter === 'NONAKTIF' && !p.isaktif)
    return matchSearch && matchStatus
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedPeriode } =
    usePagination(filteredPeriode, 10)

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Master Periode & Jadwal Hibah
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Pengaturan kalender seleksi penelitian, penetapan pagu, dan pembukaan gelombang usulan
          </p>
        </div>
        <Button
          variant="primary"
          size="sm"
          iconLeft={Plus}
          onClick={openCreateModal}
          className="w-full sm:w-auto shrink-0 whitespace-nowrap"
        >
          Buka Periode Baru
        </Button>
      </div>

      {errorNotice && (
        <div className="p-3.5 bg-[#ff5b5b]/10 border border-[#ff5b5b]/20 text-[#ff5b5b] rounded-xl text-xs flex items-center gap-2">
          <XCircle className="w-4 h-4 text-[#ff5b5b] shrink-0" />
          <span className="font-semibold text-[#ff5b5b]">{errorNotice}</span>
        </div>
      )}

      {/* Filter Toolbar */}
      <TableFilterBar
        search={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari kode periode, nama, tahun..."
        filters={[
          {
            key: 'status',
            value: statusFilter,
            onChange: setStatusFilter,
            options: [
              { value: 'ALL', label: 'Semua Status Periode' },
              { value: 'AKTIF', label: 'Hanya Periode Aktif' },
              { value: 'NONAKTIF', label: 'Periode Selesai / Arsip' },
            ],
          },
        ]}
        onReset={() => {
          setSearchQuery('')
          setStatusFilter('ALL')
        }}
        totalResults={filteredPeriode.length}
      />

      {/* Period Table */}
      <Card>
        <CardHeader
          title="Daftar Periode Anggaran LPPM"
          subtitle="Hanya ada satu periode yang dapat berstatus 'Aktif' pada satu waktu"
        />
        {/* Mobile: daftar kartu, tabel 7 kolom tidak muat di layar sempit */}
        <div className="lg:hidden divide-y divide-[#e7e9eb]">
          {isLoading ? (
            <p className="px-4 py-8 text-center text-xs text-[#98a6ad]">Memuat data periode...</p>
          ) : pagedPeriode.length === 0 ? (
            <div className="px-4 py-8 text-center text-xs">
              <p className="font-semibold text-[#313a46]">Tidak ada periode hibah ditemukan</p>
              <p className="text-[#98a6ad] mt-1">Coba sesuaikan kata kunci pencarian atau status periode.</p>
            </div>
          ) : (
            pagedPeriode.map((p) => (
              <div key={p.id} className="p-4 space-y-3 text-xs">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <span className="font-mono font-bold text-[#188ae2] block">{p.kodeperiode}</span>
                    <span className="text-[#313a46] font-medium block break-words">{p.nama}</span>
                    <span className="text-[10px] text-[#98a6ad] block mt-0.5">
                      Tahun Anggaran {p.tahun} &bull; Kuota: {p.kuotaProposal} usulan
                    </span>
                  </div>
                  <div className="shrink-0">
                    <StatusPeriodeBadge isaktif={p.isaktif} />
                  </div>
                </div>

                <dl className="grid grid-cols-2 gap-x-3 gap-y-2">
                  <JadwalItem label="Buka Usulan">{formatDate(p.tglBukaUsulan)}</JadwalItem>
                  <JadwalItem label="Tutup Usulan">{formatDate(p.tglTutupUsulan)}</JadwalItem>
                  <JadwalItem label="Batas Review">{formatDate(p.tglBatasReview)}</JadwalItem>
                  <JadwalItem label="Batas Revisi">{formatDate(p.tglBatasRevisi)}</JadwalItem>
                  <JadwalItem label="Monev">{formatDate(p.tglMonev)}</JadwalItem>
                  <JadwalItem label="Laporan Akhir">{formatDate(p.tglLaporanAkhir)}</JadwalItem>
                  {p.tglPelaksanaanMulai && (
                    <div className="col-span-2">
                      <JadwalItem label="Pelaksanaan">
                        {formatDate(p.tglPelaksanaanMulai)}
                        {p.tglPelaksanaanSelesai !== p.tglPelaksanaanMulai && ` - ${formatDate(p.tglPelaksanaanSelesai)}`}
                      </JadwalItem>
                    </div>
                  )}
                  <div className="col-span-2">
                    <JadwalItem label="Pagu Anggaran">
                      <span className="font-tabular font-bold">{formatRupiah(p.totalPagu)}</span>
                    </JadwalItem>
                  </div>
                </dl>

                <PeriodeActions
                  periode={p}
                  togglingId={togglingId}
                  onEdit={openEditModal}
                  onToggle={handleToggle}
                  isMobile
                />
              </div>
            ))
          )}
        </div>

        <div className="hidden lg:block overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-3 py-3 text-center">Aksi</th>
                <th className="px-4 py-3 min-w-[14rem]">Kode & Nama Periode</th>
                <th className="px-3 py-3">Penerimaan Usulan</th>
                <th className="px-3 py-3">Batas Review & Revisi</th>
                <th className="px-3 py-3">Jadwal Monev</th>
                <th className="px-3 py-3 text-right">Pagu Anggaran</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton rows={4} cols={6} />
              ) : pagedPeriode.length === 0 ? (
                <TableEmptyState
                  colSpan={6}
                  message="Tidak ada periode hibah ditemukan"
                  submessage="Coba sesuaikan kata kunci pencarian atau status periode."
                  onReset={() => {
                    setSearchQuery('')
                    setStatusFilter('ALL')
                  }}
                />
              ) : (
                pagedPeriode.map((p) => (
                <tr key={p.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                  <td className="px-3 py-3.5 text-center whitespace-nowrap">
                    <PeriodeActions
                      periode={p}
                      togglingId={togglingId}
                      onEdit={openEditModal}
                      onToggle={handleToggle}
                    />
                  </td>
                  <td className="px-4 py-3.5">
                    <div className="flex items-center gap-2">
                      <span className="font-mono text-xs font-bold text-[#188ae2]">{p.kodeperiode}</span>
                      <StatusPeriodeBadge isaktif={p.isaktif} />
                    </div>
                    <span className="text-[#313a46] text-[11px] font-medium">{p.nama}</span>
                    <span className="text-[10px] text-[#98a6ad] block mt-0.5">
                      Tahun Anggaran {p.tahun} &bull; Kuota: {p.kuotaProposal} usulan
                    </span>
                  </td>
                  <td className="px-3 py-3.5 whitespace-nowrap">
                    <span className="text-[#313a46] font-medium">{formatDate(p.tglBukaUsulan)}</span>
                    <span className="text-[#98a6ad] mx-1">&rarr;</span>
                    <span className="text-[#313a46] font-medium">{formatDate(p.tglTutupUsulan)}</span>
                  </td>
                  <td className="px-3 py-3.5 whitespace-nowrap">
                    <div className="text-[11px]">
                      <span className="text-[#98a6ad]">Review: </span>
                      <strong className="text-[#313a46]">{formatDate(p.tglBatasReview)}</strong>
                    </div>
                    <div className="text-[11px]">
                      <span className="text-[#98a6ad]">Revisi: </span>
                      <strong className="text-[#313a46]">{formatDate(p.tglBatasRevisi)}</strong>
                    </div>
                  </td>
                  <td className="px-3 py-3.5 whitespace-nowrap">
                    <span className="text-slate-800 font-medium">{formatDate(p.tglMonev)}</span>
                    <span className="text-[10px] text-slate-400 block">
                      Laporan Akhir: {formatDate(p.tglLaporanAkhir)}
                    </span>
                    {p.tglPelaksanaanMulai && (
                      <span className="text-[10px] text-slate-400 block">
                        Pelaksanaan: {formatDate(p.tglPelaksanaanMulai)}
                        {p.tglPelaksanaanSelesai !== p.tglPelaksanaanMulai && ` - ${formatDate(p.tglPelaksanaanSelesai)}`}
                      </span>
                    )}
                  </td>
                  <td className="px-3 py-3.5 text-right whitespace-nowrap font-tabular font-bold text-slate-900">
                    {formatRupiah(p.totalPagu)}
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

      {/* Modal Buka Periode Baru / Edit Periode */}
      <Modal
        isOpen={isModalOpen}
        onClose={closeModal}
        title={isEditing ? `Edit Periode ${formData.kodeperiode}` : 'Buka Periode Pengusulan Baru'}
        subtitle={
          isEditing
            ? 'Mengubah nama, tahun anggaran, dan jadwal gelombang aktif periode ini'
            : 'Menetapkan tanggal buka, batas telaah reviewer, dan total alokasi dana'
        }
        maxWidth="max-w-2xl"
        footer={
          <>
            <Button variant="secondary" size="sm" onClick={closeModal} disabled={isSubmitting}>
              Batal
            </Button>
            <Button
              variant="primary"
              size="sm"
              onClick={handleSubmit}
              isLoading={isSubmitting}
            >
              {isEditing ? 'Simpan Perubahan' : 'Simpan & Buka Periode'}
            </Button>
          </>
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4 text-xs">
          {formError && (
            <p className="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 font-semibold flex items-start gap-2">
              <XCircle className="w-4 h-4 shrink-0 mt-0.5" /> {formError}
            </p>
          )}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Kode Periode</label>
              <input
                type="text"
                required
                disabled={isEditing}
                value={formData.kodeperiode}
                onChange={(e) => setFormData({ ...formData, kodeperiode: e.target.value })}
                className="w-full px-3 py-2 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 font-mono disabled:bg-slate-100 disabled:text-slate-500"
              />
              {isEditing && (
                <p className="text-[10px] text-slate-400 mt-1">Kode periode tidak dapat diubah.</p>
              )}
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Tahun Anggaran</label>
              <input
                type="number"
                required
                value={formData.tahun}
                onChange={(e) => setFormData({ ...formData, tahun: e.target.value })}
                className="w-full px-3 py-2 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500 font-mono"
              />
            </div>
          </div>

          <div>
            <label className="block font-semibold text-slate-700 mb-1">Nama Periode</label>
            <input
              type="text"
              required
              value={formData.nama}
              onChange={(e) => setFormData({ ...formData, nama: e.target.value })}
              className="w-full px-3 py-2 border border-slate-300 rounded-md focus:ring-1 focus:ring-blue-500"
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Tanggal Buka Usulan</label>
              <input
                type="date"
                required
                value={formData.tglBukaUsulan}
                onChange={(e) => setFormData({ ...formData, tglBukaUsulan: e.target.value })}
                className="w-full px-3 py-2 border border-slate-300 rounded-md"
              />
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Tanggal Tutup Usulan</label>
              <input
                type="date"
                required
                value={formData.tglTutupUsulan}
                onChange={(e) => setFormData({ ...formData, tglTutupUsulan: e.target.value })}
                className="w-full px-3 py-2 border border-slate-300 rounded-md"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Batas Penilaian Reviewer</label>
              <input
                type="date"
                required
                value={formData.tglBatasReview}
                onChange={(e) => setFormData({ ...formData, tglBatasReview: e.target.value })}
                className="w-full px-3 py-2 border border-slate-300 rounded-md"
              />
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Batas Unggah Revisi Proposal</label>
              <input
                type="date"
                required
                value={formData.tglBatasRevisi}
                onChange={(e) => setFormData({ ...formData, tglBatasRevisi: e.target.value })}
                className="w-full px-3 py-2 border border-slate-300 rounded-md"
              />
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Jadwal Monev</label>
              <input
                type="date"
                required
                value={formData.tglMonev}
                onChange={(e) => setFormData({ ...formData, tglMonev: e.target.value })}
                className="w-full px-3 py-2 border border-slate-300 rounded-md"
              />
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Batas Laporan Akhir</label>
              <input
                type="date"
                required
                value={formData.tglLaporanAkhir}
                onChange={(e) => setFormData({ ...formData, tglLaporanAkhir: e.target.value })}
                className="w-full px-3 py-2 border border-slate-300 rounded-md"
              />
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Tanggal Pelaksanaan Mulai</label>
              <input
                type="date"
                value={formData.tglPelaksanaanMulai}
                onChange={(e) => setFormData({ ...formData, tglPelaksanaanMulai: e.target.value })}
                className="w-full px-3 py-2 border border-slate-300 rounded-md"
              />
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Tanggal Pelaksanaan Selesai (kosong = sehari)</label>
              <input
                type="date"
                value={formData.tglPelaksanaanSelesai}
                onChange={(e) => setFormData({ ...formData, tglPelaksanaanSelesai: e.target.value })}
                className="w-full px-3 py-2 border border-slate-300 rounded-md"
              />
            </div>
          </div>

          {!isEditing && (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Total Pagu Dana (Rp)</label>
              <input
                type="number"
                required
                value={formData.totalPagu}
                onChange={(e) => setFormData({ ...formData, totalPagu: e.target.value })}
                className="w-full px-3 py-2 border border-slate-300 rounded-md font-tabular"
              />
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Target Kuota Proposal</label>
              <input
                type="number"
                required
                value={formData.kuotaProposal}
                onChange={(e) => setFormData({ ...formData, kuotaProposal: e.target.value })}
                className="w-full px-3 py-2 border border-slate-300 rounded-md font-tabular"
              />
            </div>
          </div>
          )}

          {!isEditing && (
          <div className="pt-2">
            <label className="flex items-center gap-2 cursor-pointer select-none">
              <input
                type="checkbox"
                checked={formData.isaktif}
                onChange={(e) => setFormData({ ...formData, isaktif: e.target.checked })}
                className="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-slate-300"
              />
              <span className="font-semibold text-slate-800">
                Jadikan periode ini langsung aktif (otomatis menonaktifkan periode lain)
              </span>
            </label>
          </div>
          )}
        </form>
      </Modal>
    </div>
  )
}
