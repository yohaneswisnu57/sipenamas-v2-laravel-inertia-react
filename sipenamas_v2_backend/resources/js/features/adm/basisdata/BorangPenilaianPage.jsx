import React, { useEffect, useState, useCallback } from 'react'
import { useLocation } from '@/lib/router'
import { ClipboardList, Plus, Pencil, Trash2, ListChecks } from 'lucide-react'
import { basisDataApi } from '../../../services/api/basisDataApi'
import { Card, CardHeader, CardFooter } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Modal } from '../../../components/ui/Modal'
import { Pagination } from '../../../components/ui/Pagination'
import { usePagination } from '../../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../../components/ui/TableComponents'
import { borangPenilaianConfig } from './entityConfig'

const emptyMaster = { KODESOAL: '', DESKRIPSI: '' }
const emptyDetailRow = () => ({ NOMOR: '', KRITERIAPENILAIAN: '', BOBOTPERSEN: '' })

/**
 * Halaman master-detail untuk Borang Penilaian Proposal/Poster/Presentasi -
 * baris kriteria (detail) selalu disimpan sebagai satu batch lewat
 * replaceDetails (delete-then-insert), persis padanan legacy
 * soalpenilaian*.php addData()/editData().
 */
export default function BorangPenilaianPage() {
  // Rute statis (bukan :slug) supaya tidak bentrok dengan
  // MasterDataCrudPage - slug diambil dari path terakhir.
  const slug = useLocation().pathname.split('/').pop()
  const config = borangPenilaianConfig[slug]

  const [list, setList] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')

  const [isMasterModalOpen, setIsMasterModalOpen] = useState(false)
  const [isSubmittingMaster, setIsSubmittingMaster] = useState(false)
  const [editingId, setEditingId] = useState(null)
  const [masterForm, setMasterForm] = useState(emptyMaster)

  const [isDetailModalOpen, setIsDetailModalOpen] = useState(false)
  const [isSavingDetail, setIsSavingDetail] = useState(false)
  const [detailParentId, setDetailParentId] = useState(null)
  const [detailRows, setDetailRows] = useState([])

  const loadData = useCallback(async () => {
    if (!slug) return
    setIsLoading(true)
    try {
      const res = await basisDataApi.list(slug)
      setList(res.data)
    } finally {
      setIsLoading(false)
    }
  }, [slug])

  useEffect(() => {
    loadData()
  }, [loadData])

  const filteredList = list.filter((row) => {
    if (!searchQuery.trim()) return true
    const q = searchQuery.toLowerCase()
    return (
      (row.KODESOAL && row.KODESOAL.toLowerCase().includes(q)) ||
      (row.DESKRIPSI && row.DESKRIPSI.toLowerCase().includes(q))
    )
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems } = usePagination(
    filteredList,
    10
  )

  if (!config) {
    return <div className="text-xs text-[#98a6ad]">Entity borang penilaian tidak dikenal.</div>
  }

  const openCreateMaster = () => {
    setEditingId(null)
    setMasterForm(emptyMaster)
    setIsMasterModalOpen(true)
  }

  const openEditMaster = (row) => {
    setEditingId(row.id)
    setMasterForm({ KODESOAL: row.KODESOAL ?? '', DESKRIPSI: row.DESKRIPSI ?? '' })
    setIsMasterModalOpen(true)
  }

  const handleSubmitMaster = async (e) => {
    e.preventDefault()
    setIsSubmittingMaster(true)
    try {
      if (editingId) {
        await basisDataApi.update(slug, editingId, masterForm)
      } else {
        await basisDataApi.create(slug, masterForm)
      }
      setIsMasterModalOpen(false)
      await loadData()
    } finally {
      setIsSubmittingMaster(false)
    }
  }

  const handleDeleteMaster = async (row) => {
    if (!window.confirm('Hapus borang ini beserta seluruh kriteria penilaiannya?')) return
    await basisDataApi.remove(slug, row.id)
    await loadData()
  }

  const openDetailEditor = (row) => {
    setDetailParentId(row.id)
    const rows = (row.detail ?? []).map((d) => ({
      NOMOR: d.NOMOR ?? '',
      KRITERIAPENILAIAN: d.KRITERIAPENILAIAN ?? '',
      BOBOTPERSEN: d.BOBOTPERSEN ?? '',
    }))
    setDetailRows(rows.length ? rows : [emptyDetailRow()])
    setIsDetailModalOpen(true)
  }

  const updateDetailRow = (index, key, value) => {
    setDetailRows((rows) => rows.map((r, i) => (i === index ? { ...r, [key]: value } : r)))
  }

  const addDetailRow = () => setDetailRows((rows) => [...rows, emptyDetailRow()])
  const removeDetailRow = (index) =>
    setDetailRows((rows) => rows.filter((_, i) => i !== index))

  const totalBobot = detailRows.reduce((sum, r) => sum + (Number(r.BOBOTPERSEN) || 0), 0)

  const handleSaveDetail = async () => {
    setIsSavingDetail(true)
    try {
      await basisDataApi.replaceDetails(slug, detailParentId, detailRows)
      setIsDetailModalOpen(false)
      await loadData()
    } finally {
      setIsSavingDetail(false)
    }
  }

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight flex items-center gap-2">
            <ClipboardList className="w-5 h-5 text-[#188ae2]" />
            {config.label}
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Setiap borang memiliki satu set kriteria penilaian dengan bobot persentase evaluasi
          </p>
        </div>
        <Button variant="primary" size="sm" iconLeft={Plus} onClick={openCreateMaster}>
          Tambah Borang
        </Button>
      </div>

      {/* Filter & Search toolbar */}
      <TableFilterBar
        searchValue={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari kode soal atau deskripsi borang..."
        onReset={() => setSearchQuery('')}
        totalCount={list.length}
        filteredCount={filteredList.length}
      />

      <Card className="border-[#e7e9eb] shadow-sm">
        <CardHeader
          title={<span className="font-heading font-bold text-[#313a46]">Daftar {config.label}</span>}
          subtitle={<span className="text-xs text-[#98a6ad]">Total {totalItems} instrumen borang terdaftar</span>}
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-3 py-3 text-center">Aksi</th>
                <th className="px-4 py-3">Kode Soal</th>
                <th className="px-4 py-3">Deskripsi</th>
                <th className="px-3 py-3 text-center">Jumlah Kriteria</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton cols={4} rows={5} />
              ) : paginatedItems.length === 0 ? (
                <TableEmptyState
                  colSpan={4}
                  message={
                    searchQuery
                      ? 'Tidak ada borang penilaian yang cocok dengan kriteria pencarian.'
                      : 'Belum ada data borang.'
                  }
                  onReset={searchQuery ? () => setSearchQuery('') : null}
                />
              ) : (
                paginatedItems.map((row) => (
                  <tr key={row.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                    <td className="px-3 py-3 text-center whitespace-nowrap">
                      <div className="flex items-center justify-center gap-1.5">
                        <Button
                          variant="secondary"
                          size="xs"
                          iconLeft={ListChecks}
                          onClick={() => openDetailEditor(row)}
                        >
                          Kriteria
                        </Button>
                        <Button
                          variant="ghost"
                          size="xs"
                          iconLeft={Pencil}
                          onClick={() => openEditMaster(row)}
                        >
                          Ubah
                        </Button>
                        <Button
                          variant="ghost"
                          size="xs"
                          iconLeft={Trash2}
                          className="text-[#ff5b5b] hover:text-[#ff5b5b] hover:bg-[#ff5b5b]/10"
                          onClick={() => handleDeleteMaster(row)}
                        >
                          Hapus
                        </Button>
                      </div>
                    </td>
                    <td className="px-4 py-3 font-mono font-semibold text-[#313a46]">{row.KODESOAL}</td>
                    <td className="px-4 py-3 text-[#313a46]">{row.DESKRIPSI}</td>
                    <td className="px-3 py-3 text-center font-semibold text-[#188ae2]">
                      <span className="bg-[#188ae2]/10 px-2 py-0.5 rounded-full text-xs">
                        {(row.detail ?? []).length} kriteria
                      </span>
                    </td>
                  </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        <CardFooter className="border-t border-[#e7e9eb] bg-white">
          <Pagination
            page={page}
            totalPages={totalPages}
            totalItems={totalItems}
            pageSize={pageSize}
            onPageChange={setPage}
          />
        </CardFooter>
      </Card>

      {/* Modal Borang (master) */}
      <Modal
        isOpen={isMasterModalOpen}
        onClose={() => setIsMasterModalOpen(false)}
        title={editingId ? `Ubah ${config.label}` : `Tambah ${config.label}`}
        footer={
          <>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setIsMasterModalOpen(false)}
              disabled={isSubmittingMaster}
            >
              Batal
            </Button>
            <Button
              variant="primary"
              size="sm"
              onClick={handleSubmitMaster}
              isLoading={isSubmittingMaster}
            >
              Simpan Borang
            </Button>
          </>
        }
      >
        <form onSubmit={handleSubmitMaster} className="space-y-4 text-xs">
          <div>
            <label className="block font-semibold text-[#313a46] mb-1.5">Kode Soal *</label>
            <input
              type="text"
              required
              value={masterForm.KODESOAL}
              onChange={(e) => setMasterForm({ ...masterForm, KODESOAL: e.target.value })}
              className="w-full px-3 py-2 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg font-mono text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white transition-colors"
            />
          </div>
          <div>
            <label className="block font-semibold text-[#313a46] mb-1.5">Deskripsi *</label>
            <input
              type="text"
              required
              value={masterForm.DESKRIPSI}
              onChange={(e) => setMasterForm({ ...masterForm, DESKRIPSI: e.target.value })}
              className="w-full px-3 py-2 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white transition-colors"
            />
          </div>
        </form>
      </Modal>

      {/* Modal Kriteria Penilaian (detail) */}
      <Modal
        isOpen={isDetailModalOpen}
        onClose={() => setIsDetailModalOpen(false)}
        title="Kriteria Penilaian Borang"
        subtitle="Menyimpan akan memperbarui seluruh daftar rubrik kriteria untuk borang ini"
        maxWidth="max-w-3xl"
        footer={
          <>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setIsDetailModalOpen(false)}
              disabled={isSavingDetail}
            >
              Batal
            </Button>
            <Button
              variant="primary"
              size="sm"
              onClick={handleSaveDetail}
              isLoading={isSavingDetail}
            >
              Simpan Kriteria
            </Button>
          </>
        }
      >
        <div className="space-y-3 text-xs">
          <div className="grid grid-cols-[80px_1fr_110px_40px] gap-2 font-semibold text-[#313a46] pb-1 border-b border-[#e7e9eb]">
            <span>Nomor</span>
            <span>Kriteria Penilaian</span>
            <span>Bobot (%)</span>
            <span />
          </div>
          {detailRows.map((row, i) => (
            <div key={i} className="grid grid-cols-[80px_1fr_110px_40px] gap-2 items-start">
              <input
                type="number"
                value={row.NOMOR}
                onChange={(e) => updateDetailRow(i, 'NOMOR', e.target.value)}
                className="px-2 py-1.5 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white"
              />
              <textarea
                rows={1}
                value={row.KRITERIAPENILAIAN}
                onChange={(e) => updateDetailRow(i, 'KRITERIAPENILAIAN', e.target.value)}
                className="px-2 py-1.5 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white"
              />
              <input
                type="number"
                value={row.BOBOTPERSEN}
                onChange={(e) => updateDetailRow(i, 'BOBOTPERSEN', e.target.value)}
                className="px-2 py-1.5 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white font-tabular"
              />
              <button
                type="button"
                onClick={() => removeDetailRow(i)}
                className="text-[#ff5b5b] hover:text-[#ff5b5b]/80 p-1.5 rounded hover:bg-[#ff5b5b]/10 cursor-pointer transition-colors"
              >
                <Trash2 className="w-4 h-4" />
              </button>
            </div>
          ))}
          <div className="flex items-center justify-between pt-2 border-t border-[#e7e9eb]">
            <Button variant="secondary" size="xs" iconLeft={Plus} onClick={addDetailRow}>
              Tambah Baris
            </Button>
            <span
              className={`font-bold font-heading text-xs px-2.5 py-1 rounded-full border ${
                totalBobot === 100
                  ? 'bg-[#10c469]/10 text-[#0b7941] border-[#10c469]/30'
                  : 'bg-[#f9c851]/10 text-[#966b0a] border-[#f9c851]/30'
              }`}
            >
              Total Bobot: {totalBobot}%
            </span>
          </div>
        </div>
      </Modal>
    </div>
  )
}
