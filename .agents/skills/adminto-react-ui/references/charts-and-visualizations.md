# Adminto ApexCharts & Data Visualizations Blueprints

Dokumen ini memuat blueprint opsi dan implementasi **React ApexCharts** yang menggunakan palet warna dan styling tema Adminto.

---

## 1. RadialBar Progress Chart Widget

```tsx
import ReactApexChart from 'react-apexcharts'
import { ApexOptions } from 'apexcharts'

type RadialChartProps = {
  percentage: number
  color?: string
  height?: number
}

export const AdmintoRadialProgress = ({ percentage, color = '#188ae2', height = 85 }: RadialChartProps) => {
  const chartOpts: ApexOptions = {
    series: [percentage],
    chart: {
      type: 'radialBar',
      height: height,
      sparkline: { enabled: true },
    },
    plotOptions: {
      radialBar: {
        hollow: { size: '55%' },
        dataLabels: {
          name: { show: false },
          value: {
            offsetY: 4,
            fontSize: '13px',
            fontWeight: '600',
            formatter: (val) => `${val}%`,
          },
        },
      },
    },
    colors: [color],
  }

  return (
    <ReactApexChart
      options={chartOpts}
      series={chartOpts.series}
      type="radialBar"
      height={height}
    />
  )
}
```

---

## 2. Revenue / Sales Area Chart

```tsx
import ReactApexChart from 'react-apexcharts'
import { ApexOptions } from 'apexcharts'

type AreaChartProps = {
  categories: string[]
  seriesData: { name: string; data: number[] }[]
  height?: number
}

export const AdmintoAreaChart = ({
  categories = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul'],
  seriesData = [
    { name: 'Current Period', data: [31, 40, 28, 51, 42, 109, 100] },
    { name: 'Previous Period', data: [11, 32, 45, 32, 34, 52, 41] },
  ],
  height = 320,
}: AreaChartProps) => {
  const options: ApexOptions = {
    chart: {
      type: 'area',
      height: height,
      toolbar: { show: false },
    },
    stroke: { curve: 'smooth', width: 2 },
    fill: {
      type: 'gradient',
      gradient: {
        shadeIntensity: 1,
        opacityFrom: 0.4,
        opacityTo: 0.05,
        stops: [0, 90, 100],
      },
    },
    xaxis: { categories: categories },
    colors: ['#188ae2', '#5b69bc'], // Primary & Purple
    legend: { position: 'top', horizontalAlign: 'right' },
    grid: {
      borderColor: '#e7e9eb',
      strokeDashArray: 4,
    },
  }

  return (
    <ReactApexChart
      options={options}
      series={seriesData}
      type="area"
      height={height}
    />
  )
}
```

---

## 3. Donut / Pie Status Distribution Chart

```tsx
import ReactApexChart from 'react-apexcharts'
import { ApexOptions } from 'apexcharts'

type DonutChartProps = {
  labels: string[]
  series: number[]
  height?: number
}

export const AdmintoDonutChart = ({
  labels = ['Direct', 'Affiliate', 'Sponsored', 'Organic'],
  series = [44, 55, 41, 17],
  height = 280,
}: DonutChartProps) => {
  const options: ApexOptions = {
    chart: { type: 'donut', height: height },
    labels: labels,
    colors: ['#188ae2', '#10c469', '#f9c851', '#ff5b5b'], // Primary, Success, Warning, Danger
    legend: { position: 'bottom' },
    plotOptions: {
      pie: {
        donut: {
          size: '70%',
        },
      },
    },
    dataLabels: { enabled: false },
  }

  return (
    <ReactApexChart
      options={options}
      series={series}
      type="donut"
      height={height}
    />
  )
}
```
