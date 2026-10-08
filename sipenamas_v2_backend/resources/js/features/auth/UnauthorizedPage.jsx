import React from 'react'
import { Link } from '@/lib/router'
import { ShieldAlert, ArrowLeft } from 'lucide-react'
import { Button } from '../../components/ui/Button'
import { RoleSwitcher } from '../../components/layout/RoleSwitcher'

export default function UnauthorizedPage() {
  return (
    <div className="min-h-screen bg-[#f6f7fb] flex items-center justify-center p-6 text-left">
      <div className="max-w-md w-full text-center bg-white p-8 rounded-xl border border-[#e7e9eb] shadow-sm">
        <div className="w-14 h-14 rounded-full bg-[#ff5b5b]/10 text-[#ff5b5b] flex items-center justify-center mx-auto mb-4 border border-[#ff5b5b]/20">
          <ShieldAlert className="w-7 h-7" />
        </div>
        <h2 className="text-xl font-bold font-heading text-[#313a46] mb-1.5">403 — Akses Ditolak</h2>
        <p className="text-xs text-[#98a6ad] mb-6 leading-relaxed">
          Akun Anda saat ini tidak memiliki izin (<em>insufficient role</em>) untuk mengakses modul ini. Silakan gunakan pemilih peran di bawah jika akun Anda terdaftar pada modul lain.
        </p>

        <div className="flex justify-center mb-6">
          <RoleSwitcher />
        </div>

        <Link to="/" className="block">
          <Button variant="secondary" size="md" iconLeft={ArrowLeft} className="w-full justify-center">
            Kembali ke Beranda
          </Button>
        </Link>
      </div>
    </div>
  )
}
