import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter, Route, Routes } from 'react-router-dom'

vi.mock('../../../src/services/api/reviewerApi', () => ({
  reviewerApi: {
    getPenugasanDetail: vi.fn(),
    getBorang: vi.fn(),
    submitNilaiRubrik: vi.fn(),
    ambilDokumenProposal: vi.fn(),
  },
}))

import { reviewerApi } from '../../../src/services/api/reviewerApi'
import PenilaianProposalPage from '../../../src/features/rev/PenilaianProposalPage'

const BORANG = [
  { nomor: 1, kriteria: '<div>Perumusan <b>Masalah</b></div>', bobot: 60, skor: null },
  { nomor: 2, kriteria: 'Metode', bobot: 40, skor: null },
]

function renderPage() {
  return render(
    <MemoryRouter initialEntries={['/rev/penilaian/5']}>
      <Routes>
        <Route path="/rev/penilaian/:id" element={<PenilaianProposalPage />} />
      </Routes>
    </MemoryRouter>
  )
}

beforeEach(() => {
  vi.clearAllMocks()
  vi.spyOn(window, 'confirm').mockReturnValue(true)
  reviewerApi.getPenugasanDetail.mockResolvedValue({ data: { id: '5', judul: 'Usulan', biayaUsulan: 10000000 } })
  reviewerApi.getBorang.mockResolvedValue({ data: BORANG })
  reviewerApi.submitNilaiRubrik.mockResolvedValue({ success: true })
  reviewerApi.ambilDokumenProposal.mockResolvedValue('blob:proposal-5')
})

const CATATAN_FINAL = 'Proposal sudah sesuai dengan roadmap penelitian, metodologi jelas, dan anggaran wajar untuk luaran yang ditargetkan.'

/** Isi syarat FINAL legacy: rekomendasi biaya dan komentar minimal 100 karakter. */
async function isiSyaratFinal(user) {
  await user.type(screen.getByLabelText(/Rekomendasi Penyesuaian Anggaran/i), '10000000')
  await user.click(screen.getByLabelText(/Catatan Evaluasi/i))
  await user.paste(CATATAN_FINAL)
}

