import React, { useState } from 'react'
import {
  BookOpenCheck,
  Plus,
  CheckCircle2,
  Clock,
  Award,
  ExternalLink,
  XCircle,
} from 'lucide-react'
import { penelitiApi } from '../../services/api/penelitiApi'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Modal } from '../../components/ui/Modal'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

export default function InsentifJurnalPage({ insentifList = [], indexJurnalOptions = [] }) {
  const isLoading = false
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [notice, setNotice] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState('')
  const [searchQuery, setSearchQuery] = useState('')
  const [tingkatFilter, setTingkatFilter] = useState('ALL')
  const [statusFilter, setStatusFilter] = useState('ALL')

  const [formData, setFormData] = useState({
    judulArtikel: '',
    namaJurnal: '',
    tingkatJurnal: '',
    tahunTerbit: String(new Date().getFullYear()),
    volumeNomor: '',
  })

  const handleSubmit = async (e) => {
    e.preventDefault()
    setIsSubmitting(true)
    setFormError('')
    try {
      await penelitiApi.createInsentifJurnal(formData)
      setIsModalOpen(false)
      setNotice('Klaim insentif publikasi artikel ilmiah berhasil dikirim ke LPPM!')
      setTimeout(() => setNotice(''), 4000)
    } catch (err) {
      setFormError(err?.message || 'Gagal mengirim klaim insentif. Silakan coba lagi.')
    } finally {
      setIsSubmitting(false)
    }
  }

  const filteredInsentif = insentifList.filter((ins) => {
    const q = searchQuery.toLowerCase()
    const matchSearch =
      !searchQuery ||
      (ins.judulArtikel && ins.judulArtikel.toLowerCase().includes(q)) ||
      (ins.namaJurnal && ins.namaJurnal.toLowerCase().includes(q)) ||
      (ins.namaDosen && ins.namaDosen.toLowerCase().includes(q))
    const matchTingkat = tingkatFilter === 'ALL' || ins.tingkatJurnal === tingkatFilter
    const matchStatus = statusFilter === 'ALL' || ins.status === statusFilter
    return matchSearch && matchTingkat && matchStatus
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedInsentif } =
    usePagination(filteredInsentif, 10)

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Klaim Insentif Publikasi Artikel Ilmiah
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Pemberian reward penghargaan publikasi artikel pada jurnal internasional bereputasi dan jurnal nasional terakreditasi SINTA
          </p>
        </div>
        <Button
          variant="primary"
          size="sm"
          iconLeft={Plus}
          onClick={() => setIsModalOpen(true)}
        >
          Klaim Insentif Baru
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
        searchPlaceholder="Cari judul artikel, nama jurnal, atau nama dosen..."
        filters={[
          {
            key: 'tingkat',
            label: 'Indeksasi',
            value: tingkatFilter,
            onChange: setTingkatFilter,
            options: [
              { value: 'ALL', label: 'Semua Tingkat' },
              ...indexJurnalOptions.map((i) => ({ value: i.kode, label: i.nama })),
            ],
          },
          {
            key: 'status',
            label: 'Status Pencairan',
            value: statusFilter,
            onChange: setStatusFilter,
            options: [
              { value: 'ALL', label: 'Semua Status' },
              { value: 'CAIR_BAU', label: 'Cair BAU' },
              { value: 'VERIFIKASI_LPPM', label: 'Verifikasi LPPM' },
            ],
          },
        ]}
        onReset={() => {
          setSearchQuery('')
          setTingkatFilter('ALL')
          setStatusFilter('ALL')
        }}
        totalCount={insentifList.length}
        filteredCount={filteredInsentif.length}
      />

      {/* Table */}
      <Card>
        <CardHeader
          title="Daftar Pengajuan Insentif Publikasi"
          subtitle="Reward akan ditransfer ke rekening dosen setelah divalidasi oleh LPPM & BAU UKWMS"
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-4 py-3">Judul Artikel</th>
                <th className="px-3 py-3">Nama Jurnal & Edisi</th>
                <th className="px-3 py-3">Tingkat Indeksasi</th>
                <th className="px-3 py-3 text-center">Status Pencairan</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton cols={4} rows={5} />
              ) : filteredInsentif.length === 0 ? (
                <TableEmptyState
                  colSpan={4}
                  message={
                    searchQuery || tingkatFilter !== 'ALL' || statusFilter !== 'ALL'
                      ? 'Tidak ada pengajuan insentif yang cocok dengan kriteria pencarian.'
                      : 'Belum ada data pengajuan insentif publikasi.'
                  }
                  onReset={
                    searchQuery || tingkatFilter !== 'ALL' || statusFilter !== 'ALL'
                      ? () => {
                          setSearchQuery('')
                          setTingkatFilter('ALL')
                          setStatusFilter('ALL')
                        }
                      : null
                  }
                />
              ) : (
                pagedInsentif.map((ins) => (
                  <tr key={ins.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                    <td className="px-4 py-3.5 max-w-sm">
                      <p className="font-semibold text-[#313a46] line-clamp-2" title={ins.judulArtikel}>
                        {ins.judulArtikel}
                      </p>
                      <span className="text-[11px] text-[#98a6ad] block mt-0.5">{ins.namaDosen}</span>
                    </td>

                    <td className="px-3 py-3.5 whitespace-nowrap">
                      <p className="font-medium text-[#313a46]">{ins.namaJurnal}</p>
                      <span className="text-[11px] text-[#98a6ad] font-mono">{ins.volumeNomor}</span>
                    </td>

                    <td className="px-3 py-3.5 whitespace-nowrap">
                      <span className="px-2 py-0.5 rounded-full bg-[#188ae2]/10 text-[#188ae2] border border-[#188ae2]/20 font-semibold text-[11px]">
                        {ins.tingkatJurnalNama || ins.tingkatJurnal || '-'}
                      </span>
                    </td>

                    <td className="px-3 py-3.5 text-center whitespace-nowrap">
                      {ins.status === 'CAIR_BAU' ? (
                        <Badge variant="success" size="sm" pill>
                          <CheckCircle2 className="w-3 h-3" />
                          Cair BAU
                        </Badge>
                      ) : (
                        <Badge variant="warning" size="sm" pill>
                          <Clock className="w-3 h-3" />
                          Verifikasi LPPM
                        </Badge>
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

      {/* Modal Klaim */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => {
          setIsModalOpen(false)
          setFormError('')
        }}
        title="Formulir Pengajuan Insentif Publikasi"
        subtitle="Pastikan artikel telah terbit online (in press / published) dan berafiliasi UKWMS"
        maxWidth="max-w-lg"
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
            <Button variant="primary" size="sm" onClick={handleSubmit} isLoading={isSubmitting}>
              Simpan & Kirim
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
            <label className="block font-semibold text-slate-700 mb-1">Judul Artikel Terbit *</label>
            <textarea
              rows={2}
              required
              value={formData.judulArtikel}
              onChange={(e) => setFormData({ ...formData, judulArtikel: e.target.value })}
              className="w-full p-2 border border-slate-300 rounded-md"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Nama Jurnal *</label>
              <input
                type="text"
                required
                value={formData.namaJurnal}
                onChange={(e) => setFormData({ ...formData, namaJurnal: e.target.value })}
                className="w-full p-2 border border-slate-300 rounded-md"
              />
            </div>
            <div>
              <label htmlFor="insentif-index" className="block font-semibold text-slate-700 mb-1">Terindeks Dalam *</label>
              <select
                id="insentif-index"
                required
                value={formData.tingkatJurnal}
                onChange={(e) => setFormData({ ...formData, tingkatJurnal: e.target.value })}
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
          </div>

          <div>
            <label htmlFor="insentif-tahun" className="block font-semibold text-slate-700 mb-1">Tahun Terbit *</label>
            <input
              id="insentif-tahun"
              type="number"
              required
              value={formData.tahunTerbit}
              onChange={(e) => setFormData({ ...formData, tahunTerbit: e.target.value })}
              className="w-full p-2 border border-slate-300 rounded-md font-mono"
            />
          </div>

          <div>
            <label className="block font-semibold text-slate-700 mb-1">Volume, Nomor, dan Halaman *</label>
            <input
              type="text"
              required
              value={formData.volumeNomor}
              onChange={(e) => setFormData({ ...formData, volumeNomor: e.target.value })}
              placeholder="Contoh: Vol. 25, No. 3, pp. 110-125"
              className="w-full p-2 border border-slate-300 rounded-md font-mono"
            />
          </div>
        </form>
      </Modal>
    </div>
  )
}
