import { request, simulateDelay, toQueryString, unduhBerkas } from './apiClient'
import { masterDataApi } from './masterDataApi'

/**
 * Terhubung ke backend Laravel sungguhan - lihat
 * app/Http/Controllers/Api/V1/Admin/*.php di sipenamas_v2_backend.
 *
 * getWhatsappGatewayStatus/sendWhatsappBroadcast MASIH mock - integrasi
 * WhatsApp gateway belum dibangun di backend (rencana Fase 4).
 */
export const adminApi = {
  async getDashboardStats() {
    return request('/adm/dashboard')
  },

  async getPeriodeList() {
    return request('/adm/periode')
  },

  async createPeriode(payload) {
    return request('/adm/periode', {
      method: 'POST',
      body: JSON.stringify(payload),
    })
  },

  async updatePeriode(id, payload) {
    return request(`/adm/periode/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    })
  },

  async togglePeriodeStatus(id) {
    return request(`/adm/periode/${id}/toggle-aktif`, { method: 'POST' })
  },

  async getSkimList() {
    return masterDataApi.getSkimList()
  },

  async getPlottingList(filters = {}) {
    const { status, fakultas, search, kdperiode } = filters
    return request(`/adm/plotting${toQueryString({ status, fakultas, search, kdperiode })}`)
  },

  async assignReviewers(proposalId, { reviewer1Id, reviewer2Id }) {
    return request(`/adm/plotting/${proposalId}/reviewer`, {
      method: 'POST',
      body: JSON.stringify({ reviewer1Id, reviewer2Id }),
    })
  },

  async finalizePlotting(proposalId) {
    return request(`/adm/plotting/${proposalId}/finalize`, { method: 'POST' })
  },

  async tambahReviewerKe3(proposalId, { reviewerBaruId }) {
    return request(`/adm/plotting/${proposalId}/reviewer/tambah`, {
      method: 'POST',
      body: JSON.stringify({ reviewerBaruId }),
    })
  },

  async assignRevisiVerifikator(proposalId, { reviewerId }) {
    return request(`/adm/plotting/${proposalId}/revisi-verifikator`, {
      method: 'POST',
      body: JSON.stringify({ reviewerId }),
    })
  },

  async getFinalApprovalList(kdperiode) {
    return request(`/adm/final-approval${toQueryString({ kdperiode })}`)
  },

  async submitFinalDecision(proposalId, { status, biayaDisetujui }) {
    return request(`/adm/final-approval/${proposalId}`, {
      method: 'POST',
      body: JSON.stringify({ status, biayaDisetujui }),
    })
  },

  // Surat Tugas / STPP / SPD (padanan adm/myphp/finalapproval.php legacy)
  async generateSurat({ ids, nomor, tanggal, abaikanDuplikasi = false }) {
    return request('/adm/surat/generate', {
      method: 'POST',
      body: JSON.stringify({ ids, nomor, tanggal, abaikanDuplikasi }),
    })
  },

  async finalSurat(ids) {
    return request('/adm/surat/final', { method: 'POST', body: JSON.stringify({ ids }) })
  },

  async unduhSurat(id, jenis) {
    return unduhBerkas(`/adm/final-approval/${id}/surat/${jenis}`, `${jenis}_${id}.docx`)
  },

  // Status ketuntasan (padanan adm/myphp/hasilpenelitian.php legacy)
  async getKetuntasanList(kdperiode) {
    return request(`/adm/ketuntasan${toQueryString({ kdperiode })}`)
  },

  async updateKetuntasan(id, status) {
    return request(`/adm/ketuntasan/${id}`, { method: 'PUT', body: JSON.stringify({ status }) })
  },

  async getSintaExportData() {
    return request('/adm/export/sinta')
  },

  async getUserRbacList() {
    return request('/adm/users')
  },

  async updateUserRole(userId, { allowedRoles, primaryRole, permissions }) {
    return request(`/adm/users/${userId}/roles`, {
      method: 'PUT',
      body: JSON.stringify({ allowedRoles, primaryRole, permissions }),
    })
  },

  async getRoleList() {
    return request('/adm/roles')
  },

  async createRole(name) {
    return request('/adm/roles', {
      method: 'POST',
      body: JSON.stringify({ name }),
    })
  },

  async updateRolePermissions(role, { permissions }) {
    return request(`/adm/roles/${role}/permissions`, {
      method: 'PUT',
      body: JSON.stringify({ permissions }),
    })
  },

  async deleteRole(role) {
    return request(`/adm/roles/${role}`, { method: 'DELETE' })
  },

  async getPermissionList() {
    return request('/adm/permissions')
  },

  async updatePermissionLabel(permissionId, { label, description }) {
    return request(`/adm/permissions/${permissionId}`, {
      method: 'PUT',
      body: JSON.stringify({ label, description }),
    })
  },

  // --- Belum ada di backend (rencana Fase 4) - tetap mock ---

  async getWhatsappGatewayStatus() {
    await simulateDelay(100)
    return {
      success: true,
      data: {
        isConnected: true,
        deviceNumber: '+62 812-3456-7890',
        deviceName: 'LPPM-UKWMS-Official-Gateway',
        batteryLevel: 98,
        queueCount: 0,
        lastBroadcast: '2026-03-10 09:00:00',
      },
    }
  },

  async sendWhatsappBroadcast({ recipientGroup }) {
    await simulateDelay(300)
    return {
      success: true,
      message: `Pesan broadcast berhasil dijadwalkan ke target grup (${recipientGroup}) [MOCK - gateway belum diintegrasikan]`,
    }
  },
}
