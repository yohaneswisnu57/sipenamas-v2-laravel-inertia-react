import React from 'react'
import { UserCog } from 'lucide-react'
import { useAuthStore } from '../../store/authStore'
import { ROLE_LABELS } from '../../utils/constants'

export function ImpersonationBanner() {
  const { user, isImpersonating, stopImpersonating } = useAuthStore()

  if (!isImpersonating || !user) return null

  // Login as hanya bisa dimulai dari Manajemen Pengguna, jadi server
  // mengembalikan admin ke halaman itu, bukan ke dashboard.
  const handleReturn = async () => {
    await stopImpersonating()
  }

  return (
    <div className="flex items-center justify-center gap-3 bg-amber-400 text-amber-950 text-xs font-medium px-4 py-2">
      <UserCog className="w-4 h-4 shrink-0" />
      <span>
        Anda login sebagai <strong>{user.name}</strong> ({ROLE_LABELS[user.activeRole] || user.activeRole})
      </span>
      <button
        onClick={handleReturn}
        className="ml-2 px-2.5 py-0.5 rounded bg-amber-950 text-amber-50 font-semibold hover:bg-amber-900 transition-colors cursor-pointer"
      >
        Kembali ke akun Anda
      </button>
    </div>
  )
}
