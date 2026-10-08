import React from 'react'
import { Search, X, Filter, RotateCcw } from 'lucide-react'

/**
 * Skeleton rows for tables during loading state.
 * Generates pulsing placeholder bars that mimic real tabular data.
 */
export function TableSkeleton({ rows = 5, cols = 5 }) {
  return (
    <>
      {Array.from({ length: rows }).map((_, rIdx) => (
        <tr key={rIdx} className="animate-pulse border-b border-[#e7e9eb]">
          {Array.from({ length: cols }).map((_, cIdx) => {
            // Pseudo-random but deterministic widths based on row and col index
            const widthPct = Math.min(95, Math.max(40, 50 + ((rIdx * 23 + cIdx * 37) % 45)))
            return (
              <td key={cIdx} className="px-4 py-3.5">
                <div
                  className="h-3.5 bg-[#e7e9eb] rounded-md"
                  style={{ width: `${widthPct}%` }}
                />
              </td>
            )
          })}
        </tr>
      ))}
    </>
  )
}

/**
 * Empty state row for tables when no data matches search or filter.
 */
export function TableEmptyState({
  colSpan = 5,
  message = 'Tidak ada data ditemukan',
  submessage = 'Coba sesuaikan kata kunci pencarian atau filter yang dipilih.',
  onReset,
}) {
  return (
    <tr>
      <td colSpan={colSpan} className="px-4 py-12 text-center">
        <div className="flex flex-col items-center justify-center max-w-sm mx-auto text-left sm:text-center">
          <div className="w-12 h-12 rounded-full bg-[#f6f7fb] border border-[#e7e9eb] flex items-center justify-center mb-3">
            <Search className="w-5 h-5 text-[#98a6ad]" />
          </div>
          <p className="font-bold font-heading text-xs text-[#313a46]">{message}</p>
          {submessage && (
            <p className="text-[11.5px] text-[#98a6ad] mt-1 leading-relaxed">
              {submessage}
            </p>
          )}
          {onReset && (
            <button
              type="button"
              onClick={onReset}
              className="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-[#188ae2] hover:bg-[#188ae2]/10 rounded-lg transition-colors cursor-pointer"
            >
              <RotateCcw className="w-3.5 h-3.5" />
              <span>Reset Pencarian & Filter</span>
            </button>
          )}
        </div>
      </td>
    </tr>
  )
}

/**
 * Standard Adminto Search and Filter toolbar for data tables.
 *
 * @param {string} search - Current search string
 * @param {function} onSearchChange - Callback when search changes
 * @param {string} searchPlaceholder - Placeholder for search input
 * @param {Array} filters - Array of filter configs: [{ key, value, onChange, options: [{ value, label }], placeholder }]
 * @param {function} onReset - Optional reset all filters handler
 * @param {number} totalResults - Optional number of matching results to show
 */
export function TableFilterBar({
  search,
  searchValue,
  onSearchChange,
  searchPlaceholder = 'Cari data...',
  filters = [],
  onReset,
  totalResults,
  totalCount,
  filteredCount,
}) {
  const query = searchValue !== undefined ? searchValue : (search ?? '')
  const hasActiveFilters =
    Boolean(query) ||
    filters.some((f) => f.value && f.value !== '' && f.value !== 'ALL')

  return (
    <div className="bg-white border border-[#e7e9eb] rounded-xl p-3 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
      <div className="flex flex-1 flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
        {/* Search Input */}
        {onSearchChange && (
          <div className="relative flex-1 sm:max-w-xs">
            <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[#98a6ad]" />
            <input
              type="text"
              value={query}
              onChange={(e) => onSearchChange(e.target.value)}
              placeholder={searchPlaceholder}
              className="w-full pl-9 pr-8 py-1.5 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] placeholder:text-[#98a6ad] focus:outline-none focus:border-[#188ae2] focus:bg-white transition-colors"
            />
            {query && (
              <button
                type="button"
                onClick={() => onSearchChange('')}
                className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#98a6ad] hover:text-[#313a46] cursor-pointer"
                title="Hapus pencarian"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            )}
          </div>
        )}

        {/* Filter Dropdowns */}
        {filters.map((filter) => (
          <div key={filter.key} className="sm:max-w-xs">
            <select
              value={filter.value}
              onChange={(e) => filter.onChange(e.target.value)}
              className="w-full px-3 py-1.5 bg-[#f6f7fb] border border-[#e7e9eb] rounded-lg text-xs text-[#313a46] focus:outline-none focus:border-[#188ae2] focus:bg-white transition-colors"
            >
              {filter.placeholder && <option value="">{filter.placeholder}</option>}
              {filter.options.map((opt, optIdx) => (
                <option key={`${filter.key}-${opt.value ?? optIdx}`} value={opt.value}>
                  {opt.label}
                </option>
              ))}
            </select>
          </div>
        ))}

        {/* Reset Button if active */}
        {hasActiveFilters && onReset && (
          <button
            type="button"
            onClick={onReset}
            className="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs text-[#98a6ad] hover:text-[#ff5b5b] hover:bg-[#ff5b5b]/10 rounded-lg transition-colors cursor-pointer whitespace-nowrap"
            title="Reset semua filter"
          >
            <RotateCcw className="w-3.5 h-3.5" />
            <span>Reset</span>
          </button>
        )}
      </div>

      {/* Result counter if provided */}
      {filteredCount !== undefined ? (
        <div className="text-[#98a6ad] text-[11.5px] whitespace-nowrap self-end sm:self-center font-medium">
          Ditemukan: <strong className="text-[#313a46]">{filteredCount}</strong>
          {totalCount !== undefined && totalCount !== filteredCount && (
            <span> dari {totalCount} data</span>
          )}
        </div>
      ) : typeof totalResults === 'number' ? (
        <div className="text-[#98a6ad] text-[11.5px] whitespace-nowrap self-end sm:self-center font-medium">
          Ditemukan: <strong className="text-[#313a46]">{totalResults}</strong> data
        </div>
      ) : null}
    </div>
  )
}
