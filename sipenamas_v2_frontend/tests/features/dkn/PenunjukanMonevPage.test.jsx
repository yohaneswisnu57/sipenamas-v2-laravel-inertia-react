import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

vi.mock('../../../src/services/api/dekanApi', () => ({
  dekanApi: {
    getMonevList: vi.fn(),
    getMonevKandidat: vi.fn(),
    assignMonev: vi.fn(),
  },
}))

import { dekanApi } from '../../../src/services/api/dekanApi'
import PenunjukanMonevPage from '../../../src/features/dkn/PenunjukanMonevPage'

const ITEM = { id: 7, judul: 'Penelitian Tuntas', tahun: 2026, skim: 'Penelitian Dasar', reviewerMonev: null, isFinal: false }

beforeEach(() => {
  vi.clearAllMocks()
})

describe('PenunjukanMonevPage', () => {
  it('assigns the chosen GJM candidate as reviewer monev', async () => {
    dekanApi.getMonevList.mockResolvedValue({ data: [ITEM] })
    dekanApi.getMonevKandidat.mockResolvedValue({ data: [{ kodeperson: 'G001', nama: 'Dr. GJM', prodi: 'Informatika' }] })
    dekanApi.assignMonev.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    render(<PenunjukanMonevPage />)

    await user.click(await screen.findByRole('button', { name: /Tunjuk Reviewer/i }))
    await screen.findByRole('option', { name: /Dr. GJM/ })
    await user.selectOptions(screen.getByLabelText('Reviewer Monev'), 'G001')
    await user.click(screen.getByRole('button', { name: 'Simpan' }))

    expect(dekanApi.assignMonev).toHaveBeenCalledWith(7, { kodeperson: 'G001' })
    expect(await screen.findByText(/Data penunjukan sudah disimpan/)).toBeInTheDocument()
  })
})