describe('PenilaianProposalPage borang', () => {
  it('renders borang criteria as plain text with their weight', async () => {
    renderPage()

    expect(await screen.findByText('1. Perumusan Masalah')).toBeInTheDocument()
    expect(screen.getByText('Bobot 60%')).toBeInTheDocument()
  })

  it('splits legacy Word HTML criteria into a title and bullet lines as plain text', async () => {
    reviewerApi.getBorang.mockResolvedValue({
      data: [{
        nomor: 1, bobot: 20, skor: null,
        kriteria: '<div><u>Perumusan Masalah:</u></div><p>-<span>&nbsp;&nbsp;\n</span><span>Ketajaman\nperumusan masalah.</span></p><p>-&nbsp; Kesesuaian tujuan.</p><img src=x onerror="alert(1)"><script>alert(2)</script>',
      }],
    })
    renderPage()

    expect(await screen.findByText('1. Perumusan Masalah:')).toBeInTheDocument()
    expect(screen.getByText('• Ketajaman perumusan masalah.')).toBeInTheDocument()
    expect(screen.getByText('• Kesesuaian tujuan.')).toBeInTheDocument()
    expect(document.querySelector('img')).toBeNull()
    expect(screen.queryByText(/alert/)).not.toBeInTheDocument()
  })

  it('computes the total from skor x bobot and submits every criterion', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.selectOptions(await screen.findByLabelText('Skor kriteria 1'), '7')
    await user.selectOptions(screen.getByLabelText('Skor kriteria 2'), '5')
    await isiSyaratFinal(user)

    expect(screen.getByText('620')).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Kirim FINAL/i }))

    expect(reviewerApi.submitNilaiRubrik).toHaveBeenCalledWith(
      '5',
      expect.objectContaining({ skor: [{ nomor: 1, skor: 7 }, { nomor: 2, skor: 5 }] })
    )
  })

  it('sends komentar revisi as separate items', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.selectOptions(await screen.findByLabelText('Skor kriteria 1'), '7')
    await user.selectOptions(screen.getByLabelText('Skor kriteria 2'), '5')
    await isiSyaratFinal(user)
    await user.click(screen.getByRole('button', { name: /Tambah Komentar Revisi/i }))
    await user.type(screen.getByLabelText('Komentar revisi 1'), 'Perjelas metodologi')
    await user.click(screen.getByRole('button', { name: /Kirim FINAL/i }))

    expect(reviewerApi.submitNilaiRubrik).toHaveBeenCalledWith('5', expect.objectContaining({ komentarRevisi: ['Perjelas metodologi'] }))
  })

  it('blocks a FINAL penilaian whose comment is shorter than 100 characters', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.selectOptions(await screen.findByLabelText('Skor kriteria 1'), '7')
    await user.selectOptions(screen.getByLabelText('Skor kriteria 2'), '5')
    await user.type(screen.getByLabelText(/Rekomendasi Penyesuaian Anggaran/i), '10000000')
    await user.type(screen.getByLabelText(/Catatan Evaluasi/i), 'Sudah sesuai')
    await user.click(screen.getByRole('button', { name: /Kirim FINAL/i }))

    expect(await screen.findByText('Komentar minimal berisi 100 karakter.')).toBeInTheDocument()
    expect(reviewerApi.submitNilaiRubrik).not.toHaveBeenCalled()
  })

  it('warns that a total below 400 becomes an automatic rejection', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.selectOptions(await screen.findByLabelText('Skor kriteria 1'), '3')
    await user.selectOptions(screen.getByLabelText('Skor kriteria 2'), '3')

    expect(screen.getByText(/rekomendasi otomatis DITOLAK/i)).toBeInTheDocument()
  })

  it('shows a submitted penilaian read-only without a submit button', async () => {
    reviewerApi.getPenugasanDetail.mockResolvedValue({
      data: {
        id: '5', judul: 'Usulan', biayaUsulan: 10000000,
        penilaianSaya: { isFinal: true, totalSkor: 600, hasil: 'PERBAIKAN', catatan: 'Catatan tersimpan', rekomendasiDana: 9000000, komentarRevisi: ['Perjelas metodologi'] },
      },
    })
    reviewerApi.getBorang.mockResolvedValue({ data: BORANG.map((k) => ({ ...k, skor: 6 })) })
    renderPage()

    expect(await screen.findByText(/Penilaian sudah dikirim \(hasil: PERBAIKAN, total 600\)/)).toBeInTheDocument()
    expect(screen.getByLabelText('Skor kriteria 1')).toBeDisabled()
    expect(screen.getByLabelText('Komentar revisi 1')).toHaveValue('Perjelas metodologi')
    expect(screen.getByLabelText('Komentar revisi 1')).toBeDisabled()
    expect(screen.queryByRole('button', { name: /Simpan Penilaian/ })).not.toBeInTheDocument()
  })

  it('shows the proposal PDF with a download link so the reviewer can read it before scoring', async () => {
    renderPage()

    expect(reviewerApi.ambilDokumenProposal).toHaveBeenCalledWith('5')
    expect(await screen.findByTitle('Naskah proposal')).toHaveAttribute('src', 'blob:proposal-5')
    expect(screen.getByRole('link', { name: /Unduh Naskah Proposal PDF/i })).toHaveAttribute('href', 'blob:proposal-5')
  })

  it('shows an error instead of the preview when the proposal file cannot be loaded', async () => {
    reviewerApi.ambilDokumenProposal.mockRejectedValue(new Error('Berkas Proposal belum diunggah'))
    renderPage()

    expect(await screen.findByText('Berkas Proposal belum diunggah')).toBeInTheDocument()
    expect(screen.queryByTitle('Naskah proposal')).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /Unduh Naskah Proposal PDF/i })).not.toBeInTheDocument()
  })
})
