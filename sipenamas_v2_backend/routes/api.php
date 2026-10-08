<?php

use App\Http\Controllers\Api\V1\Admin\DashboardController as AdmDashboardController;
use App\Http\Controllers\Api\V1\Admin\FinalApprovalController;
use App\Http\Controllers\Api\V1\Admin\KetuntasanController;
use App\Http\Controllers\Api\V1\Admin\MasterDataCrudController;
use App\Http\Controllers\Api\V1\Admin\PeriodeController as AdmPeriodeController;
use App\Http\Controllers\Api\V1\Admin\PermissionLabelController;
use App\Http\Controllers\Api\V1\Admin\PlottingController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SintaExportController;
use App\Http\Controllers\Api\V1\Admin\SuratKeputusanController;
use App\Http\Controllers\Api\V1\Admin\UserController;
use App\Http\Controllers\Api\V1\Akreditasi\ReportController as AkreditasiReportController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Dekan\AnggaranController as DekanAnggaranController;
use App\Http\Controllers\Api\V1\Dekan\ApprovalController as DekanApprovalController;
use App\Http\Controllers\Api\V1\Dekan\LaporanAkhirController as DekanLaporanAkhirController;
use App\Http\Controllers\Api\V1\Dekan\MonevController as DekanMonevController;
use App\Http\Controllers\Api\V1\Dekan\MonitoringController as DekanMonitoringController;
use App\Http\Controllers\Api\V1\Dekan\PengajuanController as DekanPengajuanController;
use App\Http\Controllers\Api\V1\DokumenPublikController;
use App\Http\Controllers\Api\V1\MasterDataController;
use App\Http\Controllers\Api\V1\Rektorat\DashboardController as RektoratDashboardController;
use App\Http\Controllers\Api\V1\Rektorat\MbkmController as RektoratMbkmController;
use App\Http\Controllers\Api\V1\Reviewer\PenugasanController;
use Illuminate\Support\Facades\Route;

// Padanan appz/ONAIR/login/myphp/dologin.php - tidak butuh token, dibatasi 5 percobaan/menit.
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

