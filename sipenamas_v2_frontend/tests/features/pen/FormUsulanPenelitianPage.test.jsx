import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, within, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MemoryRouter } from 'react-router-dom'

const mockNavigate = vi.fn()

vi.mock('react-router-dom', async (importOriginal) => {
  const actual = await importOriginal()
  return {
    ...actual,
    useNavigate: () => mockNavigate,
  }
})

vi.mock('../../../src/services/api/penelitiApi', () => ({
  penelitiApi: {
    createPenelitian: vi.fn(),
  },
}))

vi.mock('../../../src/services/api/masterDataApi', () => ({
  masterDataApi: {
    getSkimList: vi.fn(),
    getFakultasList: vi.fn().mockResolvedValue({ data: [] }),
    getPeriodeAktif: vi.fn().mockResolvedValue({ data: { tahun: '2027', nama: 'Periode Uji 2027' } }),
    getSumberDanaList: vi.fn().mockResolvedValue({ data: [{ kode: 'INTERNAL', nama: 'Dana Internal' }] }),
    searchDosen: vi.fn().mockResolvedValue({ data: [] }),
  },
}))

import { penelitiApi } from '../../../src/services/api/penelitiApi'
import { masterDataApi } from '../../../src/services/api/masterDataApi'
import FormUsulanPenelitianPage from '../../../src/features/pen/FormUsulanPenelitianPage'

// Langkah 3 legacy: komposisi dana harus tepat 100% (Honorarium 30 tetap).
async function isiKomposisi(user) {
  const [bahan, perjalanan, laporan] = screen.getAllByRole('spinbutton')
  for (const [el, v] of [[bahan, '50'], [perjalanan, '15'], [laporan, '5']]) {
    await user.clear(el)
    await user.type(el, v)
  }
}

async function keLangkahAkhir(user) {
  await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
  await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
  await isiKomposisi(user)
  await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
  await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
}

function renderPage() {
  return render(
    <MemoryRouter>
      <FormUsulanPenelitianPage />
    </MemoryRouter>
  )
}

const SKIM_OPTIONS = [
  { kode: 'PDP', nama: 'Penelitian Dosen Pemula', maxDana: 20000000 },
  { kode: 'PTUPT', nama: 'Penelitian Terapan', maxDana: 50000000 },
]

beforeEach(() => {
  mockNavigate.mockClear()
  penelitiApi.createPenelitian.mockReset()
  masterDataApi.getSkimList.mockReset()
  masterDataApi.getSkimList.mockResolvedValue({ data: SKIM_OPTIONS })
  masterDataApi.searchDosen.mockReset()
  masterDataApi.searchDosen.mockResolvedValue({ data: [] })
})

