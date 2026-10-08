import React, { useEffect, useMemo, useState, useCallback } from 'react'
import { useParams } from '@/lib/router'
import { Database, Plus, Pencil, Trash2, Copy } from 'lucide-react'
import { basisDataApi } from '../../../services/api/basisDataApi'
import { Card, CardHeader, CardFooter } from '../../../components/ui/Card'
import { Button } from '../../../components/ui/Button'
import { Modal } from '../../../components/ui/Modal'
import { Pagination } from '../../../components/ui/Pagination'
import { usePagination } from '../../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../../components/ui/TableComponents'
import { entityConfig } from './entityConfig'

function emptyFormData(fields) {
  const data = {}
  fields.forEach((f) => {
    data[f.key] = f.type === 'checkbox' ? false : ''
  })
  return data
}

function formatCellValue(field, value) {
  if (field.type === 'checkbox') return value ? 'Ya' : 'Tidak'
  return value ?? ''
}

export default function MasterDataCrudPage() {
  const { slug } = useParams()
  const config = entityConfig[slug]

  const [list, setList] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchQuery, setSearchQuery] = useState('')
  const [selectOptions, setSelectOptions] = useState({})
  const [scopeValue, setScopeValue] = useState('')
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [editingId, setEditingId] = useState(null)
  const [formData, setFormData] = useState(() => emptyFormData(config?.fields ?? []))
  const [copyDst, setCopyDst] = useState('')
  const [isCopying, setIsCopying] = useState(false)

  const selectFields = useMemo(
    () => (config?.fields ?? []).filter((f) => f.type === 'select'),
    [config]
  )

  // Preload dropdown options for every select field, and for the scope selector.
  useEffect(() => {
    if (!config) return
    let cancelled = false

    const sources = new Set(selectFields.map((f) => f.select.source))
    if (config.scope) sources.add(config.scope.source)

    Promise.all(
      [...sources].map((source) =>
        basisDataApi.list(source).then((res) => [source, res.data])
      )
    ).then((entries) => {
      if (cancelled) return
      const map = {}
      entries.forEach(([source, data]) => {
        map[source] = data
      })
      setSelectOptions(map)
    })

    return () => {
      cancelled = true
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [slug])

  const loadData = useCallback(async () => {
    if (!config) return
    if (config.scope && !scopeValue) {
      setList([])
      return
    }
    setIsLoading(true)
    try {
      const params = config.scope ? { [config.scope.param]: scopeValue } : {}
      const res = await basisDataApi.list(slug, params)
      setList(res.data)
    } finally {
      setIsLoading(false)
    }
  }, [slug, config, scopeValue])

  useEffect(() => {
    loadData()
  }, [loadData])

  // Default the scope selector to the first available option once loaded.
  useEffect(() => {
    if (!config?.scope || scopeValue) return
    const options = selectOptions[config.scope.source]
    if (options?.length) setScopeValue(options[0][config.scope.value])
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [config, selectOptions])

  const filteredList = useMemo(() => {
    if (!searchQuery.trim()) return list
    const q = searchQuery.toLowerCase()
    return list.filter((row) =>
      Object.values(row).some(
        (val) => val !== null && val !== undefined && String(val).toLowerCase().includes(q)
      )
    )
  }, [list, searchQuery])

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems } = usePagination(
    filteredList,
    10
  )

  if (!config) {
    return <div className="text-xs text-[#98a6ad]">Entity basis data tidak dikenal.</div>
  }

  const openCreateModal = () => {
    setEditingId(null)
    setFormData(emptyFormData(config.fields))
    setIsModalOpen(true)
  }

  const openEditModal = (row) => {
    setEditingId(row[config.idKey])
    const data = {}
    config.fields.forEach((f) => {
      data[f.key] = row[f.key] ?? (f.type === 'checkbox' ? false : '')
    })
    setFormData(data)
    setIsModalOpen(true)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setIsSubmitting(true)
    try {
      const payload = { ...formData }
      if (config.scope) payload[config.scope.param] = scopeValue

      if (editingId) {
        await basisDataApi.update(slug, editingId, payload)
      } else {
        await basisDataApi.create(slug, payload)
      }
      setIsModalOpen(false)
      await loadData()
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleDelete = async (row) => {
    if (!window.confirm('Hapus data ini? Tindakan ini tidak bisa dibatalkan.')) return
    await basisDataApi.remove(slug, row[config.idKey])
    await loadData()
  }

  const handleCopy = async () => {
    if (!copyDst || !scopeValue) return
    setIsCopying(true)
    try {
      await basisDataApi.copyScoped(slug, scopeValue, copyDst)
      setCopyDst('')
      await loadData()
    } finally {
      setIsCopying(false)
    }
  }

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight flex items-center gap-2">
            <Database className="w-5 h-5 text-[#188ae2]" />
            {config.label}
          </h4>
          {config.readOnly ? (
            <p className="text-xs text-[#98a6ad] mt-0.5">
              Data ini disinkronkan dari sumber basis data eksternal dan bersifat read-only.
            </p>
          ) : (
            <p className="text-xs text-[#98a6ad] mt-0.5">
              Kelola entitas basis data referensi sistem LPPM UKWMS
            </p>
          )}
        </div>
        {!config.readOnly && (
          <Button variant="primary" size="sm" iconLeft={Plus} onClick={openCreateModal}>
            Tambah {config.label}
          </Button>
        )}
      </div>

      {config.scope && (
        <Card className="border-[#e7e9eb] shadow-sm">
          <div className="p-4 flex flex-col sm:flex-row sm:items-end gap-3 bg-[#f6f7fb]/40 rounded-xl">
            <div className="flex-1">
              <label className="block text-xs font-semibold text-[#313a46] mb-1.5">
                {config.scope.label}
              </label>
              <select
                value={scopeValue}
                onChange={(e) => setScopeValue(e.target.value)}
                className="w-full px-3 py-2 bg-white border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2]"
              >
                <option value="">Pilih {config.scope.label}</option>
                {(selectOptions[config.scope.source] ?? []).map((opt) => (
                  <option key={opt[config.scope.value]} value={opt[config.scope.value]}>
                    {opt[config.scope.optionLabel]}
                  </option>
                ))}
              </select>
            </div>
            {!config.readOnly && (
              <div className="flex-1 flex gap-2">
                <select
                  value={copyDst}
                  onChange={(e) => setCopyDst(e.target.value)}
                  className="flex-1 px-3 py-2 bg-white border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2]"
                >
                  <option value="">Salin ke {config.scope.label} lain...</option>
                  {(selectOptions[config.scope.source] ?? [])
                    .filter((opt) => opt[config.scope.value] !== scopeValue)
                    .map((opt) => (
                      <option key={opt[config.scope.value]} value={opt[config.scope.value]}>
                        {opt[config.scope.optionLabel]}
                      </option>
                    ))}
                </select>
                <Button
                  variant="secondary"
                  size="sm"
                  iconLeft={Copy}
                  isLoading={isCopying}
                  disabled={!copyDst}
                  onClick={handleCopy}
                >
                  Salin
                </Button>
              </div>
            )}
          </div>
        </Card>
      )}

      {/* Filter & Search toolbar */}
      <TableFilterBar
        searchValue={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder={`Cari data ${config.label.toLowerCase()}...`}
        onReset={() => setSearchQuery('')}
        totalCount={list.length}
        filteredCount={filteredList.length}
      />

      <Card className="border-[#e7e9eb] shadow-sm">
        <CardHeader
          title={<span className="font-heading font-bold text-[#313a46]">Daftar {config.label}</span>}
          subtitle={<span className="text-xs text-[#98a6ad]">Total {totalItems} entitas data tersimpan</span>}
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                {!config.readOnly && (
                  <th className="px-3 py-3 text-center whitespace-nowrap">Aksi</th>
                )}
                {config.fields.map((f) => (
                  <th key={f.key} className="px-4 py-3 whitespace-nowrap">
                    {f.label}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton
                  cols={config.fields.length + (config.readOnly ? 0 : 1)}
                  rows={5}
                />
              ) : paginatedItems.length === 0 ? (
                <TableEmptyState
                  colSpan={config.fields.length + (config.readOnly ? 0 : 1)}
                  message={
                    searchQuery
                      ? 'Tidak ada data yang cocok dengan kriteria pencarian.'
                      : 'Belum ada data tersedia.'
                  }
                  onReset={searchQuery ? () => setSearchQuery('') : null}
                />
              ) : (
                paginatedItems.map((row) => (
                  <tr key={row[config.idKey]} className="hover:bg-[#f6f7fb]/60 transition-colors">
                    {!config.readOnly && (
                      <td className="px-3 py-3 text-center whitespace-nowrap">
                        <div className="flex items-center justify-center gap-1.5">
                          <Button
                            variant="ghost"
                            size="xs"
                            iconLeft={Pencil}
                            onClick={() => openEditModal(row)}
                          >
                            Ubah
                          </Button>
                          <Button
                            variant="ghost"
                            size="xs"
                            iconLeft={Trash2}
                            className="text-[#ff5b5b] hover:text-[#ff5b5b] hover:bg-[#ff5b5b]/10"
                            onClick={() => handleDelete(row)}
                          >
                            Hapus
                          </Button>
                        </div>
                      </td>
                    )}
                    {config.fields.map((f) => (
                      <td key={f.key} className="px-4 py-3 whitespace-nowrap text-[#313a46]">
                        {formatCellValue(f, row[f.key])}
                      </td>
                    ))}
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

      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        title={editingId ? `Ubah ${config.label}` : `Tambah ${config.label}`}
        maxWidth="max-w-2xl"
        footer={
          <>
            <Button variant="secondary" size="sm" onClick={() => setIsModalOpen(false)} disabled={isSubmitting}>
              Batal
            </Button>
            <Button variant="primary" size="sm" onClick={handleSubmit} isLoading={isSubmitting}>
              Simpan
            </Button>
          </>
        }
      >
        <form onSubmit={handleSubmit} className="space-y-4 text-xs">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {config.fields.map((f) => (
              <div key={f.key} className={f.type === 'textarea' ? 'sm:col-span-2' : ''}>
                <label className="block font-semibold text-[#313a46] mb-1.5">
                  {f.label}
                  {f.required && <span className="text-[#ff5b5b]"> *</span>}
                </label>
                {f.type === 'select' ? (
                  <select
                    required={f.required}
                    value={formData[f.key] ?? ''}
                    onChange={(e) => setFormData({ ...formData, [f.key]: e.target.value })}
                    className="w-full px-3 py-2 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white transition-colors"
                  >
                    <option value="">- Pilih -</option>
                    {(selectOptions[f.select.source] ?? [])
                      .filter((opt) => !f.select.filter || f.select.filter(opt))
                      .map((opt) => (
                        <option key={opt[f.select.value]} value={opt[f.select.value]}>
                          {opt[f.select.label]}
                        </option>
                      ))}
                  </select>
                ) : f.type === 'textarea' ? (
                  <textarea
                    required={f.required}
                    rows={3}
                    value={formData[f.key] ?? ''}
                    onChange={(e) => setFormData({ ...formData, [f.key]: e.target.value })}
                    className="w-full px-3 py-2 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white transition-colors"
                  />
                ) : f.type === 'checkbox' ? (
                  <label className="flex items-center gap-2 cursor-pointer select-none pt-1.5">
                    <input
                      type="checkbox"
                      checked={!!formData[f.key]}
                      onChange={(e) => setFormData({ ...formData, [f.key]: e.target.checked })}
                      className="w-4 h-4 rounded text-[#188ae2] focus:ring-[#188ae2] border-[#e7e9eb]"
                    />
                    <span className="text-[#313a46] font-medium">Ya</span>
                  </label>
                ) : (
                  <input
                    type={f.type === 'number' ? 'number' : 'text'}
                    required={f.required}
                    value={formData[f.key] ?? ''}
                    onChange={(e) => setFormData({ ...formData, [f.key]: e.target.value })}
                    className="w-full px-3 py-2 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white transition-colors"
                  />
                )}
              </div>
            ))}
          </div>
        </form>
      </Modal>
    </div>
  )
}
