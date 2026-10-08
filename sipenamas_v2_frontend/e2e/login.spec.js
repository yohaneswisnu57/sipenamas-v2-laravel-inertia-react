import { test, expect } from '@playwright/test'

test.describe('Halaman Login SIPENAMAS V2 (UI/UX Smoke Test)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/login')
  })

  test('menampilkan header dan elemen branding utama aplikasi', async ({ page }) => {
    await expect(page.locator('text=SIPENAMAS').first()).toBeVisible()
    await expect(page.locator('text=Sistem Informasi Penelitian & Abdimas')).toBeVisible()
    await expect(page.getByRole('button', { name: /Masuk ke Sistem/i })).toBeVisible()
  })

  test('interaksi tab SSO dan Mitra/Reviewer Luar berfungsi dengan baik', async ({ page }) => {
    const ssoTab = page.getByRole('button', { name: /Civitas UKWMS \(SSO\)/i })
    const externalTab = page.getByRole('button', { name: /Mitra \/ Reviewer Luar/i })

    await expect(ssoTab).toBeVisible()
    await expect(externalTab).toBeVisible()

    // Klik tab Mitra / Reviewer Luar
    await externalTab.click()
    await expect(page.getByPlaceholder('nama@mitra.ac.id')).toBeVisible()
    await expect(page.locator('text=Masuk Akun Eksternal')).toBeVisible()

    // Beralih kembali ke tab Civitas UKWMS (SSO)
    await ssoTab.click()
    await expect(page.getByPlaceholder(/0715088201/i)).toBeVisible()
    await expect(page.locator('text=Masuk dengan Akun Pegawai')).toBeVisible()
  })

  test('validasi HTML5 aktif jika form dikirim tanpa kredensial', async ({ page }) => {
    const usernameInput = page.getByPlaceholder(/0715088201/i)
    const submitBtn = page.getByRole('button', { name: /Masuk ke Sistem/i })

    // Cek atribut required pada input username dan password
    await expect(usernameInput).toHaveAttribute('required', '')
    await submitBtn.click()

    // Pastikan tombol tetap ada dan halaman tidak terlempar ke dashboard tanpa autentikasi
    await expect(submitBtn).toBeVisible()
  })
})