describe('FormUsulanPenelitianPage', () => {
  it('renders step 1 fields on initial mount', () => {
    renderPage()

    expect(screen.getByPlaceholderText(/Pengembangan Prototipe Sensor Pintar/i)).toBeInTheDocument()
    expect(screen.getByText(/Skema Penelitian/i)).toBeInTheDocument()
  })

  it('loads skim options from masterDataApi and selects the first one by default', async () => {
    renderPage()

    const option = await screen.findByRole('option', { name: /Penelitian Dosen Pemula/i })
    expect(option.selected).toBe(true)
  })

  it('does not show a max-dana ceiling next to the skim options', async () => {
    renderPage()

    await screen.findByRole('option', { name: /Penelitian Dosen Pemula/i })
    expect(screen.queryByText(/Maks:/i)).not.toBeInTheDocument()
  })

  it('starts empty without sample data and shows the active periode', async () => {
    const user = userEvent.setup()
    renderPage()

    expect(await screen.findByText(/Periode aktif: Periode Uji 2027/)).toBeInTheDocument()
    expect(screen.queryByDisplayValue(/Teknologi Informasi dan Komunikasi/)).not.toBeInTheDocument()
    expect(screen.queryByText(/Rumpun Ilmu/)).not.toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    expect(screen.getAllByRole('spinbutton')[0]).toHaveValue(0)
  })

  it('shows Honorarium fixed at 30 percent and read-only on step 3', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))

    const honor = screen.getAllByRole('spinbutton')[3]
    expect(honor).toHaveValue(30)
    expect(honor).toHaveAttribute('readonly')
    expect(screen.getByText(/Maks 70%/)).toBeInTheDocument()
    expect(screen.getByText(/Maks 40%/)).toBeInTheDocument()
    expect(screen.getByText(/Maks 5%/)).toBeInTheDocument()
  })

  it('navigates through all 5 steps via Selanjutnya/Sebelumnya buttons', async () => {
    const user = userEvent.setup()
    renderPage()

    for (let i = 0; i < 4; i++) {
      await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    }

    expect(screen.getByText(/Langkah 5/i)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Sebelumnya/i }))
    expect(screen.getByText(/Langkah 4/i)).toBeInTheDocument()
  })

  it('typing in judul updates the field value', async () => {
    const user = userEvent.setup()
    renderPage()

    const textarea = screen.getByPlaceholderText(/Pengembangan Prototipe Sensor Pintar/i)
    await user.type(textarea, 'Judul Riset Baru')

    expect(textarea).toHaveValue('Judul Riset Baru')
  })

  it('adding and removing a dosen anggota row', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    await user.click(screen.getByRole('button', { name: /Tambah Anggota Dosen/i }))

    const nppInput = screen.getByPlaceholderText(/NIDN \/ NIK/i)
    await user.type(nppInput, 'P00001')
    expect(nppInput).toHaveValue('P00001')

    const removeButton = screen.getByText(/Dosen Anggota #1/i)
      .closest('div')
      .querySelector('button')
    await user.click(removeButton)

    expect(screen.queryByPlaceholderText(/NIDN \/ NIK/i)).not.toBeInTheDocument()
  })

  it('searches and selects dosen anggota from combobox filling npp, nama, and prodi automatically', async () => {
    const user = userEvent.setup()
    masterDataApi.searchDosen.mockResolvedValue({
      data: [
        { npp: 'DSN01', nama: 'Dr. Budi Santoso', prodi: 'Teknik Informatika' },
        { npp: 'DSN02', nama: 'Dr. Siti Aminah', prodi: 'Teknik Elektro' },
      ],
    })
    renderPage()

    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    await user.click(screen.getByRole('button', { name: /Tambah Anggota Dosen/i }))

    const searchInput = screen.getByPlaceholderText(/Cari Dosen/i)
    await user.click(searchInput)

    const option = await screen.findByText('Dr. Budi Santoso')
    await user.click(option)

    expect(await screen.findByText('Dr. Budi Santoso')).toBeInTheDocument()
    expect(screen.getByText('DSN01')).toBeInTheDocument()
    expect(screen.getByText('Teknik Informatika')).toBeInTheDocument()
    expect(screen.getByText('Ganti Dosen')).toBeInTheDocument()

    await user.click(screen.getByText('Ganti Dosen'))
    expect(screen.getByPlaceholderText(/Cari Dosen/i)).toBeInTheDocument()
  })

  it('blocks submit and reports when komposisi does not total 100 percent', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    expect(screen.getByText(/harus berjumlah 100 persen/)).toBeInTheDocument()

    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    await user.click(screen.getByRole('button', { name: /Kirim Usulan/i }))

    expect(penelitiApi.createPenelitian).not.toHaveBeenCalled()
    expect(screen.getByText(/Total komposisi: 30%/)).toBeInTheDocument()
  })

  it('flags a category above its legacy maximum', async () => {
    const user = userEvent.setup()
    renderPage()

    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    const [bahan] = screen.getAllByRole('spinbutton')
    await user.clear(bahan)
    await user.type(bahan, '71')

    expect(screen.getByText(/Bahan & Peralatan maksimal 70%/)).toBeInTheDocument()
  })

  it('submitting calls penelitiApi.createPenelitian with the current form data and navigates to /pen/penelitian', async () => {
    penelitiApi.createPenelitian.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    renderPage()

    await keLangkahAkhir(user)

    await user.click(screen.getByRole('button', { name: /Kirim Usulan/i }))

    expect(penelitiApi.createPenelitian).toHaveBeenCalledTimes(1)
    const payload = penelitiApi.createPenelitian.mock.calls[0][0]
    expect(payload).toMatchObject({
      skimKode: 'PDP',
      anggotaDosen: [],
      komposisiBahanPeralatan: 50,
      komposisiPerjalanan: 15,
      komposisiLaporan: 5,
    })
    expect(mockNavigate).toHaveBeenCalledWith('/pen/penelitian')
  })

  it('saves a draft with ajukan false', async () => {
    penelitiApi.createPenelitian.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    renderPage()

    await keLangkahAkhir(user)

    await user.click(screen.getByRole('button', { name: /Simpan Draft/i }))

    expect(penelitiApi.createPenelitian.mock.calls[0][0]).toMatchObject({ ajukan: false })
  })

  it('lets the peneliti add a mitra and includes it in the submit payload', async () => {
    penelitiApi.createPenelitian.mockResolvedValue({ success: true })
    const user = userEvent.setup()
    renderPage()

    await user.click(screen.getByRole('button', { name: /Selanjutnya/i })) // step 2

    await user.click(screen.getByRole('button', { name: /Tambah Mitra/i }))
    await user.type(screen.getByPlaceholderText(/Nama Mitra/i), 'Dr. John Doe')
    await user.type(screen.getByPlaceholderText(/Instansi Asal/i), 'Universitas Mitra ABC')

    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    await isiKomposisi(user)
    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    await user.click(screen.getByRole('button', { name: /Selanjutnya/i }))
    await user.click(screen.getByRole('button', { name: /Kirim Usulan/i }))

    const payload = penelitiApi.createPenelitian.mock.calls[0][0]
    expect(payload.mitra).toEqual([
      { nama: 'Dr. John Doe', instansi: 'Universitas Mitra ABC', tugas: '' },
    ])
  })

  it('submit button shows loading state while the request is in flight', async () => {
    let resolveCreate
    penelitiApi.createPenelitian.mockReturnValue(
      new Promise((resolve) => {
        resolveCreate = resolve
      })
    )
    const user = userEvent.setup()
    renderPage()

    await keLangkahAkhir(user)

    const submitButton = screen.getByRole('button', { name: /Kirim Usulan/i })
    await user.click(submitButton)

    expect(submitButton).toBeDisabled()

    resolveCreate({ success: true })
    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Kirim Usulan/i })).not.toBeDisabled()
    })
  })

  it('shows backend validation messages when submission is rejected', async () => {
    const error = new Error('Data yang dikirim tidak valid.')
    error.errors = { skimKode: ['Kuota sebagai ketua (3 usulan per periode) sudah terpenuhi.'] }
    penelitiApi.createPenelitian.mockRejectedValue(error)
    const user = userEvent.setup()
    renderPage()

    await keLangkahAkhir(user)
    await user.click(screen.getByRole('button', { name: /Kirim Usulan/i }))

    expect(await screen.findByText('Data yang dikirim tidak valid.')).toBeInTheDocument()
    expect(screen.getByText(/Kuota sebagai ketua/)).toBeInTheDocument()
    expect(mockNavigate).not.toHaveBeenCalled()
  })
})
