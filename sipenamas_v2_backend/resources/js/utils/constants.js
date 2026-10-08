export const ROLES = {
  ADMIN: 'ADM',
  PENELITI: 'PEN',
  DEKAN: 'DKN',
  REVIEWER: 'REV',
  REKTORAT: 'RKT',
  AKREDITASI: 'AKR',
}

export const ROLE_LABELS = {
  [ROLES.ADMIN]: 'Administrator LPPM',
  [ROLES.PENELITI]: 'Dosen Peneliti / Pelaksana',
  [ROLES.DEKAN]: 'Dekan Fakultas',
  [ROLES.REVIEWER]: 'Reviewer Penilai',
  [ROLES.REKTORAT]: 'Pimpinan Universitas / Rektorat',
  [ROLES.AKREDITASI]: 'Tim Akreditasi & SPMI',
  [null]: 'No Role',
}

export const ROLE_BADGE_COLORS = {
  [ROLES.ADMIN]: 'bg-rose-50 text-rose-700 border-rose-200',
  [ROLES.PENELITI]: 'bg-blue-50 text-blue-700 border-blue-200',
  [ROLES.DEKAN]: 'bg-purple-50 text-purple-700 border-purple-200',
  [ROLES.REVIEWER]: 'bg-amber-50 text-amber-700 border-amber-200',
  [ROLES.REKTORAT]: 'bg-indigo-50 text-indigo-700 border-indigo-200',
  [ROLES.AKREDITASI]: 'bg-teal-50 text-teal-700 border-teal-200',
}

// Permission CRUD granular per modul (per-user, bukan per-role) - lihat
// Person::modulePermissions() di sipenamas_v2_backend.
export const PERMISSION_ACTIONS = {
  CREATE: 'create',
  READ: 'read',
  UPDATE: 'update',
  DELETE: 'delete',
}

export const PERMISSION_ACTION_LABELS = {
  [PERMISSION_ACTIONS.CREATE]: 'Buat',
  [PERMISSION_ACTIONS.READ]: 'Lihat',
  [PERMISSION_ACTIONS.UPDATE]: 'Ubah',
  [PERMISSION_ACTIONS.DELETE]: 'Hapus',
}

// Label aksi permission granular per peran/modul, dipakai di halaman
// Manajemen Role. Daftar aksi VALID untuk tiap peran datang dari API
// (GET /adm/roles -> field `actions`, lihat App\Support\Rbac\PermissionCatalog
// di sipenamas_v2_backend) - beda peran punya aksi yang berbeda-beda,
// bukan lagi CRUD generik yang seragam untuk semua peran. Map di bawah ini
// cuma label tampilan; kalau ada aksi baru dari backend yang belum ada
// labelnya di sini, ManajemenRolePage.jsx fallback ke humanized slug-nya.
export const ROLE_ACTION_LABELS = {
  read: 'Lihat',
  create: 'Tambah',
  'submit-revisi': 'Ajukan Revisi',
  'submit-monev': 'Isi Monev Hasil',
  'submit-laporan-akhir': 'Ajukan Laporan Akhir',
  'assign-reviewer': 'Tetapkan Reviewer',
  'decide-final-approval': 'Putuskan Persetujuan Akhir',
  'toggle-periode-aktif': 'Aktif/Nonaktifkan Periode',
  'manage-users': 'Kelola Pengguna',
  'manage-roles': 'Kelola Peran',
  'export-sinta': 'Ekspor SINTA',
}

export const STATUS_USULAN = {
  DRAFT: 'DRAFT',
  SUBMITTED: 'SUBMITTED',
  DITOLAK_DEKAN: 'DITOLAK_DEKAN',
  DISETUJUI_DEKAN: 'DISETUJUI_DEKAN',
  PLOTTED: 'PLOTTED',
  REVIEW: 'REVIEW',
  REVISI: 'REVISI',
  MENUNGGU_VERIFIKASI_REVISI: 'MENUNGGU_VERIFIKASI_REVISI',
  FINAL_APPROVAL: 'FINAL_APPROVAL',
  LOLOS: 'LOLOS',
  TIDAK_LOLOS: 'TIDAK_LOLOS',
  MONEV: 'MONEV',
  LAPORAN_AKHIR: 'LAPORAN_AKHIR',
  TUNTAS: 'TUNTAS',
}

