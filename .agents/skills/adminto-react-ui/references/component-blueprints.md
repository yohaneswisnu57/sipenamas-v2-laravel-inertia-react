# Adminto React Component Blueprints

Dokumen ini memuat blueprint kode React (TypeScript/JavaScript) siap pakai untuk komponen-komponen standar tema Adminto.

---

## 1. ComponentContainerCard (Pembungkus Standar Kartu)

```tsx
import { ReactNode } from 'react'
import { Card, CardBody, CardHeader, Dropdown, DropdownToggle, DropdownMenu, DropdownItem } from 'react-bootstrap'
import { Icon } from '@iconify/react'

type ContainerCardProps = {
  title: string
  description?: ReactNode
  children: ReactNode
  menuActions?: { label: string; onClick?: () => void }[]
}

export const ComponentContainerCard = ({ title, description, children, menuActions }: ContainerCardProps) => {
  return (
    <Card>
      <CardHeader className="d-flex justify-content-between align-items-center border-0 border-bottom border-dashed">
        <h4 className="header-title">{title}</h4>
        {menuActions && (
          <Dropdown>
            <DropdownToggle as="a" className="drop-arrow-none card-drop cursor-pointer">
              <Icon icon="ri:more-2-fill" className="fs-18 text-muted" />
            </DropdownToggle>
            <DropdownMenu align="end">
              {menuActions.map((action, idx) => (
                <DropdownItem key={idx} onClick={action.onClick}>
                  {action.label}
                </DropdownItem>
              ))}
            </DropdownMenu>
          </Dropdown>
        )}
      </CardHeader>
      <CardBody>
        {description && <p className="text-muted fs-13 mb-3">{description}</p>}
        {children}
      </CardBody>
    </Card>
  )
}

export default ComponentContainerCard
```

---

## 2. Statistics Widget Card

```tsx
import { Card, CardBody } from 'react-bootstrap'
import { Icon } from '@iconify/react'

type StatWidgetProps = {
  title: string
  value: string | number
  growth: {
    percentage: string
    isPositive: boolean
  }
  icon: string
  variant?: 'primary' | 'success' | 'danger' | 'warning' | 'info' | 'purple'
}

export const StatWidgetCard = ({ title, value, growth, icon, variant = 'primary' }: StatWidgetProps) => {
  return (
    <Card>
      <CardBody>
        <div className="d-flex justify-content-between align-items-start">
          <div>
            <p className="text-muted text-uppercase fs-12 fw-bold mb-2">{title}</p>
            <h3 className="mb-0 fw-semibold text-dark">{value}</h3>
          </div>
          <div className={`avatar-sm rounded bg-${variant}-subtle text-${variant} d-flex align-items-center justify-content-center`}>
            <Icon icon={icon} className="fs-22" />
          </div>
        </div>
        <div className="border-top border-dashed mt-3 pt-2 d-flex align-items-center">
          <span className={`me-1 d-inline-flex align-items-center ${growth.isPositive ? 'text-success' : 'text-danger'}`}>
            <Icon icon={growth.isPositive ? 'ri:arrow-up-line' : 'ri:arrow-down-line'} className="me-1" />
            {growth.percentage}
          </span>
          <span className="text-muted fs-12">Since last month</span>
        </div>
      </CardBody>
    </Card>
  )
}

export default StatWidgetCard
```

---

## 3. PageBreadcrumb (Header Halaman & Breadcrumb)

```tsx
import { Row, Col, Breadcrumb } from 'react-bootstrap'
import { Link } from 'react-router-dom'

type BreadcrumbProps = {
  title: string
  subName?: string
}

export const PageBreadcrumb = ({ title, subName }: BreadcrumbProps) => {
  return (
    <Row className="mb-3">
      <Col xs={12}>
        <div className="page-title-box d-flex align-items-center justify-content-between">
          <h4 className="page-title fs-18 fw-semibold mb-0">{title}</h4>
          <Breadcrumb className="m-0">
            <Breadcrumb.Item linkAs={Link} linkProps={{ to: '/' }}>
              Adminto
            </Breadcrumb.Item>
            {subName && (
              <Breadcrumb.Item linkAs={Link} linkProps={{ to: '#' }}>
                {subName}
              </Breadcrumb.Item>
            )}
            <Breadcrumb.Item active>{title}</Breadcrumb.Item>
          </Breadcrumb>
        </div>
      </Col>
    </Row>
  )
}

export default PageBreadcrumb
```

---

## 4. TanStack ReactTable Blueprint (Advanced Data Table)

