import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Routes, Route } from 'react-router-dom'

const mockNavigate = vi.fn()

vi.mock('react-router-dom', async (importOriginal) => ({
  ...(await importOriginal()),
  useNavigate: () => mockNavigate,
}))

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: {
    getPenelitianDetail: vi.fn(),
    createPenelitian: vi.fn(),
    updatePenelitian: vi.fn(),
    deletePenelitian: vi.fn(),
    unduhPengesahan: vi.fn(),
  },
}))

vi.mock('../../../src/services/api/masterDataApi', () => ({
  masterDataApi: { getSkimList: vi.fn(), getFakultasList: vi.fn().mockResolvedValue({ data: [] }), getPeriodeAktif: vi.fn().mockResolvedValue({ data: null }), getSumberDanaList: vi.fn().mockResolvedValue({ data: [{ kode: 'INTERNAL', nama: 'Dana Internal' }] }) },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import { masterDataApi } from '../../../src/services/api/masterDataApi'
import FormUsulanPenelitianPage from '../../../src/features/pen/FormUsulanPenelitianPage'
import DetailPenelitianPage from '../../../src/features/pen/DetailPenelitianPage'

const DRAFT = {
  id: '9',
  kodeUsulan: 'PR-2026-FT-009',
  judul: 'Judul Draft',
  status: 'DRAFT',
  skimKode: 'PDP',
  skimNama: 'Penelitian Dosen Pemula',
  biayaUsulan: 5000000,
  komposisiBahanPeralatan: 50,
  komposisiPerjalanan: 15,
  komposisiLaporan: 5,
  isKetua: true,
  isPengajuanFinal: false,
  catatanDekan: null,
  anggotaDosen: [],
  anggotaMahasiswa: [],
  mitra: [],
  rabItems: [],
  pengesahan: { ketua: { nik: 'P1', nama: 'Ketua', qr: null }, dekan: null },
}

beforeEach(() => {
  vi.clearAllMocks()
  masterDataApi.getSkimList.mockResolvedValue({ data: [{ kode: 'PDP', nama: 'Penelitian Dosen Pemula' }] })
  penelitiApi.getPenelitianDetail.mockResolvedValue({ data: DRAFT })
})

describe('draft usulan', () => {
  it('edit mode loads the draft and saves through updatePenelitian', async () => {
    penelitiApi.updatePenelitian.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    render(
      <MemoryRouter initialEntries={['/pen/penelitian/9/edit']}>
        <Routes>
          <Route path="/pen/penelitian/:id/edit" element={<FormUsulanPenelitianPage />} />
        </Routes>
      </MemoryRouter>
    )

    expect(await screen.findByDisplayValue('Judul Draft')).toBeInTheDocument()
    for (let i = 0; i < 4; i++) {
      await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    }
    await user.click(screen.getByRole('button', { name: /Kirim Usulan/i }))

    expect(penelitiApi.updatePenelitian).toHaveBeenCalledWith('9', expect.objectContaining({ judul: 'Judul Draft', ajukan: true }))
    expect(penelitiApi.createPenelitian).not.toHaveBeenCalled()
  })

  it('ketua deletes the usulan from the detail page after confirming', async () => {
    penelitiApi.deletePenelitian.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    render(
      <MemoryRouter initialEntries={['/pen/penelitian/9']}>
        <Routes>
          <Route path="/pen/penelitian/:id" element={<DetailPenelitianPage />} />
        </Routes>
      </MemoryRouter>
    )

    await user.click(await screen.findByRole('button', { name: /Hapus Usulan/i }))
    await user.click(screen.getByRole('button', { name: /Ya, Hapus/i }))

    expect(penelitiApi.deletePenelitian).toHaveBeenCalledWith('9')
    expect(mockNavigate).toHaveBeenCalledWith('/pen/penelitian')
  })
})
