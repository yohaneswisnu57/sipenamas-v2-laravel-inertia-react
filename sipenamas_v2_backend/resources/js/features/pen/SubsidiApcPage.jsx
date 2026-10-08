import React, { useState } from 'react'
import {
  DollarSign,
  Plus,
  CheckCircle2,
  Clock,
  FileText,
  ExternalLink,
  UploadCloud,
  XCircle,
} from 'lucide-react'
import { penelitiApi } from '../../services/api/penelitiApi'
import { formatRupiah, formatDate } from '../../utils/formatters'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Modal } from '../../components/ui/Modal'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

export default function SubsidiApcPage({ apcList = [], indexJurnalOptions = [] }) {
  const isLoading = false
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState('')
  const [notice, setNotice] = useState('')
  const [searchQuery, setSearchQuery] = useState('')
  const [kategoriFilter, setKategoriFilter] = useState('ALL')
  const [statusFilter, setStatusFilter] = useState('ALL')

  // Form state
  const [formData, setFormData] = useState({
    judulArtikel: '',
    namaJurnal: '',
    penerbit: '',
    kategoriJurnal: '',
    nominalPengajuan: '',
    urlArtikel: '',
  })

  const handleSubmit = async (e) => {
    e.preventDefault()
    setIsSubmitting(true)
    setFormError('')
    try {
      await penelitiApi.createSubsidiApc(formData)
      setIsModalOpen(false)
      setNotice('Permohonan subsidi APC berhasil diajukan ke LPPM!')
      setTimeout(() => setNotice(''), 4000)
    } catch (err) {
      setFormError(err?.message || 'Gagal mengirim pengajuan subsidi APC. Silakan coba lagi.')
    } finally {
      setIsSubmitting(false)
    }
  }

  const filteredApc = apcList.filter((apc) => {
    const q = searchQuery.toLowerCase()
    const matchSearch =
      !searchQuery ||
      (apc.judulArtikel && apc.judulArtikel.toLowerCase().includes(q)) ||
      (apc.namaJurnal && apc.namaJurnal.toLowerCase().includes(q)) ||
      (apc.penerbit && apc.penerbit.toLowerCase().includes(q))
    const matchKategori = kategoriFilter === 'ALL' || apc.kategoriJurnal === kategoriFilter
    const matchStatus = statusFilter === 'ALL' || apc.status === statusFilter
    return matchSearch && matchKategori && matchStatus
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedApc } =
    usePagination(filteredApc, 10)

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Permohonan Subsidi Biaya Publikasi (APC)
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Bantuan dana Article Processing Charge untuk artikel ilmiah pada jurnal Open Access bereputasi internasional (Scopus Q1/Q2)
          </p>
        </div>
        <Button
          variant="primary"
          size="sm"
          iconLeft={Plus}
          onClick={() => setIsModalOpen(true)}
        >
          Ajukan Subsidi Baru
        </Button>
      </div>

      {notice && (
        <div className="p-3.5 bg-[#10c469]/10 border border-[#10c469]/20 text-[#10c469] rounded-xl text-xs flex items-center gap-2">
          <CheckCircle2 className="w-4 h-4 text-[#10c469] shrink-0" />
          <span className="font-semibold text-[#0c8a49]">{notice}</span>
        </div>
      )}

      {/* Filter toolbar */}
      <TableFilterBar
        searchValue={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari judul artikel, nama jurnal, atau penerbit..."
        filters={[
          {
            key: 'kategori',
            label: 'Kategori Jurnal',
            value: kategoriFilter,
            onChange: setKategoriFilter,
            options: [
              { value: 'ALL', label: 'Semua Kategori' },
              ...indexJurnalOptions.map((i) => ({ value: i.kode, label: i.nama })),
            ],
          },
          {
            key: 'status',
            label: 'Status',
            value: statusFilter,
            onChange: setStatusFilter,
            options: [
              { value: 'ALL', label: 'Semua Status' },
              { value: 'DISETUJUI_LPPM', label: 'Disetujui LPPM' },
              { value: 'REVIEW_LPPM', label: 'Review LPPM' },
            ],
          },
        ]}
        onReset={() => {
          setSearchQuery('')
          setKategoriFilter('ALL')
          setStatusFilter('ALL')
        }}
        totalCount={apcList.length}
        filteredCount={filteredApc.length}
      />

      {/* Table */}
      <Card>
        <CardHeader
          title="Riwayat Permohonan Subsidi APC"
          subtitle="Proses telaah LPPM dan koordinasi pencairan dana ke rekening pengusul"
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-4 py-3">Judul Artikel & Jurnal</th>
                <th className="px-3 py-3">Penerbit & Kategori</th>
                <th className="px-3 py-3 text-right">Nominal Pengajuan</th>
                <th className="px-3 py-3 text-right">Nominal Disetujui</th>
                <th className="px-3 py-3 text-center">Status</th>
                <th className="px-3 py-3 text-center">Berkas</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton cols={6} rows={5} />
              ) : filteredApc.length === 0 ? (
                <TableEmptyState
                  colSpan={6}
                  message={
                    searchQuery || kategoriFilter !== 'ALL' || statusFilter !== 'ALL'
                      ? 'Tidak ada permohonan subsidi APC yang cocok dengan kriteria pencarian.'
                      : 'Belum ada riwayat permohonan subsidi APC.'
                  }
                  onReset={
                    searchQuery || kategoriFilter !== 'ALL' || statusFilter !== 'ALL'
                      ? () => {
                          setSearchQuery('')
                          setKategoriFilter('ALL')
                          setStatusFilter('ALL')
                        }
                      : null
                  }
                />
              ) : (
                pagedApc.map((apc) => (
                <tr key={apc.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                  <td className="px-4 py-3.5 max-w-sm">
                    <p className="font-semibold text-[#313a46] line-clamp-2" title={apc.judulArtikel}>
                      {apc.judulArtikel}
                    </p>
                    <span className="text-[11px] text-[#188ae2] font-medium block mt-0.5">
                      {apc.namaJurnal}
                    </span>
                  </td>

                  <td className="px-3 py-3.5 whitespace-nowrap">
                    <p className="font-medium text-[#313a46]">{apc.penerbit}</p>
                    <span className="text-[10px] px-2 py-0.5 rounded-full bg-[#5b69bc]/10 text-[#5b69bc] border border-[#5b69bc]/20 font-semibold">
                      {apc.kategoriJurnalNama || apc.kategoriJurnal || '-'}
                    </span>
                  </td>

                  <td className="px-3 py-3.5 text-right whitespace-nowrap font-tabular text-[#6c757d]">
                    {formatRupiah(apc.nominalPengajuan)}
                  </td>

                  <td className="px-3 py-3.5 text-right whitespace-nowrap font-tabular font-bold text-[#313a46]">
                    {apc.nominalDisetujui ? formatRupiah(apc.nominalDisetujui) : '-'}
                  </td>

                  <td className="px-3 py-3.5 text-center whitespace-nowrap">
                    {apc.status === 'DISETUJUI_LPPM' ? (
                      <Badge variant="success" size="sm" pill>
                        <CheckCircle2 className="w-3 h-3" />
                        Disetujui LPPM
                      </Badge>
                    ) : (
                      <Badge variant="warning" size="sm">
                        <Clock className="w-3 h-3" />
                        Review LPPM
                      </Badge>
                    )}
                  </td>

                  <td className="px-3 py-3.5 text-center whitespace-nowrap">
                    <Button variant="secondary" size="xs" iconLeft={FileText}>
                      Invoice & LoA
                    </Button>
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

      {/* Modal Ajukan Subsidi APC Baru */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => {
          setIsModalOpen(false)
          setFormError('')
        }}
        title="Formulir Permohonan Subsidi APC"
        subtitle="Lampirkan Letter of Acceptance (LoA) resmi dan invoice resmi dari penerbit"
        maxWidth="max-w-xl"
        footer={
          <>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => {
                setIsModalOpen(false)
                setFormError('')
              }}
              disabled={isSubmitting}
            >
              Batal
            </Button>
            <Button
              variant="primary"
              size="sm"
              onClick={handleSubmit}
              isLoading={isSubmitting}
            >
              Kirim Pengajuan Subsidi
            </Button>
          </>
        }
      >
        <form onSubmit={handleSubmit} className="space-y-3.5 text-xs">
          {formError && (
            <p className="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 font-semibold flex items-start gap-2">
              <XCircle className="w-4 h-4 shrink-0 mt-0.5" /> {formError}
            </p>
          )}
          <div>
            <label className="block font-semibold text-slate-700 mb-1">Judul Artikel Ilmiah *</label>
            <textarea
              rows={2}
              required
              value={formData.judulArtikel}
              onChange={(e) => setFormData({ ...formData, judulArtikel: e.target.value })}
              placeholder="Judul artikel yang telah diterima terbit..."
              className="w-full p-2 border border-slate-300 rounded-md"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Nama Jurnal Ilmiah *</label>
              <input
                type="text"
                required
                value={formData.namaJurnal}
                onChange={(e) => setFormData({ ...formData, namaJurnal: e.target.value })}
                placeholder="Contoh: IEEE Access"
                className="w-full p-2 border border-slate-300 rounded-md"
              />
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Nama Penerbit (Publisher) *</label>
              <input
                type="text"
                required
                value={formData.penerbit}
                onChange={(e) => setFormData({ ...formData, penerbit: e.target.value })}
                placeholder="Contoh: IEEE / Elsevier / Springer"
                className="w-full p-2 border border-slate-300 rounded-md"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label htmlFor="apc-index" className="block font-semibold text-slate-700 mb-1">Terindeks Dalam *</label>
              <select
                id="apc-index"
                required
                value={formData.kategoriJurnal}
                onChange={(e) => setFormData({ ...formData, kategoriJurnal: e.target.value })}
                className="w-full p-2 border border-slate-300 rounded-md"
              >
                <option value="">- Pilih index jurnal -</option>
                {indexJurnalOptions.map((i) => (
                  <option key={i.kode} value={i.kode}>
                    {i.nama}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Nominal Tagihan APC (Rp) *</label>
              <input
                type="number"
                required
                value={formData.nominalPengajuan}
                onChange={(e) => setFormData({ ...formData, nominalPengajuan: e.target.value })}
                className="w-full p-2 border border-slate-300 rounded-md font-tabular"
              />
            </div>
          </div>

          <div>
            <label className="block font-semibold text-slate-700 mb-1">Unggah LoA & Invoice (PDF Maks 10 MB) *</label>
            <div className="p-4 border-2 border-dashed border-slate-300 rounded-lg text-center bg-slate-50 cursor-pointer">
              <UploadCloud className="w-6 h-6 text-blue-600 mx-auto mb-1" />
              <span className="text-slate-600 text-xs">Pilih file PDF berkas invoice & LoA</span>
            </div>
          </div>
        </form>
      </Modal>
    </div>
  )
}
