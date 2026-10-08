import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: {
    getPenelitianDetail: vi.fn(),
    getRencanaTarget: vi.fn().mockResolvedValue({ data: [] }),
  },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import DetailPenelitianPage from '../../../src/features/pen/DetailPenelitianPage'

function renderPage() {
  return render(
    <MemoryRouter initialEntries={['/pen/penelitian/1']}>
      <Routes>
        <Route path="/pen/penelitian/:id" element={<DetailPenelitianPage />} />
      </Routes>
    </MemoryRouter>
  )
}

const BASE_PROPOSAL = {
  id: '1',
  kodeUsulan: 'PR-2026-FT-001',
  judul: 'Usulan Uji Pengesahan',
  ketuaNama: 'Dr. Budi Santoso',
  prodiNama: 'Informatika',
  fakultasNama: 'Fakultas Teknik',
  skimNama: 'Penelitian Dosen Pemula',
  status: 'SUBMITTED',
  isLembarPengesahanFinal: true,
  biayaUsulan: 10000000,
  anggotaDosen: [],
  anggotaMahasiswa: [],
  rabItems: [],
}

const KETUA_QR = { nik: 'P00099', nama: 'Dr. Budi Santoso', qr: 'data:image/svg+xml;base64,KETUA' }
const DEKAN_QR = { nik: 'P00001', nama: 'Dr. Siti Aminah', qr: 'data:image/svg+xml;base64,DEKAN' }

beforeEach(() => {
  penelitiApi.getPenelitianDetail.mockReset()
})

describe('DetailPenelitianPage pengesahan QR', () => {
  it('renders the ketua QR code image with NIK and nama', async () => {
    penelitiApi.getPenelitianDetail.mockResolvedValue({
      data: { ...BASE_PROPOSAL, pengesahan: { ketua: KETUA_QR, dekan: null } },
    })
    renderPage()

    const img = await screen.findByAltText(/QR Pengesahan Ketua Pengusul/i)
    expect(img).toHaveAttribute('src', KETUA_QR.qr)
    expect(screen.getAllByText(/Dr. Budi Santoso/).length).toBeGreaterThan(0)
    expect(screen.getByText('P00099')).toBeInTheDocument()
  })

  it('shows a waiting message instead of a QR code when the Dekan has not decided yet', async () => {
    penelitiApi.getPenelitianDetail.mockResolvedValue({
      data: { ...BASE_PROPOSAL, pengesahan: { ketua: KETUA_QR, dekan: null } },
    })
    renderPage()

    await screen.findByAltText(/QR Pengesahan Ketua Pengusul/i)
    expect(screen.getByText(/Menunggu keputusan Dekan/i)).toBeInTheDocument()
    expect(screen.queryByAltText(/QR Pengesahan Dekan Fakultas/i)).not.toBeInTheDocument()
  })

  it('renders the Dekan QR code once the Dekan has decided', async () => {
    penelitiApi.getPenelitianDetail.mockResolvedValue({
      data: { ...BASE_PROPOSAL, pengesahan: { ketua: KETUA_QR, dekan: DEKAN_QR } },
    })
    renderPage()

    const img = await screen.findByAltText(/QR Pengesahan Dekan Fakultas/i)
    expect(img).toHaveAttribute('src', DEKAN_QR.qr)
    expect(screen.getByText('P00001')).toBeInTheDocument()
  })
})

const DISETUJUI = { isKetua: true, adaDokumenProposal: true, catatanDekan: { keputusan: 'SETUJU' } }

