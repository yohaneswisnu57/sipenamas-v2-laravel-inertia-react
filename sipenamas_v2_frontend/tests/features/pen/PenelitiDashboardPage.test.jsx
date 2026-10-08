import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: { getDashboardStats: vi.fn(), getPenelitianList: vi.fn(), getTemplates: vi.fn(), unduhTemplate: vi.fn(), lihatTemplate: vi.fn() },
}))

vi.mock('../../../src/store/authStore', () => ({
  useAuthStore: () => ({ user: { id: 1, name: 'Dr. Ketua', nidn: null, prodi: 'Informatika' } }),
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import PenelitiDashboardPage from '../../../src/features/pen/PenelitiDashboardPage'

function renderPage(stats) {
  penelitiApi.getDashboardStats.mockResolvedValue({ data: stats })
  penelitiApi.getPenelitianList.mockResolvedValue({ data: [] })
  penelitiApi.getTemplates.mockResolvedValue({ data: TEMPLATES })
  return render(
    <MemoryRouter>
      <PenelitiDashboardPage />
    </MemoryRouter>
  )
}

const TEMPLATES = [
  { id: 'tpl03-proposal', skim: 'Penelitian Dosen Pemula', jenis: 'proposal', readOnly: false, name: 'Template_Proposal_Penelitian_Dosen_Pemula.docx' },
  { id: 'tpl03-panduan', skim: 'Penelitian Dosen Pemula', jenis: 'panduan', readOnly: true, name: 'Panduan_Penelitian_Dosen_Pemula.pdf' },
]

const BASE = { totalUsulan: 1, usulanAktif: 1, totalDanaDisetujui: 0 }

describe('PenelitiDashboardPage', () => {
  it('shows the revision deadline from the active gelombang', async () => {
    renderPage({ ...BASE, pendingAction: { id: '5', kodeUsulan: 'PR-2026-FT-005' }, batasRevisi: '2026-11-20' })

    expect(await screen.findByText(/PR-2026-FT-005 Memerlukan Perbaikan/)).toBeInTheDocument()
    expect(screen.getByText(/Batas akhir perbaikan revisi/)).toBeInTheDocument()
    expect(screen.getByText(/20 Nov 2026/)).toBeInTheDocument()
    expect(screen.queryByText(/30 April 2026/)).not.toBeInTheDocument()
  })

  it('omits the deadline sentence when the gelombang has no TGLREVISI_TO', async () => {
    renderPage({ ...BASE, pendingAction: { id: '5', kodeUsulan: 'PR-2026-FT-005' }, batasRevisi: null })

    await screen.findByText(/PR-2026-FT-005 Memerlukan Perbaikan/)
    expect(screen.queryByText(/Batas akhir perbaikan revisi/)).not.toBeInTheDocument()
  })

  it('does not show a placeholder NIDN or fake trend labels', async () => {
    renderPage({ ...BASE, pendingAction: null })

    await screen.findByText('Total Usulan Saya')
    expect(screen.queryByText('0715088201')).not.toBeInTheDocument()
    expect(screen.queryByText(/periode 2026/)).not.toBeInTheDocument()
  })

  it('downloads proposal templates and hides panduan', async () => {
    renderPage(BASE)

    expect(await screen.findByText('Penelitian Dosen Pemula')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /proposal/i }))
    expect(penelitiApi.unduhTemplate).toHaveBeenCalledWith('tpl03-proposal', 'Template_Proposal_Penelitian_Dosen_Pemula.docx')
    expect(screen.queryByRole('button', { name: /panduan/i })).not.toBeInTheDocument()
  })
})
