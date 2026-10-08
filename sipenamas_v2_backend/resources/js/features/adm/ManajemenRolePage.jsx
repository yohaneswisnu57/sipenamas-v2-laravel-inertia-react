import React, { useEffect, useMemo, useState } from 'react'
import { ShieldCheck, Save, Loader2, RotateCcw, Check, ChevronDown, Plus, X, Trash2, XCircle } from 'lucide-react'
import { ROLE_LABELS, ROLE_BADGE_COLORS } from '../../utils/constants'
import { adminApi } from '../../services/api/adminApi'
import { Card, CardHeader } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Switch } from '../../components/ui/Switch'

const ACRONYMS = new Set(['apc', 'hki', 'sinta'])

const titleCase = (text) =>
  text
    .split(' ')
    .map((word) => (ACRONYMS.has(word) ? word.toUpperCase() : word.charAt(0).toUpperCase() + word.slice(1)))
    .join(' ')

function PermissionCheckbox({ checked, onChange, label }) {
  return (
    <button
      type="button"
      onClick={() => onChange(!checked)}
      className="flex items-center gap-2.5 text-left cursor-pointer group"
    >
      <span
        className={`flex items-center justify-center w-4.5 h-4.5 rounded shrink-0 border transition-colors ${
          checked
            ? 'bg-[#188ae2] border-[#188ae2]'
            : 'bg-white border-[#e7e9eb] group-hover:border-[#98a6ad]'
        }`}
      >
        {checked && <Check className="w-3 h-3 text-white" strokeWidth={3} />}
      </span>
      <span className="text-xs text-[#313a46] font-medium">{label}</span>
    </button>
  )
}

function ResourceSection({ group, checked, onToggleOne, onToggleAll }) {
  const [isOpen, setIsOpen] = useState(true)
  const names = group.actions.map((a) => a.permission)
  const allChecked = names.length > 0 && names.every((n) => checked.includes(n))

  return (
    <div className="px-6 py-4">
      <div className="flex items-center justify-between">
        <button
          type="button"
          onClick={() => setIsOpen((v) => !v)}
          className="flex items-center gap-2 cursor-pointer"
        >
          <p className="text-sm font-bold font-heading text-[#313a46]">{titleCase(group.resource)}</p>
          <ChevronDown className={`w-3.5 h-3.5 text-[#98a6ad] transition-transform ${isOpen ? '' : '-rotate-90'}`} />
        </button>
        <button
          onClick={() => onToggleAll(!allChecked)}
          className="text-xs font-semibold text-[#188ae2] hover:text-[#188ae2]/80 cursor-pointer transition-colors"
        >
          {allChecked ? 'Batalkan semua' : 'Pilih semua'}
        </button>
      </div>

      {isOpen && (
        <div className="mt-3 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-4 gap-y-3">
          {group.actions.map((action) => (
            <PermissionCheckbox
              key={action.permission}
              checked={checked.includes(action.permission)}
              onChange={(next) => onToggleOne(action.permission, next)}
              label={action.label}
            />
          ))}
        </div>
      )}
    </div>
  )
}

