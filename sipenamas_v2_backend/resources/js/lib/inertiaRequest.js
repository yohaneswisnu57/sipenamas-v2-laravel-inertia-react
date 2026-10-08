import { router } from '@inertiajs/react'
import { url } from './router'
import { ApiError } from '../services/api/apiClient'

function firstError(errors) {
  const first = Object.values(errors || {})[0]
  return Array.isArray(first) ? first[0] : first
}

/**
 * Aksi tulis lewat router Inertia ke route web. Server menjawab redirect
 * (biasanya kembali ke halaman ini), jadi props halaman otomatis segar; tidak
 * perlu memuat ulang data manual.
 *
 * Bentuk hasilnya sama dengan request() lama supaya handler halaman tidak
 * berubah: resolve `{ success, message }` dari flash sukses, reject
 * ApiError(message, status, errors) dari flash error atau error validasi.
 *
 * `options.only` membatasi props yang dimuat ulang.
 */
export function kirim(method, path, data = {}, options = {}) {
  return new Promise((resolve, reject) => {
    router.visit(url(path), {
      method,
      data,
      only: options.only,
      forceFormData: options.forceFormData,
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        const flash = page.props.flash || {}
        if (flash.error) {
          reject(new ApiError(flash.error, 422))
        } else {
          resolve({ success: true, message: flash.success })
        }
      },
      onError: (errors) => {
        reject(new ApiError(firstError(errors) || 'Data yang dikirim tidak valid.', 422, errors))
      },
      onHttpException: (response) => {
        reject(new ApiError('Terjadi kesalahan pada server.', response?.status || 500))
        return false
      },
      onNetworkError: () => {
        reject(new ApiError('Tidak dapat terhubung ke server.', 0))
        return false
      },
    })
  })
}

/**
 * Buka atau muat ulang jendela yang isinya props halaman ini (mis.
 * `?kelengkapan=12`) tanpa pindah halaman. Nilai null/undefined menghapus
 * parameter dari URL.
 */
export function muatJendela(params, only) {
  return new Promise((resolve) => {
    const query = new URLSearchParams(window.location.search)
    Object.entries(params).forEach(([key, value]) => {
      if (value === null || value === undefined || value === '') {
        query.delete(key)
      } else {
        query.set(key, value)
      }
    })
    const qs = query.toString()
    router.visit(`${window.location.pathname}${qs ? `?${qs}` : ''}`, {
      only,
      preserveState: true,
      preserveScroll: true,
      replace: true,
      onFinish: () => resolve(),
    })
  })
}
