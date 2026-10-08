import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Routes, Route } from 'react-router-dom'

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: {
    getRevisi: vi.fn(),
    saveResponRevisi: vi.fn(),
    uploadDokumenRevisi: vi.fn(),
    pratinjauDokumenRevisi: vi.fn(),
    finalRevisi: vi.fn(),
  },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import RevisiProposalPage from '../../../src/features/pen/RevisiProposalPage'

const KOMENTAR = [
  { id: 11, reviewerKe: 1, komentar: 'Perjelas metodologi', respon: null },
  { id: 12, reviewerKe: 2, komentar: 'Rasionalisasi anggaran', respon: 'Sudah disesuaikan' },
]
const REVISI = { id: 4, judul: 'Usulan Revisi', komentar: KOMENTAR, alasanTertutup: null, adaVerifikator: true, isFinal: false, adaDokumenRevisi: false, catatanVerifikator: null }

function renderPage() {
  return render(
    <MemoryRouter initialEntries={['/pen/revisi/4']}>
      <Routes>
        <Route path="/pen/revisi/:id" element={<RevisiProposalPage />} />
      </Routes>
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  penelitiApi.getRevisi.mockResolvedValue({ data: REVISI })
})

describe('RevisiProposalPage', () => {
  it('shows each reviewer comment with its reply as a thread', async () => {
    renderPage()

    expect(await screen.findByText('Perjelas metodologi')).toBeInTheDocument()
    expect(screen.getByText('Reviewer 2')).toBeInTheDocument()
    expect(screen.getByText('Sudah disesuaikan')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Balas' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Ubah balasan' })).toBeInTheDocument()
  })

  it('posts a reply under the chosen comment', async () => {
    penelitiApi.saveResponRevisi.mockResolvedValue({ data: [{ ...KOMENTAR[0], respon: 'Bab 3 ditambah' }, KOMENTAR[1]] })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: 'Balas' }))
    await user.type(screen.getByLabelText(/Balasan untuk komentar Reviewer 1/), 'Bab 3 ditambah')
    await user.click(screen.getByRole('button', { name: /Kirim Balasan/i }))

    expect(penelitiApi.saveResponRevisi).toHaveBeenCalledWith('4', 11, 'Bab 3 ditambah')
    expect(await screen.findByText('Bab 3 ditambah')).toBeInTheDocument()
  })

  it('uploads the chosen PDF as a draft without sending it', async () => {
    penelitiApi.uploadDokumenRevisi.mockResolvedValue({ message: 'Draft naskah revisi disimpan' })
    const user = userEvent.setup()
    const { container } = renderPage()
    await screen.findByText('Perjelas metodologi')
    const pdf = new File(['%PDF'], 'revisi.pdf', { type: 'application/pdf' })

    await user.upload(container.querySelector('input[type="file"]'), pdf)
    await user.click(screen.getByRole('button', { name: 'Unggah Draft' }))

    expect(penelitiApi.uploadDokumenRevisi).toHaveBeenCalledWith('4', pdf)
    expect(penelitiApi.finalRevisi).not.toHaveBeenCalled()
    expect(await screen.findByText('Draft naskah revisi disimpan')).toBeInTheDocument()
  })

  it('keeps set final disabled until a draft has been uploaded', async () => {
    renderPage()

    expect(await screen.findByRole('button', { name: /Set Final & Kirim ke Reviewer/i })).toBeDisabled()
  })

  it('shows the draft badge and lets the ketua replace the draft', async () => {
    penelitiApi.getRevisi.mockResolvedValue({ data: { ...REVISI, adaDokumenRevisi: true, tsUploadRevisi: '2026-10-07 10:00:00' } })
    renderPage()

    expect(await screen.findByText('DRAFT')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Ganti Naskah' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Pratinjau' })).toBeInTheDocument()
  })

  it('sets the revisi final only after confirmation', async () => {
    penelitiApi.getRevisi.mockResolvedValue({ data: { ...REVISI, adaDokumenRevisi: true } })
    penelitiApi.finalRevisi.mockResolvedValue({ message: 'Revisi proposal final dan dikirim ke reviewer' })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Set Final & Kirim ke Reviewer/i }))
    expect(penelitiApi.finalRevisi).not.toHaveBeenCalled()
    await user.click(screen.getByRole('button', { name: 'Ya, Set Final' }))

    expect(penelitiApi.finalRevisi).toHaveBeenCalledWith('4')
    expect(await screen.findByText(/Revisi proposal final dan dikirim ke reviewer/)).toBeInTheDocument()
  })

  it('locks replies and sending once the revisi is final or closed', async () => {
    penelitiApi.getRevisi.mockResolvedValue({ data: { ...REVISI, isFinal: true, adaDokumenRevisi: true } })
    renderPage()

    expect(await screen.findByText('FINAL')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Balas' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Ganti Naskah' })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Set Final/i })).not.toBeInTheDocument()
  })
})
