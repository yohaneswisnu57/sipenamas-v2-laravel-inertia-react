import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor, within } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/adminApi', () => ({
  adminApi: {
    getPlottingList: vi.fn(),
    assignReviewers: vi.fn(),
    finalizePlotting: vi.fn(),
    tambahReviewerKe3: vi.fn(),
    assignRevisiVerifikator: vi.fn(),
  },
}))

vi.mock('../../../src/services/api/masterDataApi', () => ({
  masterDataApi: {
    searchReviewer: vi.fn(),
    getFakultasList: vi.fn().mockResolvedValue({ data: [] }),
    getPeriodeList: vi.fn().mockResolvedValue({
      data: [
        { kodeperiode: '2026-1', tahun: 2026, isaktif: 1 },
        { kodeperiode: '2025-1', tahun: 2025, isaktif: 0 },
      ],
    }),
  },
}))

import { adminApi } from '../../../src/services/api/adminApi'
import { masterDataApi } from '../../../src/services/api/masterDataApi'
import PlottingReviewerPage from '../../../src/features/adm/PlottingReviewerPage'

function renderPage() {
  return render(
    <MemoryRouter>
      <PlottingReviewerPage />
    </MemoryRouter>
  )
}

const REVIEWER_OPTIONS = [
  { id: 'REV001', name: 'Dr. Ani', prodi: 'Informatika', isExternal: false },
  { id: 'REV002', name: 'Dr. Budi', prodi: 'Elektro', isExternal: false },
  { id: 'REV003', name: 'Dr. Citra', prodi: 'Industri', isExternal: true },
]

beforeEach(() => {
  masterDataApi.searchReviewer.mockReset().mockResolvedValue({ data: REVIEWER_OPTIONS })
  adminApi.assignReviewers.mockReset().mockResolvedValue({ success: true })
  adminApi.finalizePlotting.mockReset().mockResolvedValue({ success: true })
  adminApi.tambahReviewerKe3.mockReset().mockResolvedValue({ success: true })
})

const DRAFT_PROPOSAL = {
  id: '1',
  kodeUsulan: 'PR-2026-FT-001',
  judul: 'Usulan Draft Plotting',
  ketuaNama: 'Dr. Budi',
  prodiNama: 'Informatika',
  fakultasKode: 'FT',
  biayaUsulan: 10000000,
  skimKode: 'PDP',
  status: 'PLOTTED',
  isApprovedByDekan: true,
  statusPenunjukanReviewer: 'DRAFT',
  reviewer1: { id: 'REV001', penugasanId: '10', nama: 'Dr. Ani', statusKesediaan: 'MENUNGGU' },
  reviewer2: { id: 'REV002', penugasanId: '11', nama: 'Dr. Budi', statusKesediaan: 'MENUNGGU' },
}

const DECLINED_PROPOSAL = {
  ...DRAFT_PROPOSAL,
  id: '2',
  kodeUsulan: 'PR-2026-FT-002',
  statusPenunjukanReviewer: 'FINAL',
  reviewer1: { id: 'REV001', penugasanId: '20', nama: 'Dr. Ani', statusKesediaan: 'MENOLAK' },
}

