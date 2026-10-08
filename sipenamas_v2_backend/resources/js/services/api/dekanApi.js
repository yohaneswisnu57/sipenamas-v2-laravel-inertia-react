import { request, toQueryString, unduhBerkas, ambilBerkasUrl } from './apiClient'
import { STATUS_USULAN } from '../../utils/constants'

/**
 * getApprovalList/approveProposal/rejectProposal terhubung ke backend
 * sungguhan - lihat app/Http/Controllers/Api/V1/Dekan/ApprovalController.php
 * di sipenamas_v2_backend. Fakultas ditentukan backend dari relasi
 * fakultas.KDDEKAN = token login, tidak dikirim dari client.
 *
 * getPaguAnggaran memakai padanan legacy dkn/myphp/anggaranpenelitian.php:
 * pagu disimpan per PRODI (`prodi_anggaran`), bukan per fakultas - tabel
 * `fakultas_anggaran` legacy tidak ada di database produksi. Menu legacy
 * read-only (editData() dikomentari), jadi tidak ada endpoint tulis.
 *
 * getDashboardStats dihitung dari endpoint asli (daftar pengajuan + pagu),
 * tidak ada endpoint statistik dekan terpisah di backend.
 */
export const dekanApi = {
  async getDashboardStats() {
    const [pengajuan, pagu] = await Promise.all([this.getPengajuanList('ALL'), this.getPaguAnggaran()])
    const list = pengajuan.data || []
    const danaDisetujui = list
      .filter((p) => [STATUS_USULAN.LOLOS, STATUS_USULAN.MONEV, STATUS_USULAN.TUNTAS].includes(p.status))
      .reduce((sum, p) => sum + (p.biayaDisetujui || 0), 0)
    const paguTotal = pagu.data?.totalAlokasi || 0

    return {
      success: true,
      data: {
        totalUsulan: list.length,
        menungguPersetujuan: list.filter((p) => p.status === STATUS_USULAN.SUBMITTED).length,
        risetAktif: list.filter((p) => [STATUS_USULAN.LOLOS, STATUS_USULAN.MONEV].includes(p.status)).length,
        totalDanaDisetujui: danaDisetujui,
        paguFakultas: paguTotal,
        serapanPaguPersen: paguTotal > 0 ? Math.round((pagu.data.totalDisetujui / paguTotal) * 100) : 0,
      },
    }
  },

  async getPengajuanList(kdperiode) {
    return request(`/dkn/pengajuan${toQueryString({ kdperiode })}`)
  },

  async getApprovalList() {
    return request('/dkn/proposal')
  },

  async ambilDokumenProposal(id) {
    return ambilBerkasUrl(`/dkn/proposal/${id}/dokumen-proposal`)
  },

  async approveProposal(id, { catatan }) {
    return request(`/dkn/proposal/${id}/approve`, {
      method: 'POST',
      body: JSON.stringify({ catatan }),
    })
  },

  async rejectProposal(id, { catatan }) {
    return request(`/dkn/proposal/${id}/reject`, {
      method: 'POST',
      body: JSON.stringify({ catatan }),
    })
  },

  // Persetujuan laporan akhir (padanan dkn/myphp/approvallaporan.php legacy)
  async getLaporanAkhirList(kdperiode) {
    return request(`/dkn/laporan-akhir${toQueryString({ kdperiode })}`)
  },

  async approveLaporanAkhir(id) {
    return request(`/dkn/laporan-akhir/${id}/approve`, { method: 'POST' })
  },

  async lihatLembarPengesahanLaporan(id) {
    return unduhBerkas(`/dkn/laporan-akhir/${id}/lembar-pengesahan`, null)
  },

  async getMonevList() {
    return request('/dkn/monev')
  },

  async getMonevKandidat(id) {
    return request(`/dkn/monev/${id}/kandidat`)
  },

  async assignMonev(id, { kodeperson }) {
    return request(`/dkn/monev/${id}/penunjukan`, {
      method: 'POST',
      body: JSON.stringify({ kodeperson: kodeperson || null }),
    })
  },

  async getPaguAnggaran({ kdperiode } = {}) {
    return request(`/dkn/anggaran-penelitian${toQueryString({ kdperiode })}`)
  },

  async getMonitoringBelumTuntas({ kdperiode } = {}) {
    return request(`/dkn/belum-tuntas${toQueryString({ kdperiode })}`)
  },
}
