import { request, ambilBerkasUrl, unduhBerkas } from './apiClient'

/**
 * Terhubung ke backend Laravel sungguhan - lihat
 * app/Http/Controllers/Api/V1/Reviewer/PenugasanController.php di
 * sipenamas_v2_backend. Identitas reviewer ditentukan backend dari token
 * (bukan dikirim dari client) - sama seperti penelitiApi.
 */
export const reviewerApi = {
  async getPenugasanList() {
    return request('/rev/penugasan')
  },

  async getPenugasanDetail(id) {
    return request(`/rev/penugasan/${id}`)
  },

  /** Naskah proposal yang dinilai (FILE_DOKUMENPROPOSAL_INIT) - juga dipakai verifikator revisi untuk naskah sebelum revisi. */
  async ambilDokumenProposal(proposalId) {
    return ambilBerkasUrl(`/rev/penugasan/${proposalId}/dokumen-proposal`)
  },

  /** Naskah revisi yang diunggah peneliti. */
  async ambilDokumenProposalRevisi(proposalId) {
    return ambilBerkasUrl(`/rev/penugasan/${proposalId}/dokumen-proposal-revisi`)
  },

  async confirmKesediaan(proposalId, { bersedia, alasan }) {
    return request(`/rev/penugasan/${proposalId}/kesediaan`, {
      method: 'POST',
      body: JSON.stringify({ bersedia, alasan }),
    })
  },

  async getBorang(proposalId) {
    return request(`/rev/penugasan/${proposalId}/borang`)
  },

  /**
   * `skor`: [{ nomor, skor }]. `status` DRAFT = simpan sementara (skor boleh
   * belum lengkap), FINAL = kunci penilaian (semua kriteria wajib).
   */
  async submitNilaiRubrik(proposalId, { skor, catatan, rekomendasiStatus, rekomendasiDana, komentarRevisi = [], status = 'FINAL' }) {
    return request(`/rev/penugasan/${proposalId}/penilaian`, {
      method: 'POST',
      body: JSON.stringify({ skor, catatan, rekomendasiStatus, rekomendasiDana, komentarRevisi, status }),
    })
  },

  /** Ganti judul proposal; judul asli dicadangkan backend (legacy REVISIJUDUL). */
  async revisiJudul(proposalId, judulBaru) {
    return request(`/rev/penugasan/${proposalId}/revisi-judul`, {
      method: 'POST',
      body: JSON.stringify({ judulBaru }),
    })
  },

  /** Rubrik penilaian (legacy res/BUTIRPENILAIAN.docx). */
  async unduhPanduanPenilaian() {
    return unduhBerkas('/rev/panduan-penilaian', 'Panduan_Penilaian.docx')
  },

  /** Komentar revisi semua reviewer beserta tanggapan peneliti. */
  async getKomentarRevisi(proposalId) {
    return request(`/rev/penugasan/${proposalId}/komentar-revisi`)
  },

  async verifikasiRevisi(proposalId, { status, catatan }) {
    return request(`/rev/penugasan/${proposalId}/verifikasi-revisi`, {
      method: 'POST',
      body: JSON.stringify({ status, catatan }),
    })
  },

  async getRevisiVerifikasiQueue() {
    return request('/rev/penugasan/revisi')
  },
}
