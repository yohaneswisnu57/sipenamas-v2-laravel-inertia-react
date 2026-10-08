import React, { useState, useRef, useEffect } from 'react'
import { ChevronDown, Check, ShieldCheck } from 'lucide-react'
import { useAuthStore } from '../../store/authStore'
import { ROLES, ROLE_LABELS, ROLE_BADGE_COLORS } from '../../utils/constants'

export function RoleSwitcher() {
  const [isOpen, setIsOpen] = useState(false)
  const dropdownRef = useRef(null)
  const { user, switchRole } = useAuthStore()

  useEffect(() => {
    const handleClickOutside = (e) => {
      if (dropdownRef.current && !dropdownRef.current.contains(e.target)) {
        setIsOpen(false)
      }
    }
    document.addEventListener('mousedown', handleClickOutside)
    return () => document.removeEventListener('mousedown', handleClickOutside)
  }, [])

  if (!user) return null

  const activeRole = user.activeRole || user.role || ROLES.ADMIN
  const allowedRoles = user.allowedRoles || [activeRole]

  const handleSelect = async (role) => {
    setIsOpen(false)
    // Server menyimpan peran aktif di session lalu mengarahkan ke dashboard peran itu.
    await switchRole(role)
  }

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className={`flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold border transition-all cursor-pointer select-none ${
          ROLE_BADGE_COLORS[activeRole] || 'bg-slate-100 text-slate-700 border-slate-300'
        }`}
        title="Ganti Sudut Pandang Peran (Role Switcher)"
      >
        <ShieldCheck className="w-3.5 h-3.5" />
        <span>{ROLE_LABELS[activeRole]}</span>
        <ChevronDown className="w-3 h-3 opacity-60 ml-0.5" />
      </button>

      {isOpen && (
        <div className="absolute right-0 mt-1.5 w-64 bg-white border border-slate-200 rounded-lg shadow-lg py-1.5 z-50 text-left">
          <div className="px-3 py-1.5 border-b border-slate-100">
            <p className="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">
              Pilih Portal Akses
            </p>
            <p className="text-xs text-slate-600 mt-0.5 font-medium truncate">
              {user.name}
            </p>
          </div>

          <div className="py-1">
            {allowedRoles.map((role) => {
              const isCurrent = activeRole === role
              return (
                <button
                  key={role}
                  onClick={() => handleSelect(role)}
                  className={`w-full flex items-center justify-between px-3 py-2 text-xs font-medium transition-colors text-left cursor-pointer ${
                    isCurrent
                      ? 'bg-slate-50 text-blue-700 font-semibold'
                      : 'text-slate-700 hover:bg-slate-100'
                  }`}
                >
                  <div className="flex items-center gap-2">
                    <span
                      className={`w-2 h-2 rounded-full ${
                        isCurrent ? 'bg-blue-600' : 'bg-slate-300'
                      }`}
                    />
                    <span>{ROLE_LABELS[role]}</span>
                  </div>
                  {isCurrent && <Check className="w-3.5 h-3.5 text-blue-600" />}
                </button>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}
