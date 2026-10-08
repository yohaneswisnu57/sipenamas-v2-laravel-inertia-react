import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

vi.mock('../../../src/services/api/adminApi', () => ({
  adminApi: {
    getPeriodeList: vi.fn(),
    createPeriode: vi.fn(),
    togglePeriodeStatus: vi.fn(),
  },
}))

import { adminApi } from '../../../src/services/api/adminApi'
import MasterPeriodePage from '../../../src/features/adm/MasterPeriodePage'

const PERIODE = {
  id: 1,
  kodeperiode: '2026-G1',
  nama: 'Tahun Anggaran 2026 (Gelombang I)',
  tahun: 2026,
  isaktif: 1,
  totalPagu: 500000000,
}

beforeEach(() => {
  vi.clearAllMocks()
  adminApi.getPeriodeList.mockResolvedValue({ data: [PERIODE] })
})

describe('MasterPeriodePage', () => {
  it('shows an error instead of failing silently when toggling status fails', async () => {
    adminApi.togglePeriodeStatus.mockRejectedValue(new Error('Periode ini masih punya usulan berjalan.'))
    const user = userEvent.setup()
    render(<MasterPeriodePage />)

    // Tombol muncul di tabel desktop dan kartu mobile (lg:hidden); jsdom tidak menerapkan CSS.
    await user.click((await screen.findAllByRole('button', { name: /Tutup Periode/i }))[0])

    expect(await screen.findByText('Periode ini masih punya usulan berjalan.')).toBeInTheDocument()
    expect(adminApi.getPeriodeList).toHaveBeenCalledTimes(1)
  })

  it('reloads the list after successfully toggling status', async () => {
    adminApi.togglePeriodeStatus.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    render(<MasterPeriodePage />)

    // Tombol muncul di tabel desktop dan kartu mobile (lg:hidden); jsdom tidak menerapkan CSS.
    await user.click((await screen.findAllByRole('button', { name: /Tutup Periode/i }))[0])

    expect(adminApi.togglePeriodeStatus).toHaveBeenCalledWith(1)
    expect(adminApi.getPeriodeList).toHaveBeenCalledTimes(2)
  })

  it('shows the server error inside the create-periode modal and keeps it open', async () => {
    adminApi.createPeriode.mockRejectedValue(new Error('Tanggal buka harus sebelum tanggal tutup.'))
    const user = userEvent.setup()
    render(<MasterPeriodePage />)

    await user.click(await screen.findByRole('button', { name: /Buka Periode Baru/i }))
    await user.click(screen.getByRole('button', { name: /Simpan & Buka Periode/i }))

    expect(await screen.findByText('Tanggal buka harus sebelum tanggal tutup.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Simpan & Buka Periode/i })).toBeInTheDocument()
  })

  it('clears the create-periode error when the modal is closed and reopened', async () => {
    adminApi.createPeriode.mockRejectedValue(new Error('Tanggal buka harus sebelum tanggal tutup.'))
    const user = userEvent.setup()
    render(<MasterPeriodePage />)

    await user.click(await screen.findByRole('button', { name: /Buka Periode Baru/i }))
    await user.click(screen.getByRole('button', { name: /Simpan & Buka Periode/i }))
    await screen.findByText('Tanggal buka harus sebelum tanggal tutup.')

    await user.click(screen.getByRole('button', { name: 'Batal' }))
    await user.click(screen.getByRole('button', { name: /Buka Periode Baru/i }))

    expect(screen.queryByText('Tanggal buka harus sebelum tanggal tutup.')).not.toBeInTheDocument()
  })
})
