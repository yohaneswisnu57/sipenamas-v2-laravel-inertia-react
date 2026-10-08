import { describe, it, expect, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: { getPenelitianList: vi.fn() },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import DaftarAbdimasPage from '../../../src/features/pen/DaftarAbdimasPage'

describe('DaftarAbdimasPage', () => {
  it('lists ABDIMAS usulan from the API with a link to the detail page', async () => {
    penelitiApi.getPenelitianList.mockResolvedValue({
      data: [
        {
          id: '7',
          kodeUsulan: 'PR-2026-FT-007',
          judul: 'Pendampingan UMKM',
          skimNama: 'Abdimas Internal',
          tempatLokasi: 'Desa Wonorejo',
          biayaDisetujui: null,
          status: 'SUBMITTED',
        },
      ],
    })

    render(
      <MemoryRouter>
        <DaftarAbdimasPage />
      </MemoryRouter>
    )

    expect(await screen.findByText('Pendampingan UMKM')).toBeInTheDocument()
    expect(penelitiApi.getPenelitianList).toHaveBeenCalledWith({ jenis: 'ABDIMAS' })
    expect(screen.getByText('Desa Wonorejo')).toBeInTheDocument()
    expect(screen.getByRole('link', { name: /Detail/ })).toHaveAttribute('href', '/pen/penelitian/7')
    expect(screen.queryByText(/PKM-2026-FT-001/)).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Ajukan Proposal Abdimas/ })).not.toBeInTheDocument()
  })
})
