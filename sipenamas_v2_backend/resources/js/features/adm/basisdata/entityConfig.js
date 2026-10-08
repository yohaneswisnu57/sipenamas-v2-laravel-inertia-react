/**
 * Registry frontend untuk menu "Basis Data" - cermin dari
 * config/master_data.php di backend. Satu entri per slug menentukan label,
 * kolom form (dipakai juga sebagai kolom tabel), dan sumber opsi dropdown
 * (semuanya lewat basisDataApi.list() ke entity lain, termasuk yang baru
 * dibuat di batch ini - lihat catatan di rencana implementasi).
 *
 * type field: text | number | textarea | select | checkbox
 * select: { source: slug entity lain, value: kolom value, label: kolom label, filter?: (row) => bool }
 */
export const entityConfig = {
  fakultas: {
    label: 'Fakultas',
    idKey: 'id',
    fields: [
      { key: 'KODEFAKULTAS', label: 'Kode Fakultas', type: 'text', required: true },
      { key: 'NAMAFAKULTAS', label: 'Nama Fakultas', type: 'text', required: true },
      {
        key: 'KDDEKAN',
        label: 'Dekan',
        type: 'select',
        select: { source: 'personal', value: 'KODEPERSON', label: 'NAMALENGKAP', filter: (r) => r.ISDEKAN },
      },
    ],
  },

  jurusan: {
    label: 'Jurusan',
    idKey: 'id',
    fields: [
      { key: 'KODEJURUSAN', label: 'Kode Jurusan', type: 'text', required: true },
      { key: 'NAMAJURUSAN', label: 'Nama Jurusan', type: 'text', required: true },
      {
        key: 'KDFAKULTAS',
        label: 'Fakultas',
        type: 'select',
        select: { source: 'fakultas', value: 'KODEFAKULTAS', label: 'NAMAFAKULTAS' },
      },
    ],
  },

  prodi: {
    label: 'Program Studi',
    idKey: 'id',
    fields: [
      { key: 'KODEPRODI', label: 'Kode Prodi', type: 'text', required: true },
      { key: 'NAMAPRODI', label: 'Nama Prodi', type: 'text', required: true },
      {
        key: 'KDJURUSAN',
        label: 'Jurusan',
        type: 'select',
        select: { source: 'jurusan', value: 'KODEJURUSAN', label: 'NAMAJURUSAN' },
      },
      {
        key: 'KDFAKULTAS',
        label: 'Fakultas',
        type: 'select',
        select: { source: 'fakultas', value: 'KODEFAKULTAS', label: 'NAMAFAKULTAS' },
      },
    ],
  },

  'fokus-penelitian': {
    label: 'Fokus Penelitian',
    idKey: 'id',
    fields: [
      { key: 'DESKRIPSI', label: 'Deskripsi', type: 'text', required: true },
      { key: 'URUTAN', label: 'Urutan', type: 'number' },
    ],
  },

  'fokus-abdimas': {
    label: 'Fokus ABDIMAS',
    idKey: 'id',
    fields: [
      { key: 'DESKRIPSI', label: 'Deskripsi', type: 'text', required: true },
      { key: 'URUTAN', label: 'Urutan', type: 'number' },
    ],
  },

  'skim-penelitian': {
    label: 'Skim Penelitian',
    idKey: 'id',
    fields: [
      { key: 'KODESKIM', label: 'Kode Skim', type: 'text', required: true },
      { key: 'NAMASKIM', label: 'Nama Skim', type: 'text', required: true },
      { key: 'MINANGGOTA', label: 'Min. Anggota', type: 'number', required: true },
      { key: 'MAXANGGOTA', label: 'Maks. Anggota', type: 'number', required: true },
      { key: 'ISABDIMAS', label: 'Skim ABDIMAS', type: 'checkbox' },
      { key: 'STATUSPEN', label: 'Status', type: 'text' },
      {
        key: 'DEFKDSUMBERDANA',
        label: 'Sumber Dana Default',
        type: 'select',
        select: { source: 'sumber-dana', value: 'KODESUMBERDANA', label: 'NAMASUMBERDANA' },
      },
      { key: 'KETERANGAN', label: 'Keterangan', type: 'textarea' },
      {
        key: 'KDSOALPENILAIANPROPOSAL',
        label: 'Borang Penilaian Proposal',
        type: 'select',
        select: { source: 'borang-proposal', value: 'KODESOAL', label: 'DESKRIPSI' },
      },
      {
        key: 'KDSOALPENILAIANPOSTER',
        label: 'Borang Penilaian Poster',
        type: 'select',
        select: { source: 'borang-poster', value: 'KODESOAL', label: 'DESKRIPSI' },
      },
      { key: 'ISSTATUSPEMAPARAN', label: 'Ada Pemaparan', type: 'checkbox' },
      { key: 'ISOPENBUDGET', label: 'Anggaran Terbuka', type: 'checkbox' },
      { key: 'ANGGARANPERPENELITIAN', label: 'Plafon Dana per Penelitian', type: 'number' },
      { key: 'ISAKTIF', label: 'Aktif', type: 'checkbox' },
      { key: 'NOMORKODEANGGARAN', label: 'Nomor Kode Anggaran', type: 'text' },
    ],
  },

  'rencana-target': {
    label: 'Rencana Target',
    idKey: 'id',
    scope: {
      param: 'kdskim',
      column: 'KDSKIM',
      label: 'Skim Penelitian',
      source: 'skim-penelitian',
      value: 'KODESKIM',
      optionLabel: 'NAMASKIM',
    },
    fields: [
      { key: 'KATEGORI', label: 'Kategori', type: 'text', required: true },
      { key: 'SUBKATEGORI', label: 'Subkategori', type: 'text', required: true },
      { key: 'ISWAJIB', label: 'Wajib', type: 'checkbox' },
      { key: 'ISADAINSENTIF', label: 'Ada Insentif', type: 'checkbox' },
      { key: 'INDIKATORNYA', label: 'Indikator', type: 'text' },
      { key: 'URUTAN', label: 'Urutan', type: 'number' },
    ],
  },

  'sumber-dana': {
    label: 'Sumber Dana',
    idKey: 'id',
    fields: [
      { key: 'KODESUMBERDANA', label: 'Kode Sumber Dana', type: 'text', required: true },
      { key: 'NAMASUMBERDANA', label: 'Nama Sumber Dana', type: 'text', required: true },
      { key: 'ISDANALPPM', label: 'Dana LPPM', type: 'checkbox' },
      { key: 'KETERANGAN', label: 'Keterangan', type: 'textarea' },
    ],
  },

  'kode-anggaran': {
    label: 'Tabel Kode Anggaran',
    idKey: 'id',
    fields: [
      { key: 'NOMORKODE', label: 'Nomor Kode', type: 'text', required: true },
      { key: 'KETERANGAN', label: 'Keterangan', type: 'text' },
    ],
  },

  'monev-penelitian': {
    label: 'Borang Monev Penelitian',
    idKey: 'id',
    fields: [
      { key: 'NOMOR', label: 'Nomor', type: 'number', required: true },
      { key: 'ASPEKPENILAIAN', label: 'Aspek Penilaian', type: 'textarea', required: true },
      { key: 'PIL01', label: 'Pilihan 1', type: 'text' },
      { key: 'PIL02', label: 'Pilihan 2', type: 'text' },
      { key: 'PIL03', label: 'Pilihan 3', type: 'text' },
      { key: 'PIL04', label: 'Pilihan 4', type: 'text' },
      { key: 'PIL05', label: 'Pilihan 5', type: 'text' },
    ],
  },

  'monev-abdimas': {
    label: 'Borang Monev ABDIMAS',
    idKey: 'id',
    fields: [
      { key: 'NOMOR', label: 'Nomor', type: 'number', required: true },
      { key: 'ASPEKPENILAIAN', label: 'Aspek Penilaian', type: 'textarea', required: true },
      { key: 'PIL01', label: 'Pilihan 1', type: 'text' },
      { key: 'PIL02', label: 'Pilihan 2', type: 'text' },
      { key: 'PIL03', label: 'Pilihan 3', type: 'text' },
      { key: 'PIL04', label: 'Pilihan 4', type: 'text' },
      { key: 'PIL05', label: 'Pilihan 5', type: 'text' },
    ],
  },

  mahasiswa: {
    label: 'Mahasiswa',
    idKey: 'id',
    readOnly: true,
    fields: [
      { key: 'NIM', label: 'NIM', type: 'text' },
      { key: 'NAMAMAHASISWA', label: 'Nama Mahasiswa', type: 'text' },
      { key: 'NAMAPRODI', label: 'Program Studi', type: 'text' },
      { key: 'STATUSNYA', label: 'Status', type: 'text' },
      { key: 'PERIODEDAFTAR', label: 'Periode Daftar', type: 'text' },
      { key: 'EMAIL', label: 'Email', type: 'text' },
    ],
  },

  'index-jurnal': {
    label: 'Index Jurnal',
    idKey: 'id',
    fields: [
      { key: 'KODEINDEXJURNAL', label: 'Kode Index Jurnal', type: 'text', required: true },
      { key: 'NAMAINDEXJURNAL', label: 'Nama Index Jurnal', type: 'text', required: true },
      { key: 'ADAINSENTIF', label: 'Ada Insentif', type: 'checkbox' },
      { key: 'BOLEHAPC', label: 'Boleh Subsidi APC', type: 'checkbox' },
      { key: 'URUTAN', label: 'Urutan', type: 'number' },
    ],
  },

  personal: {
    label: 'Personal',
    idKey: 'id',
    fields: [
      { key: 'KODEPERSON', label: 'Kode Person', type: 'text', required: true },
      { key: 'NAMALENGKAP', label: 'Nama Lengkap', type: 'text', required: true },
      { key: 'GENDER', label: 'Jenis Kelamin', type: 'text' },
      { key: 'HP', label: 'No. HP', type: 'text' },
      { key: 'EMAIL', label: 'Email', type: 'text' },
      {
        key: 'KDFAKULTAS',
        label: 'Fakultas',
        type: 'select',
        select: { source: 'fakultas', value: 'KODEFAKULTAS', label: 'NAMAFAKULTAS' },
      },
      {
        key: 'KDPRODI',
        label: 'Program Studi',
        type: 'select',
        select: { source: 'prodi', value: 'KODEPRODI', label: 'NAMAPRODI' },
      },
      { key: 'JABATAN', label: 'Jabatan', type: 'text' },
      { key: 'ISPENELITI', label: 'Peneliti', type: 'checkbox' },
      { key: 'ISDEKAN', label: 'Dekan', type: 'checkbox' },
      { key: 'ISREKTOR', label: 'Rektor', type: 'checkbox' },
      { key: 'ISGJM', label: 'GJM', type: 'checkbox' },
      { key: 'ISREVIEWERPENELITIAN', label: 'Reviewer Penelitian', type: 'checkbox' },
      { key: 'ISREVIEWERABDIMAS', label: 'Reviewer ABDIMAS', type: 'checkbox' },
      { key: 'ISEXTERNAL', label: 'Eksternal', type: 'checkbox' },
      { key: 'ISOPERATORPENELITIAN', label: 'Operator Penelitian', type: 'checkbox' },
      { key: 'ISOPERATORABDIMAS', label: 'Operator ABDIMAS', type: 'checkbox' },
    ],
  },

  'person-sinta': {
    label: 'Person-Sinta',
    idKey: 'id',
    readOnly: true,
    fields: [
      { key: 'SINTAID', label: 'SINTA ID', type: 'text' },
      { key: 'NIDN', label: 'NIDN', type: 'text' },
      { key: 'NAMA', label: 'Nama', type: 'text' },
      { key: 'AFILIASI', label: 'Afiliasi', type: 'text' },
      { key: 'JABATANFUNGSIONAL', label: 'Jabatan Fungsional', type: 'text' },
      { key: 'SINTA_SCORE_OVERALL', label: 'Skor SINTA', type: 'text' },
      { key: 'SINTA_RANK', label: 'Peringkat SINTA', type: 'text' },
    ],
  },
}

export const borangPenilaianSlugs = ['borang-proposal', 'borang-poster', 'borang-presentasi']

export const borangPenilaianConfig = {
  'borang-proposal': { label: 'Borang Penilaian Proposal' },
  'borang-poster': { label: 'Borang Penilaian Poster' },
  'borang-presentasi': { label: 'Borang Penilaian Presentasi' },
}
