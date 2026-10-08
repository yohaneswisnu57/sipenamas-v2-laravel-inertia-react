import { useState, useMemo, useEffect } from 'react'

export function usePagination(items = [], pageSize = 10) {
  const [page, setPage] = useState(1)

  const totalItems = items ? items.length : 0
  const totalPages = Math.max(1, Math.ceil(totalItems / pageSize))
  const safePage = Math.max(1, Math.min(page, totalPages))

  useEffect(() => {
    if (page > totalPages) setPage(totalPages)
  }, [totalPages, page])

  const paginatedItems = useMemo(() => {
    const start = (safePage - 1) * pageSize
    return (items || []).slice(start, start + pageSize)
  }, [items, safePage, pageSize])

  return { page: safePage, setPage, totalPages, totalItems, pageSize, paginatedItems }
}
