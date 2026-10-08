import React, { useState } from 'react'
import { NavLink, useLocation } from '@/lib/router'
import {
  LayoutDashboard,
  CalendarDays,
  Award,
  Users2,
  FileCheck2,
  FileSpreadsheet,
  ShieldAlert,
  UserCog,
  KeyRound,
  ChevronDown,
  ChevronRight,
  HelpCircle,
  MessageSquareShare,
  FileText,
  PlusCircle,
  HeartHandshake,
  DollarSign,
  BookOpenCheck,
  Sparkles,
  CheckSquare,
  BarChart3,
  AlertCircle,
  GraduationCap,
  Download,
  BookMarked,
  ScrollText,
  Database,
  Building2,
  Landmark,
  Target,
  ClipboardList,
  ClipboardCheck,
  UserCircle,
  Contact,
  Hash,
  BookText,
  UserCheck,
} from 'lucide-react'
import { useAuthStore } from '../../store/authStore'
import { ROLES, ROLE_LABELS } from '../../utils/constants'

export function Sidebar() {
  const { user, hasPermission } = useAuthStore()
  const location = useLocation()
  const activeRole = user?.activeRole || user?.role
  const [openGroups, setOpenGroups] = useState({})

  // Setiap item boleh menyatakan `requires: '<aksi>'` (lihat
  // App\Support\Rbac\PermissionCatalog di backend untuk daftar aksi valid
  // per role). Item tanpa `requires` gate ke 'read' - baseline akses lihat
  // yang dimiliki tiap role di katalog. Item hanya tampil kalau
  // modulePermissions user untuk activeRole memuat aksi tsb.
  const canSee = (item) => hasPermission(activeRole, item.requires || 'read')

  const toggleGroup = (label) => {
    setOpenGroups((prev) => ({ ...prev, [label]: !prev[label] }))
  }

  const navMenus = {
    [ROLES.ADMIN]: [
      { label: 'Dashboard LPPM', path: '/adm/dashboard', icon: LayoutDashboard },
      { label: 'Master Periode & Jadwal', path: '/adm/periode', icon: CalendarDays, requires: 'toggle-periode-aktif' },
      { label: 'Plotting Reviewer', path: '/adm/plotting', icon: Users2, requires: 'assign-reviewer' },
      { label: 'Final Approval & SK', path: '/adm/final-approval', icon: FileCheck2, requires: 'decide-final-approval' },
      { label: 'Status Ketuntasan', path: '/adm/ketuntasan', icon: ClipboardCheck, requires: 'decide-final-approval' },
      { label: 'Ekspor Format SINTA', path: '/adm/sinta', icon: FileSpreadsheet, requires: 'export-sinta' },
      {
        label: 'Manajemen User',
        icon: ShieldAlert,
        children: [
          { label: 'User', path: '/adm/users', icon: UserCog, requires: 'manage-users' },
          { label: 'Role', path: '/adm/roles', icon: KeyRound, requires: 'manage-roles' },
          { label: 'Permission', path: '/adm/permissions', icon: ScrollText, requires: 'manage-roles' },
        ],
      },
      {
        label: 'Basis Data',
        icon: Database,
        requires: 'manage-basisdata',
        children: [
          { label: 'Personal', path: '/adm/basisdata/personal', icon: UserCircle, requires: 'manage-basisdata' },
          { label: 'Person-Sinta', path: '/adm/basisdata/person-sinta', icon: Contact, requires: 'manage-basisdata' },
          { label: 'Fakultas', path: '/adm/basisdata/fakultas', icon: Building2, requires: 'manage-basisdata' },
          { label: 'Jurusan', path: '/adm/basisdata/jurusan', icon: Building2, requires: 'manage-basisdata' },
          { label: 'Program Studi', path: '/adm/basisdata/prodi', icon: Building2, requires: 'manage-basisdata' },
          { label: 'Fokus Penelitian', path: '/adm/basisdata/fokus-penelitian', icon: Target, requires: 'manage-basisdata' },
          { label: 'Fokus ABDIMAS', path: '/adm/basisdata/fokus-abdimas', icon: Target, requires: 'manage-basisdata' },
          { label: 'Skim Penelitian', path: '/adm/basisdata/skim-penelitian', icon: Award, requires: 'manage-basisdata' },
          { label: 'Rencana Target', path: '/adm/basisdata/rencana-target', icon: Target, requires: 'manage-basisdata' },
          { label: 'Sumber Dana', path: '/adm/basisdata/sumber-dana', icon: Landmark, requires: 'manage-basisdata' },
          { label: 'Tabel Kode Anggaran', path: '/adm/basisdata/kode-anggaran', icon: Hash, requires: 'manage-basisdata' },
          { label: 'Borang Penilaian Proposal', path: '/adm/basisdata/borang-proposal', icon: ClipboardList, requires: 'manage-basisdata' },
          { label: 'Borang Penilaian Poster', path: '/adm/basisdata/borang-poster', icon: ClipboardList, requires: 'manage-basisdata' },
          { label: 'Borang Penilaian Presentasi', path: '/adm/basisdata/borang-presentasi', icon: ClipboardList, requires: 'manage-basisdata' },
          { label: 'Borang Monev Penelitian', path: '/adm/basisdata/monev-penelitian', icon: ClipboardCheck, requires: 'manage-basisdata' },
          { label: 'Borang Monev ABDIMAS', path: '/adm/basisdata/monev-abdimas', icon: ClipboardCheck, requires: 'manage-basisdata' },
          { label: 'Mahasiswa', path: '/adm/basisdata/mahasiswa', icon: GraduationCap, requires: 'manage-basisdata' },
          { label: 'Index Jurnal', path: '/adm/basisdata/index-jurnal', icon: BookText, requires: 'manage-basisdata' },
        ],
      },
      { label: 'WhatsApp Gateway', path: '/adm/whatsapp', icon: MessageSquareShare },
    ],
    [ROLES.PENELITI]: [
      { label: 'Dashboard Peneliti', path: '/pen/dashboard', icon: LayoutDashboard },
      { label: 'Daftar Penelitian', path: '/pen/penelitian', icon: FileText },
      { label: 'Persetujuan Anggota Tim', path: '/pen/kesediaan-tim', icon: UserCheck },
      { label: 'Laporan Akhir Penelitian', path: '/pen/laporan-akhir', icon: FileCheck2, requires: 'submit-laporan-akhir' },
      { label: 'Monev Hasil Penelitian', path: '/pen/monev-hasil', icon: ClipboardCheck, requires: 'submit-monev' },
      { label: 'Pengabdian (Abdimas)', path: '/pen/abdimas', icon: HeartHandshake },
      { label: 'Subsidi APC Jurnal', path: '/pen/subsidi-apc', icon: DollarSign },
      { label: 'Insentif Publikasi', path: '/pen/insentif-jurnal', icon: BookOpenCheck },
      { label: 'Pendaftaran HKI / Paten', path: '/pen/hki', icon: Sparkles, requires: 'create' },
    ],
    [ROLES.DEKAN]: [
      { label: 'Dashboard Fakultas', path: '/dkn/dashboard', icon: LayoutDashboard },
      { label: 'Pagu Anggaran Prodi', path: '/dkn/pagu', icon: BarChart3 },
      { label: 'Persetujuan Laporan Akhir', path: '/dkn/laporan-akhir', icon: FileCheck2, requires: 'approve-proposal' },
      { label: 'Penunjukan Reviewer Monev', path: '/dkn/monev', icon: ScrollText, requires: 'approve-proposal' },
      { label: 'Monitoring Belum Tuntas', path: '/dkn/monitoring', icon: AlertCircle },
    ],
    [ROLES.REVIEWER]: [
      { label: 'Dashboard Reviewer', path: '/rev/dashboard', icon: LayoutDashboard },
      { label: 'Konfirmasi Penugasan', path: '/rev/kesediaan', icon: CheckSquare, requires: 'confirm-kesediaan' },
      { label: 'Penilaian Proposal', path: '/rev/penilaian', icon: BookMarked, requires: 'submit-penilaian' },
      { label: 'Validasi Revisi Naskah', path: '/rev/revisi', icon: FileCheck2, requires: 'submit-penilaian' },
    ],
    [ROLES.REKTORAT]: [
      { label: 'Executive Dashboard', path: '/rkt/dashboard', icon: LayoutDashboard },
      { label: 'Approval Riset Strategis', path: '/rkt/strategis', icon: Award },
      { label: 'Survei MBKM', path: '/rkt/mbkm', icon: GraduationCap },
    ],
    [ROLES.AKREDITASI]: [
      { label: 'Dashboard IKU Riset', path: '/akr/dashboard', icon: LayoutDashboard },
      { label: 'Data Mining Borang', path: '/akr/data-mining', icon: BarChart3 },
      { label: 'Rekap Luaran & HKI', path: '/akr/luaran', icon: Award },
      { label: 'Ekspor Tabel LKPS Excel', path: '/akr/export-lkps', icon: Download },
    ],
  }

  // Tidak ada fallback ke menu ADMIN kalau activeRole kosong/tak dikenal -
  // fail-closed (menu kosong) alih-alih fail-open ke role paling privileged.
  const currentItems = (navMenus[activeRole] || [])
    .filter(canSee)
    .map((item) =>
      item.children ? { ...item, children: item.children.filter(canSee) } : item
    )
    .filter((item) => !item.children || item.children.length > 0)

  const activeRoleLabel = ROLE_LABELS[activeRole] || activeRole

  return (
    <aside className="sidenav-menu">
      {/* 1. LogoBox Khas Adminto */}
      <div className="h-[70px] border-b border-[#e7e9eb] px-5 flex items-center justify-between bg-white shrink-0">
        <NavLink to="/" className="flex items-center gap-2.5 text-decoration-none">
          <div className="w-8 h-8 rounded-lg bg-[#188ae2] flex items-center justify-center text-white font-bold text-base shadow-xs font-heading">
            S
          </div>
          <span className="text-[19px] font-bold tracking-tight text-[#313a46] font-heading flex items-center">
            SIPENAMAS<span className="text-[#188ae2]">.</span>
          </span>
        </NavLink>
        <span className="text-[10px] font-bold uppercase tracking-wider bg-[#188ae2]/10 text-[#188ae2] px-1.5 py-0.5 rounded font-mono">
          v2.0
        </span>
      </div>

      {/* 2. Sidenav User Box Khas Adminto (.sidenav-user) */}
      <div className="sidenav-user">
        <div className="text-center">
          <img
            src={user?.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100'}
            alt="user-avatar"
            className="sidenav-user-avatar"
          />
          <div className="mt-2 flex items-center justify-center gap-1">
            <div>
              <span className="sidenav-user-name block">{user?.name || 'Pengguna LPPM'}</span>
              <p className="sidenav-user-role mb-0">{activeRoleLabel}</p>
            </div>
          </div>
        </div>
      </div>

      {/* 3. Navigation Menu (.side-nav) */}
      <div className="flex-1 overflow-y-auto overscroll-contain">
        <ul className="side-nav">
          <li className="side-nav-title">Menu Utama</li>

          {currentItems.map((item) => {
            const Icon = item.icon

            if (item.children) {
              const isGroupActive = item.children.some((child) => {
                const childPath = child.path.split('?')[0]
                return location.pathname === childPath || location.pathname.startsWith(`${childPath}/`)
              })
              const isOpen = openGroups[item.label] ?? isGroupActive

              return (
                <li key={item.label} className={`side-nav-item ${isGroupActive ? 'active' : ''}`}>
                  <div
                    role="button"
                    tabIndex={0}
                    onClick={() => toggleGroup(item.label)}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault()
                        toggleGroup(item.label)
                      }
                    }}
                    className={`side-nav-link ${isGroupActive ? 'active' : ''}`}
                  >
                    <span className="menu-icon">
                      <Icon size={19} />
                    </span>
                    <span className="menu-text">{item.label}</span>
                    <span className={`menu-arrow ${isOpen ? 'open' : ''}`}>
                      <ChevronRight size={15} />
                    </span>
                  </div>

                  {isOpen && (
                    <ul className="sub-menu">
                      {item.children.map((child) => {
                        const childPath = child.path.split('?')[0]
                        const childSearch = child.path.split('?')[1]
                        const isChildActive =
                          (location.pathname === childPath || location.pathname.startsWith(`${childPath}/`)) &&
                          (!childSearch || location.search === `?${childSearch}`)

                        return (
                          <li key={child.path} className="side-nav-item">
                            <NavLink
                              to={child.path}
                              className={`side-nav-link ${isChildActive ? 'active' : ''}`}
                            >
                              <span className="menu-text">{child.label}</span>
                            </NavLink>
                          </li>
                        )
                      })}
                    </ul>
                  )}
                </li>
              )
            }

            const isItemActive = location.pathname === item.path || location.pathname.startsWith(`${item.path}/`)

            return (
              <li key={item.path} className={`side-nav-item ${isItemActive ? 'active' : ''}`}>
                <NavLink
                  to={item.path}
                  className={`side-nav-link ${isItemActive ? 'active' : ''}`}
                >
                  <span className="menu-icon">
                    <Icon size={19} />
                  </span>
                  <span className="menu-text">{item.label}</span>
                </NavLink>
              </li>
            )
          })}

        </ul>

        {/* 4. Help Box Khas Adminto (.help-box) */}
        <div className="help-box">
          <h5 className="font-heading font-semibold text-[13.5px] text-[#313a46] mb-1">
            Pusat Bantuan LPPM
          </h5>
          <p className="text-[11.5px] text-[#8a969c] mb-2.5 leading-relaxed">
            Perlu asistensi pedoman pengusulan atau review usulan?
          </p>
          <a
            href="https://lppm.ukwms.ac.id"
            target="_blank"
            rel="noreferrer"
            className="inline-block px-3.5 py-1.5 rounded text-xs font-semibold bg-[#ff5b5b] text-white hover:bg-[#e04545] transition shadow-xs text-decoration-none"
          >
            Panduan Hibah
          </a>
        </div>
      </div>

      {/* 5. Footer Sidenav */}
      <div className="px-4 py-2 border-t border-[#e7e9eb] bg-white text-[11px] text-[#8a969c] flex items-center justify-between shrink-0">
        <span>SIPENAMAS LPPM</span>
        <span className="font-mono text-[10px]">&copy; 2026</span>
      </div>
    </aside>
  )
}