export const TAHAP_USULAN = {
  PROSES: {
    label: 'Pengajuan Dalam Proses',
    statuses: [
      STATUS_USULAN.DRAFT,
      STATUS_USULAN.SUBMITTED,
      STATUS_USULAN.DISETUJUI_DEKAN,
      STATUS_USULAN.PLOTTED,
      STATUS_USULAN.REVIEW,
      STATUS_USULAN.REVISI,
      STATUS_USULAN.MENUNGGU_VERIFIKASI_REVISI,
      STATUS_USULAN.FINAL_APPROVAL,
    ],
  },
  BERJALAN: {
    label: 'Sedang Berjalan',
    statuses: [STATUS_USULAN.LOLOS, STATUS_USULAN.MONEV, STATUS_USULAN.LAPORAN_AKHIR],
  },
  TUNTAS: { label: 'Tuntas', statuses: [STATUS_USULAN.TUNTAS] },
}

export const STATUS_METADATA = {
  [STATUS_USULAN.DRAFT]: {
    label: 'Draft Pengajuan',
    badge: 'bg-slate-100 text-slate-700 border-slate-300',
    step: 1,
  },
  [STATUS_USULAN.SUBMITTED]: {
    label: 'Menunggu Persetujuan Dekan',
    badge: 'bg-blue-50 text-blue-700 border-blue-200',
    step: 2,
  },
  [STATUS_USULAN.DITOLAK_DEKAN]: {
    label: 'Ditolak Fakultas',
    badge: 'bg-rose-50 text-rose-700 border-rose-200',
    step: 2,
  },
  [STATUS_USULAN.DISETUJUI_DEKAN]: {
    label: 'Disetujui Dekan, Menunggu Plotting',
    badge: 'bg-sky-50 text-sky-700 border-sky-200',
    step: 3,
  },
  [STATUS_USULAN.PLOTTED]: {
    label: 'Plotting Reviewer',
    badge: 'bg-purple-50 text-purple-700 border-purple-200',
    step: 3,
  },
  [STATUS_USULAN.REVIEW]: {
    label: 'Dalam Penilaian Reviewer',
    badge: 'bg-amber-50 text-amber-800 border-amber-200',
    step: 4,
  },
  [STATUS_USULAN.REVISI]: {
    label: 'Perlu Revisi Proposal',
    badge: 'bg-orange-50 text-orange-700 border-orange-200',
    step: 4,
  },
  [STATUS_USULAN.MENUNGGU_VERIFIKASI_REVISI]: {
    label: 'Menunggu Verifikasi Revisi',
    badge: 'bg-fuchsia-50 text-fuchsia-700 border-fuchsia-200',
    step: 4,
  },
  [STATUS_USULAN.FINAL_APPROVAL]: {
    label: 'Sidang Final LPPM',
    badge: 'bg-indigo-50 text-indigo-700 border-indigo-200',
    step: 5,
  },
  [STATUS_USULAN.LOLOS]: {
    label: 'Lolos / Terbit SK',
    badge: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    step: 6,
  },
  [STATUS_USULAN.TIDAK_LOLOS]: {
    label: 'Tidak Lolos Pendanaan',
    badge: 'bg-red-50 text-red-700 border-red-200',
    step: 6,
  },
  [STATUS_USULAN.MONEV]: {
    label: 'Tahap Monev Kemajuan',
    badge: 'bg-teal-50 text-teal-700 border-teal-200',
    step: 8,
  },
  [STATUS_USULAN.LAPORAN_AKHIR]: {
    label: 'Unggah Laporan Akhir',
    badge: 'bg-violet-50 text-violet-700 border-violet-200',
    step: 10,
  },
  [STATUS_USULAN.TUNTAS]: {
    label: 'Selesai & Tuntas',
    badge: 'bg-emerald-100 text-emerald-800 border-emerald-300 font-semibold',
    step: 11,
  },
}

export const STATUS_USULAN_LABELS = Object.fromEntries(
  Object.entries(STATUS_METADATA).map(([key, meta]) => [key, meta.label])
)