describe('PlottingReviewerPage draft/final + replace reviewer', () => {
  it('only offers the proposal reviewers as revisi verifikator', async () => {
    adminApi.getPlottingList.mockResolvedValue({
      data: [{ ...DRAFT_PROPOSAL, status: 'REVISI', statusPenunjukanReviewer: 'FINAL' }],
    })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Tunjuk Verifikator Revisi/i }))

    const select = screen.getByRole('option', { name: 'Pilih reviewer...' }).closest('select')
    const options = within(select).getAllByRole('option').map((o) => o.textContent)
    expect(options).toEqual(['Pilih reviewer...', 'Dr. Ani', 'Dr. Budi'])
  })

  it('disables a reviewer that already completed a revisi verification', async () => {
    adminApi.getPlottingList.mockResolvedValue({
      data: [{
        ...DRAFT_PROPOSAL,
        status: 'REVISI',
        statusPenunjukanReviewer: 'FINAL',
        reviewer1: { ...DRAFT_PROPOSAL.reviewer1, sudahVerifikasiRevisi: true },
      }],
    })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Tunjuk Verifikator Revisi/i }))

    const select = screen.getByRole('option', { name: 'Pilih reviewer...' }).closest('select')
    const aniOption = within(select).getAllByRole('option').find((o) => o.textContent.startsWith('Dr. Ani'))
    expect(aniOption).toBeDisabled()
    expect(aniOption.textContent).toMatch(/tidak bisa dipilih lagi/i)
    expect(within(select).getByRole('option', { name: 'Dr. Budi' })).not.toBeDisabled()
  })

  it('offers a 3rd reviewer when the score gap flag is set', async () => {
    adminApi.getPlottingList.mockResolvedValue({
      data: [{ ...DRAFT_PROPOSAL, statusPenunjukanReviewer: 'FINAL', isButuhReviewerKetiga: true }],
    })
    renderPage()

    expect(await screen.findByText(/butuh reviewer ke-3/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Tunjuk Reviewer Ke-3/i })).toBeInTheDocument()
  })

  it('hides the plot button until the Dekan approves', async () => {
    adminApi.getPlottingList.mockResolvedValue({
      data: [{ ...DRAFT_PROPOSAL, isApprovedByDekan: false, reviewer1: null, reviewer2: null }],
    })
    renderPage()

    expect(await screen.findByText(/Menunggu persetujuan Dekan/i)).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Plot Reviewer/i })).not.toBeInTheDocument()
  })

  it('shows a draft badge and finalize button when plotting is still draft', async () => {
    adminApi.getPlottingList.mockResolvedValue({ data: [DRAFT_PROPOSAL] })
    renderPage()

    expect(await screen.findByText(/Draft — Reviewer Belum Diberi Tahu/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /Finalisasi & Kirim ke Reviewer/i })).toBeInTheDocument()
  })

  it('calls finalizePlotting when the finalize button is clicked', async () => {
    adminApi.getPlottingList.mockResolvedValue({ data: [DRAFT_PROPOSAL] })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Finalisasi & Kirim ke Reviewer/i }))

    expect(adminApi.finalizePlotting).toHaveBeenCalledWith('1')
  })

  it('re-enables the finalize button after the request completes', async () => {
    adminApi.getPlottingList.mockResolvedValue({ data: [DRAFT_PROPOSAL] })
    let resolveFinalize
    adminApi.finalizePlotting.mockReturnValue(
      new Promise((resolve) => {
        resolveFinalize = resolve
      })
    )
    const user = userEvent.setup()
    renderPage()

    const finalizeButton = await screen.findByRole('button', { name: /Finalisasi & Kirim ke Reviewer/i })
    await user.click(finalizeButton)

    expect(finalizeButton).toBeDisabled()

    resolveFinalize({ success: true })

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Finalisasi & Kirim ke Reviewer/i })).not.toBeDisabled()
    })
  })

  it('does not show the draft badge once plotting is final', async () => {
    adminApi.getPlottingList.mockResolvedValue({ data: [DECLINED_PROPOSAL] })
    renderPage()

    await screen.findByText('PR-2026-FT-002', { exact: false })
    expect(screen.queryByText(/Draft — Reviewer Belum Diberi Tahu/i)).not.toBeInTheDocument()
  })

  it('shows a Tunjuk Reviewer Ke-3 action when a reviewer declined, adding a 3rd without touching the other two', async () => {
    adminApi.getPlottingList.mockResolvedValue({ data: [DECLINED_PROPOSAL] })
    const user = userEvent.setup()
    renderPage()

    expect(await screen.findByText('MENOLAK')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Tunjuk Reviewer Ke-3/i }))
    await user.click(await screen.findByPlaceholderText(/Cari reviewer/i))
    await user.click(await screen.findByRole('button', { name: /Dr\. Citra/ }))
    await user.click(screen.getByRole('button', { name: /^Tambahkan$/i }))

    expect(adminApi.tambahReviewerKe3).toHaveBeenCalledWith('2', { reviewerBaruId: 'REV003' })
  })

  it('does not show the Tunjuk Reviewer Ke-3 action once a 3rd reviewer already exists', async () => {
    adminApi.getPlottingList.mockResolvedValue({
      data: [{ ...DECLINED_PROPOSAL, reviewer3: { id: 'REV003', penugasanId: '30', nama: 'Dr. Citra', statusKesediaan: 'MENUNGGU' } }],
    })
    renderPage()

    await screen.findByText('MENOLAK')
    expect(screen.queryByRole('button', { name: /Tunjuk Reviewer Ke-3/i })).not.toBeInTheDocument()
  })

  it('shows the server error inside the modal instead of failing silently', async () => {
    adminApi.getPlottingList.mockResolvedValue({
      data: [{ ...DRAFT_PROPOSAL, status: 'REVISI', statusPenunjukanReviewer: 'FINAL' }],
    })
    adminApi.assignRevisiVerifikator.mockRejectedValue(
      new Error('Reviewer ini sudah pernah menyelesaikan verifikasi revisi usulan ini dan tidak boleh ditunjuk lagi.')
    )
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Tunjuk Verifikator Revisi/i }))
    const select = screen.getByRole('option', { name: 'Pilih reviewer...' }).closest('select')
    await user.selectOptions(select, 'REV001')
    await user.click(screen.getByRole('button', { name: /^Tunjuk Verifikator$/i }))

    expect(await screen.findByText(/sudah pernah menyelesaikan verifikasi/i)).toBeInTheDocument()
    // Modal tetap terbuka supaya admin bisa memilih reviewer lain.
    expect(screen.getByRole('button', { name: /^Tunjuk Verifikator$/i })).toBeInTheDocument()
  })

  it('clears a previous error when the plotting modal is reopened', async () => {
    adminApi.getPlottingList.mockResolvedValue({ data: [DRAFT_PROPOSAL] })
    adminApi.assignReviewers.mockRejectedValue(new Error('Reviewer sudah menolak tugas lain.'))
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Pilih Ulang/i }))
    await user.click(screen.getByRole('button', { name: /Simpan & Kirim Undangan/i }))
    expect(await screen.findByText(/sudah menolak tugas lain/i)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Batal' }))
    await user.click(screen.getByRole('button', { name: /Pilih Ulang/i }))

    expect(screen.queryByText(/sudah menolak tugas lain/i)).not.toBeInTheDocument()
  })
})

