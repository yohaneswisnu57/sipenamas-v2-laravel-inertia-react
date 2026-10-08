import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/dekanApi', () => ({
  dekanApi: {
    getDashboardStats: vi.fn(),
    getPengajuanList: vi.fn(),
    approveProposal: vi.fn(),
    rejectProposal: vi.fn(),
    ambilDokumenProposal: vi.fn(),
  },
}))

vi.mock('../../../src/services/api/masterDataApi', () => ({
  masterDataApi: { getPeriodeList: vi.fn().mockResolvedValue({ data: [] }) },
}))

import { dekanApi } from '../../../src/services/api/dekanApi'
import DekanDashboardPage from '../../../src/features/dkn/DekanDashboardPage'

function renderPage() {
  return render(
    <MemoryRouter>
      <DekanDashboardPage />
    </MemoryRouter>
  )
}

const PROPOSAL = {
  id: '1',
  kodeUsulan: 'PR-2026-FT-001',
  judul: 'Usulan Uji Approval',
  ketuaNama: 'Dr. Budi',
  prodiNama: 'Informatika',
  biayaUsulan: 10000000,
  skimKode: 'PDP',
  status: 'SUBMITTED',
  isDokumenProposalFinal: true,
}

beforeEach(() => {
  dekanApi.getDashboardStats.mockReset().mockResolvedValue({
    data: {
      totalUsulan: 1, menungguPersetujuan: 1, risetAktif: 0, totalDanaDisetujui: 0,
      paguFakultas: 450000000, serapanPaguPersen: 0,
    },
  })
  dekanApi.getPengajuanList.mockReset().mockResolvedValue({ data: [PROPOSAL] })
  dekanApi.approveProposal.mockReset().mockResolvedValue({ success: true })
  dekanApi.rejectProposal.mockReset().mockResolvedValue({ success: true })
  dekanApi.ambilDokumenProposal.mockReset().mockResolvedValue('blob:proposal-1')
})

describe('DekanDashboardPage approve/reject catatan', () => {
  it('disables both approve and reject buttons until catatan is filled', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Setujui \/ Telaah/i }))

    expect(screen.getByRole('button', { name: /Setujui Usulan/i })).toBeDisabled()
    expect(screen.getByRole('button', { name: /Tolak Usulan/i })).toBeDisabled()
  })

  it('enables approve once catatan is filled and sends it to approveProposal', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Setujui \/ Telaah/i }))
    await screen.findByTitle('Naskah proposal')
    await user.type(screen.getByPlaceholderText(/Rencana Induk Penelitian/i), 'Sesuai RIP Fakultas')

    const approveButton = screen.getByRole('button', { name: /Setujui Usulan/i })
    expect(approveButton).not.toBeDisabled()

    await user.click(approveButton)

    expect(dekanApi.approveProposal).toHaveBeenCalledWith('1', { catatan: 'Sesuai RIP Fakultas' })
  })

  it('sends catatan to rejectProposal when rejecting', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Setujui \/ Telaah/i }))
    await screen.findByTitle('Naskah proposal')
    await user.type(screen.getByPlaceholderText(/Rencana Induk Penelitian/i), 'Belum sesuai RIP')

    await user.click(screen.getByRole('button', { name: /Tolak Usulan/i }))

    expect(dekanApi.rejectProposal).toHaveBeenCalledWith('1', { catatan: 'Belum sesuai RIP' })
  })

  it('shows the proposal PDF with a download link before the dekan decides', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Setujui \/ Telaah/i }))

    expect(dekanApi.ambilDokumenProposal).toHaveBeenCalledWith('1')
    expect(await screen.findByTitle('Naskah proposal')).toHaveAttribute('src', 'blob:proposal-1')
    expect(screen.getByRole('link', { name: /Unduh Proposal/i })).toHaveAttribute('href', 'blob:proposal-1')
  })

  it('keeps the decision locked when the proposal cannot be loaded', async () => {
    dekanApi.ambilDokumenProposal.mockRejectedValue(new Error('Berkas proposal belum diunggah'))
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Setujui \/ Telaah/i }))
    expect(await screen.findByText('Berkas proposal belum diunggah')).toBeInTheDocument()
    await user.type(screen.getByPlaceholderText(/Rencana Induk Penelitian/i), 'Catatan')

    expect(screen.getByRole('button', { name: /Setujui Usulan/i })).toBeDisabled()
  })

  it('shows the server message when the decision is already locked', async () => {
    dekanApi.approveProposal.mockRejectedValue(new Error('Usulan ini sudah disetujui Dekan dan keputusannya tidak dapat diubah.'))
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Setujui \/ Telaah/i }))
    await screen.findByTitle('Naskah proposal')
    await user.type(screen.getByPlaceholderText(/Rencana Induk Penelitian/i), 'Sesuai')
    await user.click(screen.getByRole('button', { name: /Setujui Usulan/i }))

    expect(await screen.findByText(/sudah disetujui Dekan/)).toBeInTheDocument()
  })
})
