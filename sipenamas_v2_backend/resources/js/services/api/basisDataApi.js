import { request, toQueryString } from './apiClient'

/**
 * Padanan generik Api\V1\Admin\MasterDataCrudController - satu service
 * dipakai bersama oleh seluruh 17 submenu "Basis Data", entity dibedakan
 * lewat parameter `slug` (lihat entityConfig.js & config/master_data.php
 * di backend).
 */
export const basisDataApi = {
  async list(slug, params = {}) {
    return request(`/adm/basisdata/${slug}${toQueryString(params)}`)
  },

  async create(slug, payload) {
    return request(`/adm/basisdata/${slug}`, {
      method: 'POST',
      body: JSON.stringify(payload),
    })
  },

  async update(slug, id, payload) {
    return request(`/adm/basisdata/${slug}/${id}`, {
      method: 'PUT',
      body: JSON.stringify(payload),
    })
  },

  async remove(slug, id) {
    return request(`/adm/basisdata/${slug}/${id}`, { method: 'DELETE' })
  },

  async replaceDetails(slug, id, rows) {
    return request(`/adm/basisdata/${slug}/${id}/detail`, {
      method: 'PUT',
      body: JSON.stringify({ rows }),
    })
  },

  async copyScoped(slug, src, dst) {
    return request(`/adm/basisdata/${slug}/copy`, {
      method: 'POST',
      body: JSON.stringify({ src, dst }),
    })
  },
}
