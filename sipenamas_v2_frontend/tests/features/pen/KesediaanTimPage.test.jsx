import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: {
    getKesediaanTim: vi.fn(),
    setujuiKesediaanTim: vi.fn(),
  },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import KesediaanTimPage from '../../../src/features/pen/KesediaanTimPage'

const ITEM = { timId: 11, penelitianId: '5', judul: 'Usulan Rekan', skimNama: 'Penelitian Dasar', ketuaNama: 'Dr. Ketua', tugas: null }

function renderPage() {
  return render(
    <MemoryRouter>
      <KesediaanTimPage />
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('KesediaanTimPage', () => {
  it('approves a membership and reloads the list', async () => {
    penelitiApi.getKesediaanTim.mockResolvedValueOnce({ data: [ITEM] }).mockResolvedValueOnce({ data: [] })
    penelitiApi.setujuiKesediaanTim.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Setujui Keanggotaan/i }))

    expect(penelitiApi.setujuiKesediaanTim).toHaveBeenCalledWith(11)
    expect(await screen.findByText(/Tidak ada permintaan persetujuan/)).toBeInTheDocument()
  })

  it('shows the quota message when approval is rejected', async () => {
    penelitiApi.getKesediaanTim.mockResolvedValue({ data: [ITEM] })
    const error = new Error('Data yang dikirim tidak valid.')
    error.errors = { kuota: ['Kuota keterlibatan (5 usulan per periode) sudah terpenuhi.'] }
    penelitiApi.setujuiKesediaanTim.mockRejectedValue(error)
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Setujui Keanggotaan/i }))

    expect(await screen.findByText(/Kuota keterlibatan/)).toBeInTheDocument()
  })
})
