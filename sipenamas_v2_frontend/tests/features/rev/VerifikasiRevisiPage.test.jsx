import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/reviewerApi', () => ({
  reviewerApi: {
    getRevisiVerifikasiQueue: vi.fn(),
    getKomentarRevisi: vi.fn(),
    verifikasiRevisi: vi.fn(),
    ambilDokumenProposal: vi.fn(),
    ambilDokumenProposalRevisi: vi.fn(),
  },
}))

import { reviewerApi } from '../../../src/services/api/reviewerApi'
import VerifikasiRevisiPage from '../../../src/features/rev/VerifikasiRevisiPage'

function renderPage() {
  return render(
    <MemoryRouter>
      <VerifikasiRevisiPage />
    </MemoryRouter>
  )
}

const PROPOSAL = {
  id: '1',
  kodeUsulan: 'PR-2026-FT-001',
  judul: 'Usulan Menunggu Verifikasi Revisi',
  ketuaNama: 'Dr. Budi',
  prodiNama: 'Informatika',
  biayaUsulan: 10000000,
  skimNama: 'Penelitian Dosen Pemula',
}

beforeEach(() => {
  reviewerApi.getRevisiVerifikasiQueue.mockReset().mockResolvedValue({ data: [PROPOSAL] })
  reviewerApi.verifikasiRevisi.mockReset().mockResolvedValue({ success: true })
  reviewerApi.getKomentarRevisi.mockReset().mockResolvedValue({
    data: [{ id: 1, reviewerKe: 1, komentar: 'Perjelas metodologi', respon: 'Metodologi sudah diperbaiki sesuai catatan' }],
  })
  reviewerApi.ambilDokumenProposal.mockReset().mockResolvedValue('blob:proposal-awal-1')
  reviewerApi.ambilDokumenProposalRevisi.mockReset().mockResolvedValue('blob:proposal-revisi-1')
  window.open = vi.fn()
})

describe('VerifikasiRevisiPage', () => {
  it('renders the proposal awaiting verification with its comment thread', async () => {
    renderPage()

    expect(await screen.findByText('Usulan Menunggu Verifikasi Revisi')).toBeInTheDocument()
    expect(await screen.findByText(/Metodologi sudah diperbaiki/i)).toBeInTheDocument()
    expect(screen.getByText(/Perjelas metodologi/)).toBeInTheDocument()
  })

  it('shows an empty state when there is nothing to verify', async () => {
    reviewerApi.getRevisiVerifikasiQueue.mockResolvedValue({ data: [] })
    renderPage()

    expect(await screen.findByText(/Tidak ada revisi yang menunggu verifikasi/i)).toBeInTheDocument()
  })

  it('submits an approval with catatan', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /^Setujui$/i }))
    await user.type(screen.getByPlaceholderText(/Jelaskan alasan keputusan/i), 'Sudah sesuai')
    const setujuiButtons = screen.getAllByRole('button', { name: /^Setujui$/i })
    await user.click(setujuiButtons[setujuiButtons.length - 1])

    expect(reviewerApi.verifikasiRevisi).toHaveBeenCalledWith('1', { status: 'DISETUJUI', catatan: 'Sudah sesuai' })
  })

  it('requires catatan before the approve button in the modal is enabled', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /^Setujui$/i }))

    const modalButtons = screen.getAllByRole('button', { name: /^Setujui$/i })
    expect(modalButtons[modalButtons.length - 1]).toBeDisabled()
  })

  it('submits a rejection (kembalikan) with catatan', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Kembalikan/i }))
    await user.type(screen.getByPlaceholderText(/Jelaskan alasan keputusan/i), 'Masih ada yang kurang')
    const kembalikanButtons = screen.getAllByRole('button', { name: /Kembalikan/i })
    await user.click(kembalikanButtons[kembalikanButtons.length - 1])

    expect(reviewerApi.verifikasiRevisi).toHaveBeenCalledWith('1', { status: 'DITOLAK', catatan: 'Masih ada yang kurang' })
  })

  it('opens the original proposal in a new tab when "Lihat Naskah Awal" is clicked', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Lihat Naskah Awal/i }))

    expect(reviewerApi.ambilDokumenProposal).toHaveBeenCalledWith('1')
    expect(window.open).toHaveBeenCalledWith('blob:proposal-awal-1', '_blank', 'noopener')
  })

  it('opens the revised proposal in a new tab when "Lihat Naskah Revisi" is clicked', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Lihat Naskah Revisi/i }))

    expect(reviewerApi.ambilDokumenProposalRevisi).toHaveBeenCalledWith('1')
    expect(window.open).toHaveBeenCalledWith('blob:proposal-revisi-1', '_blank', 'noopener')
  })

  it('shows an error when the revised document cannot be opened', async () => {
    reviewerApi.ambilDokumenProposalRevisi.mockRejectedValue(new Error('Berkas Revisi belum diunggah'))
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Lihat Naskah Revisi/i }))

    expect(await screen.findByText('Berkas Revisi belum diunggah')).toBeInTheDocument()
  })
})
