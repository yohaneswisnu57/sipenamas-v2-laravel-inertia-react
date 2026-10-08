import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { StatusBadge } from '../../../src/components/common/StatusBadge'
import { STATUS_USULAN } from '../../../src/utils/constants'

describe('StatusBadge SUBMITTED', () => {
  it('menampilkan "Melengkapi Dokumen Pengajuan" bila dokumen proposal belum final', () => {
    render(<StatusBadge status={STATUS_USULAN.SUBMITTED} dokumenFinal={false} />)
    expect(screen.getByText('Melengkapi Dokumen Pengajuan')).toBeInTheDocument()
  })

  it('menampilkan "Menunggu Persetujuan Dekan" bila dokumen sudah final', () => {
    render(<StatusBadge status={STATUS_USULAN.SUBMITTED} dokumenFinal={true} />)
    expect(screen.getByText('Menunggu Persetujuan Dekan')).toBeInTheDocument()
  })

  it('tidak mengubah label bila prop dokumenFinal tidak diberikan', () => {
    render(<StatusBadge status={STATUS_USULAN.SUBMITTED} />)
    expect(screen.getByText('Menunggu Persetujuan Dekan')).toBeInTheDocument()
  })

  it('tidak mengubah status lain', () => {
    render(<StatusBadge status={STATUS_USULAN.DISETUJUI_DEKAN} dokumenFinal={false} />)
    expect(screen.getByText('Disetujui Dekan, Menunggu Plotting')).toBeInTheDocument()
  })
})
