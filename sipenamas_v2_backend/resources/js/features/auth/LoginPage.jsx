import React, { useState } from 'react'
import {
  ShieldCheck,
  Building2,
  Lock,
  User,
  ArrowRight,
  Info,
  ExternalLink,
  BookOpen,
} from 'lucide-react'
import { Button } from '../../components/ui/Button'
import { Card, CardContent } from '../../components/ui/Card'
import { useAuthStore } from '../../store/authStore'
import { ROLE_LABELS } from '../../utils/constants'

export default function LoginPage() {
  const { login } = useAuthStore()

  const [activeTab, setActiveTab] = useState('sso') // 'sso' | 'external'
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [isLoading, setIsLoading] = useState(false)
  const [errorMessage, setErrorMessage] = useState('')

  const handleLogin = async (e) => {
    if (e) e.preventDefault()
    setIsLoading(true)
    setErrorMessage('')

    try {
      // Server membuat session lalu mengarahkan ke dashboard peran default.
      await login({ username, password })
    } catch (err) {
      setErrorMessage(err.message || 'Gagal masuk. Periksa NIK/NPP dan password Anda.')
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="min-h-screen bg-[#f6f7fb] text-[#313a46] flex flex-col justify-between selection:bg-[#188ae2] selection:text-white">
      {/* Top Bar Header */}
      <header className="px-6 py-4 border-b border-[#e7e9eb] bg-white flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-8 h-8 rounded-lg bg-[#188ae2] flex items-center justify-center font-bold text-white text-sm shadow-xs font-heading">
            S
          </div>
          <div>
            <span className="font-bold text-[16px] tracking-tight text-[#313a46] font-heading">
              SIPENAMAS<span className="text-[#188ae2]">.</span>
            </span>
            <span className="text-[#8a969c] text-xs ml-2 hidden sm:inline">
              Universitas Katolik Widya Mandala Surabaya
            </span>
          </div>
        </div>
        <div className="text-xs text-[#8a969c] flex items-center gap-3">
          <span className="hidden md:inline">LPPM Helpdesk: (031) 5678478</span>
          <a
            href="https://lppm.ukwms.ac.id"
            target="_blank"
            rel="noreferrer"
            className="text-[#188ae2] hover:underline flex items-center gap-1 font-medium"
          >
            Portal LPPM <ExternalLink className="w-3 h-3" />
          </a>
        </div>
      </header>

      {/* Main Login Form Container */}
      <main className="flex-1 flex items-center justify-center p-4 sm:p-6 my-6">
        <div className="w-full max-w-4xl grid grid-cols-1 md:grid-cols-12 gap-8 items-center">
          {/* Left Column: Institutional Info */}
          <div className="md:col-span-6 space-y-5 text-left">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#188ae2]/10 text-[#188ae2] border border-[#188ae2]/20 text-xs font-semibold">
              <ShieldCheck className="w-3.5 h-3.5" />
              <span>Sistem Informasi Penelitian & Abdimas</span>
            </div>

            <h2 className="text-2xl sm:text-3xl font-bold tracking-tight text-[#313a46] font-heading leading-snug">
              Manajemen Siklus Hibah & Publikasi Ilmiah UKWMS
            </h2>

            <p className="text-xs sm:text-sm text-[#8a969c] leading-relaxed">
              Selamat datang di portal SIPENAMAS generasi kedua. Dirancang untuk mempercepat pengusulan proposal, penilaian reviewer, pengesahan fakultas, dan ekspor SINTA.
            </p>

            <div className="bg-white border border-[#e7e9eb] rounded-xl p-4 text-xs space-y-2.5 shadow-2xs">
              <div className="flex items-center gap-2 font-semibold text-[#b57a04]">
                <Info className="w-4 h-4 shrink-0 text-[#f9c851]" />
                <span>Pengumuman Hibah Aktif (2026-G1)</span>
              </div>
              <p className="text-[#6c757d] text-[11px] leading-normal">
                Penerimaan usulan riset internal dibuka hingga <strong>31 Maret 2026</strong>. Pastikan Anggota Tim Dosen telah mengonfirmasi kesediaan sebelum batas akhir.
              </p>
            </div>
          </div>

          {/* Right Column: Actual Login Card (Adminto Style) */}
          <div className="md:col-span-6">
            <Card className="bg-white border-[#e7e9eb] shadow-[0_4px_12px_rgba(0,0,0,0.05)]">
              <CardContent className="p-6 sm:p-8 text-left">
                {/* Dual Tab Buttons */}
                <div className="grid grid-cols-2 p-1 bg-[#f6f7fb] rounded-lg border border-[#e7e9eb] mb-6">
                  <button
                    type="button"
                    onClick={() => {
                      setActiveTab('sso')
                      setUsername('')
                    }}
                    className={`py-2 text-xs font-semibold rounded-md transition-all cursor-pointer ${
                      activeTab === 'sso'
                        ? 'bg-white text-[#188ae2] shadow-2xs font-bold'
                        : 'text-[#8a969c] hover:text-[#313a46]'
                    }`}
                  >
                    Civitas UKWMS (SSO)
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setActiveTab('external')
                      setUsername('')
                    }}
                    className={`py-2 text-xs font-semibold rounded-md transition-all cursor-pointer ${
                      activeTab === 'external'
                        ? 'bg-white text-[#188ae2] shadow-2xs font-bold'
                        : 'text-[#8a969c] hover:text-[#313a46]'
                    }`}
                  >
                    Mitra / Reviewer Luar
                  </button>
                </div>

                <div className="mb-5">
                  <h3 className="text-lg font-bold text-[#313a46] font-heading">
                    {activeTab === 'sso' ? 'Masuk dengan Akun Pegawai' : 'Masuk Akun Eksternal'}
                  </h3>
                  <p className="text-xs text-[#8a969c] mt-0.5">
                    {activeTab === 'sso'
                      ? 'Gunakan NIK/NPP dan password portal pegawai UKWMS Anda.'
                      : 'Masukkan alamat email terdaftar bagi mitra/reviewer eksternal.'}
                  </p>
                </div>

                {errorMessage && (
                  <div className="mb-4 p-3 rounded-lg bg-[#ff5b5b]/10 border border-[#ff5b5b]/20 text-[#ff5b5b] text-xs font-medium">
                    {errorMessage}
                  </div>
                )}

                <form onSubmit={handleLogin} className="space-y-4">
                  <div>
                    <label className="block text-xs font-semibold text-[#313a46] mb-1.5 font-heading">
                      {activeTab === 'sso' ? 'NIK / NPP / Username' : 'Alamat Email Terdaftar'}
                    </label>
                    <div className="relative">
                      <User className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[#8a969c]" />
                      <input
                        type="text"
                        required
                        value={username}
                        onChange={(e) => setUsername(e.target.value)}
                        placeholder={activeTab === 'sso' ? 'Contoh: 0715088201 atau LPPM001' : 'nama@mitra.ac.id'}
                        className="w-full text-xs pl-9 pr-3 py-2.5 bg-white border border-[#ced4da] rounded-lg text-[#313a46] placeholder:text-[#8a969c] focus:outline-none focus:ring-2 focus:ring-[#188ae2]/20 focus:border-[#188ae2] transition"
                      />
                    </div>
                  </div>

                  <div>
                    <div className="flex items-center justify-between mb-1.5">
                      <label className="block text-xs font-semibold text-[#313a46] font-heading">Password</label>
                      <a href="#" className="text-[11px] text-[#188ae2] hover:underline font-medium">
                        Lupa password?
                      </a>
                    </div>
                    <div className="relative">
                      <Lock className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[#8a969c]" />
                      <input
                        type="password"
                        required
                        value={password}
                        onChange={(e) => setPassword(e.target.value)}
                        placeholder="••••••••"
                        className="w-full text-xs pl-9 pr-3 py-2.5 bg-white border border-[#ced4da] rounded-lg text-[#313a46] placeholder:text-[#8a969c] focus:outline-none focus:ring-2 focus:ring-[#188ae2]/20 focus:border-[#188ae2] transition"
                      />
                    </div>
                  </div>

                  <Button
                    type="submit"
                    variant="primary"
                    size="lg"
                    isLoading={isLoading}
                    iconRight={ArrowRight}
                    className="w-full mt-2"
                  >
                    Masuk ke Sistem
                  </Button>
                </form>
              </CardContent>
            </Card>
          </div>
        </div>
      </main>

      {/* Footer */}
      <footer className="px-6 py-4 border-t border-[#e7e9eb] bg-white text-center text-xs text-[#8a969c]">
        &copy; {new Date().getFullYear()} Lembaga Penelitian dan Pengabdian kepada Masyarakat (LPPM) — Universitas Katolik Widya Mandala Surabaya.
      </footer>
    </div>
  )
}
