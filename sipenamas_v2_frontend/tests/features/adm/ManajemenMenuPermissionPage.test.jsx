import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

vi.mock('../../../src/services/api/adminApi', () => ({
  adminApi: {
    getPermissionList: vi.fn(),
    updatePermissionLabel: vi.fn(),
  },
}))

import { adminApi } from '../../../src/services/api/adminApi'
import ManajemenMenuPermissionPage from '../../../src/features/adm/ManajemenMenuPermissionPage'

const RESOURCES = [
  {
    resource: 'penelitian',
    actions: [
      { id: 1, action: 'view', permission: 'view penelitian', label: 'Lihat Penelitian', description: '' },
    ],
  },
]

beforeEach(() => {
  vi.clearAllMocks()
  adminApi.getPermissionList.mockResolvedValue({ data: RESOURCES })
})

describe('ManajemenMenuPermissionPage', () => {
  it('shows an error instead of failing silently when saving fails', async () => {
    adminApi.updatePermissionLabel.mockRejectedValue(new Error('Label tidak boleh kosong.'))
    const user = userEvent.setup()
    render(<ManajemenMenuPermissionPage />)

    const input = await screen.findByDisplayValue('Lihat Penelitian')
    await user.clear(input)
    await user.type(input, 'Lihat Riset')
    await user.click(screen.getByRole('button', { name: 'Simpan' }))

    expect(await screen.findByText('Label tidak boleh kosong.')).toBeInTheDocument()
  })

  it('shows a success notice after saving', async () => {
    adminApi.updatePermissionLabel.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    render(<ManajemenMenuPermissionPage />)

    const input = await screen.findByDisplayValue('Lihat Penelitian')
    await user.clear(input)
    await user.type(input, 'Lihat Riset')
    await user.click(screen.getByRole('button', { name: 'Simpan' }))

    expect(await screen.findByText(/berhasil disimpan/i)).toBeInTheDocument()
  })
})
