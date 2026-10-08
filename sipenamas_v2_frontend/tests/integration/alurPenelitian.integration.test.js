// @vitest-environment node
/**
 * Uji integrasi frontend <-> backend: satu usulan penelitian dijalankan dari
 * pengajuan sampai TUNTAS dan monev lewat modul API frontend
 * (src/services/api/*) terhadap backend Laravel sungguhan. Yang diuji adalah
 * kecocokan path, payload, dan bentuk respons antara kedua sisi - hal yang
 * tidak tertangkap uji komponen (API-nya di-mock).
 *
 * Dilewati kecuali dijalankan dengan backend sekali pakai (sqlite):
 *
 *   # di sipenamas_v2_backend
 *   export APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=/tmp/e2e.sqlite \
 *          LEGACY_RES_PATH=/tmp/e2e_res E2E_TOKEN_FILE=/tmp/e2e_tokens.json
 *   rm -f $DB_DATABASE && touch $DB_DATABASE
 *   php artisan migrate --force
 *   php artisan db:seed --class='Database\Seeders\E2eAlurPenelitianSeeder' --force
 *   php artisan serve --port=8089
 *
 *   # di sipenamas_v2_frontend
 *   VITE_API_BASE_URL=http://127.0.0.1:8089/api/v1 E2E_TOKEN_FILE=/tmp/e2e_tokens.json \
 *     npx vitest run tests/integration
 */
import { describe, it, expect, beforeAll, vi } from 'vitest'
import { readFileSync, existsSync } from 'node:fs'

const tokenFile = process.env.E2E_TOKEN_FILE
const aktif = Boolean(tokenFile && existsSync(tokenFile) && process.env.VITE_API_BASE_URL)

const penyimpanan = new Map()
globalThis.localStorage = {
  getItem: (k) => (penyimpanan.has(k) ? penyimpanan.get(k) : null),
  setItem: (k, v) => penyimpanan.set(k, String(v)),
  removeItem: (k) => penyimpanan.delete(k),
}
// Pengganti DOM untuk unduhBerkas(): cukup catat bahwa berkas sampai.
const unduhan = []
globalThis.window = { open: (url) => unduhan.push(url) }
globalThis.document = { createElement: () => ({ click: () => unduhan.push('unduh') }) }

const PDF = '%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n'
const pdf = (nama) => new File([PDF], nama, { type: 'application/pdf' })

