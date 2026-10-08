import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: {
    getInsentifJurnalList: vi.fn(),
    createInsentifJurnal: vi.fn(),
  },
}))

vi.mock('../../../src/services/api/masterDataApi', () => ({
  masterDataApi: { getIndexJurnalList: vi.fn() },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import { masterDataApi } from '../../../src/services/api/masterDataApi'
import InsentifJurnalPage from '../../../src/features/pen/InsentifJurnalPage'

beforeEach(() => {
  vi.clearAllMocks()
  penelitiApi.getInsentifJurnalList.mockResolvedValue({ data: [] })
  masterDataApi.getIndexJurnalList.mockResolvedValue({
    data: [
      { kode: 'SQ1', nama: 'Scopus Q1', adaInsentif: true },
      { kode: 'S2', nama: 'SINTA 2', adaInsentif: true },
    ],
  })
})

describe('InsentifJurnalPage', () => {
  it('shows the server error inside the modal instead of failing silently', async () => {
    penelitiApi.createInsentifJurnal.mockRejectedValue(new Error('Kuota insentif tahun ini sudah habis.'))
    const user = userEvent.setup()
    render(<InsentifJurnalPage />)

    await user.click(await screen.findByRole('button', { name: 'Klaim Insentif Baru' }))
    await user.click(screen.getByRole('button', { name: /Simpan & Kirim/i }))

    expect(await screen.findByText('Kuota insentif tahun ini sudah habis.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Simpan & Kirim/i })).toBeInTheDocument()
  })

  it('submits successfully and shows the success notice', async () => {
    penelitiApi.createInsentifJurnal.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    render(<InsentifJurnalPage />)

    await user.click(await screen.findByRole('button', { name: 'Klaim Insentif Baru' }))
    await user.click(screen.getByRole('button', { name: /Simpan & Kirim/i }))

    expect(await screen.findByText(/berhasil dikirim/i)).toBeInTheDocument()
  })

  it('offers index options from the master table without reward amounts and sends the index code', async () => {
    penelitiApi.createInsentifJurnal.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    render(<InsentifJurnalPage />)

    await user.click(await screen.findByRole('button', { name: 'Klaim Insentif Baru' }))
    const select = screen.getByLabelText(/Terindeks Dalam/i)
    expect(await within(select).findByRole('option', { name: 'SINTA 2' })).toBeInTheDocument()
    expect(screen.queryByText(/Rp 15\.000\.000/)).not.toBeInTheDocument()
    expect(screen.queryByDisplayValue('Vol. 10, No. 2')).not.toBeInTheDocument()
    expect(screen.getByLabelText(/Tahun Terbit/i)).toHaveValue(new Date().getFullYear())

    await user.selectOptions(select, 'S2')
    await user.click(screen.getByRole('button', { name: /Simpan & Kirim/i }))

    expect(penelitiApi.createInsentifJurnal).toHaveBeenCalledWith(expect.objectContaining({ tingkatJurnal: 'S2' }))
    expect(penelitiApi.createInsentifJurnal.mock.calls[0][0]).not.toHaveProperty('nominalReward')
  })
})
