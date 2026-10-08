import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: {
    getHkiList: vi.fn(),
    createHki: vi.fn(),
  },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import HkiPatenPage from '../../../src/features/pen/HkiPatenPage'

beforeEach(() => {
  vi.clearAllMocks()
  penelitiApi.getHkiList.mockResolvedValue({ data: [] })
})

describe('HkiPatenPage', () => {
  it('shows the server error inside the modal instead of failing silently', async () => {
    penelitiApi.createHki.mockRejectedValue(new Error('Judul ciptaan wajib diisi.'))
    const user = userEvent.setup()
    render(<HkiPatenPage />)

    await user.click(await screen.findByRole('button', { name: 'Daftarkan Ciptaan / Paten' }))
    await user.click(screen.getByRole('button', { name: /Kirim Pendaftaran/i }))

    expect(await screen.findByText('Judul ciptaan wajib diisi.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Kirim Pendaftaran/i })).toBeInTheDocument()
  })

  it('submits successfully and shows the success notice', async () => {
    penelitiApi.createHki.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    render(<HkiPatenPage />)

    await user.click(await screen.findByRole('button', { name: 'Daftarkan Ciptaan / Paten' }))
    await user.type(screen.getByPlaceholderText(/Tuliskan judul ciptaan/i), 'Aplikasi Uji')
    await user.click(screen.getByRole('button', { name: /Kirim Pendaftaran/i }))

    expect(await screen.findByText(/berhasil disimpan/i)).toBeInTheDocument()
  })
})
