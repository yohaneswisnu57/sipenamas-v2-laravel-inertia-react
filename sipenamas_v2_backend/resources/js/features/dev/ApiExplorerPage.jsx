import React, { useState } from 'react'
import { Code2, Server, ArrowRight, ShieldCheck } from 'lucide-react'
import { Card, CardHeader, CardContent } from '../../components/ui/Card'
import { Badge } from '../../components/ui/Badge'
import { TableSkeleton, TableEmptyState, TableFilterBar } from '../../components/ui/TableComponents'

export default function ApiExplorerPage() {
  const [searchQuery, setSearchQuery] = useState('')
  const [methodFilter, setMethodFilter] = useState('ALL')
  const endpoints = [
    {
      group: 'Auth API (Otentikasi)',
      items: [
        { method: 'POST', path: '/api/v1/auth/login', desc: 'Login SSO Pegawai UKWMS vs Akun Eksternal' },
        { method: 'GET', path: '/api/v1/auth/me', desc: 'Profil sesi user & daftar allowedRoles' },
        { method: 'POST', path: '/api/v1/auth/switch-role', desc: 'Ganti activeRole yang sedang dipakai' },
      ],
    },
    {
      group: 'Admin LPPM API',
      items: [
        { method: 'GET', path: '/api/v1/adm/dashboard-stats', desc: 'Statistik agregat pagu, dana usulan, dan antrean' },
        { method: 'GET', path: '/api/v1/adm/periode', desc: 'Daftar seluruh periode anggaran & status aktif' },
        { method: 'POST', path: '/api/v1/adm/periode', desc: 'Buka periode / tahun anggaran baru' },
        { method: 'POST', path: '/api/v1/adm/plotting', desc: 'Plotting Reviewer 1 & 2 ke proposal usulan' },
        { method: 'POST', path: '/api/v1/adm/final-approval', desc: 'Penetapan status lolos, dana definitif, & nomor SK' },
        { method: 'GET', path: '/api/v1/adm/sinta-export', desc: 'Payload tabel standar sinkronisasi SINTA' },
        { method: 'GET', path: '/api/v1/adm/users-rbac', desc: 'Manajemen personil & matriks izin multi-role' },
      ],
    },
    {
      group: 'Peneliti API',
      items: [
        { method: 'GET', path: '/api/v1/pen/dashboard', desc: 'Ringkasan portofolio & pengingat usulan dosen' },
        { method: 'POST', path: '/api/v1/pen/penelitian', desc: 'Submit usulan baru (Tim Dosen, Mahasiswa, RAB)' },
        { method: 'GET', path: '/api/v1/pen/penelitian/:id', desc: 'Detail usulan & lembar pengesahan ber-QR' },
        { method: 'POST', path: '/api/v1/pen/penelitian/:id/revisi/dokumen', desc: 'Unggah draft naskah revisi' },
        { method: 'POST', path: '/api/v1/pen/penelitian/:id/revisi/final', desc: 'Set final naskah revisi' },
        { method: 'GET', path: '/api/v1/pen/monev-hasil', desc: 'Daftar penugasan reviewer monev' },
        { method: 'POST', path: '/api/v1/pen/monev-hasil/:id/kesimpulan', desc: 'Simpan kesimpulan & final borang monev' },
        { method: 'GET', path: '/api/v1/pen/laporan-akhir', desc: 'Daftar penelitian lolos untuk laporan akhir' },
        { method: 'PUT', path: '/api/v1/pen/laporan-akhir/:id/capaian/:targetId', desc: 'Simpan realisasi capaian & luaran' },
        { method: 'POST', path: '/api/v1/pen/subsidi-apc', desc: 'Pengajuan bantuan biaya publikasi jurnal' },
        { method: 'POST', path: '/api/v1/pen/insentif-jurnal', desc: 'Klaim reward insentif publikasi artikel terbit' },
        { method: 'POST', path: '/api/v1/pen/hki', desc: 'Pendaftaran Hak Cipta / Paten ke Sentra HKI' },
      ],
    },
    {
      group: 'Dekan API',
      items: [
        { method: 'GET', path: '/api/v1/dkn/approval-list', desc: 'Daftar usulan dosen fakultas menunggu pengesahan' },
        { method: 'POST', path: '/api/v1/dkn/approval/:id', desc: 'Persetujuan Dekan & sematkan TTD digital' },
        { method: 'GET', path: '/api/v1/dkn/pagu-fakultas', desc: 'Monitoring alokasi dan serapan pagu per prodi' },
      ],
    },
    {
      group: 'Reviewer API',
      items: [
        { method: 'GET', path: '/api/v1/rev/penugasan', desc: 'Daftar proposal yang ditugaskan ke reviewer' },
        { method: 'POST', path: '/api/v1/rev/kesediaan/:id', desc: 'Konfirmasi kesediaan / deklarasi konflik kepentingan' },
        { method: 'POST', path: '/api/v1/rev/nilai/:id', desc: 'Simpan rubrik skor terbobot & rekomendasi dana' },
      ],
    },
    {
      group: 'Rektorat & Akreditasi API',
      items: [
        { method: 'GET', path: '/api/v1/rkt/executive-stats', desc: 'Distribusi fakultas, tren tahunan, dan serapan' },
        { method: 'GET', path: '/api/v1/akr/data-mining', desc: 'Ekstraksi borang penelitian multi-kriteria' },
        { method: 'GET', path: '/api/v1/akr/lkps-export', desc: 'Generator tabel 3.b.1 LKPS format Excel' },
      ],
    },
  ]

  return (
    <div className="space-y-6 text-left max-w-5xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#e7e9eb] gap-4">
        <div>
          <h4 className="text-xl font-bold font-heading text-[#313a46] tracking-tight">
            Katalog Kontrak Mock API (Backend Blueprint)
          </h4>
          <p className="text-xs text-[#98a6ad] mt-0.5">
            Daftar seluruh endpoint, metode HTTP, dan model data yang telah disiapkan di frontend untuk memudahkan pengembangan backend
          </p>
        </div>
      </div>

      <div className="p-4 bg-[#188ae2]/10 border border-[#188ae2]/20 rounded-xl text-xs text-[#313a46] leading-relaxed shadow-sm">
        <p className="font-bold font-heading text-[#188ae2] flex items-center gap-1.5 mb-1.5">
          <Server className="w-4 h-4" />
          <span>Arsitektur Service Terpisah:</span>
        </p>
        Seluruh panggilan data di frontend dibungkus di dalam folder <code className="bg-white text-[#188ae2] px-2 py-0.5 rounded-md font-mono border border-[#188ae2]/20">src/services/api/</code>. Ketika backend Anda (Go / Laravel / Express) telah siap, cukup ubah <code className="bg-white text-[#188ae2] px-2 py-0.5 rounded-md font-mono border border-[#188ae2]/20">USE_MOCK = false</code> di <code className="bg-white text-[#188ae2] px-2 py-0.5 rounded-md font-mono border border-[#188ae2]/20">src/services/api/apiClient.js</code> dan sesuaikan <code className="bg-white text-[#188ae2] px-2 py-0.5 rounded-md font-mono border border-[#188ae2]/20">VITE_API_BASE_URL</code> tanpa perlu menyentuh komponen UI halaman!
      </div>

      {/* Filter toolbar */}
      <TableFilterBar
        searchValue={searchQuery}
        onSearchChange={setSearchQuery}
        searchPlaceholder="Cari endpoint URL, deskripsi bisnis, atau modul..."
        filters={[
          {
            key: 'method',
            label: 'Method HTTP',
            value: methodFilter,
            onChange: setMethodFilter,
            options: [
              { value: 'ALL', label: 'Semua Method' },
              { value: 'GET', label: 'GET' },
              { value: 'POST', label: 'POST' },
              { value: 'PUT', label: 'PUT' },
              { value: 'DELETE', label: 'DELETE' },
            ],
          },
        ]}
        onReset={() => {
          setSearchQuery('')
          setMethodFilter('ALL')
        }}
        totalCount={endpoints.reduce((acc, g) => acc + g.items.length, 0)}
        filteredCount={endpoints.reduce((acc, ep) => {
          return (
            acc +
            ep.items.filter((item) => {
              const q = searchQuery.toLowerCase()
              const matchSearch =
                !searchQuery ||
                item.path.toLowerCase().includes(q) ||
                item.desc.toLowerCase().includes(q) ||
                ep.group.toLowerCase().includes(q)
              const matchMethod = methodFilter === 'ALL' || item.method === methodFilter
              return matchSearch && matchMethod
            }).length
          )
        }, 0)}
      />

      <div className="space-y-6">
        {endpoints
          .map((ep) => ({
            ...ep,
            items: ep.items.filter((item) => {
              const q = searchQuery.toLowerCase()
              const matchSearch =
                !searchQuery ||
                item.path.toLowerCase().includes(q) ||
                item.desc.toLowerCase().includes(q) ||
                ep.group.toLowerCase().includes(q)
              const matchMethod = methodFilter === 'ALL' || item.method === methodFilter
              return matchSearch && matchMethod
            }),
          }))
          .filter((ep) => ep.items.length > 0)
          .map((ep, idx) => (
            <Card key={idx} className="border-[#e7e9eb] shadow-sm">
              <CardHeader
                title={<span className="font-heading font-bold text-[#313a46]">{ep.group}</span>}
                subtitle={<span className="text-xs text-[#98a6ad]">{ep.items.length} endpoint ditampilkan</span>}
              />
              <div className="overflow-x-auto">
                <table className="w-full text-left text-xs">
                  <thead className="bg-[#f6f7fb] text-[#6c757d] font-semibold text-[11px] uppercase tracking-wider border-b border-[#e7e9eb]">
                    <tr>
                      <th className="px-4 py-2.5 w-24">Method</th>
                      <th className="px-4 py-2.5">Endpoint URL</th>
                      <th className="px-4 py-2.5">Deskripsi Fungsi Bisnis</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-[#e7e9eb] font-mono text-[11px]">
                    {ep.items.map((item, i) => (
                      <tr key={i} className="hover:bg-[#f6f7fb]/60 transition-colors">
                        <td className="px-4 py-2.5">
                          <span
                            className={`px-2 py-0.5 rounded-full font-bold text-[10px] uppercase inline-block text-center ${
                              item.method === 'GET'
                                ? 'bg-[#188ae2]/10 text-[#188ae2] border border-[#188ae2]/20'
                                : 'bg-[#10c469]/10 text-[#10c469] border border-[#10c469]/20'
                            }`}
                          >
                            {item.method}
                          </span>
                        </td>
                        <td className="px-4 py-2.5 text-[#313a46] font-semibold">{item.path}</td>
                        <td className="px-4 py-2.5 font-sans text-[#6c757d]">{item.desc}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </Card>
          ))}
      </div>
    </div>
  )
}
