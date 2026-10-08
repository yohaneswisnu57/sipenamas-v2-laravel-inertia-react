import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

vi.mock('../../../src/services/api/adminApi', () => ({
  adminApi: {
    getRoleList: vi.fn(),
    createRole: vi.fn(),
    deleteRole: vi.fn(),
    updateRolePermissions: vi.fn(),
  },
}))

import { adminApi } from '../../../src/services/api/adminApi'
import ManajemenRolePage from '../../../src/features/adm/ManajemenRolePage'

const ROLES = [
  {
    role: 'PEN',
    isProtected: false,
    resources: [
      {
        resource: 'penelitian',
        actions: [{ key: 'view', permission: 'view penelitian', label: 'Lihat Penelitian', granted: true }],
      },
    ],
  },
]

beforeEach(() => {
  vi.clearAllMocks()
  adminApi.getRoleList.mockResolvedValue({ data: ROLES })
})

describe('ManajemenRolePage', () => {
  it('shows a red error notice (not the green success style) when saving permissions fails', async () => {
    adminApi.updateRolePermissions.mockRejectedValue(new Error('Peran ini tidak boleh kehilangan akses terakhirnya.'))
    const user = userEvent.setup()
    render(<ManajemenRolePage />)

    await user.click(await screen.findByText('Lihat Penelitian'))
    await user.click(screen.getByRole('button', { name: /Simpan Perubahan/i }))

    const message = await screen.findByText('Peran ini tidak boleh kehilangan akses terakhirnya.')
    expect(message.closest('div')).toHaveClass('text-[#ff5b5b]')
  })

  it('shows a green success notice after saving permissions', async () => {
    adminApi.updateRolePermissions.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    render(<ManajemenRolePage />)

    await user.click(await screen.findByText('Lihat Penelitian'))
    await user.click(screen.getByRole('button', { name: /Simpan Perubahan/i }))

    const message = await screen.findByText(/tersimpan/i)
    expect(message.closest('div')).toHaveClass('text-[#0b7941]')
  })
})
