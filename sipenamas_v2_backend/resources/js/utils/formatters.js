export function formatRupiah(amount) {
  if (amount === undefined || amount === null || isNaN(amount)) return 'Rp 0'
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(amount)
}

export function formatDate(dateString) {
  if (!dateString) return '-'
  try {
    const d = new Date(dateString)
    return new Intl.DateTimeFormat('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
    }).format(d)
  } catch {
    return dateString
  }
}

export function formatDateTime(dateString) {
  if (!dateString) return '-'
  try {
    const d = new Date(dateString)
    return new Intl.DateTimeFormat('id-ID', {
      day: 'numeric',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    }).format(d)
  } catch {
    return dateString
  }
}

export function formatPercentage(val, total) {
  if (!total || total === 0) return '0%'
  return Math.round((val / total) * 100) + '%'
}

export function truncateText(text, length = 80) {
  if (!text) return ''
  return text.length > length ? text.slice(0, length) + '…' : text
}
