import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/reviewerApi', () => ({
  reviewerApi: { getPenugasanList: vi.fn(), confirmKesediaan: vi.fn() },
}))

import { reviewerApi } from '../../../src/services/api/reviewerApi'
import KesediaanReviewerPage from '../../../src/features/rev/KesediaanReviewerPage'

const TUGAS = { id: '1', kodeUsulan: 'PR-2026-FT-001', judul: 'Usulan Uji', skimNama: 'Penelitian Dasar', ketuaNama: 'Dr. Ketua', biayaUsulan: 5000000 }

const renderPage = () =>
  render(
    <MemoryRouter>
      <KesediaanReviewerPage />
    </MemoryRouter>
  )

beforeEach(() => vi.clearAllMocks())

describe('KesediaanReviewerPage', () => {
  it('replaces the buttons with the status once the reviewer has confirmed', async () => {
    reviewerApi.getPenugasanList
      .mockResolvedValueOnce({ data: [{ ...TUGAS, kesediaanSaya: 'MENUNGGU' }] })
      .mockResolvedValueOnce({ data: [{ ...TUGAS, kesediaanSaya: 'BERSEDIA', tglKesediaanSaya: '2026-10-01 03:38:10' }] })
    reviewerApi.confirmKesediaan.mockResolvedValue({})
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Bersedia Menilai/ }))

    expect(reviewerApi.confirmKesediaan).toHaveBeenCalledWith('1', { bersedia: true })
    expect(await screen.findByText('Bersedia menilai')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Bersedia Menilai/ })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Tolak Tugas/ })).not.toBeInTheDocument()
  })

  it('shows a declined assignment without buttons', async () => {
    reviewerApi.getPenugasanList.mockResolvedValue({ data: [{ ...TUGAS, kesediaanSaya: 'MENOLAK' }] })
    renderPage()

    expect(await screen.findByText('Menolak tugas')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Tolak Tugas/ })).not.toBeInTheDocument()
  })
})
