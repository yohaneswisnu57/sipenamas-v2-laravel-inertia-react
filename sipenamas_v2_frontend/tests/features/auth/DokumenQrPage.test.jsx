import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render } from '@testing-library/react'
import { MemoryRouter, Routes, Route } from 'react-router-dom'
import DokumenQrPage from '../../../src/features/auth/DokumenQrPage'
import { API_BASE_URL } from '../../../src/services/api/apiClient'

const lokasiAsli = window.location

beforeEach(() => {
  Object.defineProperty(window, 'location', { configurable: true, value: { replace: vi.fn() } })
})

afterEach(() => {
  Object.defineProperty(window, 'location', { configurable: true, value: lokasiAsli })
})

describe('DokumenQrPage', () => {
  it('opens the surat PDF of the scanned QR code from the backend', () => {
    render(
      <MemoryRouter initialEntries={['/dox/SPD/kode 123']}>
        <Routes>
          <Route path="/dox/:jenis/:kode" element={<DokumenQrPage />} />
        </Routes>
      </MemoryRouter>
    )

    expect(window.location.replace).toHaveBeenCalledWith(`${API_BASE_URL}/dox/SPD/kode%20123`)
  })
})
