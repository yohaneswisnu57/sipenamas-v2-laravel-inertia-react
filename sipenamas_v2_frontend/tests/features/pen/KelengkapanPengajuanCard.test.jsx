import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: {
    getRencanaTarget: vi.fn(),
    updateRencanaTarget: vi.fn(),
    uploadDokumenProposal: vi.fn(),
    saveDanaPenyertaanProposal: vi.fn(),
    generateLembarPengesahanProposal: vi.fn(),
    finalLembarPengesahanProposal: vi.fn(),
    finalDokumenProposal: vi.fn(),
    pratinjauDokumenProposal: vi.fn(),
    unduhPengesahan: vi.fn(),
  },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import { KelengkapanPengajuanCard } from '../../../src/features/pen/KelengkapanPengajuanCard'

const PROPOSAL = {
  id: '7',
  isKetua: true,
  isDokumenProposalFinal: false,
  adaDokumenProposal: false,
  danaMitra: 0,
  danaInkind: 0,
  adaLembarPengesahan: true,
  isLembarPengesahanFinal: true,
  anggotaDosen: [{ npp: 'P00001', nama: 'Dr. Anggota', isApproved: true }],
}

beforeEach(() => {
  vi.clearAllMocks()
  penelitiApi.getRencanaTarget.mockResolvedValue({ data: [] })
})

describe('KelengkapanPengajuanCard', () => {
  it('blocks the upload while an anggota has not approved', async () => {
    render(
      <KelengkapanPengajuanCard
        proposal={{ ...PROPOSAL, anggotaDosen: [{ npp: 'P00001', nama: 'Dr. Anggota', isApproved: false }] }}
        onUpdated={vi.fn()}
      />
    )

    expect(await screen.findByText(/Menunggu persetujuan 1 anggota/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Unggah Proposal PDF/i })).not.toBeInTheDocument()
  })

  it('uploads the PDF and hands the updated proposal back', async () => {
    const updated = { ...PROPOSAL, adaDokumenProposal: true }
    penelitiApi.uploadDokumenProposal.mockResolvedValue({ data: updated, message: 'Dokumen proposal berhasil diunggah' })
    const onUpdated = vi.fn()
    const user = userEvent.setup()
    const { container } = render(<KelengkapanPengajuanCard proposal={PROPOSAL} onUpdated={onUpdated} />)

    await screen.findByRole('button', { name: /Unggah Proposal PDF/i })
    const file = new File(['%PDF'], 'proposal.pdf', { type: 'application/pdf' })
    await user.upload(container.querySelector('input[type="file"]'), file)

    expect(penelitiApi.uploadDokumenProposal).toHaveBeenCalledWith('7', file)
    expect(onUpdated).toHaveBeenCalledWith(updated)
  })

  it('blocks the upload until the lembar pengesahan is final', async () => {
    render(<KelengkapanPengajuanCard proposal={{ ...PROPOSAL, isLembarPengesahanFinal: false }} onUpdated={vi.fn()} />)

    expect(await screen.findByText(/Set final lembar pengesahan terlebih dulu/)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Unggah Proposal PDF/i })).not.toBeInTheDocument()
  })

  it('generate is disabled until dana penyertaan is saved, then set final asks for confirmation', async () => {
    const belum = { ...PROPOSAL, danaMitra: null, danaInkind: null, adaLembarPengesahan: false, isLembarPengesahanFinal: false }
    const final = { ...PROPOSAL }
    penelitiApi.saveDanaPenyertaanProposal.mockResolvedValue({ data: { ...belum, danaMitra: 0, danaInkind: 500 }, message: 'Dana Penyertaan sudah diupdate' })
    penelitiApi.finalLembarPengesahanProposal.mockResolvedValue({ data: final, message: 'Lembar pengesahan berstatus final' })
    const onUpdated = vi.fn()
    const user = userEvent.setup()
    const { rerender } = render(<KelengkapanPengajuanCard proposal={belum} onUpdated={onUpdated} />)

    expect(await screen.findByRole('button', { name: 'Generate Dokumen' })).toBeDisabled()
    await user.type(screen.getByLabelText(/dana mitra/i), '0')
    await user.type(screen.getByLabelText(/in-kind/i), '500')
    await user.click(screen.getByRole('button', { name: /Simpan Dana Penyertaan/i }))
    expect(penelitiApi.saveDanaPenyertaanProposal).toHaveBeenCalledWith('7', { danaMitra: '0', danaInkind: '500' })

    rerender(<KelengkapanPengajuanCard proposal={{ ...belum, danaMitra: 0, danaInkind: 500, adaLembarPengesahan: true }} onUpdated={onUpdated} />)
    await user.click(screen.getByRole('button', { name: 'Set Final' }))
    expect(penelitiApi.finalLembarPengesahanProposal).not.toHaveBeenCalled()
    await user.click(screen.getByRole('button', { name: 'Ya, Set Final' }))

    expect(penelitiApi.finalLembarPengesahanProposal).toHaveBeenCalledWith('7')
    expect(onUpdated).toHaveBeenLastCalledWith(final)
  })

  it('sets the uploaded proposal as final after confirmation', async () => {
    const final = { ...PROPOSAL, adaDokumenProposal: true, isDokumenProposalFinal: true }
    penelitiApi.finalDokumenProposal.mockResolvedValue({ data: final, message: 'Dokumen proposal final' })
    const onUpdated = vi.fn()
    const user = userEvent.setup()
    render(<KelengkapanPengajuanCard proposal={{ ...PROPOSAL, adaDokumenProposal: true }} onUpdated={onUpdated} />)

    await user.click(await screen.findByRole('button', { name: 'Set Dokumen Final' }))
    await user.click(screen.getByRole('button', { name: 'Ya, Set Final' }))

    expect(penelitiApi.finalDokumenProposal).toHaveBeenCalledWith('7')
    expect(onUpdated).toHaveBeenCalledWith(final)
  })

  it('previews the uploaded draft proposal without setting it final', async () => {
    penelitiApi.pratinjauDokumenProposal.mockResolvedValue()
    const user = userEvent.setup()
    render(<KelengkapanPengajuanCard proposal={{ ...PROPOSAL, adaDokumenProposal: true }} onUpdated={vi.fn()} />)

    await user.click(await screen.findByRole('button', { name: /Pratinjau Proposal/ }))

    expect(penelitiApi.pratinjauDokumenProposal).toHaveBeenCalledWith('7')
    expect(penelitiApi.finalDokumenProposal).not.toHaveBeenCalled()
  })

  it('saves the chosen optional targets', async () => {
    penelitiApi.getRencanaTarget.mockResolvedValue({
      data: [
        { id: 1, kategori: 'Publikasi', subkategori: 'Jurnal', isWajib: true, isDipilih: true },
        { id: 2, kategori: 'HKI', subkategori: 'Hak Cipta', isWajib: false, isDipilih: false },
      ],
    })
    penelitiApi.updateRencanaTarget.mockResolvedValue({ data: [] })
    const user = userEvent.setup()
    render(<KelengkapanPengajuanCard proposal={PROPOSAL} onUpdated={vi.fn()} />)

    await user.click(await screen.findByLabelText(/HKI - Hak Cipta/))
    await user.click(screen.getByRole('button', { name: /Simpan Target/i }))

    expect(penelitiApi.updateRencanaTarget).toHaveBeenCalledWith('7', [1, 2])
  })
})
