import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: { getPenelitianList: vi.fn() },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import DaftarPenelitianPage from '../../../src/features/pen/DaftarPenelitianPage'

const rows = [
  { id: '1', judul: 'Usulan Review', status: 'REVIEW', skimNama: 'PD' },
  { id: '2', judul: 'Usulan Monev', status: 'MONEV', skimNama: 'PD' },
  { id: '3', judul: 'Usulan Selesai', status: 'TUNTAS', skimNama: 'PD' },
]

function renderPage(search = '') {
  return render(
    <MemoryRouter initialEntries={[`/pen/penelitian${search}`]}>
      <DaftarPenelitianPage />
    </MemoryRouter>
  )
}

beforeEach(() => {
  penelitiApi.getPenelitianList.mockResolvedValue({ data: rows })
})

describe('DaftarPenelitianPage filter tahap', () => {
  it.each([
    ['PROSES', 'Usulan Review', ['Usulan Monev', 'Usulan Selesai']],
    ['BERJALAN', 'Usulan Monev', ['Usulan Review', 'Usulan Selesai']],
    ['TUNTAS', 'Usulan Selesai', ['Usulan Review', 'Usulan Monev']],
  ])('tahap %s hanya menampilkan baris yang sesuai', async (tahap, tampil, sembunyi) => {
    renderPage(`?tahap=${tahap}`)

    expect(await screen.findByText(tampil)).toBeInTheDocument()
    sembunyi.forEach((judul) => expect(screen.queryByText(judul)).not.toBeInTheDocument())
  })

  it('tanpa filter menampilkan semua usulan', async () => {
    renderPage()

    expect(await screen.findByText('Usulan Review')).toBeInTheDocument()
    expect(screen.getByText('Usulan Monev')).toBeInTheDocument()
    expect(screen.getByText('Usulan Selesai')).toBeInTheDocument()
  })
})
