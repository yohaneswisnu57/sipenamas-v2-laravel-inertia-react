import React from 'react'
import {
  Sparkles,
  CheckCircle2,
  AlertTriangle,
  FileText,
  Coins,
  ShieldCheck,
  Send,
  Download,
  TrendingUp,
  Award,
  Layers,
  Palette,
} from 'lucide-react'
import { Button } from '../../components/ui/Button'
import { Badge } from '../../components/ui/Badge'
import { Card, CardHeader, CardContent } from '../../components/ui/Card'
import { StatCard } from '../../components/common/StatCard'
import { StatusBadge } from '../../components/common/StatusBadge'
import { StepIndicator } from '../../components/common/StepIndicator'
import { STATUS_USULAN, ROLES, ROLE_LABELS, ROLE_BADGE_COLORS } from '../../utils/constants'

export function UiKitShowcasePage() {
  return (
    <div className="space-y-8 text-left max-w-5xl mx-auto">
      {/* Header */}
      <div className="pb-3 border-b border-[#e7e9eb]">
        <div className="flex items-center gap-2 text-xs font-semibold text-[#188ae2] uppercase tracking-wider mb-1">
          <Palette className="w-4 h-4" />
          <span>Adminto Design System Showcase</span>
        </div>
        <h2 className="text-2xl font-bold tracking-tight text-[#313a46] font-heading">
          SIPENAMAS UI Kit & Token Visual
        </h2>
        <p className="text-xs text-[#8a969c] mt-1">
          Desain baru mengacu pada template referensi Adminto (Coderthemes) — Tipografi Outfit & Public Sans, Soft Palettes, dan Komponen Atomik Presisi.
        </p>
      </div>

      {/* 1. Color Palette Tokens */}
      <Card>
        <CardHeader
          title="1. Palet Warna Utama Adminto"
          subtitle="Token warna standar untuk brand, fungsionalitas status, dan aksen UI"
        />
        <CardContent>
          <div className="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3 text-center">
            <div className="p-3 rounded-xl bg-[#188ae2] text-white shadow-xs">
              <p className="text-xs font-bold font-heading">Primary</p>
              <p className="text-[10px] font-mono mt-1 opacity-80">#188ae2</p>
            </div>
            <div className="p-3 rounded-xl bg-[#5b69bc] text-white shadow-xs">
              <p className="text-xs font-bold font-heading">Brand Indigo</p>
              <p className="text-[10px] font-mono mt-1 opacity-80">#5b69bc</p>
            </div>
            <div className="p-3 rounded-xl bg-[#10c469] text-white shadow-xs">
              <p className="text-xs font-bold font-heading">Success</p>
              <p className="text-[10px] font-mono mt-1 opacity-80">#10c469</p>
            </div>
            <div className="p-3 rounded-xl bg-[#ff5b5b] text-white shadow-xs">
              <p className="text-xs font-bold font-heading">Danger</p>
              <p className="text-[10px] font-mono mt-1 opacity-80">#ff5b5b</p>
            </div>
            <div className="p-3 rounded-xl bg-[#f9c851] text-slate-900 shadow-xs">
              <p className="text-xs font-bold font-heading">Warning</p>
              <p className="text-[10px] font-mono mt-1 opacity-80">#f9c851</p>
            </div>
            <div className="p-3 rounded-xl bg-[#35b8e0] text-white shadow-xs">
              <p className="text-xs font-bold font-heading">Info</p>
              <p className="text-[10px] font-mono mt-1 opacity-80">#35b8e0</p>
            </div>
            <div className="p-3 rounded-xl bg-[#252631] text-white shadow-xs">
              <p className="text-xs font-bold font-heading">Dark Navy</p>
              <p className="text-[10px] font-mono mt-1 opacity-80">#252631</p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* 2. Stat Widgets Adminto */}
      <div>
        <div className="mb-3">
          <h3 className="header-title text-base font-semibold text-[#313a46]">
            2. Adminto Stat Cards (Widget Statistik)
          </h3>
          <p className="text-xs text-[#8a969c]">Widget metrik dengan angka Outfit, badge tren, dan mini progress bar soft</p>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
          <StatCard
            label="Total Usulan Riset"
            value="128"
            subtext="Proposal aktif periode 2026"
            icon={FileText}
            iconColor="text-[#188ae2] bg-[#188ae2]/10 border-[#188ae2]/20"
            trend={{ value: '+14.8%', label: 'vs tahun lalu', positive: true }}
            progress={72}
            progressColor="bg-[#188ae2]"
          />
          <StatCard
            label="Dana Disetujui"
            value="Rp 1,42 M"
            subtext="Serapan pagu: 78%"
            icon={Coins}
            iconColor="text-[#10c469] bg-[#10c469]/10 border-[#10c469]/20"
            trend={{ value: '78%', label: 'realisasi', positive: true }}
            progress={78}
            progressColor="bg-[#10c469]"
          />
          <StatCard
            label="Target Publikasi"
            value="64 Jurnal"
            subtext="Target luaran SINTA 1-2"
            icon={Award}
            iconColor="text-[#5b69bc] bg-[#5b69bc]/10 border-[#5b69bc]/20"
            trend={{ value: '92%', label: 'on track', positive: true }}
            progress={92}
            progressColor="bg-[#5b69bc]"
          />
        </div>
      </div>

      {/* 3. Button Variants */}
      <Card>
        <CardHeader
          title="3. Buttons (Solid & Soft Variants Adminto)"
          subtitle="Varian tombol solid, soft pastel, outline, dan sizing"
        />
        <CardContent className="space-y-5">
          <div>
            <p className="text-xs font-semibold text-[#8a969c] uppercase tracking-wider mb-2.5">Solid Variants</p>
            <div className="flex flex-wrap items-center gap-2.5">
              <Button variant="primary" size="md">Primary (#188ae2)</Button>
              <Button variant="brand" size="md">Brand Indigo (#5b69bc)</Button>
              <Button variant="success" size="md" iconLeft={CheckCircle2}>Success Action</Button>
              <Button variant="danger" size="md">Danger Action</Button>
              <Button variant="secondary" size="md">Secondary Button</Button>
              <Button variant="ghost" size="md">Ghost Action</Button>
            </div>
          </div>

          <div className="pt-4 border-t border-[#f1f4f8]">
            <p className="text-xs font-semibold text-[#8a969c] uppercase tracking-wider mb-2.5">Soft Pastel Variants (Khas Adminto)</p>
            <div className="flex flex-wrap items-center gap-2.5">
              <Button variant="soft-primary" size="md">Soft Primary</Button>
              <Button variant="soft-success" size="md" iconLeft={CheckCircle2}>Soft Success</Button>
              <Button variant="soft-danger" size="md">Soft Danger</Button>
              <Button variant="soft-warning" size="md">Soft Warning</Button>
              <Button variant="soft-info" size="md">Soft Info</Button>
            </div>
          </div>

          <div className="pt-4 border-t border-[#f1f4f8]">
            <p className="text-xs font-semibold text-[#8a969c] uppercase tracking-wider mb-2.5">Sizes & State</p>
            <div className="flex flex-wrap items-center gap-2">
              <Button variant="secondary" size="xs">Extra Small (xs)</Button>
              <Button variant="secondary" size="sm">Small (sm)</Button>
              <Button variant="secondary" size="md">Medium (md)</Button>
              <Button variant="secondary" size="lg">Large (lg)</Button>
              <Button variant="primary" size="sm" isLoading>Loading...</Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* 4. Badges */}
      <Card>
        <CardHeader
          title="4. Badges (Pill & Soft Colors)"
          subtitle="Badge ber-radius pill dengan kontras warna lembut"
        />
        <CardContent className="space-y-3">
          <div className="flex flex-wrap items-center gap-2">
            <Badge variant="primary">Primary Pill</Badge>
            <Badge variant="brand">Brand Indigo</Badge>
            <Badge variant="success">Success Pill</Badge>
            <Badge variant="danger">Danger Pill</Badge>
            <Badge variant="warning">Warning Pill</Badge>
            <Badge variant="info">Info Pill</Badge>
            <Badge variant="dark">Dark Navy</Badge>
            <Badge variant="neutral">Neutral</Badge>
          </div>
        </CardContent>
      </Card>

      {/* 5. Lifecycle Proposal Status Badges */}
      <Card>
        <CardHeader
          title="5. Proposal Lifecycle Status Badges (State Machine)"
          subtitle="Pemetaan status transisi dari dbsipenamas"
        />
        <CardContent>
          <div className="flex flex-wrap gap-2">
            {Object.values(STATUS_USULAN).map((st) => (
              <StatusBadge key={st} status={st} />
            ))}
          </div>
        </CardContent>
      </Card>

      {/* 6. Role Badges */}
      <Card>
        <CardHeader title="6. Role-Based Badges (Multi-Tier RBAC)" />
        <CardContent>
          <div className="flex flex-wrap gap-3">
            {Object.values(ROLES).map((role) => (
              <span
                key={role}
                className={`px-3 py-1 rounded-full text-xs font-semibold border ${ROLE_BADGE_COLORS[role]}`}
              >
                {ROLE_LABELS[role]} ({role})
              </span>
            ))}
          </div>
        </CardContent>
      </Card>

      {/* 7. Step Indicator */}
      <Card>
        <CardHeader title="7. Lifecycle Progress Stepper" />
        <CardContent className="space-y-6">
          <div>
            <span className="text-xs text-[#8a969c] font-semibold mb-1 block">Status: Review Proposal</span>
            <StepIndicator currentStatus={STATUS_USULAN.REVIEW} />
          </div>
          <div>
            <span className="text-xs text-[#8a969c] font-semibold mb-1 block">Status: Lolos / Terbit SK</span>
            <StepIndicator currentStatus={STATUS_USULAN.LOLOS} />
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
