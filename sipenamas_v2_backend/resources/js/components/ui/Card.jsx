import React from 'react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function Card({ children, className = '', ...props }) {
  return (
    <div
      className={twMerge(
        clsx(
          'bg-white border border-[#e7e9eb] rounded-xl shadow-[0_1px_3px_rgba(0,0,0,0.04)] overflow-hidden',
          className
        )
      )}
      {...props}
    >
      {children}
    </div>
  )
}

export function CardHeader({ children, className = '', title, subtitle, action }) {
  return (
    <div
      className={twMerge(
        clsx(
          'px-5 py-3.5 border-b border-[#eef2f7] flex items-center justify-between gap-4',
          className
        )
      )}
    >
      <div>
        {title && <h3 className="header-title text-[15px] font-semibold text-[#313a46]">{title}</h3>}
        {subtitle && <p className="text-xs text-[#8a969c] mt-0.5">{subtitle}</p>}
        {children}
      </div>
      {action && <div className="shrink-0 flex items-center gap-1.5">{action}</div>}
    </div>
  )
}

export function CardContent({ children, className = '' }) {
  return <div className={twMerge(clsx('p-5', className))}>{children}</div>
}

export function CardFooter({ children, className = '' }) {
  return (
    <div
      className={twMerge(
        clsx('px-5 py-3 bg-[#f6f7fb]/60 border-t border-[#eef2f7] flex items-center justify-between gap-3 text-xs text-[#8a969c]', className)
      )}
    >
      {children}
    </div>
  )
}
