import { router, usePage } from '@inertiajs/react'
import { url } from '../lib/router'

/**
 * Sesi login sekarang milik server (session cookie Laravel), bukan token di
 * localStorage. Hook ini mempertahankan bentuk API useAuthStore lama supaya
 * komponen tidak perlu ditulis ulang: data user dibaca dari shared prop
 * `auth` (HandleInertiaRequests), aksi auth adalah POST ke route web yang
 * mengarahkan ulang (redirect) ke halaman tujuan.
 */
function firstError(errors) {
  const first = Object.values(errors || {})[0]
  return Array.isArray(first) ? first[0] : first
}

function postVisit(path, data = {}) {
  return new Promise((resolve) => {
    router.post(url(path), data, {
      onSuccess: () => resolve({ success: true }),
      onError: (errors) => resolve({ success: false, message: firstError(errors) || 'Permintaan gagal.' }),
      onHttpException: () => {
        resolve({ success: false, message: 'Permintaan gagal. Silakan coba lagi.' })
        return false
      },
      onNetworkError: () => {
        resolve({ success: false, message: 'Tidak dapat terhubung ke server.' })
        return false
      },
    })
  })
}

export function useAuthStore() {
  const { auth } = usePage().props
  const user = auth?.user || null

  return {
    user,
    isAuthenticated: !!user,
    isLoading: false,
    error: null,
    isImpersonating: !!auth?.impersonating,

    login: async ({ username, password, roleHint }) => {
      const res = await postVisit('/login', { username, password, roleHint })
      if (!res.success) {
        throw new Error(res.message)
      }
      return res
    },

    logout: () => postVisit('/logout'),

    switchRole: (role) => postVisit('/switch-role', { role }),

    impersonate: (kodeperson, role) =>
      postVisit(`/impersonate/${encodeURIComponent(kodeperson)}`, role ? { role } : {}),

    stopImpersonating: () => postVisit('/impersonate/leave'),

    // Cek aksi granular per modul, mis. hasPermission('ADM', 'manage-users').
    hasPermission: (role, action) => {
      const permissions = user?.modulePermissions?.[role]
      return Array.isArray(permissions) && permissions.includes(action)
    },
  }
}
