/**
 * Central API Client for SIPENAMAS V2.
 *
 * USE_MOCK = false: modul Auth, master data, Peneliti (PEN), Admin (ADM),
 * Reviewer (REV), Dekan (DKN), Rektorat (RKT), dan Akreditasi (AKR) sudah
 * tersambung ke backend Laravel di sipenamas_v2_backend.
 *
 * Sisa endpoint yang MASIH mock murni: adminApi.getWhatsappGatewayStatus/
 * sendWhatsappBroadcast - legacy memakai Wappin (API template cloud) dengan
 * tabel antrian `z_log_wa_msg` + `settingan_tplnotifikasi`, bukan gateway
 * perangkat; porting-nya butuh kredensial dan worker pengirim tersendiri.
 */

export const USE_MOCK = false
import { router } from '@inertiajs/react'
import { url } from '../../lib/router'

// Satu domain dengan halaman Inertia: API dipanggil lewat path relatif dengan
// cookie session (Sanctum stateful), bukan Bearer token di localStorage.
export const API_BASE_URL = url('/api/v1')

/** Token CSRF dari cookie XSRF-TOKEN yang ditulis Laravel. */
function xsrfHeader() {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)
  return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {}
}

const sessionOptions = { credentials: 'same-origin' }

// Helper to simulate realistic HTTP roundtrip latency (dipakai file-file yang masih mock)
export const simulateDelay = (ms = 200) => new Promise((resolve) => setTimeout(resolve, ms))

export class ApiError extends Error {
  constructor(message, status = 400, errors = null) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.errors = errors
  }
}

/**
 * Bangun query string dari object filter, otomatis buang key bernilai
 * undefined/null/'' supaya tidak terkirim sebagai literal string "undefined".
 */
export function toQueryString(params = {}) {
  const usp = new URLSearchParams()
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      usp.set(key, value)
    }
  })
  const qs = usp.toString()
  return qs ? `?${qs}` : ''
}

async function handleResponse(response) {
  const data = await response.json().catch(() => ({}))

  if (response.status === 401) {
    // Session habis: kembali ke halaman login.
    router.visit(url('/login'))
  }

  if (!response.ok) {
    throw new ApiError(data.message || 'Terjadi kesalahan pada server', response.status, data.errors)
  }

  return data
}

export async function request(endpoint, options = {}) {
  const headers = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...xsrfHeader(),
    ...options.headers,
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...sessionOptions,
    ...options,
    headers,
  })

  return handleResponse(response)
}

/**
 * Sama seperti request(), tapi untuk endpoint yang menerima file upload
 * (multipart/form-data). JANGAN set header Content-Type manual di sini -
 * browser yang menentukan boundary multipart-nya sendiri.
 */
export async function requestForm(endpoint, formData, options = {}) {
  const headers = {
    Accept: 'application/json',
    ...xsrfHeader(),
    ...options.headers,
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    method: 'POST',
    ...sessionOptions,
    ...options,
    headers,
    body: formData,
  })

  return handleResponse(response)
}

/**
 * Ambil berkas dari endpoint ber-token. `filename` null -> buka di tab baru
 * (pratinjau), selain itu diunduh dengan nama tersebut.
 */
export async function ambilBerkasUrl(endpoint, { base = API_BASE_URL } = {}) {
  const res = await fetch(`${base}${endpoint}`, {
    ...sessionOptions,
    headers: { Accept: 'application/json' },
  })
  if (!res.ok) {
    const body = await res.json().catch(() => ({}))
    throw new ApiError(body.message || 'Gagal mengambil berkas', res.status, body.errors)
  }
  return URL.createObjectURL(await res.blob())
}

export async function unduhBerkas(endpoint, filename, options = {}) {
  let newWindow = null
  if (!filename) {
    // Buka tab baru secara synchronous agar tidak diblokir browser (popup blocker)
    newWindow = window.open('about:blank', '_blank', 'noopener')
  }
  try {
    const url = await ambilBerkasUrl(endpoint, options)
    if (filename) {
      const a = document.createElement('a')
      a.href = url
      a.download = filename
      a.click()
      setTimeout(() => URL.revokeObjectURL(url), 1000)
    } else {
      if (newWindow) newWindow.location.href = url
    }
  } catch (error) {
    if (newWindow) newWindow.close()
    throw error
  }
}
