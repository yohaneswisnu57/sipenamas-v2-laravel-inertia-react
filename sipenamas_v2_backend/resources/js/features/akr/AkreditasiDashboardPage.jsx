import React, { useState, useEffect } from 'react'
import { Link } from '@/lib/router'
import { Award, BookOpen, Download, Layers } from 'lucide-react'
import { akreditasiApi } from '../../services/api/akreditasiApi'
import { StatCard } from '../../components/common/StatCard'
import { Card, CardHeader, CardContent } from '../../components/ui/Card'
import { Button } from '../../components/ui/Button'

export default function AkreditasiDashboardPage() {
  const [stats, setStats] = useState(null)
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    async function load() {
      setIsLoading(true)
      try {
        const res = await akreditasiApi.getAkreditasiStats()
        setStats(res.data)
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
        Memuat data akreditasi & IKU...
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
              Dashboard Akreditasi, SPMI & IKU Riset
            </h4>
            <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-[#35b8e0]/10 text-[#35b8e0] border border-[#35b8e0]/20">
              Portal Akreditasi
            </span>
          </div>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Penyediaan data portofolio luaran penelitian untuk instrumen akreditasi program studi (LAM / BAN-PT)
          </p>
        </div>

        <Link to="/akr/export-lkps">
          <Button variant="primary" size="sm" iconLeft={Download}>
            Ekspor Tabel LKPS Excel
          </Button>
        </Link>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <StatCard
          label="Total Publikasi Terdaftar"
          value={stats?.totalPublikasi || 0}
          subtext="Seluruh pengajuan insentif publikasi"
          icon={BookOpen}
          iconColor="text-[#5b69bc] bg-[#5b69bc]/10"
          trend="+12% YoY"
          trendType="up"
          progress={78}
          progressColor="bg-[#5b69bc]"
        />
        <StatCard
          label="Publikasi Scopus"
          value={stats?.totalPublikasiScopus || 0}
          subtext="Terindeks Scopus Q1-Q4"
          icon={Award}
          iconColor="text-[#188ae2] bg-[#188ae2]/10"
          trend="Target 85%"
          trendType="up"
          progress={65}
          progressColor="bg-[#188ae2]"
        />
        <StatCard
          label="HKI & Paten Terdaftar"
          value={stats?.totalHkiPaten || 0}
          subtext="Hak Cipta & Paten DJKI"
          icon={Layers}
          iconColor="text-[#f9c851] bg-[#f9c851]/10"
          trend="+8 invensi"
          trendType="up"
          progress={82}
          progressColor="bg-[#f9c851]"
        />
      </div>

      {/* Information Box */}
      <Card>
        <CardHeader
          title="Panduan Pemenuhan Borang LKPS / LED"
          subtitle="Instrumen Kriteria 5 (Keuangan, Sarana, dan Prasarana) & Kriteria 7 (Penelitian Dosen)"
        />
        <CardContent className="space-y-3 text-xs text-[#6c757d]">
          <p className="leading-relaxed">
            Data pada portal ini dihubungkan langsung ke pangkalan data penelitian dosen di <strong className="text-[#313a46]">SIPENAMAS</strong>. Tim penyusun borang akreditasi program studi dapat langsung mengekstraksi data riwayat usulan, nominal anggaran, sumber pendanaan (internal vs eksternal), serta luaran artikel jurnal per dosen.
          </p>
          <div className="flex flex-wrap gap-3 pt-2">
            <Link to="/akr/data-mining">
              <Button variant="secondary" size="sm">
                Buka Data Mining Borang
              </Button>
            </Link>
            <Link to="/akr/export-lkps">
              <Button variant="soft-primary" size="sm">
                Generate Tabel 3.b.1 LKPS
              </Button>
            </Link>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
