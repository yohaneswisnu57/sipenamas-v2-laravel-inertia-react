# Adminto Form Inputs & React Hook Form Blueprints

Dokumen ini memuat blueprint untuk input form standar yang terintegrasi dengan `react-hook-form`, `react-flatpickr`, `imask`, dan styling tema Adminto.

---

## 1. TextFormInput (Input Teks Standar)

```tsx
import { Form, FormLabel, FormControl, FormText } from 'react-bootstrap'
import { Control, Controller, FieldPath, FieldValues } from 'react-hook-form'

type TextFormInputProps<TFieldValues extends FieldValues = FieldValues> = {
  name: FieldPath<TFieldValues>
  label?: string
  placeholder?: string
  type?: string
  control: Control<TFieldValues>
  containerClassName?: string
  helpText?: string
}

export const TextFormInput = <TFieldValues extends FieldValues = FieldValues>({
  name,
  label,
  placeholder,
  type = 'text',
  control,
  containerClassName = 'mb-3',
  helpText,
}: TextFormInputProps<TFieldValues>) => {
  return (
    <Controller
      name={name}
      control={control}
      render={({ field, fieldState }) => (
        <Form.Group className={containerClassName}>
          {label && <FormLabel className="fw-medium">{label}</FormLabel>}
          <FormControl
            {...field}
            type={type}
            placeholder={placeholder}
            isInvalid={Boolean(fieldState.error)}
          />
          {helpText && !fieldState.error && (
            <FormText className="text-muted fs-12">{helpText}</FormText>
          )}
          {fieldState.error && (
            <Form.Control.Feedback type="invalid">
              {fieldState.error.message}
            </Form.Control.Feedback>
          )}
        </Form.Group>
      )}
    />
  )
}

export default TextFormInput
```

---

## 2. TextAreaFormInput (Textarea)

```tsx
import { Form, FormLabel, FormControl } from 'react-bootstrap'
import { Control, Controller, FieldPath, FieldValues } from 'react-hook-form'

type TextAreaFormInputProps<TFieldValues extends FieldValues = FieldValues> = {
  name: FieldPath<TFieldValues>
  label?: string
  placeholder?: string
  rows?: number
  control: Control<TFieldValues>
  containerClassName?: string
}

export const TextAreaFormInput = <TFieldValues extends FieldValues = FieldValues>({
  name,
  label,
  placeholder,
  rows = 3,
  control,
  containerClassName = 'mb-3',
}: TextAreaFormInputProps<TFieldValues>) => {
  return (
    <Controller
      name={name}
      control={control}
      render={({ field, fieldState }) => (
        <Form.Group className={containerClassName}>
          {label && <FormLabel className="fw-medium">{label}</FormLabel>}
          <FormControl
            as="textarea"
            rows={rows}
            {...field}
            placeholder={placeholder}
            isInvalid={Boolean(fieldState.error)}
          />
          {fieldState.error && (
            <Form.Control.Feedback type="invalid">
              {fieldState.error.message}
            </Form.Control.Feedback>
          )}
        </Form.Group>
      )}
    />
  )
}

export default TextAreaFormInput
```

---

## 3. SelectFormInput & Choices Input (Dropdown & Multi-Select)

```tsx
import { Form, FormLabel, FormSelect } from 'react-bootstrap'
import { Control, Controller, FieldPath, FieldValues } from 'react-hook-form'

type OptionType = {
  value: string | number
  label: string
}

type SelectFormInputProps<TFieldValues extends FieldValues = FieldValues> = {
  name: FieldPath<TFieldValues>
  label?: string
  options: OptionType[]
  control: Control<TFieldValues>
  containerClassName?: string
  placeholder?: string
}

export const SelectFormInput = <TFieldValues extends FieldValues = FieldValues>({
  name,
  label,
  options,
  control,
  containerClassName = 'mb-3',
  placeholder = 'Select an option...',
}: SelectFormInputProps<TFieldValues>) => {
  return (
    <Controller
      name={name}
      control={control}
      render={({ field, fieldState }) => (
        <Form.Group className={containerClassName}>
          {label && <FormLabel className="fw-medium">{label}</FormLabel>}
          <FormSelect {...field} isInvalid={Boolean(fieldState.error)}>
            <option value="">{placeholder}</option>
            {options.map((opt) => (
              <option key={opt.value} value={opt.value}>
                {opt.label}
              </option>
            ))}
          </FormSelect>
          {fieldState.error && (
            <Form.Control.Feedback type="invalid">
              {fieldState.error.message}
            </Form.Control.Feedback>
          )}
        </Form.Group>
      )}
    />
  )
}
```

---

## 4. CustomFlatpickr (Date & Time Picker)

```tsx
import Flatpickr from 'react-flatpickr'
import { FormLabel, Form } from 'react-bootstrap'

type FlatpickrProps = {
  label?: string
  value?: Date | [Date, Date] | string
  onChange?: (dates: Date[]) => void
  options?: any
  placeholder?: string
  className?: string
}

export const CustomFlatpickr = ({
  label,
  value,
  onChange,
  options = { dateFormat: 'Y-m-d' },
  placeholder = 'Select date...',
  className = 'form-control',
}: FlatpickrProps) => {
  return (
    <Form.Group className="mb-3">
      {label && <FormLabel className="fw-medium">{label}</FormLabel>}
      <Flatpickr
        className={className}
        value={value}
        onChange={onChange}
        options={options}
        placeholder={placeholder}
      />
    </Form.Group>
  )
}
```

---

## 5. MaskedInput (Input Format Uang, Tanggal, No. HP via IMask)

```tsx
import { IMaskInput } from 'react-imask'
import { Form, FormLabel } from 'react-bootstrap'

type MaskedInputProps = {
  label: string
  mask: any
  placeholder?: string
  onAccept?: (value: any) => void
}

export const MaskedInput = ({ label, mask, placeholder, onAccept }: MaskedInputProps) => {
  return (
    <Form.Group className="mb-3">
      <FormLabel className="fw-medium">{label}</FormLabel>
      <IMaskInput
        mask={mask}
        placeholder={placeholder}
        onAccept={onAccept}
        className="form-control"
      />
    </Form.Group>
  )
}
```

---

## 6. File Dropzone Upload Blueprint

```tsx
import { useCallback } from 'react'
import { useDropzone } from 'react-dropzone'
import { Icon } from '@iconify/react'

type DropzoneProps = {
  onFilesAccepted: (files: File[]) => void
  label?: string
  maxFiles?: number
}

export const AdmintoDropzone = ({ onFilesAccepted, label = 'Drop files here or click to upload.', maxFiles = 5 }: DropzoneProps) => {
  const onDrop = useCallback(
    (acceptedFiles: File[]) => {
      onFilesAccepted(acceptedFiles)
    },
    [onFilesAccepted]
  )

  const { getRootProps, getInputProps, isDragActive } = useDropzone({
    onDrop,
    maxFiles,
  })

  return (
    <div
      {...getRootProps()}
      className={`dropzone border-2 border-dashed rounded p-4 text-center cursor-pointer ${
        isDragActive ? 'border-primary bg-primary-subtle' : 'border-light bg-light'
      }`}
    >
      <input {...getInputProps()} />
      <div className="mb-2">
        <Icon icon="ri:upload-cloud-2-line" className="fs-36 text-primary" />
      </div>
      <h5 className="fs-14 mb-1">{label}</h5>
      <p className="text-muted fs-12 mb-0">PDF, PNG, JPG, or DOCX (Max 10MB)</p>
    </div>
  )
}
```
