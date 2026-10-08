import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: {
    getLaporanAkhirList: vi.fn(),
    getKuesionerPenelitian: vi.fn(),
    saveKuesionerJawaban: vi.fn(),
    getKelengkapanLaporan: vi.fn(),
    getCapaianLuaran: vi.fn(),
    saveCapaianLuaran: vi.fn(),
  },
}))
vi.mock('../../../src/services/api/masterDataApi', () => ({
  masterDataApi: { getPeriodeList: vi.fn(), searchMahasiswa: vi.fn() },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import { masterDataApi } from '../../../src/services/api/masterDataApi'
import LaporanAkhirPage from '../../../src/features/pen/LaporanAkhirPage'

const ITEM = { id: 5, judul: 'Penelitian Lolos', tahun: 2026, skim: 'Penelitian Dasar', peran: 'KETUA', isLembarPengesahanFinal: false, isDisetujuiDekan: false, statusKetuntasan: '-' }

function renderPage() {
  return render(
    <MemoryRouter>
      <LaporanAkhirPage />
    </MemoryRouter>
  )
}

function daftar(overrides = {}, item = {}) {
  return { data: { kdperiode: '2026', isKuesionerSelesai: false, items: [{ ...ITEM, ...item }], ...overrides } }
}

beforeEach(() => {
  vi.clearAllMocks()
  masterDataApi.getPeriodeList.mockResolvedValue({ data: [{ kodeperiode: '2026', tahun: '2026' }] })
})

describe('LaporanAkhirPage', () => {
  it('blocks kelengkapan until the kuesioner is complete', async () => {
    penelitiApi.getLaporanAkhirList.mockResolvedValue(daftar())
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Kelengkapan Laporan/i }))

    expect(await screen.findByText(/melengkapi kuesioner terlebih dulu/)).toBeInTheDocument()
    expect(penelitiApi.getKelengkapanLaporan).not.toHaveBeenCalled()
  })

  it('blocks capaian until the lembar pengesahan is final', async () => {
    penelitiApi.getLaporanAkhirList.mockResolvedValue(daftar({ isKuesionerSelesai: true }))
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Capaian dan Luaran/i }))

    expect(await screen.findByText(/Laporan belum lengkap\/Final/)).toBeInTheDocument()
    expect(penelitiApi.getCapaianLuaran).not.toHaveBeenCalled()
  })

  it('saves a kuesioner answer as soon as it is chosen', async () => {
    penelitiApi.getLaporanAkhirList.mockResolvedValue(daftar())
    penelitiApi.getKuesionerPenelitian.mockResolvedValue({
      data: {
        kdperiode: '2026',
        isDone: false,
        kelompok: { A: 'LAYANAN ADMINISTRASI', B: null, C: null },
        pilihan: [{ kode: 'A', label: 'Sangat Tidak Puas' }, { kode: 'D', label: 'Sangat Puas' }],
        pertanyaan: [{ id: 31, nomor: 1, kelompok: 'A', uraian: 'Petugas ramah', jawab: null }],
      },
    })
    penelitiApi.saveKuesionerJawaban.mockResolvedValue({ data: { isDone: true } })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Kuesioner' }))
    await user.selectOptions(await screen.findByLabelText(/Petugas ramah/), 'D')

    expect(penelitiApi.saveKuesionerJawaban).toHaveBeenCalledWith(31, 'D')
    expect(await screen.findByText('Lengkap')).toBeInTheDocument()
  })

  it('saves realisasi of a target from the capaian window', async () => {
    penelitiApi.getLaporanAkhirList.mockResolvedValue(daftar({ isKuesionerSelesai: true }, { isLembarPengesahanFinal: true }))
    const capaian = {
      data: {
        id: 5,
        judul: 'Penelitian Lolos',
        isInsentifFinal: false,
        target: [{ id: 70, kategori: 'Unggah laporan penelitian', subkategori: '', indikator: 'Selesai', isWajib: true, isAdaInsentif: false, isRealisasi: false, keterangan: '', statusTayang: null, adaDokumen: true, ekstensi: 'PDF' }],
      },
    }
    penelitiApi.getCapaianLuaran.mockResolvedValue(capaian)
    penelitiApi.saveCapaianLuaran.mockResolvedValue({ ...capaian, message: 'Capaian disimpan' })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Capaian dan Luaran/i }))
    await user.click(await screen.findByRole('checkbox'))
    await user.click(screen.getByRole('button', { name: 'Simpan' }))

    expect(penelitiApi.saveCapaianLuaran).toHaveBeenCalledWith(5, 70, { realisasi: true, keterangan: '', statusTayang: '' })
    expect(await screen.findByText('Capaian disimpan')).toBeInTheDocument()
  })
})
