import React from 'react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function Button({
  children,
  variant = 'primary',
  size = 'md',
  className = '',
  disabled = false,
  isLoading = false,
  iconLeft: IconLeft,
  iconRight: IconRight,
  type = 'button',
  onClick,
  ...props
}) {
  const baseStyles =
    'inline-flex items-center justify-center font-medium rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed select-none'

  const variants = {
    primary:
      'bg-[#188ae2] text-white hover:bg-[#1272be] active:bg-[#0f60a0] focus:ring-[#188ae2]/30 shadow-xs font-medium',
    brand:
      'bg-[#5b69bc] text-white hover:bg-[#4e5aa3] active:bg-[#434d8c] focus:ring-[#5b69bc]/30 shadow-xs font-medium',
    secondary:
      'bg-white text-slate-700 border border-[#ced4da] hover:bg-slate-50 hover:border-slate-400 active:bg-slate-100 focus:ring-slate-300 shadow-2xs font-medium',
    subtle:
      'bg-slate-100 text-slate-700 hover:bg-slate-200 active:bg-slate-300 focus:ring-slate-300',
    'soft-primary':
      'bg-[#188ae2]/10 text-[#188ae2] hover:bg-[#188ae2]/20 active:bg-[#188ae2]/25 border border-[#188ae2]/20 font-medium',
    'soft-success':
      'bg-[#10c469]/10 text-[#10c469] hover:bg-[#10c469]/20 active:bg-[#10c469]/25 border border-[#10c469]/20 font-medium',
    'soft-danger':
      'bg-[#ff5b5b]/10 text-[#ff5b5b] hover:bg-[#ff5b5b]/20 active:bg-[#ff5b5b]/25 border border-[#ff5b5b]/20 font-medium',
    'soft-warning':
      'bg-[#f9c851]/15 text-[#b57a04] hover:bg-[#f9c851]/25 active:bg-[#f9c851]/30 border border-[#f9c851]/30 font-medium',
    'soft-info':
      'bg-[#35b8e0]/10 text-[#1785a2] hover:bg-[#35b8e0]/20 active:bg-[#35b8e0]/25 border border-[#35b8e0]/20 font-medium',
    danger:
      'bg-[#ff5b5b] text-white hover:bg-[#e04545] active:bg-[#c93636] focus:ring-[#ff5b5b]/30 shadow-xs font-medium',
    success:
      'bg-[#10c469] text-white hover:bg-[#0ea85a] active:bg-[#0c8f4d] focus:ring-[#10c469]/30 shadow-xs font-medium',
    ghost:
      'bg-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 focus:ring-slate-300',
    link:
      'bg-transparent text-[#188ae2] hover:underline p-0 focus:ring-0',
  }

  const sizes = {
    xs: 'text-xs px-2.5 py-1 gap-1',
    sm: 'text-xs px-3 py-1.5 gap-1.5',
    md: 'text-sm px-4 py-2 gap-2',
    lg: 'text-sm px-5 py-2.5 gap-2.5 font-semibold',
  }

  return (
    <button
      type={type}
      disabled={disabled || isLoading}
      onClick={onClick}
      className={twMerge(clsx(baseStyles, variants[variant], sizes[size], className))}
      {...props}
    >
      {isLoading ? (
        <span className="inline-block w-4 h-4 border-2 border-current border-t-transparent rounded-full animate-spin mr-1" />
      ) : IconLeft ? (
        <IconLeft className="w-4 h-4 shrink-0" />
      ) : null}
      {children}
      {!isLoading && IconRight ? <IconRight className="w-4 h-4 shrink-0" /> : null}
    </button>
  )
}
