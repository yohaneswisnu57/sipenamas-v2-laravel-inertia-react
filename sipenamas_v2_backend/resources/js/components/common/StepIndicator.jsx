import React from 'react'
import { Check } from 'lucide-react'
import { STATUS_METADATA, STATUS_USULAN } from '../../utils/constants'

export function StepIndicator({ currentStatus }) {
  const currentStep = STATUS_METADATA[currentStatus]?.step || 1

  const steps = [
    { num: 1, label: 'Pengajuan' },
    { num: 2, label: 'Dekan' },
    { num: 3, label: 'Plotting' },
    { num: 4, label: 'Review & Revisi' },
    { num: 5, label: 'Final LPPM' },
    { num: 6, label: 'Kontrak' },
    { num: 8, label: 'Monev' },
    { num: 11, label: 'Tuntas' },
  ]

  return (
    <div className="w-full py-3">
      <div className="flex items-center justify-between relative">
        {/* Connecting line */}
        <div className="absolute top-1/2 left-0 right-0 h-0.5 bg-slate-200 -translate-y-1/2 z-0" />

        {steps.map((step) => {
          const isDone = currentStep > step.num
          const isCurrent = currentStep === step.num

          return (
            <div key={step.num} className="flex flex-col items-center relative z-10">
              <div
                className={`w-7 h-7 rounded-full flex items-center justify-center text-xs font-semibold border-2 transition-colors ${
                  isDone
                    ? 'bg-emerald-600 border-emerald-600 text-white'
                    : isCurrent
                    ? 'bg-blue-600 border-blue-600 text-white ring-4 ring-blue-100'
                    : 'bg-white border-slate-300 text-slate-500'
                }`}
              >
                {isDone ? <Check className="w-4 h-4" /> : step.num}
              </div>
              <span
                className={`text-[11px] mt-1.5 whitespace-nowrap font-medium ${
                  isCurrent ? 'text-blue-700 font-bold' : isDone ? 'text-slate-800' : 'text-slate-500'
                }`}
              >
                {step.label}
              </span>
            </div>
          )
        })}
      </div>
    </div>
  )
}
