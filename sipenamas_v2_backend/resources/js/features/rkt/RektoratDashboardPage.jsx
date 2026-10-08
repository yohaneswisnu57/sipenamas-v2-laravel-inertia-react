import React, { useState, useEffect } from 'react'
import { Award, Coins } from 'lucide-react'
import { rektoratApi } from '../../services/api/rektoratApi'
import { formatRupiah } from '../../utils/formatters'
import { StatCard } from '../../components/common/StatCard'
import { Card, CardHeader, CardContent } from '../../components/ui/Card'

const FACULTY_COLORS = ['#188ae2', '#10c469', '#ff5b5b', '#f9c851', '#5b69bc', '#35b8e0', '#313a46', '#e83e8c']

export default function RektoratDashboardPage() {
  const [data, setData] = useState(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    async function load() {
      setIsLoading(true)
      try {
        const res = await rektoratApi.getExecutiveDashboardStats()
        setData(res.data)
      } finally {
        setIsLoading(false)
      }
    }
    load()
  }, [])

  if (isLoading) {
    return (
      <div className="py-16 text-center text-xs text-[#98a6ad]">
        <div className="w-7 h-7 border-2 border-[#188ae2] border-t-transparent rounded-full animate-spin mx-auto mb-2" />
        Memuat dasbor eksekutif Rektorat...
      </div>
    )
  }

  return (
    <div className="space-y-6 text-left">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
              Executive Dashboard Pimpinan Universitas (Rektorat)
            </h4>
            <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-[#5b69bc]/10 text-[#5b69bc] border border-[#5b69bc]/20">
              Portal Rektorat
            </span>
          </div>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Pemantauan makro produktivitas tridharma, serapan total dana riset, dan dampak program MBKM
          </p>
        </div>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <StatCard
          label="Total Dana Riset Universitas"
          value={formatRupiah(data?.totalDanaAll)}
          subtext="Seluruh fakultas UKWMS"
          icon={Coins}
          iconColor="text-[#10c469] bg-[#10c469]/10"
          trend="Serapan 84%"
          trendType="up"
          progress={84}
          progressColor="bg-[#10c469]"
        />
        <StatCard
          label="Total Judul Riset Terdaftar"
          value={data?.totalPenelitian || 0}
          subtext="Seluruh fakultas UKWMS"
          icon={Award}
          iconColor="text-[#5b69bc] bg-[#5b69bc]/10"
          trend="+18 judul YoY"
          trendType="up"
          progress={75}
          progressColor="bg-[#5b69bc]"
        />
      </div>

      {/* Analytics Charts & Sebaran Visuals */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
        {/* Sebaran Fakultas */}
        <div className="lg:col-span-6">
          <Card>
            <CardHeader
              title="Distribusi Riset & Dana per Fakultas"
              subtitle="Proporsi judul penelitian dan alokasi dana per fakultas"
            />
            <CardContent className="space-y-4">
              {data?.distributionByFaculty?.map((fak, idx) => {
                const pct = data.totalDanaAll > 0 ? Math.round((fak.dana / data.totalDanaAll) * 100) : 0
                return (
                  <div key={idx} className="space-y-1.5 text-xs">
                    <div className="flex justify-between items-center">
                      <span className="font-semibold text-[#313a46]">{fak.fakultas}</span>
                      <span className="font-tabular font-bold text-[#313a46]">
                        {fak.jumlah} judul ({formatRupiah(fak.dana)})
                      </span>
                    </div>
                    <div className="w-full h-2 bg-[#e7e9eb] rounded-full overflow-hidden">
                      <div
                        className="h-full rounded-full transition-all duration-500"
                        style={{ width: `${pct}%`, backgroundColor: FACULTY_COLORS[idx % FACULTY_COLORS.length] }}
                      />
                    </div>
                  </div>
                )
              })}
            </CardContent>
          </Card>
        </div>

        {/* Multi-Year Trend */}
        <div className="lg:col-span-6">
          <Card>
            <CardHeader
              title="Tren Produktivitas Riset Tahunan (2023 - 2026)"
              subtitle="Pertumbuhan jumlah usulan lolos pendanaan dan volume dana"
            />
            <CardContent className="space-y-4">
              <div className="space-y-3 text-xs">
                {data?.trendTahunan?.map((tr, idx) => (
                  <div key={idx} className="p-3 bg-[#f6f7fb] border border-[#e7e9eb] rounded-xl flex items-center justify-between">
                    <div>
                      <span className="font-bold text-sm font-heading text-[#313a46]">{tr.tahun}</span>
                      <span className="text-[11px] text-[#98a6ad] block mt-0.5">
                        {tr.lolos} dari {tr.totalUsulan} usulan disetujui
                      </span>
                    </div>
                    <div className="text-right">
                      <span className="font-tabular font-bold text-[#188ae2] text-sm">
                        {formatRupiah(tr.totalDana)}
                      </span>
                      <span className="text-[10px] text-[#10c469] font-semibold block">
                        {tr.totalUsulan > 0 ? Math.round((tr.lolos / tr.totalUsulan) * 100) : 0}% Lolos
                      </span>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}
