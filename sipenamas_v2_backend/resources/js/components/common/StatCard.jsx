import React from 'react'
import { Card } from '../ui/Card'

export function StatCard({
  label,
  value,
  subtext,
  icon: Icon,
  iconColor = 'text-[#188ae2] bg-[#188ae2]/10 border-[#188ae2]/20',
  trend,
  progress = null,
  progressColor = 'bg-[#188ae2]',
}) {
  return (
    <Card className="p-5 hover:border-[#ced4da] transition-all duration-200">
      <div className="flex items-start justify-between gap-3">
        <div className="flex-1 min-w-0">
          <p className="text-[11px] font-bold text-[#8a969c] uppercase tracking-wider">{label}</p>
          <h3 className="text-2xl font-bold text-[#313a46] mt-1.5 font-heading tracking-tight font-tabular truncate">
            {value}
          </h3>
          {subtext && <p className="text-xs text-[#8a969c] mt-1">{subtext}</p>}
        </div>
        {Icon && (
          <div className={`w-11 h-11 rounded-xl flex items-center justify-center border shrink-0 ${iconColor}`}>
            <Icon className="w-5 h-5" />
          </div>
        )}
      </div>

      {trend && (
        <div className="mt-3.5 flex items-center justify-between text-xs pt-2.5 border-t border-[#f1f4f8]">
          <div className="flex items-center gap-1.5">
            <span
              className={`inline-flex items-center gap-0.5 px-2 py-0.5 rounded-full text-[11px] font-semibold ${
                trend.positive
                  ? 'bg-[#10c469]/10 text-[#10c469] border border-[#10c469]/20'
                  : 'bg-[#ff5b5b]/10 text-[#ff5b5b] border border-[#ff5b5b]/20'
              }`}
            >
              {trend.value}
            </span>
            <span className="text-[#8a969c] text-[11px]">{trend.label}</span>
          </div>
        </div>
      )}

      {progress !== null && (
        <div className="mt-3 progress-soft w-full">
          <div
            className={`h-full rounded-full ${progressColor}`}
            style={{ width: `${Math.min(100, Math.max(0, progress))}%` }}
          />
        </div>
      )}
    </Card>
  )
}
