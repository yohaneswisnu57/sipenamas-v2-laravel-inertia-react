import React, { useEffect, useMemo } from 'react'
import { Link as InertiaLink, router, usePage } from '@inertiajs/react'

/**
 * Lapisan kompatibilitas react-router-dom di atas Inertia.
 *
 * Halaman hasil migrasi dari SPA masih memakai API react-router (Link, NavLink,
 * useNavigate, useParams, useSearchParams, useLocation, Navigate). Modul ini
 * meniru API itu memakai router Inertia, sehingga halaman cukup mengganti path
 * import. Semua path internal ditulis tanpa prefix ('/pen/dashboard'); prefix
 * deploy (mis. '/refactor/inertia') ditambahkan otomatis lewat url().
 */

/** Prefix aplikasi, diisi app.blade.php dari request()->getBaseUrl(). */
export function basePath() {
  return (typeof window !== 'undefined' && window.__SIPENAMAS_BASE__) || ''
}

/** Tambahkan prefix deploy ke path internal yang diawali '/'. */
export function url(to) {
  if (typeof to !== 'string' || !to.startsWith('/') || to.startsWith('//')) {
    return to
  }
  const base = basePath()
  if (!base || to === base || to.startsWith(`${base}/`) || to.startsWith(`${base}?`)) {
    return to
  }
  return `${base}${to}`
}

/** Buang prefix deploy dari path, kebalikan url(). */
export function stripBase(path) {
  const base = basePath()
  if (base && (path === base || path.startsWith(`${base}/`))) {
    return path.slice(base.length) || '/'
  }
  return path || '/'
}

function toHref(to) {
  if (to && typeof to === 'object') {
    return url(`${to.pathname || ''}${to.search || ''}${to.hash || ''}`)
  }
  return url(to)
}

export function useLocation() {
  const { url: pageUrl } = usePage()
  return useMemo(() => {
    const [pathAndSearch, hash = ''] = (pageUrl || '/').split('#')
    const [path, search = ''] = pathAndSearch.split('?')
    return {
      pathname: stripBase(path),
      search: search ? `?${search}` : '',
      hash: hash ? `#${hash}` : '',
      state: null,
    }
  }, [pageUrl])
}

/** Parameter route dibagikan server lewat shared prop `routeParams`. */
export function useParams() {
  return usePage().props.routeParams || {}
}

export function useNavigate() {
  return (to, options = {}) => {
    if (typeof to === 'number') {
      window.history.go(to)
      return
    }
    router.visit(toHref(to), { replace: !!options.replace })
  }
}

/**
 * Query string dikelola di klien (router.replace/push tanpa request ke server),
 * sama seperti perilaku setSearchParams react-router.
 */
export function useSearchParams() {
  const location = useLocation()
  const params = useMemo(() => new URLSearchParams(location.search), [location.search])

  const setSearchParams = (next, options = {}) => {
    const value = typeof next === 'function' ? next(new URLSearchParams(location.search)) : next
    const qs = new URLSearchParams(value).toString()
    const href = url(`${location.pathname}${qs ? `?${qs}` : ''}`)
    const visit = { url: href, preserveState: true, preserveScroll: true }
    if (options.replace) {
      router.replace(visit)
    } else {
      router.push(visit)
    }
  }

  return [params, setSearchParams]
}

export function Link({ to, replace, state, ...rest }) {
  return <InertiaLink href={toHref(to)} replace={replace} {...rest} />
}

export function NavLink({ to, end, className, style, children, ...rest }) {
  const { pathname } = useLocation()
  const target = typeof to === 'string' ? to.split('?')[0] : to?.pathname || '/'
  const isActive = end || target === '/'
    ? pathname === target
    : pathname === target || pathname.startsWith(`${target}/`)

  const resolvedClassName = typeof className === 'function' ? className({ isActive }) : className
  const resolvedStyle = typeof style === 'function' ? style({ isActive }) : style
  const resolvedChildren = typeof children === 'function' ? children({ isActive }) : children

  return (
    <InertiaLink
      href={toHref(to)}
      className={resolvedClassName}
      style={resolvedStyle}
      aria-current={isActive ? 'page' : undefined}
      {...rest}
    >
      {resolvedChildren}
    </InertiaLink>
  )
}

export function Navigate({ to, replace }) {
  useEffect(() => {
    router.visit(toHref(to), { replace: !!replace })
  }, [to, replace])
  return null
}
