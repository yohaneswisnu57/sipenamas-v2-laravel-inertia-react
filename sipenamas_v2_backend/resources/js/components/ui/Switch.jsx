import React from 'react'
import { clsx } from 'clsx'
import { twMerge } from 'tailwind-merge'

export function Switch({ checked, onChange, disabled = false, label, className = '' }) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      aria-label={label}
      disabled={disabled}
      onClick={() => onChange?.(!checked)}
      className={twMerge(
        clsx(
          'relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors duration-150 cursor-pointer',
          'focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1 focus-visible:ring-blue-500',
          checked ? 'bg-emerald-500' : 'bg-slate-200',
          disabled && 'opacity-50 cursor-not-allowed',
          className
        )
      )}
    >
      <span
        className={clsx(
          'inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition-transform duration-150',
          checked ? 'translate-x-4.5' : 'translate-x-1'
        )}
      />
    </button>
  )
}
