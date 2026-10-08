import { request, toQueryString } from './apiClient'

/**
 * Terhubung ke backend Laravel sungguhan - lihat
 * app/Http/Controllers/Api/V1/Akreditasi/ReportController.php di
 * sipenamas_v2_backend. Akreditasi murni role read-only/pelaporan, tidak
 * ada endpoint tulis.
 *
 * generateLkpsExcel TIDAK generate file .xlsx sungguhan (belum ada package
 * Excel terinstall di backend) - sebagai gantinya mengambil data asli lalu
 * generate file CSV di browser (bisa dibuka Excel juga, tanpa dependency
 * baru). Kalau butuh format .xlsx asli, itu perlu keputusan pilih package
 * terpisah, bukan bagian dari perubahan ini.
 */
export const akreditasiApi = {
  async getAkreditasiStats() {
    return request('/akr/dashboard')
  },

  async getDataMiningBorang(filters = {}) {
    const { prodi, tahun, denganMahasiswa } = filters
    return request(`/akr/data-mining${toQueryString({ prodi, tahun, denganMahasiswa })}`)
  },

  async getRekapLuaran() {
    return request('/akr/rekap-luaran')
  },

  async generateLkpsExcel({ table } = {}) {
    const source = TABLE_SOURCES[table] ?? TABLE_SOURCES.default
    const rows = await source()

    downloadCsv(table.replace(/\.xlsx$/, '.csv'), rows)

    return {
      success: true,
      message: `${table.replace(/\.xlsx$/, '.csv')} berhasil diunduh`,
    }
  },
}

const TABLE_SOURCES = {
  'Tabel_3b1_Penelitian_DTPS.xlsx': async () => (await request('/akr/data-mining')).data,
  'Tabel_3b2_Riset_Mahasiswa.xlsx': async () =>
    (await request(`/akr/data-mining${toQueryString({ denganMahasiswa: true })}`)).data,
  'Tabel_3b4_Publikasi_Ilmiah.xlsx': async () => (await request('/akr/rekap-luaran')).data.jurnal,
  default: async () => (await request('/akr/data-mining')).data,
}

function downloadCsv(filename, rows) {
  if (!rows || rows.length === 0) {
    return
  }

  const headers = Object.keys(rows[0])
  const escapeCell = (value) => `"${String(value ?? '').replace(/"/g, '""')}"`
  const lines = [
    headers.join(','),
    ...rows.map((row) => headers.map((h) => escapeCell(row[h])).join(',')),
  ]

  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  link.click()
  URL.revokeObjectURL(url)
}