describe('PlottingReviewerPage filter tahap', () => {
  it('memfilter usulan berdasarkan tahap', async () => {
    adminApi.getPlottingList.mockResolvedValue({
      data: [
        { ...DRAFT_PROPOSAL, id: '1', judul: 'Usulan Dalam Proses', status: 'PLOTTED' },
        { ...DRAFT_PROPOSAL, id: '2', judul: 'Usulan Berjalan', status: 'MONEV' },
        { ...DRAFT_PROPOSAL, id: '3', judul: 'Usulan Tuntas', status: 'TUNTAS' },
      ],
    })
    const user = userEvent.setup()
    renderPage()

    expect(await screen.findByText('Usulan Berjalan')).toBeInTheDocument()

    await user.selectOptions(screen.getByDisplayValue('Semua Tahap'), 'TUNTAS')

    expect(screen.getByText('Usulan Tuntas')).toBeInTheDocument()
    expect(screen.queryByText('Usulan Berjalan')).not.toBeInTheDocument()
    expect(screen.queryByText('Usulan Dalam Proses')).not.toBeInTheDocument()
  })

  it('loads the active periode by default and refetches when the periode changes', async () => {
    adminApi.getPlottingList.mockResolvedValue({ data: [] })
    const user = userEvent.setup()
    renderPage()

    await waitFor(() => expect(adminApi.getPlottingList).toHaveBeenCalledWith(expect.objectContaining({ kdperiode: '2026-1' })))
    expect(adminApi.getPlottingList).toHaveBeenCalledTimes(1)

    await user.selectOptions(screen.getByDisplayValue('2026'), 'ALL')
    await waitFor(() => expect(adminApi.getPlottingList).toHaveBeenLastCalledWith(expect.objectContaining({ kdperiode: 'ALL' })))
  })
})
