import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: {
    getMonevHasilList: vi.fn(),
    getMonevHasilDetail: vi.fn(),
    saveMonevJawaban: vi.fn(),
    saveMonevKesimpulan: vi.fn(),
  },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import MonevHasilPage from '../../../src/features/pen/MonevHasilPage'

const ITEM = { id: 9, judul: 'Penelitian Dimonev', tahun: 2026, skim: 'Penelitian Dasar', ketua: 'Dr. Ketua', isFinal: false }
const BORANG = {
  id: 9,
  judul: 'Penelitian Dimonev',
  soal: [{ nomor: 1, aspek: 'Konsisten dengan PEKA', pilihan: [{ kode: 'A', label: 'Tidak' }, { kode: 'B', label: 'Ya' }], jawaban: null }],
  kesimpulan: null,
  isFinal: false,
}

function renderPage() {
  return render(
    <MemoryRouter>
      <MonevHasilPage />
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  penelitiApi.getMonevHasilList.mockResolvedValue({ data: [ITEM] })
  penelitiApi.getMonevHasilDetail.mockResolvedValue({ data: BORANG })
})

describe('MonevHasilPage', () => {
  it('saves an answer as soon as it is chosen', async () => {
    penelitiApi.saveMonevJawaban.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Isi Borang Monev/i }))
    await user.selectOptions(await screen.findByLabelText(/Konsisten dengan PEKA/), 'B')

    expect(penelitiApi.saveMonevJawaban).toHaveBeenCalledWith(9, { nomor: 1, jawaban: 'B' })
  })

  it('shows the backend message when final is refused', async () => {
    penelitiApi.saveMonevKesimpulan.mockResolvedValue({
      data: { isFinal: false },
      message: 'Kesimpulan disimpan, tetapi belum final karena masih ada jawaban kosong',
    })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Isi Borang Monev/i }))
    await user.type(await screen.findByLabelText('Kesimpulan'), 'Sesuai target')
    await user.click(screen.getByRole('checkbox'))
    await user.click(screen.getByRole('button', { name: 'Simpan' }))

    expect(penelitiApi.saveMonevKesimpulan).toHaveBeenCalledWith(9, { kesimpulan: 'Sesuai target', isFinal: true })
    expect(await screen.findByText(/belum final karena masih ada jawaban kosong/)).toBeInTheDocument()
  })
})