describe('DetailPenelitianPage unduh pengesahan', () => {
  it('downloads the lembar pengesahan as PDF for the ketua once the Dekan approved', async () => {
    penelitiApi.getPenelitianDetail.mockResolvedValue({
      data: { ...BASE_PROPOSAL, ...DISETUJUI, pengesahan: { ketua: KETUA_QR, dekan: DEKAN_QR } },
    })
    penelitiApi.unduhPengesahan = vi.fn().mockResolvedValue()
    renderPage()

    const btn = await screen.findByRole('button', { name: /Unduh Lembar Pengesahan \(\.PDF\)/i })
    btn.click()

    expect(penelitiApi.unduhPengesahan).toHaveBeenCalledWith('1', { format: 'pdf' })
    expect(screen.queryByRole('button', { name: /\.DOCX/i })).not.toBeInTheDocument()
  })

  it('requests the merged PDF and shows the server error message', async () => {
    penelitiApi.getPenelitianDetail.mockResolvedValue({
      data: { ...BASE_PROPOSAL, ...DISETUJUI, pengesahan: { ketua: KETUA_QR, dekan: DEKAN_QR } },
    })
    penelitiApi.unduhPengesahan = vi.fn().mockRejectedValue(new Error('Berkas proposal belum diunggah'))
    renderPage()

    const btn = await screen.findByRole('button', { name: /Proposal Lengkap/i })
    btn.click()

    expect(penelitiApi.unduhPengesahan).toHaveBeenCalledWith('1', { format: 'pdf', gabung: true })
    expect(await screen.findByText('Berkas proposal belum diunggah')).toBeInTheDocument()
  })

  it('hides the download buttons before the Dekan approves', async () => {
    penelitiApi.getPenelitianDetail.mockResolvedValue({
      data: { ...BASE_PROPOSAL, isKetua: true, pengesahan: { ketua: KETUA_QR, dekan: null } },
    })
    renderPage()

    expect(await screen.findByText(/dapat diunduh setelah disetujui Dekan/i)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Unduh Lembar Pengesahan/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Proposal Lengkap/i })).not.toBeInTheDocument()
  })

  it('hides the download buttons from anggota', async () => {
    penelitiApi.getPenelitianDetail.mockResolvedValue({
      data: { ...BASE_PROPOSAL, ...DISETUJUI, isKetua: false, pengesahan: { ketua: KETUA_QR, dekan: DEKAN_QR } },
    })
    renderPage()

    await screen.findByAltText(/QR Pengesahan Dekan Fakultas/i)
    expect(screen.queryByRole('button', { name: /Unduh Lembar Pengesahan/i })).not.toBeInTheDocument()
  })

  it('offers the surat tugas download only once it is final', async () => {
    penelitiApi.getPenelitianDetail.mockResolvedValue({
      data: { ...BASE_PROPOSAL, pengesahan: { ketua: KETUA_QR, dekan: null }, suratTugas: { nomor: '15', isFinal: true } },
    })
    penelitiApi.unduhSuratTugas = vi.fn().mockResolvedValue()
    renderPage()

    const btn = await screen.findByRole('button', { name: /Unduh Surat Tugas No\. 15/ })
    btn.click()

    expect(penelitiApi.unduhSuratTugas).toHaveBeenCalledWith('1')
  })

  it('hides the surat tugas download while it is not final', async () => {
    penelitiApi.getPenelitianDetail.mockResolvedValue({
      data: { ...BASE_PROPOSAL, pengesahan: { ketua: KETUA_QR, dekan: null }, suratTugas: { nomor: '15', isFinal: false } },
    })
    renderPage()

    await screen.findByAltText(/QR Pengesahan Ketua Pengusul/i)
    expect(screen.queryByRole('button', { name: /Unduh Surat Tugas/ })).not.toBeInTheDocument()
  })
})

describe('DetailPenelitianPage setelah ditolak Dekan', () => {
  it('shows the rejection reason and keeps the kelengkapan card so the ketua can redo the lembar pengesahan', async () => {
    penelitiApi.getPenelitianDetail.mockResolvedValue({
      data: {
        ...BASE_PROPOSAL,
        status: 'DITOLAK_DEKAN',
        isKetua: true,
        isDokumenProposalFinal: true,
        isLembarPengesahanFinal: false,
        catatanDekan: { keputusan: 'TOLAK', catatan: 'RAB belum sesuai' },
        pengesahan: { ketua: KETUA_QR, dekan: null },
      },
    })
    renderPage()

    expect(await screen.findByText('RAB belum sesuai')).toBeInTheDocument()
    expect(screen.getByText('Kelengkapan Pengajuan')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Generate Dokumen/i })).toBeInTheDocument()
    expect(screen.getByText(/Menunggu lembar pengesahan final/i)).toBeInTheDocument()
    expect(screen.queryByAltText(/QR Pengesahan Ketua Pengusul/i)).not.toBeInTheDocument()
  })
})

describe('DetailPenelitianPage anggaran', () => {
  it('shows "-" for dana disetujui until the final approval sets it', async () => {
    penelitiApi.getPenelitianDetail.mockResolvedValue({
      data: { ...BASE_PROPOSAL, biayaDisetujui: null, pengesahan: { ketua: null, dekan: null } },
    })
    renderPage()

    await screen.findByText('Dana Disetujui (SK):')
    expect(screen.getByText('Dana Disetujui (SK):').nextSibling).toHaveTextContent('-')
  })
})
