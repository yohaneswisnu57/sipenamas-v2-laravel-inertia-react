import { toQueryString, unduhBerkas, ambilBerkasUrl } from './apiClient'
import { kirim } from '../../lib/inertiaRequest'
import { basePath } from '../../lib/router'

/**
 * Aksi modul Peneliti (PEN) ke route web Laravel - lihat
 * app/Http/Controllers/Web/Peneliti/*.php. Data halaman tidak lagi diambil
 * dari sini: controller mengirimnya sebagai props Inertia. Aksi tulis
 * memakai kirim() (redirect + flash), berkas memakai fetch dengan cookie
 * session ke path web (bukan /api/v1).
 */
const web = () => ({ base: basePath() })

export const penelitiApi = {
  async unduhTemplate(id, name) {
    return unduhBerkas(`/pen/template/${id}/unduh`, name, web())
  },

  async lihatTemplate(id) {
    return unduhBerkas(`/pen/template/${id}/unduh`, null, web())
  },

  // `preview: true` membuka lembar di tab baru (boleh kapan saja setelah
  // di-generate); unduhan biasa hanya setelah disetujui Dekan.
  async unduhPengesahan(id, { format = 'docx', gabung = false, preview = false } = {}) {
    const params = toQueryString({
      format: format === 'pdf' ? 'pdf' : undefined,
      gabung: format === 'pdf' && gabung ? 1 : undefined,
      preview: preview ? 1 : undefined,
    })
    const filename = gabung ? 'ProposalLengkap.pdf' : `LembarPengesahan.${format}`
    if (preview) {
      return ambilBerkasUrl(`/pen/penelitian/${id}/pengesahan${params}`, web())
    }
    return unduhBerkas(`/pen/penelitian/${id}/pengesahan${params}`, filename, web())
  },

  async unduhSuratTugas(id) {
    return unduhBerkas(`/pen/penelitian/${id}/surat-tugas`, `SuratTugas_${id}.docx`, web())
  },

  async saveDanaPenyertaanProposal(id, { danaMitra, danaInkind }) {
    return kirim('put', `/pen/penelitian/${id}/dana-penyertaan`, { danaMitra, danaInkind })
  },

  async generateLembarPengesahanProposal(id) {
    return kirim('post', `/pen/penelitian/${id}/lembar-pengesahan`)
  },

  async finalLembarPengesahanProposal(id) {
    return kirim('post', `/pen/penelitian/${id}/lembar-pengesahan/final`)
  },

  async finalDokumenProposal(id) {
    return kirim('post', `/pen/penelitian/${id}/dokumen-proposal/final`)
  },

  // Server mengarahkan ke daftar usulan sesudah simpan/hapus.
  async createPenelitian(payload) {
    return kirim('post', '/pen/penelitian', payload)
  },

  async updatePenelitian(id, payload) {
    return kirim('put', `/pen/penelitian/${id}`, payload)
  },

  async deletePenelitian(id) {
    return kirim('delete', `/pen/penelitian/${id}`)
  },

  // Tahap pengajuan - lihat docs/legacy-flow/penelitian.md §2.3-2.5
  async setujuiKesediaanTim(timId) {
    return kirim('post', `/pen/kesediaan-tim/${timId}/setuju`)
  },

  /** Naskah proposal terunggah (draft/final) sebagai object URL untuk pratinjau. */
  async pratinjauDokumenProposal(id) {
    return ambilBerkasUrl(`/pen/penelitian/${id}/dokumen-proposal`, web())
  },

  /** `dokumenProposal` harus objek File PDF asli. */
  async uploadDokumenProposal(id, dokumenProposal) {
    return kirim('post', `/pen/penelitian/${id}/dokumen-proposal`, { dokumenProposal })
  },

  async updateRencanaTarget(id, targetIds) {
    return kirim('put', `/pen/penelitian/${id}/rencana-target`, { targetIds })
  },

  // Revisi proposal: tanggapan per komentar reviewer (padanan
  // hasilreviewpenelitian.php legacy).
  async saveResponRevisi(id, komentarId, respon) {
    return kirim('put', `/pen/penelitian/${id}/revisi/komentar/${komentarId}`, { respon })
  },

  /** Unggah naskah revisi sebagai draft; boleh diganti sampai di-set final. */
  async uploadDokumenRevisi(id, dokumenRevisi) {
    return kirim('post', `/pen/penelitian/${id}/revisi/dokumen`, { dokumenRevisi })
  },

  async pratinjauDokumenRevisi(id) {
    return ambilBerkasUrl(`/pen/penelitian/${id}/revisi/dokumen`, web())
  },

  async finalRevisi(id) {
    return kirim('post', `/pen/penelitian/${id}/revisi/final`)
  },

  async saveMonevJawaban(id, { nomor, jawaban }) {
    return kirim('put', `/pen/monev-hasil/${id}/jawaban`, { nomor, jawaban })
  },

  async saveMonevKesimpulan(id, { kesimpulan, isFinal }) {
    return kirim('post', `/pen/monev-hasil/${id}/kesimpulan`, { kesimpulan, isFinal })
  },

  async saveKuesionerJawaban(detailId, jawab) {
    return kirim('put', `/pen/kuesioner-penelitian/${detailId}`, { jawab })
  },

  async saveDanaPenyertaanLaporan(id, { danaMitra, danaInkind }) {
    return kirim('put', `/pen/laporan-akhir/${id}/dana-penyertaan`, { danaMitra, danaInkind })
  },

  async addMahasiswaLaporan(id, nim) {
    return kirim('post', `/pen/laporan-akhir/${id}/mahasiswa`, { nim })
  },

  async deleteMahasiswaLaporan(id, mahasiswaId) {
    return kirim('delete', `/pen/laporan-akhir/${id}/mahasiswa/${mahasiswaId}`)
  },

  async generateLembarPengesahanLaporan(id) {
    return kirim('post', `/pen/laporan-akhir/${id}/lembar-pengesahan`)
  },

  async finalLembarPengesahanLaporan(id) {
    return kirim('post', `/pen/laporan-akhir/${id}/lembar-pengesahan/final`)
  },

  async unduhLembarPengesahanLaporan(id, { preview = false } = {}) {
    return unduhBerkas(
      `/pen/laporan-akhir/${id}/lembar-pengesahan${preview ? '?preview=1' : ''}`,
      preview ? null : 'LembarPengesahanHasilPenelitian.docx',
      web()
    )
  },

  async saveCapaianLuaran(id, targetId, { realisasi, keterangan, statusTayang }) {
    return kirim('put', `/pen/laporan-akhir/${id}/capaian/${targetId}`, {
      realisasi,
      keterangan,
      statusTayang: statusTayang || null,
    })
  },

  async uploadDokumenLuaran(id, targetId, file) {
    return kirim('post', `/pen/laporan-akhir/${id}/capaian/${targetId}/dokumen`, { dokumen: file })
  },

  async deleteDokumenLuaran(id, targetId) {
    return kirim('delete', `/pen/laporan-akhir/${id}/capaian/${targetId}/dokumen`)
  },

  async lihatDokumenLuaran(id, targetId) {
    return unduhBerkas(`/pen/laporan-akhir/${id}/capaian/${targetId}/dokumen`, null, web())
  },

  async createSubsidiApc(payload) {
    return kirim('post', '/pen/subsidi-apc', payload)
  },

  async createInsentifJurnal(payload) {
    // UI mengumpulkan satu field gabungan `volumeNomor` ("Vol. 10, No. 2"),
    // backend butuh volume/nomor terpisah - dipecah di sini, bukan di UI.
    const { volume, nomor } = splitVolumeNomor(payload.volumeNomor)

    return kirim('post', '/pen/insentif-jurnal', {
      judulArtikel: payload.judulArtikel,
      namaJurnal: payload.namaJurnal,
      tingkatJurnal: payload.tingkatJurnal,
      tahunTerbit: payload.tahunTerbit,
      volume,
      nomor,
    })
  },

  async createHki(payload) {
    return kirim('post', '/pen/hki', payload)
  },
}

function splitVolumeNomor(text) {
  if (!text) return { volume: null, nomor: null }
  const volMatch = text.match(/Vol\.?\s*([^,]+)/i)
  const noMatch = text.match(/No\.?\s*(.+)/i)
  return {
    volume: volMatch ? volMatch[1].trim() : null,
    nomor: noMatch ? noMatch[1].trim() : null,
  }
}