describe.skipIf(!aktif)('alur penelitian lintas peran lewat API frontend', () => {
  let tokens
  let penelitiApi, dekanApi, adminApi, reviewerApi
  const sebagai = (peran) => localStorage.setItem('sipenamas_token', tokens[peran])
  const statusUsulan = async (id) => {
    sebagai('ketua')
    return (await penelitiApi.getPenelitianDetail(id)).data.status
  }

  beforeAll(async () => {
    tokens = JSON.parse(readFileSync(tokenFile, 'utf8'))
    ;({ penelitiApi } = await import('../../src/services/api/penelitiApi'))
    ;({ dekanApi } = await import('../../src/services/api/dekanApi'))
    ;({ adminApi } = await import('../../src/services/api/adminApi'))
    ;({ reviewerApi } = await import('../../src/services/api/reviewerApi'))
  })

  it('pengajuan -> dekan -> review -> revisi -> lolos -> laporan akhir -> tuntas -> monev', async () => {
    // 2. Peneliti: draft, edit, ajukan.
    sebagai('ketua')
    const usulan = { judul: 'Uji Integrasi Sensor IoT', skimKode: 'INT01', biayaUsulan: 5000000, anggotaDosen: [{ npp: 'PEN02' }] }
    const draft = await penelitiApi.createPenelitian({ ...usulan, ajukan: false })
    const id = draft.data.id
    expect(draft.data.status).toBe('DRAFT')
    const diajukan = await penelitiApi.updatePenelitian(id, { ...usulan, judul: 'Uji Integrasi Sensor IoT (final)', ajukan: true })
    expect(diajukan.data.status).toBe('SUBMITTED')
    expect((await penelitiApi.getPenelitianList({})).data.map((p) => p.id)).toContain(id)

    // 2.3 Anggota menyetujui.
    sebagai('anggota')
    const undangan = (await penelitiApi.getKesediaanTim()).data
    expect(undangan).toHaveLength(1)
    await penelitiApi.setujuiKesediaanTim(undangan[0].timId)

    // 2.5 Lembar pengesahan lalu proposal.
    sebagai('ketua')
    await expect(penelitiApi.uploadDokumenProposal(id, pdf('proposal.pdf'))).rejects.toThrow(/lembar pengesahan/i)
    await penelitiApi.saveDanaPenyertaanProposal(id, { danaMitra: 0, danaInkind: 0 })
    expect((await penelitiApi.generateLembarPengesahanProposal(id)).data.adaLembarPengesahan).toBe(true)
    expect((await penelitiApi.finalLembarPengesahanProposal(id)).data.isLembarPengesahanFinal).toBe(true)
    expect((await penelitiApi.uploadDokumenProposal(id, pdf('proposal.pdf'))).data.adaDokumenProposal).toBe(true)
    expect((await penelitiApi.finalDokumenProposal(id)).data.isDokumenProposalFinal).toBe(true)
    expect((await penelitiApi.getRencanaTarget(id)).data.length).toBeGreaterThan(0)
    await expect(penelitiApi.unduhPengesahan(id)).rejects.toThrow(/BELUM DISETUJUI DEKAN/)

    // 3. Dekan.
    sebagai('dekan')
    expect((await dekanApi.getApprovalList()).data.map((p) => p.id)).toContain(id)
    await dekanApi.approveProposal(id, { catatan: 'Sesuai roadmap fakultas' })
    expect(await statusUsulan(id)).toBe('DISETUJUI_DEKAN')
    sebagai('ketua')
    await penelitiApi.unduhPengesahan(id)
    expect(unduhan).toContain('unduh')

    // 4. Plotting, kesediaan, penilaian.
    sebagai('admin')
    expect((await adminApi.getPlottingList()).data.map((p) => p.id)).toContain(id)
    await adminApi.assignReviewers(id, { reviewer1Id: 'REV01', reviewer2Id: 'REV02' })
    await adminApi.finalizePlotting(id)
    expect(await statusUsulan(id)).toBe('PLOTTED')

    sebagai('reviewer1')
    expect((await reviewerApi.getPenugasanList()).data.map((p) => p.id)).toContain(id)
    expect((await reviewerApi.getPenugasanDetail(id)).data.id).toBe(id)
    await reviewerApi.confirmKesediaan(id, { bersedia: true })
    const borang = (await reviewerApi.getBorang(id)).data
    expect(borang).toHaveLength(2)
    const skor = borang.map((k) => ({ nomor: k.nomor, skor: 7 }))
    await reviewerApi.submitNilaiRubrik(id, { skor, catatan: 'Perlu perbaikan kecil', rekomendasiStatus: 'REVISI', komentarRevisi: ['Perjelas metodologi'] })

    sebagai('reviewer2')
    await reviewerApi.confirmKesediaan(id, { bersedia: true })
    await reviewerApi.submitNilaiRubrik(id, { skor, catatan: 'Baik', rekomendasiStatus: 'LOLOS' })
    expect(await statusUsulan(id)).toBe('REVISI')

    // 4. Revisi sebagai utas komentar.
    sebagai('admin')
    await adminApi.assignRevisiVerifikator(id, { reviewerId: 'REV01' })
    sebagai('ketua')
    const revisi = (await penelitiApi.getRevisi(id)).data
    expect(revisi.komentar.map((k) => k.komentar)).toEqual(['Perjelas metodologi'])
    expect(revisi.alasanTertutup).toBeNull()
    await penelitiApi.saveResponRevisi(id, revisi.komentar[0].id, 'Bab 3 ditambah')
    await penelitiApi.uploadDokumenRevisi(id, pdf('revisi.pdf'))
    await penelitiApi.finalRevisi(id)
    expect(await statusUsulan(id)).toBe('MENUNGGU_VERIFIKASI_REVISI')

    sebagai('reviewer1')
    expect((await reviewerApi.getRevisiVerifikasiQueue()).data.map((p) => p.id)).toContain(id)
    expect((await reviewerApi.getKomentarRevisi(id)).data[0].respon).toBe('Bab 3 ditambah')
    await reviewerApi.verifikasiRevisi(id, { status: 'DISETUJUI', catatan: 'Sudah sesuai' })
    expect(await statusUsulan(id)).toBe('FINAL_APPROVAL')

    // 5. Final approval.
    sebagai('admin')
    expect((await adminApi.getFinalApprovalList()).data.map((p) => p.id)).toContain(id)
    await adminApi.submitFinalDecision(id, { status: 'LOLOS', biayaDisetujui: 4500000 })
    expect(await statusUsulan(id)).toBe('LOLOS')

    // 7.1 Laporan akhir.
    sebagai('ketua')
    const daftar = (await penelitiApi.getLaporanAkhirList('2026')).data
    expect(daftar.items.map((i) => String(i.id))).toContain(String(id))
    const kuesioner = (await penelitiApi.getKuesionerPenelitian('2026')).data
    for (const p of kuesioner.pertanyaan) {
      await penelitiApi.saveKuesionerJawaban(p.id, 'D')
    }
    expect((await penelitiApi.getLaporanAkhirList('2026')).data.isKuesionerSelesai).toBe(true)
    await penelitiApi.getKelengkapanLaporan(id)
    await penelitiApi.saveDanaPenyertaanLaporan(id, { danaMitra: 0, danaInkind: 100000 })
    expect((await penelitiApi.addMahasiswaLaporan(id, '5303021001')).data.mahasiswa).toHaveLength(1)
    await penelitiApi.generateLembarPengesahanLaporan(id)
    expect((await penelitiApi.finalLembarPengesahanLaporan(id)).data.isLembarPengesahanFinal).toBe(true)
    const capaian = (await penelitiApi.getCapaianLuaran(id)).data
    const target = capaian.target[0]
    await expect(penelitiApi.saveCapaianLuaran(id, target.id, { realisasi: true, keterangan: '', statusTayang: '' })).rejects.toThrow(/UPLOAD DOKUMEN/)
    await penelitiApi.uploadDokumenLuaran(id, target.id, pdf('laporan.pdf'))
    const tersimpan = await penelitiApi.saveCapaianLuaran(id, target.id, { realisasi: true, keterangan: 'Selesai', statusTayang: '' })
    expect(tersimpan.data.target[0].isRealisasi).toBe(true)
    await penelitiApi.lihatDokumenLuaran(id, target.id)
    await expect(penelitiApi.unduhLembarPengesahanLaporan(id)).rejects.toThrow(/BELUM DISETUJUI DEKAN/)

    // 7.2 Dekan menyetujui laporan, admin menetapkan tuntas.
    sebagai('dekan')
    const laporanDekan = (await dekanApi.getLaporanAkhirList('2026')).data.items.find((i) => String(i.id) === String(id))
    expect(laporanDekan).toMatchObject({ isLembarPengesahanFinal: true, isDisetujuiDekan: false, jumlahRealisasi: 1 })
    await dekanApi.approveLaporanAkhir(id)
    sebagai('ketua')
    await penelitiApi.unduhLembarPengesahanLaporan(id)

    sebagai('admin')
    const ketuntasan = (await adminApi.getKetuntasanList('2026')).data.items.find((i) => String(i.id) === String(id))
    expect(ketuntasan.isDisetujuiDekan).toBe(true)
    await adminApi.updateKetuntasan(id, 'TUNTAS')
    expect(await statusUsulan(id)).toBe('TUNTAS')

    // 6. Monev hasil.
    sebagai('dekan')
    expect((await dekanApi.getMonevList()).data.map((p) => String(p.id))).toContain(String(id))
    expect((await dekanApi.getMonevKandidat(id)).data.map((k) => k.kodeperson)).toEqual(['GJM01'])
    await dekanApi.assignMonev(id, { kodeperson: 'GJM01' })

    sebagai('gjm')
    expect((await penelitiApi.getMonevHasilList()).data.map((p) => String(p.id))).toContain(String(id))
    const monev = (await penelitiApi.getMonevHasilDetail(id)).data
    for (const soal of monev.soal) {
      await penelitiApi.saveMonevJawaban(id, { nomor: soal.nomor, jawaban: soal.pilihan[1].kode })
    }
    const final = await penelitiApi.saveMonevKesimpulan(id, { kesimpulan: 'Sesuai target', isFinal: true })
    expect(final.data.isFinal).toBe(true)
  }, 120000)
})
