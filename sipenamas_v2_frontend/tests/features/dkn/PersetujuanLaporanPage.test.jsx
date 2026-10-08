import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'

vi.mock('../../../src/services/api/dekanApi', () => ({
  dekanApi: { getLaporanAkhirList: vi.fn(), approveLaporanAkhir: vi.fn(), lihatLembarPengesahanLaporan: vi.fn() },
}))
vi.mock('../../../src/services/api/masterDataApi', () => ({
  masterDataApi: { getPeriodeList: vi.fn() },
}))

import { dekanApi } from '../../../src/services/api/dekanApi'
import { masterDataApi } from '../../../src/services/api/masterDataApi'
import PersetujuanLaporanPage from '../../../src/features/dkn/PersetujuanLaporanPage'

const ITEM = { id: 3, judul: 'Penelitian Lolos', skim: 'Penelitian Dasar', ketua: 'Dr. Ketua', isLembarPengesahanFinal: true, isDisetujuiDekan: false, jumlahTarget: 4, jumlahRealisasi: 3 }

beforeEach(() => {
  vi.clearAllMocks()
  masterDataApi.getPeriodeList.mockResolvedValue({ data: [{ kodeperiode: '2026', tahun: '2026' }] })
})

describe('PersetujuanLaporanPage', () => {
  it('approves a final laporan after confirmation', async () => {
    dekanApi.getLaporanAkhirList.mockResolvedValue({ data: { kdperiode: '2026', items: [ITEM] } })
    dekanApi.approveLaporanAkhir.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    render(<PersetujuanLaporanPage />)

    expect(await screen.findByText('3/4')).toBeInTheDocument()
    await user.click(screen.getByRole('button', { name: /Setujui Laporan/i }))
    expect(dekanApi.approveLaporanAkhir).not.toHaveBeenCalled()
    await user.click(screen.getByRole('button', { name: 'Ya, Setujui' }))

    expect(dekanApi.approveLaporanAkhir).toHaveBeenCalledWith(3)
    expect(await screen.findByText(/Laporan akhir disetujui/)).toBeInTheDocument()
  })

  it('offers no approval while the lembar pengesahan is not final, nor after approval', async () => {
    dekanApi.getLaporanAkhirList.mockResolvedValue({
      data: {
        kdperiode: '2026',
        items: [
          { ...ITEM, id: 4, judul: 'Belum final', isLembarPengesahanFinal: false },
          { ...ITEM, id: 5, judul: 'Sudah disetujui', isDisetujuiDekan: true },
        ],
      },
    })
    render(<PersetujuanLaporanPage />)

    expect(await screen.findByText('Laporan belum final')).toBeInTheDocument()
    expect(screen.getByText('Disetujui')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /Setujui Laporan/i })).not.toBeInTheDocument()
  })
})
