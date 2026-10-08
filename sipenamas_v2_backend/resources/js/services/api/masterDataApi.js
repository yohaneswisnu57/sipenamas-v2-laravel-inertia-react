import { request, toQueryString } from './apiClient'

/**
 * Endpoint referensi read-only lintas modul - padanan
 * app/Http/Controllers/Api/V1/MasterDataController.php di backend.
 */
export const masterDataApi = {
  async getPeriodeList() {
    return request('/periode')
  },

  async getPeriodeAktif() {
    return request('/periode/aktif')
  },

  async getSkimList({ abdimas } = {}) {
    return request(`/skim${toQueryString({ abdimas })}`)
  },

  /** Pilihan "Terindeks Dalam" insentif jurnal (legacy cmbindexjurnal). */
  async getIndexJurnalList({ apc } = {}) {
    return request(`/index-jurnal${toQueryString({ apc: apc ? 1 : undefined })}`)
  },

  async getFakultasList() {
    return request('/fakultas')
  },

  async getProdiList({ fakultas } = {}) {
    return request(`/prodi${toQueryString({ fakultas })}`)
  },

  async getSumberDanaList() {
    return request('/sumberdana')
  },

  async searchDosen(q) {
    return request(`/dosen${toQueryString({ q })}`)
  },

  async searchReviewer(q, idpen) {
    return request(`/reviewer${toQueryString({ q, idpen })}`)
  },

  async searchMahasiswa(q) {
    return request(`/mahasiswa${toQueryString({ q })}`)
  },
}