```tsx
import { useState } from 'react'
import {
  ColumnDef,
  flexRender,
  getCoreRowModel,
  getPaginationRowModel,
  getFilteredRowModel,
  getSortedRowModel,
  useReactTable,
} from '@tanstack/react-table'
import { Table, Button, Form, Pagination } from 'react-bootstrap'
import { Icon } from '@iconify/react'

type ReactTableProps<T> = {
  data: T[]
  columns: ColumnDef<T, any>[]
  pageSize?: number
  isSearchable?: boolean
}

export function AdmintoReactTable<T>({
  data,
  columns,
  pageSize = 5,
  isSearchable = true,
}: ReactTableProps<T>) {
  const [globalFilter, setGlobalFilter] = useState('')

  const table = useReactTable({
    data,
    columns,
    state: { globalFilter },
    onGlobalFilterChange: setGlobalFilter,
    getCoreRowModel: getCoreRowModel(),
    getPaginationRowModel: getPaginationRowModel(),
    getFilteredRowModel: getFilteredRowModel(),
    getSortedRowModel: getSortedRowModel(),
    initialState: { pagination: { pageSize } },
  })

  return (
    <div className="table-responsive">
      {isSearchable && (
        <div className="d-flex justify-content-between align-items-center mb-3">
          <div style={{ width: '250px' }}>
            <Form.Control
              type="search"
              placeholder="Search records..."
              value={globalFilter ?? ''}
              onChange={(e) => setGlobalFilter(e.target.value)}
              className="form-control-sm"
            />
          </div>
        </div>
      )}

      <Table hover className="table-centered table-nowrap mb-0">
        <thead className="table-light">
          {table.getHeaderGroups().map((headerGroup) => (
            <tr key={headerGroup.id}>
              {headerGroup.headers.map((header) => (
                <th
                  key={header.id}
                  onClick={header.column.getToggleSortingHandler()}
                  className={header.column.getCanSort() ? 'cursor-pointer select-none' : ''}
                >
                  {flexRender(header.column.columnDef.header, header.getContext())}
                  {header.column.getIsSorted() === 'asc' ? ' 🔼' : header.column.getIsSorted() === 'desc' ? ' 🔽' : ''}
                </th>
              ))}
            </tr>
          ))}
        </thead>
        <tbody>
          {table.getRowModel().rows.map((row) => (
            <tr key={row.id}>
              {row.getVisibleCells().map((cell) => (
                <td key={cell.id}>{flexRender(cell.column.columnDef.cell, cell.getContext())}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </Table>

      {/* Pagination Controls */}
      <div className="d-flex justify-content-between align-items-center mt-3">
        <div className="text-muted fs-13">
          Showing {table.getRowModel().rows.length} of {data.length} entries
        </div>
        <Pagination className="mb-0 pagination-sm">
          <Pagination.Prev
            onClick={() => table.previousPage()}
            disabled={!table.getCanPreviousPage()}
          />
          <Pagination.Next
            onClick={() => table.nextPage()}
            disabled={!table.getCanNextPage()}
          />
        </Pagination>
      </div>
    </div>
  )
}
```

---

## 5. Modal Dialog Standar Adminto

```tsx
import { Modal, Button } from 'react-bootstrap'

type AdmintoModalProps = {
  show: boolean
  onHide: () => void
  title: string
  children: React.ReactNode
  onSave?: () => void
  saveText?: string
}

export const AdmintoModal = ({ show, onHide, title, children, onSave, saveText = 'Save Changes' }: AdmintoModalProps) => {
  return (
    <Modal show={show} onHide={onHide} centered>
      <Modal.Header closeButton className="border-bottom border-dashed">
        <Modal.Title as="h5" className="header-title m-0">
          {title}
        </Modal.Title>
      </Modal.Header>
      <Modal.Body>{children}</Modal.Body>
      <Modal.Footer className="border-top border-dashed">
        <Button variant="light" size="sm" onClick={onHide}>
          Cancel
        </Button>
        {onSave && (
          <Button variant="primary" size="sm" onClick={onSave}>
            {saveText}
          </Button>
        )}
      </Modal.Footer>
    </Modal>
  )
}
```

---

## 6. Spinners & Progress Indicators

```tsx
import { Spinner, ProgressBar } from 'react-bootstrap'

export const AdmintoSpinners = () => {
  return (
    <div className="d-flex align-items-center gap-2">
      <Spinner animation="border" variant="primary" size="sm" />
      <Spinner animation="grow" variant="success" size="sm" />
    </div>
  )
}

export const AdmintoProgressBar = ({ now, variant = 'primary' }: { now: number; variant?: string }) => {
  return (
    <ProgressBar now={now} variant={variant} className="progress-sm mb-2" />
  )
}
```
