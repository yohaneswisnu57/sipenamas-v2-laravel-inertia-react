import React, { useState, useEffect, useRef } from 'react'
import { useNavigate, useLocation } from '@/lib/router'
import {
  Bell,
  Search,
  LogOut,
  CalendarCheck,
  ExternalLink,
  Menu,
  ChevronDown,
  User,
  Shield,
} from 'lucide-react'
import { RoleSwitcher } from './RoleSwitcher'
import { useAuthStore } from '../../store/authStore'
import { ROLE_LABELS } from '../../utils/constants'

export function Navbar({ onToggleSidebar }) {
  const navigate = useNavigate()
  const location = useLocation()
  const { user, logout } = useAuthStore()
  const [profileOpen, setProfileOpen] = useState(false)
  const [searchFocused, setSearchFocused] = useState(false)
  const [searchQuery, setSearchQuery] = useState('')
  const searchInputRef = useRef(null)

  // Listen for Ctrl+K or Cmd+K
  useEffect(() => {
    const handleKeyDown = (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault()
        searchInputRef.current?.focus()
      }
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [])

  const handleLogout = async () => {
    // Server menghapus session lalu mengarahkan ke /login.
    await logout()
  }

  // Generate dynamic page title based on route
  const getPageTitle = (path) => {
    if (path.includes('/dashboard')) return 'Dashboard'
    if (path.includes('/periode')) return 'Master Periode & Jadwal'
    if (path.includes('/skim')) return 'Master Skim & Rubrik'
    if (path.includes('/plotting')) return 'Plotting Reviewer'
    if (path.includes('/final-approval')) return 'Final Approval & SK'
    if (path.includes('/sinta')) return 'Ekspor SINTA Kemdikbud'
    if (path.includes('/users')) return 'Manajemen Pengguna'
    if (path.includes('/roles')) return 'Manajemen Role'
    if (path.includes('/permissions')) return 'Hak Akses & Permission'
    if (path.includes('/basisdata')) return 'Master Basis Data'
    if (path.includes('/penelitian/baru')) return 'Pengajuan Usulan Baru'
    if (path.includes('/penelitian')) return 'Daftar Penelitian'
    if (path.includes('/abdimas')) return 'Daftar Pengabdian Masyarakat'
    if (path.includes('/revisi')) return 'Unggah Revisi Proposal'
    if (path.includes('/hki')) return 'Pendaftaran HKI & Paten'
    if (path.includes('/insentif')) return 'Insentif Publikasi Jurnal'
    if (path.includes('/subsidi-apc')) return 'Subsidi APC Jurnal'
    if (path.includes('/rev/penilaian') || path.includes('/rev/review')) return 'Penilaian Proposal'
    if (path.includes('/dkn/persetujuan')) return 'Persetujuan Dekan Fakultas'
    if (path.includes('/dkn/monev')) return 'Monitoring & Evaluasi Dekan'
    if (path.includes('/rkt')) return 'Dashboard Eksekutif Rektorat'
    if (path.includes('/akr')) return 'Instrumen Akreditasi & Borang'
    if (path.includes('/ui-kit')) return 'Design System & UI Kit'
    if (path.includes('/api-explorer')) return 'Mock API Explorer'
    return 'SIPENAMAS'
  }

  const pageTitle = getPageTitle(location.pathname)
  const activeRole = user?.activeRole || user?.role

  const handleSearchSubmit = (e) => {
    if (e.key === 'Enter' && searchQuery.trim()) {
      const query = encodeURIComponent(searchQuery.trim())
      if (activeRole === 'PENELITI') {
        navigate(`/pen/penelitian?q=${query}`)
      } else {
        navigate(`/adm/dashboard?q=${query}`)
      }
    }
  }

  return (
    <header className="h-[70px] border-b border-[#e7e9eb] bg-white px-4 sm:px-6 flex items-center justify-between shrink-0 sticky top-0 z-30 shadow-[0_1px_3px_rgba(0,0,0,0.03)]">
      {/* Left: Sidebar Toggle & Page Title & Active Period */}
      <div className="flex items-center gap-3 md:gap-4">
        {/* Sidenav Toggle Button */}
        <button
          type="button"
          onClick={onToggleSidebar}
          className="p-2 rounded-lg text-[#8a969c] hover:text-[#313a46] hover:bg-[#f6f7fb] active:bg-[#eef2f7] transition-colors"
          title="Buka/Tutup Navigasi"
        >
          <Menu className="w-5 h-5" />
        </button>

        {/* Dynamic Page Title (Adminto Style) */}
        <div className="hidden sm:block">
          <h2 className="text-[17px] font-semibold text-[#313a46] tracking-tight font-heading m-0">
            {pageTitle}
          </h2>
        </div>

        {/* Active Period Pill */}
        <div className="hidden xl:flex items-center gap-2 px-3 py-1 rounded-full bg-[#188ae2]/10 text-[#188ae2] text-xs border border-[#188ae2]/20 font-medium">
          <CalendarCheck className="w-3.5 h-3.5" />
          <span className="font-semibold text-slate-800">Periode 2026-G1</span>
          <span className="text-[#188ae2]/80">&bull; Aktif s/d 31 Mar</span>
          <span className="w-2 h-2 rounded-full bg-[#10c469] animate-pulse" />
        </div>
      </div>

      {/* Center / Search Box */}
      <div className="flex-1 max-w-xs md:max-w-sm mx-4 hidden lg:block">
        <div
          className={`relative flex items-center rounded-lg border transition-all duration-150 ${
            searchFocused
              ? 'border-[#188ae2] bg-white ring-2 ring-[#188ae2]/15 shadow-xs'
              : 'border-[#e7e9eb] bg-[#f6f7fb]/80 hover:bg-[#f6f7fb]'
          }`}
        >
          <Search className="w-4 h-4 absolute left-3 text-[#8a969c]" />
          <input
            ref={searchInputRef}
            type="text"
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            onKeyDown={handleSearchSubmit}
            onFocus={() => setSearchFocused(true)}
            onBlur={() => setSearchFocused(false)}
            placeholder="Cari proposal, NIK, judul usulan... (Enter)"
            className="w-full text-xs pl-9 pr-14 py-2 bg-transparent text-[#313a46] placeholder-[#8a969c] focus:outline-none"
          />
          <kbd
            onClick={() => searchInputRef.current?.focus()}
            className="absolute right-2.5 px-1.5 py-0.5 text-[10px] font-mono font-medium text-[#8a969c] bg-white border border-[#e7e9eb] rounded shadow-2xs cursor-pointer hover:bg-slate-50"
            title="Tekan Ctrl+K atau Cmd+K untuk fokus pencarian"
          >
            ⌘K
          </kbd>
        </div>
      </div>

      {/* Right Actions: Role Switcher, SINTA, Notifications, Profile Dropdown */}
      <div className="flex items-center gap-2 sm:gap-3">
        {/* Role Switcher */}
        <RoleSwitcher />

        {/* SINTA External Portal */}
        <a
          href="https://sinta.kemdikbud.go.id"
          target="_blank"
          rel="noreferrer"
          className="hidden md:flex items-center gap-1.5 text-xs font-medium text-[#8a969c] hover:text-[#188ae2] px-2.5 py-1.5 rounded-lg hover:bg-[#f6f7fb] transition"
          title="Buka Portal SINTA Kemdikbud"
        >
          <span>SINTA</span>
          <ExternalLink className="w-3.5 h-3.5 opacity-70" />
        </a>

        {/* Notifications */}
        <button
          className="relative p-2 rounded-lg text-[#8a969c] hover:text-[#313a46] hover:bg-[#f6f7fb] transition"
          title="Notifikasi Sistem"
        >
          <Bell className="w-4.5 h-4.5" />
          <span className="absolute top-1.5 right-1.5 flex h-4 w-4 items-center justify-center rounded-full bg-[#ff5b5b] text-[9px] font-bold text-white shadow-2xs">
            3
          </span>
        </button>

        {/* Divider */}
        <div className="h-6 w-px bg-[#e7e9eb]" />

        {/* User Profile Dropdown (Adminto Style) */}
        <div className="relative">
          <button
            onClick={() => setProfileOpen(!profileOpen)}
            className="flex items-center gap-2.5 p-1 sm:px-2 py-1 rounded-lg hover:bg-[#f6f7fb] transition text-left"
          >
            <img
              src={user?.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100'}
              alt="Avatar"
              className="w-8 h-8 rounded-full object-cover border border-[#ced4da]"
            />
            <div className="hidden md:block">
              <p className="text-xs font-semibold text-[#313a46] leading-tight font-heading">
                {user?.name?.split(' ')[0] || 'Pengguna'}
              </p>
              <p className="text-[10px] text-[#8a969c] font-medium leading-tight">
                {ROLE_LABELS[activeRole] || activeRole}
              </p>
            </div>
            <ChevronDown className="w-3.5 h-3.5 text-[#8a969c] hidden md:block" />
          </button>

          {/* Profile Dropdown Menu */}
          {profileOpen && (
            <>
              <div
                className="fixed inset-0 z-40"
                onClick={() => setProfileOpen(false)}
              />
              <div className="absolute right-0 mt-2 w-56 bg-white border border-[#e7e9eb] rounded-xl shadow-lg py-1.5 z-50 animate-in fade-in-50 zoom-in-95">
                <div className="px-4 py-2.5 border-b border-[#f1f4f8]">
                  <p className="text-[11px] font-bold uppercase tracking-wider text-[#8a969c]">
                    Selamat Datang!
                  </p>
                  <p className="text-xs font-semibold text-[#313a46] truncate mt-0.5 font-heading">
                    {user?.name || 'Pengguna LPPM'}
                  </p>
                  <p className="text-[11px] text-[#8a969c] truncate font-mono">
                    {user?.kodeperson || user?.nidn || 'UKWMS'}
                  </p>
                </div>

                <div className="py-1">
                  <button
                    onClick={() => {
                      setProfileOpen(false)
                      navigate('/adm/dashboard')
                    }}
                    className="w-full px-4 py-2 text-xs text-[#313a46] hover:bg-[#f6f7fb] hover:text-[#188ae2] flex items-center gap-2.5 transition"
                  >
                    <User className="w-4 h-4 text-[#8a969c]" />
                    <span>Profil Saya</span>
                  </button>
                </div>

                <div className="border-t border-[#f1f4f8] pt-1">
                  <button
                    onClick={handleLogout}
                    className="w-full px-4 py-2 text-xs text-[#ff5b5b] hover:bg-[#ff5b5b]/10 flex items-center gap-2.5 transition font-medium"
                  >
                    <LogOut className="w-4 h-4 text-[#ff5b5b]" />
                    <span>Keluar dari Sistem</span>
                  </button>
                </div>
              </div>
            </>
          )}
        </div>
      </div>
    </header>
  )
}
