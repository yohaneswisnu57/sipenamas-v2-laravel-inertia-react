# Adminto Layouts & Navigation Blueprints

Dokumen ini memuat blueprint arsitektur tata letak (Vertical & Horizontal Layout), TopBar, LeftSideBar, dan Footer standar tema Adminto.

---

## 1. Struktur Wrapper Layout (VerticalLayout)

```tsx
import { ReactNode, Suspense } from 'react'
import { Container } from 'react-bootstrap'

type LayoutProps = {
  children: ReactNode
}

export const VerticalLayout = ({ children }: LayoutProps) => {
  return (
    <div className="wrapper">
      {/* Top Navigation Bar */}
      <header className="app-topbar">
        {/* Topbar Content */}
      </header>

      {/* Left Sidebar Menu */}
      <aside className="app-sidebar">
        {/* Sidebar Content */}
      </aside>

      {/* Main Page Content */}
      <div className="page-content">
        <Container fluid>
          {children}
        </Container>
        
        {/* Footer */}
        <footer className="footer">
          <Container fluid>
            <div className="row">
              <div className="col-md-6 text-muted fs-13">
                {new Date().getFullYear()} &copy; Adminto - All Rights Reserved.
              </div>
              <div className="col-md-6 text-md-end text-muted fs-13">
                Design & Developed by Coderthemes
              </div>
            </div>
          </Container>
        </footer>
      </div>
    </div>
  )
}

export default VerticalLayout
```

---

## 2. TopBar Blueprint

```tsx
import { Dropdown, Form } from 'react-bootstrap'
import { Icon } from '@iconify/react'

export const TopBar = ({ onToggleSidebar }: { onToggleSidebar: () => void }) => {
  return (
    <div className="navbar-custom d-flex justify-content-between align-items-center px-3">
      {/* Left Side: Sidebar Toggle & Search */}
      <div className="d-flex align-items-center gap-2">
        <button
          type="button"
          className="btn btn-sm btn-light d-flex align-items-center justify-content-center p-2 rounded-circle"
          onClick={onToggleSidebar}
        >
          <Icon icon="ri:menu-2-line" className="fs-18" />
        </button>

        <div className="app-search d-none d-lg-block" style={{ width: '240px' }}>
          <Form.Control
            type="search"
            placeholder="Search..."
            className="form-control-sm rounded-pill"
          />
        </div>
      </div>

      {/* Right Side: Theme Mode, Notifications, Profile Dropdown */}
      <div className="d-flex align-items-center gap-2">
        {/* Notifications */}
        <Dropdown>
          <Dropdown.Toggle as="a" className="btn btn-sm btn-light rounded-circle p-2 cursor-pointer position-relative">
            <Icon icon="ri:notification-3-line" className="fs-18" />
            <span className="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger fs-10">
              3
            </span>
          </Dropdown.Toggle>
          <Dropdown.Menu align="end" className="dropdown-menu-lg p-0">
            <div className="p-3 border-bottom border-dashed">
              <h5 className="m-0 fs-14 fw-semibold">Notifications</h5>
            </div>
            <div className="p-2">
              <p className="text-muted fs-12 mb-0 text-center">No new alerts</p>
            </div>
          </Dropdown.Menu>
        </Dropdown>

        {/* Profile Dropdown */}
        <Dropdown>
          <Dropdown.Toggle as="a" className="d-flex align-items-center gap-2 text-dark text-decoration-none cursor-pointer">
            <div className="avatar-xs rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold">
              U
            </div>
            <span className="d-none d-md-inline-block fs-13 fw-semibold">User Name</span>
          </Dropdown.Toggle>
          <Dropdown.Menu align="end">
            <Dropdown.Item className="d-flex align-items-center gap-2">
              <Icon icon="ri:user-3-line" /> Profile
            </Dropdown.Item>
            <Dropdown.Item className="d-flex align-items-center gap-2">
              <Icon icon="ri:settings-3-line" /> Settings
            </Dropdown.Item>
            <Dropdown.Divider />
            <Dropdown.Item className="d-flex align-items-center gap-2 text-danger">
              <Icon icon="ri:logout-box-r-line" /> Logout
            </Dropdown.Item>
          </Dropdown.Menu>
        </Dropdown>
      </div>
    </div>
  )
}
```

---

## 3. LeftSidebar Navigation Blueprint

```tsx
import { Icon } from '@iconify/react'
import { Link, useLocation } from 'react-router-dom'

type MenuItem = {
  key: string
  label: string
  icon?: string
  url: string
  badge?: { text: string; variant: string }
}

const MENU_ITEMS: MenuItem[] = [
  { key: 'dashboard', label: 'Dashboard', icon: 'ri:dashboard-2-line', url: '/dashboard' },
  { key: 'users', label: 'Users', icon: 'ri:user-line', url: '/users' },
  { key: 'settings', label: 'Settings', icon: 'ri:settings-line', url: '/settings' },
]

export const LeftSideBar = () => {
  const location = useLocation()

  return (
    <div className="app-sidebar-menu">
      <div className="logo-box p-3 text-center">
        <h4 className="fw-bold text-primary mb-0">ADMINTO</h4>
      </div>

      <ul className="side-nav list-unstyled mb-0">
        <li className="side-nav-title px-3 py-2 fs-11 text-uppercase text-muted fw-bold">Navigation</li>
        {MENU_ITEMS.map((item) => {
          const isActive = location.pathname === item.url
          return (
            <li key={item.key} className={`side-nav-item ${isActive ? 'active' : ''}`}>
              <Link
                to={item.url}
                className={`side-nav-link d-flex align-items-center gap-2 px-3 py-2 text-decoration-none ${
                  isActive ? 'text-primary fw-semibold bg-primary-subtle' : 'text-muted'
                }`}
              >
                {item.icon && <Icon icon={item.icon} className="fs-18" />}
                <span>{item.label}</span>
                {item.badge && (
                  <span className={`badge ms-auto bg-${item.badge.variant}-subtle text-${item.badge.variant}`}>
                    {item.badge.text}
                  </span>
                )}
              </Link>
            </li>
          )
        })}
      </ul>
    </div>
  )
}
```
