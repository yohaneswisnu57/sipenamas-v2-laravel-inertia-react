# Adminto Extended UI, SweetAlert, Toasts & Auth Blueprints

Dokumen ini memuat blueprint untuk komponen UI lanjutan (Tabs, Ribbons, Avatars, SweetAlert2 / Toast notifications) dan pola halaman autentikasi/error standar Adminto.

---

## 1. Nav Tabs & Accordions

```tsx
import { Tabs, Tab, Accordion } from 'react-bootstrap'

export const AdmintoTabsExample = () => {
  return (
    <Tabs defaultActiveKey="home" className="nav-bordered mb-3">
      <Tab eventKey="home" title="Home">
        <p className="text-muted fs-13">Content for Home tab.</p>
      </Tab>
      <Tab eventKey="profile" title="Profile">
        <p className="text-muted fs-13">Content for Profile tab.</p>
      </Tab>
    </Tabs>
  )
}

export const AdmintoAccordionExample = () => {
  return (
    <Accordion defaultActiveKey="0" flush>
      <Accordion.Item eventKey="0">
        <Accordion.Header>Accordion Item #1</Accordion.Header>
        <Accordion.Body className="text-muted fs-13">
          Detail info inside accordion body with border dashed styling.
        </Accordion.Body>
      </Accordion.Item>
    </Accordion>
  )
}
```

---

## 2. Ribbons & Avatar Groups

```tsx
import { Card, CardBody } from 'react-bootstrap'

export const RibbonCard = ({ title, ribbonText, variant = 'primary' }: { title: string; ribbonText: string; variant?: string }) => {
  return (
    <Card className="ribbon-box">
      <CardBody>
        <div className={`ribbon ribbon-${variant} float-end`}>{ribbonText}</div>
        <h5 className="fs-15 mt-0">{title}</h5>
        <p className="text-muted fs-13 mb-0">Card content with top-right ribbon indicator.</p>
      </CardBody>
    </Card>
  )
}

export const AvatarGroup = () => {
  return (
    <div className="avatar-group d-flex">
      <div className="avatar-sm rounded-circle bg-primary-subtle text-primary d-flex align-items-center justify-content-center fw-bold border border-white">
        A
      </div>
      <div className="avatar-sm rounded-circle bg-success-subtle text-success d-flex align-items-center justify-content-center fw-bold border border-white ms-n2">
        B
      </div>
      <div className="avatar-sm rounded-circle bg-warning-subtle text-warning d-flex align-items-center justify-content-center fw-bold border border-white ms-n2">
        +3
      </div>
    </div>
  )
}
```

---

## 3. SweetAlert2 & Toast Notifications

```tsx
import Swal from 'sweetalert2'
import { toast } from 'react-toastify'
import { Button } from 'react-bootstrap'

export const triggerDeleteConfirm = (onConfirm: () => void) => {
  Swal.fire({
    title: 'Are you sure?',
    text: "You won't be able to revert this!",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#188ae2', // Adminto Primary
    cancelButtonColor: '#ff5b5b',  // Adminto Danger
    confirmButtonText: 'Yes, delete it!',
  }).then((result) => {
    if (result.isConfirmed) {
      onConfirm()
      toast.success('Record successfully deleted!')
    }
  })
}

export const NotificationDemo = () => {
  return (
    <div className="d-flex gap-2">
      <Button
        variant="primary"
        size="sm"
        onClick={() => toast.success('Operation completed successfully!')}
      >
        Success Toast
      </Button>
      <Button
        variant="danger"
        size="sm"
        onClick={() => triggerDeleteConfirm(() => {})}
      >
        SweetAlert Confirm
      </Button>
    </div>
  )
}
```

---

## 4. Auth Page Template (Login / Auth Card)

```tsx
import { Card, CardBody, Container, Row, Col, Button, Form } from 'react-bootstrap'
import { Link } from 'react-router-dom'

export const AdmintoAuthPage = () => {
  return (
    <div className="account-pages pt-2 pt-sm-5 pb-4 pb-sm-5 d-flex align-items-center min-vh-100 bg-light">
      <Container>
        <Row className="justify-content-center">
          <Col md={8} lg={6} xl={4}>
            <Card className="shadow-none border">
              <div className="card-header py-4 text-center bg-primary text-white">
                <h4 className="text-white fw-bold mb-0">ADMINTO</h4>
                <p className="text-white-50 fs-13 mb-0 mt-1">Sign in to your dashboard</p>
              </div>
              <CardBody className="p-4">
                <Form>
                  <Form.Group className="mb-3">
                    <Form.Label className="fw-medium">Email address</Form.Label>
                    <Form.Control type="email" placeholder="Enter your email" required />
                  </Form.Group>

                  <Form.Group className="mb-3">
                    <div className="d-flex justify-content-between align-items-center">
                      <Form.Label className="fw-medium mb-0">Password</Form.Label>
                      <Link to="/auth/recover-password" className="text-muted fs-12">
                        Forgot password?
                      </Link>
                    </div>
                    <Form.Control type="password" placeholder="Enter your password" required className="mt-1" />
                  </Form.Group>

                  <div className="d-grid mb-0 text-center">
                    <Button variant="primary" type="submit">
                      Log In
                    </Button>
                  </div>
                </Form>
              </CardBody>
            </Card>
          </Col>
        </Row>
      </Container>
    </div>
  )
}
```
