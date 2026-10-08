import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: {
    getSubsidiApcList: vi.fn(),
    createSubsidiApc: vi.fn(),
  },
}))

vi.mock('../../../src/services/api/masterDataApi', () => ({
  masterDataApi: { getIndexJurnalList: vi.fn() },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import { masterDataApi } from '../../../src/services/api/masterDataApi'
import SubsidiApcPage from '../../../src/features/pen/SubsidiApcPage'

beforeEach(() => {
  vi.clearAllMocks()
  penelitiApi.getSubsidiApcList.mockResolvedValue({ data: [] })
  masterDataApi.getIndexJurnalList.mockResolvedValue({ data: [{ kode: 'SQ1', nama: 'Scopus Q1', adaInsentif: true }] })
})

describe('SubsidiApcPage', () => {
  it('shows the server error inside the modal instead of failing silently', async () => {
    penelitiApi.createSubsidiApc.mockRejectedValue(new Error('Nominal pengajuan melebihi pagu subsidi APC.'))
    const user = userEvent.setup()
    render(<SubsidiApcPage />)

    await user.click(await screen.findByRole('button', { name: 'Ajukan Subsidi Baru' }))
    await user.click(screen.getByRole('button', { name: /Kirim Pengajuan Subsidi/i }))

    expect(await screen.findByText('Nominal pengajuan melebihi pagu subsidi APC.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Kirim Pengajuan Subsidi/i })).toBeInTheDocument()
  })

  it('submits successfully and shows the success notice', async () => {
    penelitiApi.createSubsidiApc.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    render(<SubsidiApcPage />)

    await user.click(await screen.findByRole('button', { name: 'Ajukan Subsidi Baru' }))
    await user.click(screen.getByRole('button', { name: /Kirim Pengajuan Subsidi/i }))

    expect(await screen.findByText(/berhasil diajukan/i)).toBeInTheDocument()
  })

  it('loads APC index options from the master table and starts with an empty nominal', async () => {
    const user = userEvent.setup()
    render(<SubsidiApcPage />)

    await user.click(await screen.findByRole('button', { name: 'Ajukan Subsidi Baru' }))
    const select = screen.getByLabelText(/Terindeks Dalam/i)

    expect(masterDataApi.getIndexJurnalList).toHaveBeenCalledWith({ apc: true })
    expect(await within(select).findByRole('option', { name: 'Scopus Q1' })).toHaveValue('SQ1')
    expect(select).toHaveValue('')
    expect(screen.queryByDisplayValue('25000000')).not.toBeInTheDocument()
  })
})
