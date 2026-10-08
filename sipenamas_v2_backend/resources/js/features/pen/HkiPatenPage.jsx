import React, { useState } from 'react'
import {
  Sparkles,
  Plus,
  CheckCircle2,
  Clock,
  Award,
  FileText,
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

export default function HkiPatenPage({ hkiList = [] }) {
  const isLoading = false
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [notice, setNotice] = useState('')
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [formError, setFormError] = useState('')
  const [searchQuery, setSearchQuery] = useState('')
  const [jenisFilter, setJenisFilter] = useState('ALL')
  const [statusFilter, setStatusFilter] = useState('ALL')

  const [formData, setFormData] = useState({
    jenisCiptaan: 'Program Komputer (Hak Cipta)',
    judulCiptaan: '',
  })

  const handleSubmit = async (e) => {
    e.preventDefault()
    setIsSubmitting(true)
    setFormError('')
    try {
      await penelitiApi.createHki(formData)
      setIsModalOpen(false)
      setNotice('Pengajuan pendaftaran HKI berhasil disimpan ke Sentra HKI LPPM!')
      setTimeout(() => setNotice(''), 4000)
    } catch (err) {
      setFormError(err?.message || 'Gagal menyimpan pendaftaran HKI. Silakan coba lagi.')
    } finally {
      setIsSubmitting(false)
    }
  }

  const filteredHki = hkiList.filter((hki) => {
    const q = searchQuery.toLowerCase()
    const matchSearch =
      !searchQuery ||
      (hki.judulCiptaan && hki.judulCiptaan.toLowerCase().includes(q)) ||
      (hki.namaPengusul && hki.namaPengusul.toLowerCase().includes(q)) ||
      (hki.nomorPencatatan && hki.nomorPencatatan.toLowerCase().includes(q))
    const matchJenis = jenisFilter === 'ALL' || hki.jenisCiptaan.includes(jenisFilter)
    const matchStatus = statusFilter === 'ALL' || hki.status === statusFilter
    return matchSearch && matchJenis && matchStatus
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedHki } =
    usePagination(filteredHki, 10)

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Pendaftaran Hak Kekayaan Intelektual (Sentra HKI)
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Fasilitasi pendaftaran Hak Cipta (Karya Tulis/Software/Buku) dan Paten bersama Sentra HKI LPPM UKWMS
          </p>
        </div>
        <Button
          variant="primary"
          size="sm"
          iconLeft={Plus}
          onClick={() => setIsModalOpen(true)}
        >
          Daftarkan Ciptaan / Paten
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
        searchPlaceholder="Cari judul ciptaan/invensi, nama pengusul, atau nomor pencatatan..."
        filters={[
          {
            key: 'jenis',
            label: 'Jenis Perlindungan',
            value: jenisFilter,
            onChange: setJenisFilter,
            options: [
              { value: 'ALL', label: 'Semua Jenis' },
              { value: 'Hak Cipta', label: 'Hak Cipta' },
              { value: 'Paten', label: 'Paten' },
              { value: 'Merek', label: 'Merek' },
            ],
          },
          {
            key: 'status',
            label: 'Status DJKI',
            value: statusFilter,
            onChange: setStatusFilter,
            options: [
              { value: 'ALL', label: 'Semua Status' },
              { value: 'TERBIT_SERTIFIKAT', label: 'Sertifikat Terbit' },
              { value: 'PEMERIKSAAN_DJKI', label: 'Pemeriksaan DJKI' },
            ],
          },
        ]}
        onReset={() => {
          setSearchQuery('')
          setJenisFilter('ALL')
          setStatusFilter('ALL')
        }}
        totalCount={hkiList.length}
        filteredCount={filteredHki.length}
      />

      {/* Table */}
      <Card>
        <CardHeader
          title="Daftar Portofolio HKI & Paten Terdaftar"
          subtitle="Pemantauan proses pemeriksaan substantif di DJKI Kemenkumham"
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-4 py-3">Judul Ciptaan / Invensi</th>
                <th className="px-3 py-3">Jenis Perlindungan</th>
                <th className="px-3 py-3">Nomor Pencatatan</th>
                <th className="px-3 py-3">Pengusul</th>
                <th className="px-3 py-3 text-center">Status DJKI</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton cols={5} rows={5} />
              ) : filteredHki.length === 0 ? (
                <TableEmptyState
                  colSpan={5}
                  message={
                    searchQuery || jenisFilter !== 'ALL' || statusFilter !== 'ALL'
                      ? 'Tidak ada ciptaan/paten yang cocok dengan kriteria pencarian.'
                      : 'Belum ada data ciptaan atau paten terdaftar.'
                  }
                  onReset={
                    searchQuery || jenisFilter !== 'ALL' || statusFilter !== 'ALL'
                      ? () => {
                          setSearchQuery('')
                          setJenisFilter('ALL')
                          setStatusFilter('ALL')
                        }
                      : null
                  }
                />
              ) : (
                pagedHki.map((hki) => (
                  <tr key={hki.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                    <td className="px-4 py-3.5 max-w-sm">
                      <p className="font-semibold text-[#313a46] line-clamp-2" title={hki.judulCiptaan}>
                        {hki.judulCiptaan}
                      </p>
                      <span className="text-[10px] text-[#98a6ad] block mt-0.5">
                        Tgl Daftar: {hki.tglDaftar}
                      </span>
                    </td>

                    <td className="px-3.5 py-3.5 whitespace-nowrap font-medium text-[#313a46]">
                      {hki.jenisCiptaan}
                    </td>

                    <td className="px-3.5 py-3.5 font-mono text-[#6c757d] whitespace-nowrap">
                      {hki.nomorPencatatan || <span className="text-[#98a6ad] italic">Dalam Proses</span>}
                    </td>

                    <td className="px-3.5 py-3.5 whitespace-nowrap">
                      <p className="font-medium text-[#313a46]">{hki.namaPengusul}</p>
                      <span className="text-[11px] text-[#6c757d]">{hki.fakultas}</span>
                    </td>

                    <td className="px-3.5 py-3.5 text-center whitespace-nowrap">
                      {hki.status === 'TERBIT_SERTIFIKAT' ? (
                        <Badge variant="success" size="sm" pill>
                          <CheckCircle2 className="w-3 h-3" />
                          Sertifikat Terbit
                        </Badge>
                      ) : (
                        <Badge variant="warning" size="sm" pill>
                          <Clock className="w-3 h-3" />
                          Pemeriksaan DJKI
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

      {/* Modal Daftar HKI */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => {
          setIsModalOpen(false)
          setFormError('')
        }}
        title="Formulir Pendaftaran Ciptaan / Paten"
        subtitle="Sentra HKI LPPM akan memverifikasi kelengkapan berkas deskripsi dan surat pengalihan hak"
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
              Kirim Pendaftaran
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
            <label className="block font-semibold text-slate-700 mb-1">Jenis Perlindungan HKI *</label>
            <select
              value={formData.jenisCiptaan}
              onChange={(e) => setFormData({ ...formData, jenisCiptaan: e.target.value })}
              className="w-full p-2 border border-slate-300 rounded-md"
            >
              <option value="Program Komputer (Hak Cipta)">Program Komputer (Hak Cipta)</option>
              <option value="Buku / Karya Tulis (Hak Cipta)">Buku / Karya Tulis (Hak Cipta)</option>
              <option value="Paten Sederhana">Paten Sederhana</option>
              <option value="Paten Biasa">Paten Biasa</option>
              <option value="Desain Industri">Desain Industri</option>
            </select>
          </div>

          <div>
            <label className="block font-semibold text-slate-700 mb-1">Judul Ciptaan / Invensi *</label>
            <textarea
              rows={3}
              required
              value={formData.judulCiptaan}
              onChange={(e) => setFormData({ ...formData, judulCiptaan: e.target.value })}
              placeholder="Tuliskan judul ciptaan atau invensi secara lengkap..."
              className="w-full p-2 border border-slate-300 rounded-md"
            />
          </div>
        </form>
      </Modal>
    </div>
  )
}
