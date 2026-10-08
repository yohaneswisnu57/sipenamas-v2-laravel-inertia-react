import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/adminApi', () => ({
  adminApi: {
    getUserRbacList: vi.fn(),
    updateUserRole: vi.fn(),
  },
}))

vi.mock('../../../src/services/api/authApi', () => ({
  authApi: {
    impersonate: vi.fn(),
  },
}))

const mockNavigate = vi.fn()
vi.mock('react-router-dom', async (importOriginal) => ({
  ...(await importOriginal()),
  useNavigate: () => mockNavigate,
}))

import { adminApi } from '../../../src/services/api/adminApi'
import { authApi } from '../../../src/services/api/authApi'
import { useAuthStore } from '../../../src/store/authStore'
import ManajemenUserPage from '../../../src/features/adm/ManajemenUserPage'

const USER = {
  id: 1,
  kodeperson: 'PEN01',
  name: 'Dr. Budi',
  role: 'PEN',
  allowedRoles: ['PEN'],
}

function renderPage() {
  return render(
    <MemoryRouter>
      <ManajemenUserPage />
    </MemoryRouter>
  )
}

const MULTI_ROLE_USER = {
  id: 2,
  kodeperson: 'DSN02',
  name: 'Dr. Sari',
  role: 'PEN',
  allowedRoles: ['PEN', 'REV'],
}

beforeEach(() => {
  vi.clearAllMocks()
  localStorage.clear()
  adminApi.getUserRbacList.mockResolvedValue({ data: [USER] })
  useAuthStore.setState({
    user: { kodeperson: 'SA01', isSuperAdmin: true, role: 'ADM' },
    token: 'admin-token',
    isImpersonating: false,
    originalSession: null,
  })
})

describe('ManajemenUserPage', () => {
  it('shows the server error inside the modal instead of failing silently', async () => {
    adminApi.updateUserRole.mockRejectedValue(new Error('Tidak bisa menghapus peran terakhir pengguna ini.'))
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Atur Peran/i }))
    await user.click(screen.getByRole('button', { name: /Simpan Hak Akses/i }))

    expect(await screen.findByText('Tidak bisa menghapus peran terakhir pengguna ini.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Simpan Hak Akses/i })).toBeInTheDocument()
  })

  it('clears the error when the modal is closed and reopened', async () => {
    adminApi.updateUserRole.mockRejectedValue(new Error('Tidak bisa menghapus peran terakhir pengguna ini.'))
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Atur Peran/i }))
    await user.click(screen.getByRole('button', { name: /Simpan Hak Akses/i }))
    await screen.findByText('Tidak bisa menghapus peran terakhir pengguna ini.')

    await user.click(screen.getByRole('button', { name: 'Batal' }))
    await user.click(await screen.findByRole('button', { name: /Atur Peran/i }))

    expect(screen.queryByText('Tidak bisa menghapus peran terakhir pengguna ini.')).not.toBeInTheDocument()
  })

  it('shows the success notice and reloads the list after saving', async () => {
    adminApi.updateUserRole.mockResolvedValue({ data: { warnings: [] } })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Atur Peran/i }))
    await user.click(screen.getByRole('button', { name: /Simpan Hak Akses/i }))

    expect(await screen.findByText(/berhasil diperbarui/i)).toBeInTheDocument()
    expect(adminApi.getUserRbacList).toHaveBeenCalledTimes(2)
  })

  it('logs in as the role picked in the login-as modal', async () => {
    adminApi.getUserRbacList.mockResolvedValue({ data: [MULTI_ROLE_USER] })
    authApi.impersonate.mockResolvedValue({ data: { token: 'imp-token', user: { kodeperson: 'DSN02', role: 'REV' } } })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Login as/i }))
    expect(screen.getByRole('radio', { name: /Peneliti/i })).toBeChecked()

    await user.click(screen.getByRole('radio', { name: /Reviewer/i }))
    await user.click(screen.getByRole('button', { name: 'Masuk' }))

    expect(authApi.impersonate).toHaveBeenCalledWith('DSN02', 'REV')
    expect(mockNavigate).toHaveBeenCalledWith('/rev/dashboard')
  })

  it('keeps the login-as modal open and shows the server error on failure', async () => {
    authApi.impersonate.mockRejectedValue(new Error('User tidak ditemukan atau tidak memiliki peran tersebut.'))
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Login as/i }))
    await user.click(screen.getByRole('button', { name: 'Masuk' }))

    expect(await screen.findByText('User tidak ditemukan atau tidak memiliki peran tersebut.')).toBeInTheDocument()
    expect(mockNavigate).not.toHaveBeenCalled()
  })
})
