import React, { useState, useEffect } from 'react'
import {
  Users,
  ShieldCheck,
  Check,
  KeyRound,
  ShieldAlert,
  AlertTriangle,
  XCircle,
  Edit2,
  LogIn,
} from 'lucide-react'
import { adminApi } from '../../services/api/adminApi'
import { useAuthStore } from '../../store/authStore'
import { ROLES, ROLE_LABELS, ROLE_BADGE_COLORS } from '../../utils/constants'
import { Card, CardHeader, CardFooter } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'
import { Modal } from '../../components/ui/Modal'
import { Badge } from '../../components/ui/Badge'
import { Pagination } from '../../components/ui/Pagination'
import { usePagination } from '../../hooks/usePagination'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

export default function ManajemenUserPage() {
  const { user: currentUser, impersonate } = useAuthStore()
  const [users, setUsers] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [roleFilter, setRoleFilter] = useState('ALL')

  // Login-as modal state
  const [loginAsUser, setLoginAsUser] = useState(null)
  const [loginAsRole, setLoginAsRole] = useState('')
  const [isLoggingInAs, setIsLoggingInAs] = useState(false)
  const [loginAsError, setLoginAsError] = useState('')

  // Edit role modal state
  const [editingUser, setEditingUser] = useState(null)
  const [selectedRoles, setSelectedRoles] = useState([])
  const [primaryRole, setPrimaryRole] = useState('')
  const [isSaving, setIsSaving] = useState(false)
  const [notice, setNotice] = useState('')
  const [noticeIsWarning, setNoticeIsWarning] = useState(false)
  const [formError, setFormError] = useState('')

  const loadData = async () => {
    setIsLoading(true)
    try {
      const res = await adminApi.getUserRbacList()
      setUsers(res.data)
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadData()
  }, [])

  // Role awal di modal = peran utama user (bila memang termasuk allowedRoles),
  // supaya kasus umum cukup satu klik "Masuk".
  const openLoginAsModal = (u) => {
    setLoginAsError('')
    setLoginAsUser(u)
    setLoginAsRole(u.allowedRoles.includes(u.role) ? u.role : u.allowedRoles[0])
  }

  const closeLoginAsModal = () => {
    if (isLoggingInAs) return
    setLoginAsUser(null)
    setLoginAsError('')
  }

  const handleLoginAs = async () => {
    if (!loginAsUser || !loginAsRole) return
    setIsLoggingInAs(true)
    setLoginAsError('')
    const res = await impersonate(loginAsUser.kodeperson, loginAsRole)
    setIsLoggingInAs(false)
    if (res?.success === false) {
      setLoginAsError(res.message || 'Gagal login sebagai pengguna ini.')
      return
    }
    // Server mengarahkan ke dashboard peran yang dipilih.
    setLoginAsUser(null)
  }

  const openEditModal = (u) => {
    const roles = u.allowedRoles || [u.role]
    setFormError('')
    setEditingUser(u)
    setSelectedRoles(roles)
    setPrimaryRole(u.role)
  }

  const toggleRole = (role) => {
    if (selectedRoles.includes(role)) {
      if (selectedRoles.length > 1) {
        const next = selectedRoles.filter((r) => r !== role)
        setSelectedRoles(next)
        if (primaryRole === role) setPrimaryRole(next[0])
      }
    } else {
      setSelectedRoles([...selectedRoles, role])
    }
  }

  const handleSave = async (e) => {
    e.preventDefault()
    if (!editingUser) return
    setIsSaving(true)
    setFormError('')
    try {
      const res = await adminApi.updateUserRole(editingUser.id, {
        allowedRoles: selectedRoles,
        primaryRole,
      })
      setEditingUser(null)
      const warnings = res?.data?.warnings || []
      setNoticeIsWarning(warnings.length > 0)
      setNotice(
        warnings.length > 0
          ? `Hak akses untuk ${editingUser.name} diperbarui dengan catatan: ${warnings.join(' ')}`
          : `Hak akses untuk ${editingUser.name} berhasil diperbarui!`
      )
      setTimeout(() => setNotice(''), 6000)
      await loadData()
    } catch (err) {
      setFormError(err?.message || 'Gagal menyimpan hak akses. Silakan coba lagi.')
    } finally {
      setIsSaving(false)
    }
  }

  const filteredUsers = users.filter((u) => {
    const q = search.toLowerCase()
    const matchSearch =
      !search ||
      u.name.toLowerCase().includes(q) ||
      u.kodeperson.toLowerCase().includes(q) ||
      (u.prodi && u.prodi.toLowerCase().includes(q))
    const matchRole =
      roleFilter === 'ALL' ||
      u.role === roleFilter ||
      (u.allowedRoles && u.allowedRoles.includes(roleFilter))
    return matchSearch && matchRole
  })

  const { page, setPage, totalPages, totalItems, pageSize, paginatedItems: pagedUsers } =
    usePagination(filteredUsers, 10)

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Manajemen Pengguna & Multi-Tier RBAC
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Pengaturan peran pengguna (Peneliti, Dekan, Reviewer, Admin, Rektorat, Akreditasi) dan multi-role switching
          </p>
        </div>
      </div>

      {notice && (
        <div
          className={`p-3 rounded-xl text-xs flex items-center gap-2.5 border shadow-sm ${
            noticeIsWarning
              ? 'bg-[#f9c851]/10 border-[#f9c851]/30 text-[#966b0a]'
              : 'bg-[#10c469]/10 border-[#10c469]/30 text-[#0b7941]'
          }`}
        >
          {noticeIsWarning ? (
            <AlertTriangle className="w-4 h-4 text-[#f9c851] shrink-0" />
          ) : (
            <ShieldCheck className="w-4 h-4 text-[#10c469] shrink-0" />
          )}
          <span>{notice}</span>
        </div>
      )}

      {/* Filter & Search toolbar */}
      <TableFilterBar
        searchValue={search}
        onSearchChange={setSearch}
        searchPlaceholder="Cari nama, NIDN/NIK, atau prodi..."
        filters={[
          {
            key: 'role',
            label: 'Peran',
            value: roleFilter,
            onChange: setRoleFilter,
            options: [
              { value: 'ALL', label: 'Semua Peran' },
              { value: ROLES.PENELITI, label: ROLE_LABELS[ROLES.PENELITI] },
              { value: ROLES.DEKAN, label: ROLE_LABELS[ROLES.DEKAN] },
              { value: ROLES.REVIEWER, label: ROLE_LABELS[ROLES.REVIEWER] },
              { value: ROLES.ADMIN, label: ROLE_LABELS[ROLES.ADMIN] },
              { value: ROLES.REKTORAT, label: ROLE_LABELS[ROLES.REKTORAT] },
              { value: ROLES.AKREDITASI, label: ROLE_LABELS[ROLES.AKREDITASI] },
            ],
          },
        ]}
        onReset={() => {
          setSearch('')
          setRoleFilter('ALL')
        }}
        totalCount={users.length}
        filteredCount={filteredUsers.length}
      />

      {/* Users Table */}
      <Card className="border-[#e7e9eb] shadow-sm">
        <CardHeader
          title={<span className="font-heading font-bold text-[#313a46]">Katalog Personil & Hak Akses Modul</span>}
          subtitle={<span className="text-xs text-[#98a6ad]">Setiap dosen dapat memiliki lebih dari satu peran aktif (multi-role)</span>}
        />
        <div className="overflow-x-auto">
          <table className="w-full text-left text-xs">
            <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
              <tr>
                <th className="px-3 py-3 text-center">Aksi</th>
                <th className="px-4 py-3">Nama Pengguna</th>
                <th className="px-3 py-3">NIDN / NIK</th>
                <th className="px-3 py-3">Homebase Prodi & Unit</th>
                <th className="px-3 py-3">Peran Utama</th>
                <th className="px-4 py-3">Hak Akses Modul (Allowed Roles)</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-[#e7e9eb]">
              {isLoading ? (
                <TableSkeleton cols={6} rows={6} />
              ) : filteredUsers.length === 0 ? (
                <TableEmptyState
                  colSpan={6}
                  message={
                    search || roleFilter !== 'ALL'
                      ? 'Tidak ada pengguna yang cocok dengan kriteria pencarian.'
                      : 'Belum ada data pengguna.'
                  }
                  onReset={
                    search || roleFilter !== 'ALL'
                      ? () => {
                          setSearch('')
                          setRoleFilter('ALL')
                        }
                      : null
                  }
                />
              ) : (
                pagedUsers.map((u) => (
                <tr key={u.id} className="hover:bg-[#f6f7fb]/60 transition-colors">
                  <td className="px-3 py-3.5 text-center whitespace-nowrap">
                    <div className="flex items-center justify-center gap-1.5">
                      <Button
                        variant="secondary"
                        size="xs"
                        iconLeft={Edit2}
                        onClick={() => openEditModal(u)}
                      >
                        Atur Peran
                      </Button>

                      {(currentUser?.isSuperAdmin || currentUser?.role === ROLES.ADMIN) && !u.isSuperAdmin && u.kodeperson !== currentUser.kodeperson && (u.allowedRoles || []).length > 0 && (
                        <Button
                          variant="ghost"
                          size="xs"
                          iconLeft={LogIn}
                          title="Login sebagai pengguna ini untuk testing manual"
                          onClick={() => openLoginAsModal(u)}
                        >
                          Login as
                        </Button>
                      )}
                    </div>
                  </td>
                  <td className="px-4 py-3.5">
                    <div>
                      <p className="font-semibold text-[#313a46]">{u.name}</p>
                      <span className="text-[11px] text-[#98a6ad]">{u.email}</span>
                    </div>
                  </td>

                  <td className="px-3 py-3.5 font-mono text-[#6c757d] whitespace-nowrap">
                    {u.nidn || u.kodeperson}
                  </td>

                  <td className="px-3 py-3.5 whitespace-nowrap">
                    <p className="font-medium text-[#313a46]">{u.prodi}</p>
                    <span className="text-[11px] text-[#98a6ad]">{u.fakultas}</span>
                  </td>

                  <td className="px-3 py-3.5 whitespace-nowrap">
                    <span
                      className={`px-2 py-0.5 rounded text-xs font-semibold border ${
                        ROLE_BADGE_COLORS[u.role] || 'bg-[#f6f7fb] text-[#313a46] border-[#e7e9eb]'
                      }`}
                    >
                      {ROLE_LABELS[u.role] === null ? "No Role" : ROLE_LABELS[u.role]}
                    </span>
                  </td>

                  <td className="px-4 py-3.5">
                    <div className="flex flex-wrap gap-1">
                      {u.allowedRoles?.map((r) => (
                        <span
                          key={r}
                          className="px-2 py-0.5 rounded-full text-[10px] bg-[#f6f7fb] text-[#6c757d] border border-[#e7e9eb] font-medium"
                        >
                          {r}
                        </span>
                      ))}
                    </div>
                  </td>

                </tr>
                ))
              )}
            </tbody>
          </table>
        </div>
        {!isLoading && (
          <CardFooter className="border-t border-[#e7e9eb] bg-white">
            <Pagination
              page={page}
              totalPages={totalPages}
              totalItems={totalItems}
              pageSize={pageSize}
              onPageChange={setPage}
            />
          </CardFooter>
        )}
      </Card>

      {/* Modal Login As */}
      <Modal
        isOpen={!!loginAsUser}
        onClose={closeLoginAsModal}
        title="Login Sebagai Pengguna"
        subtitle={loginAsUser ? `${loginAsUser.name} (${loginAsUser.kodeperson})` : ''}
        maxWidth="max-w-md"
        footer={
          <>
            <Button variant="secondary" size="sm" onClick={closeLoginAsModal} disabled={isLoggingInAs}>
              Batal
            </Button>
            <Button
              variant="primary"
              size="sm"
              iconLeft={LogIn}
              onClick={handleLoginAs}
              isLoading={isLoggingInAs}
              disabled={!loginAsRole}
            >
              Masuk
            </Button>
          </>
        }
      >
        {loginAsUser && (
          <div className="space-y-3 text-xs">
            {loginAsError && (
              <p className="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 font-semibold flex items-start gap-2">
                <XCircle className="w-4 h-4 shrink-0 mt-0.5" /> {loginAsError}
              </p>
            )}
            <p className="font-semibold text-[#313a46]">Pilih peran yang dipakai saat login:</p>
            <div className="space-y-2" role="radiogroup" aria-label="Peran login as">
              {loginAsUser.allowedRoles.map((role) => {
                const isChecked = loginAsRole === role
                return (
                  <label
                    key={role}
                    className={`flex items-center justify-between p-2.5 rounded-xl border cursor-pointer select-none transition ${
                      isChecked
                        ? 'border-[#188ae2] bg-[#188ae2]/5 font-semibold text-[#188ae2]'
                        : 'border-[#e7e9eb] hover:bg-[#f6f7fb] text-[#313a46]'
                    }`}
                  >
                    <div className="flex items-center gap-2.5">
                      <input
                        type="radio"
                        name="login-as-role"
                        value={role}
                        checked={isChecked}
                        onChange={() => setLoginAsRole(role)}
                        className="text-[#188ae2] focus:ring-[#188ae2] border-[#e7e9eb]"
                      />
                      <span>{ROLE_LABELS[role] || role}</span>
                    </div>
                    {role === loginAsUser.role && (
                      <span className="text-[10px] text-[#98a6ad] font-normal">Peran utama</span>
                    )}
                  </label>
                )
              })}
            </div>
            <p className="text-[11px] text-[#98a6ad]">
              Sesi Anda disimpan. Gunakan tombol "Kembali ke akun Anda" di banner atas untuk keluar dari mode login as.
            </p>
          </div>
        )}
      </Modal>

      {/* Modal Edit RBAC Roles */}
      <Modal
        isOpen={!!editingUser}
        onClose={() => {
          setEditingUser(null)
          setFormError('')
        }}
        title="Pengaturan Hak Akses & Multi-Role"
        subtitle={editingUser ? `${editingUser.name} (${editingUser.kodeperson})` : ''}
        maxWidth="max-w-md"
        footer={
          <>
            <Button
              variant="secondary"
              size="sm"
              onClick={() => {
                setEditingUser(null)
                setFormError('')
              }}
              disabled={isSaving}
            >
              Batal
            </Button>
            <Button
              variant="primary"
              size="sm"
              onClick={handleSave}
              isLoading={isSaving}
            >
              Simpan Hak Akses
            </Button>
          </>
        }
      >
        {editingUser && (
          <form onSubmit={handleSave} className="space-y-4 text-xs">
            {formError && (
              <p className="p-3 rounded-lg border border-rose-200 bg-rose-50 text-rose-700 font-semibold flex items-start gap-2">
                <XCircle className="w-4 h-4 shrink-0 mt-0.5" /> {formError}
              </p>
            )}
            <div>
              <label className="block font-semibold text-[#313a46] mb-2">
                Pilih Modul yang Diizinkan untuk Pengguna Ini:
              </label>
              <div className="space-y-2">
                {Object.values(ROLES).map((role) => {
                  const isChecked = selectedRoles.includes(role)
                  return (
                    <label
                      key={role}
                      className={`flex items-center justify-between p-2.5 rounded-xl border cursor-pointer select-none transition ${
                        isChecked
                          ? 'border-[#188ae2] bg-[#188ae2]/5 font-semibold text-[#188ae2]'
                          : 'border-[#e7e9eb] hover:bg-[#f6f7fb] text-[#313a46]'
                      }`}
                    >
                      <div className="flex items-center gap-2.5">
                        <input
                          type="checkbox"
                          checked={isChecked}
                          onChange={() => toggleRole(role)}
                          className="rounded text-[#188ae2] focus:ring-[#188ae2] border-[#e7e9eb]"
                        />
                        <span>{ROLE_LABELS[role]}</span>
                      </div>
                      <span className="font-mono text-[10px] text-[#98a6ad] font-normal">
                        GROUPAKSES_{role}
                      </span>
                    </label>
                  )
                })}
              </div>
              <p className="mt-2 text-[11px] text-[#98a6ad]">
                Hak akses CRUD per peran (Buat/Lihat/Ubah/Hapus) diatur di halaman{' '}
                <span className="font-semibold text-[#313a46]">Manajemen Role</span>, bukan di sini.
              </p>
            </div>

            <div className="pt-3 border-t border-[#e7e9eb]">
              <label className="block font-semibold text-[#313a46] mb-1.5">
                Peran Utama Saat Pertama Kali Login:
              </label>
              <select
                value={primaryRole}
                onChange={(e) => setPrimaryRole(e.target.value)}
                className="w-full p-2.5 bg-white border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2]"
              >
                {selectedRoles.map((role) => (
                  <option key={role} value={role}>
                    {ROLE_LABELS[role]}
                  </option>
                ))}
              </select>
            </div>
          </form>
        )}
      </Modal>
    </div>
  )
}
