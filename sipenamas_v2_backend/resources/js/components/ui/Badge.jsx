import React from 'react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function Badge({ children, variant = 'neutral', size = 'sm', pill = true, className = '', ...props }) {
  const variants = {
    neutral: 'bg-slate-100 text-slate-700 border-slate-200/80',
    primary: 'bg-[#188ae2]/10 text-[#188ae2] border-[#188ae2]/25',
    brand: 'bg-[#5b69bc]/10 text-[#5b69bc] border-[#5b69bc]/25',
    success: 'bg-[#10c469]/10 text-[#10c469] border-[#10c469]/25',
    warning: 'bg-[#f9c851]/20 text-[#ab7405] border-[#f9c851]/40',
    danger: 'bg-[#ff5b5b]/10 text-[#ff5b5b] border-[#ff5b5b]/25',
    info: 'bg-[#35b8e0]/10 text-[#168fae] border-[#35b8e0]/25',
    purple: 'bg-[#5b69bc]/10 text-[#5b69bc] border-[#5b69bc]/25',
    teal: 'bg-[#02bc9c]/10 text-[#02967d] border-[#02bc9c]/25',
    dark: 'bg-[#313a46]/10 text-[#313a46] border-[#313a46]/20',
  }

  const sizes = {
    xs: 'text-[10px] px-2 py-0.5 font-medium',
    sm: 'text-xs px-2.5 py-0.5 font-medium',
    md: 'text-xs px-3 py-1 font-semibold',
  }

  return (
    <span
      className={twMerge(
        clsx(
          'inline-flex items-center gap-1 border tracking-tight',
          pill ? 'rounded-full' : 'rounded-md',
          variants[variant],
          sizes[size],
          className
        )
      )}
      {...props}
    >
      {children}
    </span>
  )
}
