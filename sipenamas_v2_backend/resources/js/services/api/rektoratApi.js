import { request, toQueryString } from './apiClient'

/**
 * getExecutiveDashboardStats/getApprovalStrategisList terhubung ke backend
 * sungguhan - lihat app/Http/Controllers/Api/V1/Rektorat/DashboardController.php
 * di sipenamas_v2_backend. Rektorat murni role monitoring/oversight - tidak
 * ada aksi tulis: tombol "Setujui Riset Strategis" yang dulu ada di sini
 * SUDAH DIHAPUS karena tidak punya alur/kolom bisnis sendiri (keputusan
 * final approval sepenuhnya milik Admin/LPPM).
 *
 * MBKM memakai padanan legacy rkt/myphp/mbkm*.php. Modul MBKM legacy adalah
 * survei berhadiah (tabel `mbkm_mhs` + `mbkm_datahasil_*`), bukan pendataan
 * keterlibatan mahasiswa di penelitian: tidak ada SKS terkonversi maupun
 * dosen pembimbing di skema legacy.
 */
export const rektoratApi = {
  async getExecutiveDashboardStats() {
    return request('/rkt/dashboard')
  },

  async getApprovalStrategisList() {
    return request('/rkt/strategis')
  },

  async getMbkmPengisian({ cari } = {}) {
    return request(`/rkt/mbkm/pengisian${toQueryString({ cari })}`)
  },

  async getMbkmRekapProdi({ kampus } = {}) {
    return request(`/rkt/mbkm/rekap-prodi${toQueryString({ kampus })}`)
  },

  async getMbkmBelumMengisi({ cari } = {}) {
    return request(`/rkt/mbkm/belum-mengisi${toQueryString({ cari })}`)
  },

  async getMbkmDataHasil(responden, { cari } = {}) {
    return request(`/rkt/mbkm/data-hasil/${responden}${toQueryString({ cari })}`)
  },
}
