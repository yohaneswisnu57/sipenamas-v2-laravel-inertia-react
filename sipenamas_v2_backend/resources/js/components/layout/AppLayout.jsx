import React, { useState, useEffect } from 'react'
import { useLocation } from '@/lib/router'
import { Sidebar } from './Sidebar'
import { Navbar } from './Navbar'
import { ImpersonationBanner } from './ImpersonationBanner'

export function AppLayout({ children }) {
  const [mobileSidebarOpen, setMobileSidebarOpen] = useState(false)
  const location = useLocation()

  // Auto-close mobile drawer when user navigates
  useEffect(() => {
    setMobileSidebarOpen(false)
  }, [location.pathname])

  return (
    <div className="flex h-screen overflow-hidden bg-[#f6f7fb] text-[#313a46]">
      {/* Mobile Backdrop */}
      {mobileSidebarOpen && (
        <div
          className="fixed inset-0 bg-black/40 z-35 md:hidden backdrop-blur-xs transition-opacity"
          onClick={() => setMobileSidebarOpen(false)}
        />
      )}

      {/* Desktop & Mobile Sidebar */}
      <div
        className={`fixed inset-y-0 left-0 z-40 md:static md:h-screen md:shrink-0 transform transition-transform duration-200 ease-in-out md:translate-x-0 ${
          mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full'
        }`}
      >
        <Sidebar />
      </div>

      {/* Main Content Viewport (Adminto page-content) */}
      <div className="flex-1 flex flex-col min-w-0 h-screen overflow-y-auto">
        <Navbar onToggleSidebar={() => setMobileSidebarOpen(!mobileSidebarOpen)} />
        <ImpersonationBanner />

        <main className="flex-1 p-4 sm:p-6 md:p-7 max-w-[1440px] w-full mx-auto">
          {children}
        </main>

        {/* Adminto Style Footer */}
        <footer className="px-6 py-4 border-t border-[#e7e9eb] bg-white/70 text-xs text-[#8a969c] flex flex-col sm:flex-row items-center justify-between gap-2 mt-auto shrink-0">
          <div>
            <span className="font-heading font-semibold text-slate-700">SIPENAMAS V2</span> &bull; LPPM Universitas Katolik Widya Mandala Surabaya
          </div>
          <div className="flex items-center gap-4 text-[11px]">
            <a href="https://sinta.kemdikbud.go.id" target="_blank" rel="noreferrer" className="hover:text-[#188ae2] transition">Portal SINTA</a>
            <span className="text-[#e7e9eb]">&bull;</span>
            <span className="font-mono">T.A. 2026/2027</span>
          </div>
        </footer>
      </div>
    </div>
  )
}
