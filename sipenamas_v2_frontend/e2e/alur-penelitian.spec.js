import { test, expect } from '@playwright/test'
import fs from 'fs'

let tokens = {}
try {
  tokens = JSON.parse(fs.readFileSync('/tmp/e2e_tokens.json', 'utf8'))
} catch (e) {
  console.error("Token file not found, tests will fail")
}

async function loginAs(page, request, userKey, role = null) {
  const token = tokens[userKey]
  await page.goto('/')
  
  const response = await request.get('/api/v1/auth/me', {
    headers: { Authorization: `Bearer ${token}` }
  })
  const user = await response.json()
  
  if (role) {
    await request.post('/api/v1/auth/switch-role', {
      headers: { Authorization: `Bearer ${token}` },
      data: { role }
    })
    user.data.activeRole = role
  }
  
  await page.evaluate(({ t, u }) => {
    localStorage.setItem('sipenamas_token', t)
    localStorage.setItem('sipenamas_user', JSON.stringify(u))
  }, { t: token, u: user.data })
  
  await page.goto('/')
}

test.describe('Alur Penelitian V2 E2E', () => {
  test.setTimeout(120000)

  test('Skenario Minimal: Pengajuan s/d Disetujui Dekan', async ({ page, request }) => {
    // 1. KETUA MEMBUAT & MENGAJUKAN USULAN
    await test.step('Ketua membuat usulan', async () => {
      await loginAs(page, request, 'ketua')
      await page.goto('/pen/penelitian/baru')
      
      // Step 1: Informasi Dasar
      await expect(page.getByText('1. Informasi Dasar')).toBeVisible()
      await page.getByPlaceholder(/Pengembangan Prototipe/).fill('Usulan E2E Playwright')
      
      // Skema Penelitian & Fakultas Pengusul are select elements.
      // We can grab them by their position or label.
      // We'll use locator('select') since there are only two initially.
      const selects = page.locator('select')
      await selects.nth(0).selectOption('INT01') // Skim Penelitian Dasar
      await selects.nth(1).selectOption('FT') // Teknik
      
      // Bidang Fokus
      await page.locator('input[type="text"]').nth(1).fill('Rekayasa Perangkat Lunak')
      
      // Latar Belakang / Ringkasan
      await page.getByPlaceholder(/Tuliskan latar belakang/).fill('Ini adalah ringkasan usulan untuk E2E testing Playwright.')
      
      // Step 2: Tim Peneliti
      await page.getByText('2. Tim Peneliti').click()
      await page.getByRole('button', { name: /Tambah Anggota/i }).click()
      await page.getByPlaceholder(/Cari Dosen/).fill('Anggota')
      await page.getByText('Dr. Anggota').click()
      
      // Step 5: Konfirmasi (Skip RAB and Luaran for minimal test if they are not strictly validated by frontend)
      await page.getByText('5. Konfirmasi').click()
      
      // Centang pernyataan
      await page.getByRole('checkbox').check()
      
      // Ajukan Usulan
      await page.getByRole('button', { name: 'Ajukan Usulan Final' }).click()
      
      // Harus redirect ke daftar penelitian
      await expect(page.locator('text=Status: SUBMITTED').first() || page.getByRole('heading', { name: /Daftar Usulan Penelitian/ })).toBeVisible()
    })
    
    let usulanId = ''
    
    // 2. ANGGOTA MENYETUJUI KESEDIAAN
    await test.step('Anggota menyetujui', async () => {
      await loginAs(page, request, 'anggota')
      await page.goto('/pen/kesediaan-tim')
      
      // Setujui undangan pertama
      const btnSetuju = page.getByRole('button', { name: /Setuju/i }).first()
      await btnSetuju.click()
      // Konfirmasi SweetAlert / Modal
      await page.getByRole('button', { name: /Ya, Setuju/i }).click()
      
      await expect(page.getByText('Berhasil menyetujui')).toBeVisible()
    })
    
    // 3. KETUA FINALISASI DOKUMEN PROPOSAL
    await test.step('Ketua melengkapi proposal final', async () => {
      await loginAs(page, request, 'ketua')
      await page.goto('/pen/penelitian')
      
      // Klik Detail
      await page.getByRole('button', { name: /Detail/i }).first().click()
      
      // Pastikan URL berubah ke /pen/penelitian/:id
      await page.waitForURL(/\/pen\/penelitian\/\d+/)
      const url = page.url()
      usulanId = url.split('/').pop()
      
      // Generate & Set Final Lembar Pengesahan
      await page.getByRole('button', { name: /Generate/i }).first().click()
      // Wait for success toast or some indicator
      await page.waitForTimeout(1000)
      
      // Set Final Lembar Pengesahan
      await page.getByRole('button', { name: /Set Final/i }).first().click()
      // Confirm
      await page.getByRole('button', { name: /Ya, Finalkan/i }).click()
      
      // Upload PDF
      // Using a dummy PDF if possible, or intercept the API
      // Since it's a real API call, we need a real file
      await fs.promises.writeFile('/tmp/dummy.pdf', 'dummy content')
      const fileChooserPromise = page.waitForEvent('filechooser')
      await page.getByRole('button', { name: /Unggah Proposal/i }).click()
      const fileChooser = await fileChooserPromise
      await fileChooser.setFiles('/tmp/dummy.pdf')
      
      // Set Final Dokumen Proposal
      await page.getByRole('button', { name: /Set Dokumen Final/i }).click()
      await page.getByRole('button', { name: /Ya, Finalkan/i }).click()
      
      await expect(page.getByText(/Telah Lengkap/i)).toBeVisible()
    })
    
    // 4. DEKAN MENYETUJUI
    await test.step('Dekan menyetujui usulan', async () => {
      await loginAs(page, request, 'dekan', 'DKN')
      await page.goto('/dkn/pengajuan')
      
      // Harus ada di antrean
      await expect(page.getByText('Usulan E2E Playwright')).toBeVisible()
      
      // Klik Setujui
      await page.getByRole('button', { name: /Setujui/i }).first().click()
      // Modal catatan
      await page.getByPlaceholder(/Catatan/).fill('Disetujui Dekan via E2E')
      await page.getByRole('button', { name: /Ya, Setujui/i }).click()
      
      // Pastikan hilang dari antrean
      await expect(page.getByText('Usulan E2E Playwright')).not.toBeVisible()
    })
    
    // 5. KETUA CEK STATUS
    await test.step('Ketua mengecek status disetujui dekan', async () => {
      await loginAs(page, request, 'ketua')
      await page.goto(`/pen/penelitian/${usulanId}`)
      
      await expect(page.getByText(/Disetujui Dekan/i)).toBeVisible()
    })
  })
})