export default function ManajemenRolePage() {
  const [isLoading, setIsLoading] = useState(true)
  const [roleNames, setRoleNames] = useState([])
  const [protectedRoles, setProtectedRoles] = useState({})
  const [activeRole, setActiveRole] = useState(null)
  // { [role]: [{ resource, actions: [{ key, permission, label, granted }] }] }
  const [roleResources, setRoleResources] = useState({})
  // { [role]: string[] } - nama permission yang sedang dicentang (draft, belum tentu tersimpan)
  const [checkedByRole, setCheckedByRole] = useState({})
  const [savedByRole, setSavedByRole] = useState({})
  const [isSaving, setIsSaving] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)
  const [notice, setNotice] = useState('')
  const [noticeIsError, setNoticeIsError] = useState(false)
  const [isAddingRole, setIsAddingRole] = useState(false)
  const [newRoleName, setNewRoleName] = useState('')
  const [addRoleError, setAddRoleError] = useState('')

  const loadData = async (selectRole) => {
    setIsLoading(true)
    try {
      const res = await adminApi.getRoleList()
      const names = []
      const protectedMap = {}
      const resourcesByRole = {}
      const checked = {}
      res.data.forEach((row) => {
        names.push(row.role)
        protectedMap[row.role] = !!row.isProtected
        resourcesByRole[row.role] = row.resources || []
        checked[row.role] = (row.resources || [])
          .flatMap((group) => group.actions)
          .filter((action) => action.granted)
          .map((action) => action.permission)
      })
      setRoleNames(names)
      setProtectedRoles(protectedMap)
      setRoleResources(resourcesByRole)
      setCheckedByRole(checked)
      setSavedByRole(checked)
      setActiveRole((prev) => {
        if (selectRole && names.includes(selectRole)) return selectRole
        if (prev && names.includes(prev)) return prev
        return names[0] || null
      })
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [])

  const roleLabel = (role) => ROLE_LABELS[role] || role

  const handleCreateRole = async () => {
    const name = newRoleName.trim()
    if (!name) return
    setAddRoleError('')
    try {
      await adminApi.createRole(name)
      setNewRoleName('')
      setIsAddingRole(false)
      await loadData(name)
      setNotice(`Peran ${name} berhasil dibuat.`)
      setTimeout(() => setNotice(''), 3000)
    } catch (err) {
      setAddRoleError(err?.message || 'Gagal membuat peran baru.')
    }
  }

  const handleDeleteRole = async (role) => {
    if (!window.confirm(`Hapus peran "${roleLabel(role)}"? Tindakan ini tidak bisa dibatalkan.`)) return
    setIsDeleting(true)
    try {
      await adminApi.deleteRole(role)
      await loadData()
      setNoticeIsError(false)
      setNotice(`Peran ${roleLabel(role)} berhasil dihapus.`)
      setTimeout(() => setNotice(''), 3000)
    } catch (err) {
      setNoticeIsError(true)
      setNotice(err?.message || 'Gagal menghapus peran.')
      setTimeout(() => setNotice(''), 3000)
    } finally {
      setIsDeleting(false)
    }
  }

  const resources = roleResources[activeRole] || []
  const checked = checkedByRole[activeRole] || []
  const allPermissions = useMemo(
    () => resources.flatMap((group) => group.actions.map((a) => a.permission)),
    [resources]
  )
  const isDirty = useMemo(
    () => JSON.stringify([...checked].sort()) !== JSON.stringify([...(savedByRole[activeRole] || [])].sort()),
    [checked, savedByRole, activeRole]
  )
  const grantedCount = checked.length

  const toggleOne = (permission, next) => {
    setCheckedByRole((prev) => {
      const current = prev[activeRole] || []
      return {
        ...prev,
        [activeRole]: next ? [...current, permission] : current.filter((p) => p !== permission),
      }
    })
  }

  const toggleAll = (next) => {
    setCheckedByRole((prev) => ({ ...prev, [activeRole]: next ? allPermissions : [] }))
  }

  const discardChanges = () => {
    setCheckedByRole((prev) => ({ ...prev, [activeRole]: savedByRole[activeRole] || [] }))
  }

  const handleSave = async () => {
    setIsSaving(true)
    try {
      await adminApi.updateRolePermissions(activeRole, { permissions: checked })
      setSavedByRole((prev) => ({ ...prev, [activeRole]: checked }))
      setNoticeIsError(false)
      setNotice(`Hak akses ${roleLabel(activeRole)} tersimpan.`)
      setTimeout(() => setNotice(''), 3000)
    } catch (err) {
      setNoticeIsError(true)
      setNotice(err?.message || 'Gagal menyimpan hak akses. Silakan coba lagi.')
      setTimeout(() => setNotice(''), 3000)
    } finally {
      setIsSaving(false)
    }
  }

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Manajemen Role & Akses Granular
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Pilih peran, lalu konfigurasi izin granular per resource modul sistem
          </p>
        </div>
      </div>

      {/* Role selector */}
      <div className="flex flex-wrap items-center gap-2">
        {roleNames.map((role) => {
          const isActive = role === activeRole
          const isRoleDirty =
            JSON.stringify([...(checkedByRole[role] || [])].sort()) !==
            JSON.stringify([...(savedByRole[role] || [])].sort())

          return (
            <span
              key={role}
              className={`group relative flex items-center gap-1.5 rounded-full border transition-all ${
                isActive
                  ? 'bg-[#188ae2] text-white border-[#188ae2] shadow-sm'
                  : 'bg-white text-[#6c757d] border-[#e7e9eb] hover:border-[#188ae2] hover:text-[#188ae2]'
              }`}
            >
              <button
                type="button"
                onClick={() => setActiveRole(role)}
                className="flex items-center gap-1.5 pl-3.5 pr-2 py-1.5 text-xs font-semibold cursor-pointer"
              >
                {roleLabel(role)}
                {isRoleDirty && (
                  <span className="w-1.5 h-1.5 rounded-full bg-[#f9c851]" title="Ada perubahan belum disimpan" />
                )}
              </button>
              {!protectedRoles[role] && (
                <button
                  type="button"
                  onClick={() => handleDeleteRole(role)}
                  disabled={isDeleting}
                  title={`Hapus peran ${roleLabel(role)}`}
                  className={`flex items-center justify-center w-5 h-5 mr-1.5 rounded-full cursor-pointer transition-colors ${
                    isActive ? 'hover:bg-white/20 text-white' : 'hover:bg-[#f6f7fb] text-[#98a6ad]'
                  }`}
                >
                  <Trash2 className="w-3 h-3" />
                </button>
              )}
            </span>
          )
        })}

        {isAddingRole ? (
          <div className="flex items-center gap-1.5">
            <input
              autoFocus
              value={newRoleName}
              onChange={(e) => setNewRoleName(e.target.value)}
              onKeyDown={(e) => {
                if (e.key === 'Enter') handleCreateRole()
                if (e.key === 'Escape') {
                  setIsAddingRole(false)
                  setNewRoleName('')
                  setAddRoleError('')
                }
              }}
              placeholder="Nama peran baru"
              className="px-3 py-1.5 text-xs bg-white border border-[#e7e9eb] rounded-full text-[#313a46] focus:outline-none focus:border-[#188ae2]"
            />
            <Button variant="primary" size="xs" onClick={handleCreateRole}>
              Simpan
            </Button>
            <button
              type="button"
              onClick={() => {
                setIsAddingRole(false)
                setNewRoleName('')
                setAddRoleError('')
              }}
              className="flex items-center justify-center w-6 h-6 rounded-full text-[#98a6ad] hover:bg-[#f6f7fb] cursor-pointer"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => setIsAddingRole(true)}
            className="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border border-dashed border-[#e7e9eb] text-[#98a6ad] hover:border-[#188ae2] hover:text-[#188ae2] cursor-pointer transition-colors"
          >
            <Plus className="w-3.5 h-3.5" />
            Tambah Role
          </button>
        )}
      </div>

      {addRoleError && <p className="text-xs text-[#ff5b5b] font-medium">{addRoleError}</p>}

      {notice && (
        <div
          className={`flex items-center gap-2 p-3 rounded-xl text-xs shadow-sm ${
            noticeIsError
              ? 'bg-[#ff5b5b]/10 border border-[#ff5b5b]/30 text-[#ff5b5b] font-semibold'
              : 'bg-[#10c469]/10 border border-[#10c469]/30 text-[#0b7941]'
          }`}
        >
          {noticeIsError ? (
            <XCircle className="w-4 h-4 shrink-0" />
          ) : (
            <ShieldCheck className="w-4 h-4 shrink-0 text-[#10c469]" />
          )}
          <span>{notice}</span>
        </div>
      )}

      {isLoading ? (
        <div className="flex items-center justify-center gap-2 py-16 text-[#98a6ad] text-xs">
          <Loader2 className="w-4 h-4 animate-spin text-[#188ae2]" />
          Memuat data peran...
        </div>
      ) : resources.length === 0 ? (
        <Card className="border-[#e7e9eb] shadow-sm">
          <div className="px-6 py-10 text-center text-xs text-[#98a6ad]">
            Belum ada resource granular yang terdaftar untuk peran ini.
          </div>
        </Card>
      ) : (
        <Card className="border-[#e7e9eb] shadow-sm">
          <div className="px-6 py-4 flex items-center justify-between gap-4 border-b border-[#e7e9eb] bg-[#f6f7fb]">
            <div className="flex items-center gap-3">
              <Switch
                checked={grantedCount === allPermissions.length}
                onChange={(next) => toggleAll(next)}
                label="Pilih semua"
              />
              <div>
                <p className="text-xs font-bold font-heading text-[#313a46]">Pilih Semua Resource</p>
                <p className="text-[11px] text-[#98a6ad]">Nyalakan/matikan seluruh akses sekaligus untuk peran ini</p>
              </div>
            </div>
            <span className="text-xs font-medium text-[#98a6ad] whitespace-nowrap bg-white px-2.5 py-1 rounded-full border border-[#e7e9eb]">
              <strong className="text-[#188ae2]">{grantedCount}</strong> dari {allPermissions.length} akses
            </span>
          </div>

          <div className="divide-y divide-[#e7e9eb]">
            {resources.map((group) => (
              <ResourceSection
                key={group.resource}
                group={group}
                checked={checked}
                onToggleOne={toggleOne}
                onToggleAll={(next) => {
                  const names = group.actions.map((a) => a.permission)
                  setCheckedByRole((prev) => {
                    const current = prev[activeRole] || []
                    const withoutGroup = current.filter((p) => !names.includes(p))
                    return { ...prev, [activeRole]: next ? [...withoutGroup, ...names] : withoutGroup }
                  })
                }}
              />
            ))}
          </div>

          {isDirty && (
            <div className="flex items-center justify-between gap-4 px-6 py-3.5 bg-[#f9c851]/10 border-t border-[#f9c851]/30 rounded-b-xl">
              <span className="text-xs font-medium text-[#966b0a]">Ada perubahan hak akses yang belum disimpan</span>
              <div className="flex items-center gap-2">
                <Button variant="ghost" size="xs" iconLeft={RotateCcw} onClick={discardChanges}>
                  Batalkan
                </Button>
                <Button variant="primary" size="xs" iconLeft={Save} onClick={handleSave} isLoading={isSaving}>
                  Simpan Perubahan
                </Button>
              </div>
            </div>
          )}
        </Card>
      )}
    </div>
  )
}