// Padanan appz/dox/{JENIS}/{KODE} legacy - halaman publik dari QR surat, tanpa token.
Route::get('/dox/{jenis}/{kode}', DokumenPublikController::class)->middleware('throttle:30,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/switch-role', [AuthController::class, 'switchRole']);
    Route::post('/auth/impersonate/{kodeperson}', [AuthController::class, 'impersonate']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Master/referensi - dibaca semua modul, tidak dibatasi role tertentu.
    Route::get('/periode', [MasterDataController::class, 'periode']);
    Route::get('/periode/aktif', [MasterDataController::class, 'periodeAktif']);
    Route::get('/skim', [MasterDataController::class, 'skim']);
    Route::get('/index-jurnal', [MasterDataController::class, 'indexJurnal']);
    Route::get('/fakultas', [MasterDataController::class, 'fakultas']);
    Route::get('/prodi', [MasterDataController::class, 'prodi']);
    Route::get('/sumberdana', [MasterDataController::class, 'sumberDana']);
    Route::get('/dosen', [MasterDataController::class, 'dosen']);
    Route::get('/reviewer', [MasterDataController::class, 'reviewer']);
    Route::get('/mahasiswa', [MasterDataController::class, 'mahasiswa']);

    // Permission granular PER RESOURCE (mis. "view penelitian"), bukan lagi
    // per modul - satu resource bisa disumbang aksinya dari lintas role
    // (mis. "penelitian" disentuh PEN/REV/DKN/ADM sekaligus dengan aksi
    // beda-beda). Lihat App\Support\Rbac\PermissionCatalog untuk peta
    // lengkap role->resource->aksi.
    //
    // Endpoint DATA di sini digerbangi `permission:` SAJA (tidak lagi
    // `role:X`) supaya role custom (dibuat admin lewat Manajemen Role,
    // di luar 6 modul bawaan) bisa diberi akses data tanpa perlu jadi
    // anggota role modul manapun. Dashboard TANPA gerbang permission
    // sendiri (mis. `/pen/dashboard`) tetap digerbangi `role:X` - role
    // custom sengaja tidak mendapat dashboard/menu sidebar.
    // Modul PEN pindah ke routes/web.php (halaman Inertia).

    Route::prefix('adm')->group(function () {
        Route::middleware('role:ADM')->get('/dashboard', AdmDashboardController::class);

        Route::middleware('permission:view periode')->group(function () {
            Route::get('/periode', [AdmPeriodeController::class, 'index']);
        });

        Route::middleware('permission:view plotting')->group(function () {
            Route::get('/plotting', [PlottingController::class, 'index']);
        });

        Route::middleware('permission:view final approval')->group(function () {
            Route::get('/final-approval', [FinalApprovalController::class, 'index']);
            Route::get('/ketuntasan', [KetuntasanController::class, 'index']);
            Route::get('/final-approval/{id}/surat/{jenis}', [SuratKeputusanController::class, 'unduh']);
        });

        Route::middleware('permission:view user')->group(function () {
            Route::get('/users', [UserController::class, 'index']);
        });

        Route::middleware('permission:view role')->group(function () {
            Route::get('/roles', [RoleController::class, 'index']);
        });

        Route::middleware('permission:view basis data')->group(function () {
            Route::get('/basisdata/{slug}', [MasterDataCrudController::class, 'index']);
        });

        // Menu "Basis Data" - lihat config/master_data.php untuk daftar
        // entity & field yang di-fact-check terhadap legacy
        // appz/ONAIR/adm/myphp/*.php.
        Route::middleware('permission:manage basis data')->group(function () {
            Route::post('/basisdata/{slug}', [MasterDataCrudController::class, 'store']);
            Route::put('/basisdata/{slug}/{id}', [MasterDataCrudController::class, 'update']);
            Route::delete('/basisdata/{slug}/{id}', [MasterDataCrudController::class, 'destroy']);
            Route::put('/basisdata/{slug}/{id}/detail', [MasterDataCrudController::class, 'replaceDetails']);
            Route::post('/basisdata/{slug}/copy', [MasterDataCrudController::class, 'copyScoped']);
        });

        Route::middleware('permission:export sinta')->group(function () {
            Route::get('/export/sinta', SintaExportController::class);
        });

        Route::middleware('permission:create periode')->group(function () {
            Route::post('/periode', [AdmPeriodeController::class, 'store']);
            Route::put('/periode/{periode}', [AdmPeriodeController::class, 'update']);
        });

        Route::middleware('permission:toggle aktif periode')->group(function () {
            Route::post('/periode/{periode}/toggle-aktif', [AdmPeriodeController::class, 'toggleAktif']);
        });

        Route::middleware('permission:assign reviewer plotting')->group(function () {
            Route::post('/plotting/{penelitian}/reviewer', [PlottingController::class, 'assignReviewer']);
        });

        Route::middleware('permission:finalize reviewer plotting')->group(function () {
            Route::post('/plotting/{penelitian}/finalize', [PlottingController::class, 'finalize']);
        });

        Route::middleware('permission:add reviewer plotting')->group(function () {
            Route::post('/plotting/{penelitian}/reviewer/tambah', [PlottingController::class, 'tambahReviewer']);
        });

        Route::middleware('permission:assign revisi verifikator plotting')->group(function () {
            Route::post('/plotting/{penelitian}/revisi-verifikator', [PlottingController::class, 'assignRevisiVerifikator']);
        });

        Route::middleware('permission:decide final approval penelitian')->group(function () {
            Route::post('/final-approval/{penelitian}', [FinalApprovalController::class, 'decide']);
            Route::post('/surat/generate', [SuratKeputusanController::class, 'generate']);
            Route::post('/surat/final', [SuratKeputusanController::class, 'setFinal']);
            Route::put('/ketuntasan/{id}', [KetuntasanController::class, 'update']);
        });

        Route::middleware('permission:manage user')->group(function () {
            Route::put('/users/{kodeperson}/roles', [UserController::class, 'updateRoles']);
        });

        Route::middleware('permission:manage role')->group(function () {
            Route::post('/roles', [RoleController::class, 'store']);
            Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions']);
            Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
            Route::get('/permissions', [PermissionLabelController::class, 'index']);
            Route::put('/permissions/{permission}', [PermissionLabelController::class, 'update']);
        });
    });

    Route::prefix('rev')->group(function () {
        // Rute literal /penugasan/revisi WAJIB didaftarkan sebelum
        // /penugasan/{id} - urutan registrasi menentukan rute mana yang
        // cocok duluan di Laravel, kalau terbalik "revisi" akan tertangkap
        // sebagai {id}.
        Route::middleware('permission:view revisi queue penugasan')->group(function () {
            Route::get('/penugasan/revisi', [PenugasanController::class, 'revisiQueue']);
            Route::get('/penugasan/{id}/komentar-revisi', [PenugasanController::class, 'komentarRevisi']);
        });

        Route::middleware('permission:verifikasi revisi penugasan')->group(function () {
            Route::post('/penugasan/{id}/verifikasi-revisi', [PenugasanController::class, 'verifikasiRevisi']);
        });

        Route::middleware('permission:view penelitian')->group(function () {
            Route::get('/penugasan', [PenugasanController::class, 'index']);
            Route::get('/penugasan/{id}', [PenugasanController::class, 'show']);
            Route::get('/penugasan/{id}/dokumen-proposal', [PenugasanController::class, 'dokumenProposal']);
            Route::get('/penugasan/{id}/dokumen-proposal-revisi', [PenugasanController::class, 'dokumenProposalRevisi']);
        });

        Route::middleware('permission:confirm kesediaan penugasan')->group(function () {
            Route::post('/penugasan/{id}/kesediaan', [PenugasanController::class, 'confirmKesediaan']);
        });

        Route::middleware('permission:submit penilaian penugasan')->group(function () {
            Route::get('/panduan-penilaian', [PenugasanController::class, 'panduanPenilaian']);
            Route::get('/penugasan/{id}/borang', [PenugasanController::class, 'borang']);
            Route::post('/penugasan/{id}/penilaian', [PenugasanController::class, 'submitPenilaian']);
            Route::post('/penugasan/{id}/revisi-judul', [PenugasanController::class, 'revisiJudul']);
        });
    });

    Route::prefix('dkn')->group(function () {
        Route::middleware('permission:view penelitian')->group(function () {
            Route::get('/proposal', [DekanApprovalController::class, 'index']);
            Route::get('/proposal/{id}/dokumen-proposal', [DekanApprovalController::class, 'dokumenProposal']);
            Route::get('/pengajuan', [DekanPengajuanController::class, 'index']);
            Route::get('/monev', [DekanMonevController::class, 'index']);
            Route::get('/laporan-akhir', [DekanLaporanAkhirController::class, 'index']);
            Route::get('/laporan-akhir/{id}/lembar-pengesahan', [DekanLaporanAkhirController::class, 'lembarPengesahan']);
            Route::get('/monev/{id}/kandidat', [DekanMonevController::class, 'kandidat']);
            Route::get('/anggaran-penelitian', [DekanAnggaranController::class, 'index']);
            Route::get('/belum-tuntas', [DekanMonitoringController::class, 'belumTuntas']);
        });

        Route::middleware('permission:approve penelitian')->group(function () {
            Route::post('/proposal/{id}/approve', [DekanApprovalController::class, 'approve']);
            Route::post('/monev/{id}/penunjukan', [DekanMonevController::class, 'penunjukan']);
            Route::post('/laporan-akhir/{id}/approve', [DekanLaporanAkhirController::class, 'approve']);
        });

        Route::middleware('permission:reject penelitian')->group(function () {
            Route::post('/proposal/{id}/reject', [DekanApprovalController::class, 'reject']);
        });
    });

    Route::prefix('rkt')->group(function () {
        Route::middleware('permission:view rektorat')->group(function () {
            Route::get('/dashboard', [RektoratDashboardController::class, 'dashboard']);
            Route::get('/strategis', [RektoratDashboardController::class, 'strategis']);
            Route::get('/mbkm/pengisian', [RektoratMbkmController::class, 'pengisian']);
            Route::get('/mbkm/rekap-prodi', [RektoratMbkmController::class, 'rekapProdi']);
            Route::get('/mbkm/belum-mengisi', [RektoratMbkmController::class, 'belumMengisi']);
            Route::get('/mbkm/data-hasil/{responden}', [RektoratMbkmController::class, 'dataHasil']);
        });
    });

    Route::prefix('akr')->group(function () {
        Route::middleware('permission:view akreditasi')->group(function () {
            Route::get('/dashboard', [AkreditasiReportController::class, 'dashboard']);
            Route::get('/data-mining', [AkreditasiReportController::class, 'dataMiningBorang']);
            Route::get('/rekap-luaran', [AkreditasiReportController::class, 'rekapLuaran']);
        });
    });
});
