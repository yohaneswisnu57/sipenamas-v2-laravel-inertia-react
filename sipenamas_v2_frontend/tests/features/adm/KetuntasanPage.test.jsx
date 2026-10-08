import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

vi.mock('../../../src/services/api/adminApi', () => ({
  adminApi: { getKetuntasanList: vi.fn(), updateKetuntasan: vi.fn() },
}))
vi.mock('../../../src/services/api/masterDataApi', () => ({
  masterDataApi: { getPeriodeList: vi.fn() },
}))

import { adminApi } from '../../../src/services/api/adminApi'
import { masterDataApi } from '../../../src/services/api/masterDataApi'
import KetuntasanPage from '../../../src/features/adm/KetuntasanPage'

const ITEM = { id: 8, judul: 'Penelitian Lolos', skim: 'Penelitian Dasar', ketua: 'Dr. Ketua', prodi: 'Informatika', statusFinalApproval: 'LOLOS', isDisetujuiDekan: true, jumlahTarget: 2, jumlahRealisasi: 2, statusKetuntasan: '-' }

beforeEach(() => {
  vi.clearAllMocks()
  masterDataApi.getPeriodeList.mockResolvedValue({ data: [{ kodeperiode: '2026', tahun: '2026' }] })
  adminApi.getKetuntasanList.mockResolvedValue({ data: { kdperiode: '2026', items: [ITEM] } })
})

describe('KetuntasanPage', () => {
  it('updates the status ketuntasan of a penelitian', async () => {
    adminApi.updateKetuntasan.mockResolvedValue({ message: 'Ok, Status sudah diUpdate.' })
    const user = userEvent.setup()
    render(<KetuntasanPage />)

    const select = await screen.findByLabelText('Status ketuntasan Penelitian Lolos')
    expect(screen.getByRole('button', { name: 'Update Status' })).toBeDisabled()
    await user.selectOptions(select, 'TUNTAS')
    await user.click(screen.getByRole('button', { name: 'Update Status' }))

    expect(adminApi.updateKetuntasan).toHaveBeenCalledWith(8, 'TUNTAS')
    expect(await screen.findByText(/Status sudah diUpdate/)).toBeInTheDocument()
  })
})
