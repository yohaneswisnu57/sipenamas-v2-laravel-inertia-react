import React, { useEffect, useState } from 'react'
import { ScrollText, Save, Loader2, CheckCircle2, XCircle } from 'lucide-react'
import { adminApi } from '../../services/api/adminApi'
import { Card } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'

const ACRONYMS = new Set(['apc', 'hki', 'sinta'])

const titleCase = (text) =>
  text
    .split(' ')
    .map((word) => (ACRONYMS.has(word) ? word.toUpperCase() : word.charAt(0).toUpperCase() + word.slice(1)))
    .join(' ')

export default function ManajemenMenuPermissionPage() {
  const [isLoading, setIsLoading] = useState(true)
  // [{ resource, actions: [{ id, action, permission, label, description }] }]
  const [resources, setResources] = useState([])
  const [drafts, setDrafts] = useState({}) // { [permissionId]: { label, description } }
  const [dirtyIds, setDirtyIds] = useState({})
  const [savingId, setSavingId] = useState(null)
  const [notice, setNotice] = useState('')
  const [errorNotice, setErrorNotice] = useState('')

  const loadData = async () => {
    setIsLoading(true)
    try {
      const res = await adminApi.getPermissionList()
      const nextDrafts = {}
      ;(res.data || []).forEach((group) => {
        group.actions.forEach((action) => {
          nextDrafts[action.id] = {
            label: action.label || '',
            description: action.description || '',
          }
        })
      })
      setResources(res.data || [])
      setDrafts(nextDrafts)
      setDirtyIds({})
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [])

  const updateDraft = (id, field, value) => {
    setDrafts((prev) => ({ ...prev, [id]: { ...prev[id], [field]: value } }))
    setDirtyIds((prev) => ({ ...prev, [id]: true }))
  }

  const handleSave = async (id) => {
    setSavingId(id)
    setErrorNotice('')
    try {
      await adminApi.updatePermissionLabel(id, drafts[id])
      setDirtyIds((prev) => ({ ...prev, [id]: false }))
      setNotice('Perubahan label permission berhasil disimpan.')
      setTimeout(() => setNotice(''), 3000)
    } catch (err) {
      setErrorNotice(err?.message || 'Gagal menyimpan label permission. Silakan coba lagi.')
    } finally {
      setSavingId(null)
    }
  }

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Manajemen Kamus Permission
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Sesuaikan teks label & deskripsi yang tampil di konfigurasi peran & hak akses modul
          </p>
        </div>
      </div>

      {notice && (
        <div className="flex items-center gap-2 p-3 rounded-xl text-xs bg-[#10c469]/10 border border-[#10c469]/30 text-[#0b7941] shadow-sm">
          <CheckCircle2 className="w-4 h-4 shrink-0 text-[#10c469]" />
          <span>{notice}</span>
        </div>
      )}

      {errorNotice && (
        <div className="flex items-center gap-2 p-3 rounded-xl text-xs bg-[#ff5b5b]/10 border border-[#ff5b5b]/30 text-[#ff5b5b] shadow-sm">
          <XCircle className="w-4 h-4 shrink-0" />
          <span className="font-semibold">{errorNotice}</span>
        </div>
      )}

      {isLoading ? (
        <div className="flex items-center justify-center gap-2 py-16 text-[#98a6ad] text-xs">
          <Loader2 className="w-4 h-4 animate-spin text-[#188ae2]" />
          Memuat data permission...
        </div>
      ) : resources.length === 0 ? (
        <Card className="border-[#e7e9eb] shadow-sm">
          <div className="px-6 py-10 text-center text-xs text-[#98a6ad]">
            Belum ada permission granular yang terdaftar.
          </div>
        </Card>
      ) : (
        <Card className="border-[#e7e9eb] shadow-sm">
          <div className="divide-y divide-[#e7e9eb]">
            {resources.map((group) => (
              <div key={group.resource} className="px-6 py-4">
                <p className="text-sm font-bold font-heading text-[#313a46] mb-3">{titleCase(group.resource)}</p>
                <div className="space-y-3">
                  {group.actions.map((action) => (
                    <div
                      key={action.id}
                      className="grid grid-cols-1 sm:grid-cols-[1fr_1fr_auto] gap-2 sm:gap-3 sm:items-center"
                    >
                      <div>
                        <input
                          type="text"
                          value={drafts[action.id]?.label ?? ''}
                          onChange={(e) => updateDraft(action.id, 'label', e.target.value)}
                          placeholder="Label Tampilan"
                          className="w-full rounded-lg border border-[#e7e9eb] bg-[#f6f7fb] px-3 py-1.5 text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white transition-colors"
                          maxLength={100}
                        />
                        <p className="mt-1 font-mono text-[10px] text-[#98a6ad]">{action.permission}</p>
                      </div>
                      <input
                        type="text"
                        value={drafts[action.id]?.description ?? ''}
                        onChange={(e) => updateDraft(action.id, 'description', e.target.value)}
                        placeholder="Deskripsi (opsional)"
                        className="w-full rounded-lg border border-[#e7e9eb] bg-[#f6f7fb] px-3 py-1.5 text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white transition-colors"
                        maxLength={255}
                      />
                      <Button
                        variant="secondary"
                        size="xs"
                        iconLeft={Save}
                        onClick={() => handleSave(action.id)}
                        isLoading={savingId === action.id}
                        disabled={!dirtyIds[action.id] || !drafts[action.id]?.label}
                      >
                        Simpan
                      </Button>
                    </div>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </Card>
      )}
    </div>
  )
}
