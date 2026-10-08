import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/adminApi', () => ({
  adminApi: {
    getFinalApprovalList: vi.fn(),
    submitFinalDecision: vi.fn(),
    generateSurat: vi.fn(),
    finalSurat: vi.fn(),
    unduhSurat: vi.fn(),
  },
}))

vi.mock('../../../src/services/api/masterDataApi', () => ({
  masterDataApi: {
    getPeriodeList: vi.fn().mockResolvedValue({
      data: [
        { kodeperiode: '2026-1', tahun: 2026, isaktif: 1 },
        { kodeperiode: '2025-1', tahun: 2025, isaktif: 0 },
      ],
    }),
  },
}))

import { adminApi } from '../../../src/services/api/adminApi'
import { ApiError } from '../../../src/services/api/apiClient'
import FinalApprovalPage from '../../../src/features/adm/FinalApprovalPage'

function renderPage() {
  return render(
    <MemoryRouter>
      <FinalApprovalPage />
    </MemoryRouter>
  )
}

const PROPOSAL = {
  id: '1',
  kodeUsulan: 'PR-2026-FT-001',
  judul: 'Usulan Skor Rendah',
  ketuaNama: 'Dr. Budi',
  fakultasNama: 'Fakultas Teknik',
  skimKode: 'PDP',
  status: 'FINAL_APPROVAL',
  skorReviewer1: 350,
  skorReviewer2: 385,
  skorRataRata: 367.5,
  biayaUsulan: 10000000,
}

beforeEach(() => {
  adminApi.getFinalApprovalList.mockReset().mockResolvedValue({ data: [PROPOSAL] })
  adminApi.submitFinalDecision.mockReset()
})

describe('FinalApprovalPage', () => {
  it('shows the average score on a 0-700 scale, not 0-100', async () => {
    const user = userEvent.setup()
    renderPage()

    expect(await screen.findByText('367.5')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Tetapkan SK/i }))
    expect(screen.getByText(/\/ 700/)).toBeInTheDocument()
  })

  it('surfaces a backend error when a decision is rejected (e.g. auto-rejected proposal)', async () => {
    adminApi.submitFinalDecision.mockRejectedValue(
      new ApiError('Usulan ini sudah ditolak otomatis oleh sistem karena skor rata-rata reviewer di bawah ambang minimum - keputusan tidak dapat diubah.', 422)
    )
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /Tetapkan SK/i }))
    await user.click(screen.getByRole('button', { name: /Simpan Keputusan Final/i }))

    await waitFor(() => {
      expect(screen.getByText(/ditolak otomatis oleh sistem/i)).toBeInTheDocument()
    })
  })

  it('generates surat for the selected proposals and finalizes after confirmation', async () => {
    adminApi.getFinalApprovalList.mockResolvedValue({ data: [PROPOSAL] })
    adminApi.generateSurat.mockResolvedValue({ message: 'PROSES GENERATE SELESAI.' })
    adminApi.finalSurat.mockResolvedValue({ message: 'PROSES SELESAI.' })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByLabelText('Pilih Usulan Skor Rendah'))
    await user.type(screen.getByLabelText('Nomor awal'), '15')
    await user.click(screen.getByRole('button', { name: 'Generate' }))

    expect(adminApi.generateSurat).toHaveBeenCalledWith(
      expect.objectContaining({ ids: ['1'], nomor: 15, abaikanDuplikasi: false })
    )
    expect(await screen.findByText('PROSES GENERATE SELESAI.')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: 'Set Final' }))
    expect(adminApi.finalSurat).not.toHaveBeenCalled()
    await user.click(screen.getByRole('button', { name: 'Ya, lanjutkan' }))
    expect(adminApi.finalSurat).toHaveBeenCalledWith(['1'])
  })

  it('asks before generating with a duplicated number', async () => {
    adminApi.getFinalApprovalList.mockResolvedValue({ data: [PROPOSAL] })
    adminApi.generateSurat
      .mockRejectedValueOnce(new ApiError('DITEMUKAN DATA DENGAN NOMOR YG AKAN DUPLIKASI (15).', 409))
      .mockResolvedValueOnce({ message: 'PROSES GENERATE SELESAI.' })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByLabelText('Pilih Usulan Skor Rendah'))
    await user.type(screen.getByLabelText('Nomor awal'), '15')
    await user.click(screen.getByRole('button', { name: 'Generate' }))

    expect(await screen.findByText(/NOMOR YG AKAN DUPLIKASI \(15\)/)).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: 'Ya, lanjutkan' }))

    expect(adminApi.generateSurat).toHaveBeenLastCalledWith(expect.objectContaining({ abaikanDuplikasi: true }))
    expect(await screen.findByText('PROSES GENERATE SELESAI.')).toBeInTheDocument()
  })

  it('downloads a generated surat from the row', async () => {
    adminApi.getFinalApprovalList.mockResolvedValue({
      data: [{ ...PROPOSAL, statusFinalApproval: 'LOLOS', suratTugas: { nomor: '15', isFinal: true } }],
    })
    const user = userEvent.setup()
    renderPage()

    await user.click(await screen.findByRole('button', { name: /ST No\. 15/ }))
    expect(adminApi.unduhSurat).toHaveBeenCalledWith('1', 'ST')
  })

  it('loads the active periode by default and refetches when the periode changes', async () => {
    const user = userEvent.setup()
    renderPage()

    await waitFor(() => expect(adminApi.getFinalApprovalList).toHaveBeenCalledWith('2026-1'))
    expect(adminApi.getFinalApprovalList).toHaveBeenCalledTimes(1)

    await user.selectOptions(screen.getByDisplayValue('2026'), 'ALL')
    await waitFor(() => expect(adminApi.getFinalApprovalList).toHaveBeenLastCalledWith('ALL'))
  })
})
