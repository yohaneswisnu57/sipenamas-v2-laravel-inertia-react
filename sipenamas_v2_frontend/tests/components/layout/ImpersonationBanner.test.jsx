import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/authApi', () => ({
  authApi: {
    logout: vi.fn(),
  },
}))

const mockNavigate = vi.fn()
vi.mock('react-router-dom', async (importOriginal) => ({
  ...(await importOriginal()),
  useNavigate: () => mockNavigate,
}))

import { useAuthStore } from '../../../src/store/authStore'
import { ImpersonationBanner } from '../../../src/components/layout/ImpersonationBanner'

beforeEach(() => {
  vi.clearAllMocks()
  localStorage.clear()
  useAuthStore.setState({
    user: { kodeperson: 'DSN02', name: 'Dr. Sari', activeRole: 'REV' },
    token: 'imp-token',
    isImpersonating: true,
    originalSession: { token: 'admin-token', user: { kodeperson: 'SA01', role: 'ADM' } },
  })
})

describe('ImpersonationBanner', () => {
  it('returns the admin to Manajemen Pengguna after leaving login as', async () => {
    const user = userEvent.setup()
    render(
      <MemoryRouter>
        <ImpersonationBanner />
      </MemoryRouter>
    )

    await user.click(screen.getByRole('button', { name: 'Kembali ke akun Anda' }))

    expect(useAuthStore.getState().user.kodeperson).toBe('SA01')
    expect(mockNavigate).toHaveBeenCalledWith('/adm/users')
  })
})
