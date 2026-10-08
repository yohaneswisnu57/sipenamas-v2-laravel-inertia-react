<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\Peneliti\CapaianLuaranController;
use App\Http\Controllers\Web\Peneliti\DashboardController as PenDashboardController;
use App\Http\Controllers\Web\Peneliti\HkiController;
use App\Http\Controllers\Web\Peneliti\InsentifController;
use App\Http\Controllers\Web\Peneliti\KesediaanTimController;
use App\Http\Controllers\Web\Peneliti\KuesionerPenelitianController;
use App\Http\Controllers\Web\Peneliti\LaporanAkhirController;
use App\Http\Controllers\Web\Peneliti\MonevHasilController;
use App\Http\Controllers\Web\Peneliti\PenelitianController;
use App\Http\Controllers\Web\Peneliti\PengesahanProposalController;
use App\Http\Controllers\Web\Peneliti\RevisiProposalController;
use App\Http\Controllers\Web\Peneliti\SubsidiApcController;
use App\Http\Controllers\Web\Peneliti\TemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman Inertia
|--------------------------------------------------------------------------
|
| Path sama dengan route SPA lama (sipenamas_v2_frontend/src/routes), jadi
| tautan dan bookmark lama tetap jalan. Halaman masih memuat datanya sendiri
| dari /api/v1 memakai cookie session (Sanctum stateful); props server baru
| berisi parameter route (lihat HandleInertiaRequests::share).
|
| Guard peran sama dengan RoleRoute SPA: modul X boleh dibuka peran X dan
| ADM; Super Admin lolos lewat EnsureRole.
|
*/

Route::middleware('guest:web')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login');
});

Route::inertia('/unauthorized', 'auth/UnauthorizedPage');

// Padanan appz/dox/{JENIS}/{KODE} legacy - halaman publik dari QR surat.
Route::inertia('/dox/{jenis}/{kode}', 'auth/DokumenQrPage');

Route::middleware('auth:web')->group(function () {
    Route::get('/', [AuthController::class, 'home']);
    Route::post('/logout', [AuthController::class, 'destroy']);
    Route::post('/switch-role', [AuthController::class, 'switchRole']);
    Route::post('/impersonate/leave', [AuthController::class, 'leaveImpersonation']);
    Route::post('/impersonate/{kodeperson}', [AuthController::class, 'impersonate']);

    Route::prefix('adm')->middleware('role:ADM')->group(function () {
        Route::inertia('/dashboard', 'adm/AdminDashboardPage');
        Route::inertia('/periode', 'adm/MasterPeriodePage');
        Route::redirect('/skim', '/adm/basisdata/skim-penelitian');
        Route::inertia('/plotting', 'adm/PlottingReviewerPage');
        Route::inertia('/final-approval', 'adm/FinalApprovalPage');
        Route::inertia('/ketuntasan', 'adm/KetuntasanPage');
        Route::inertia('/sinta', 'adm/ExportSintaPage');
        Route::inertia('/users', 'adm/ManajemenUserPage');
        Route::inertia('/roles', 'adm/ManajemenRolePage');
        Route::inertia('/permissions', 'adm/ManajemenMenuPermissionPage');
        Route::inertia('/whatsapp', 'adm/WhatsappGatewayPage');
        Route::inertia('/basisdata/{borang}', 'adm/basisdata/BorangPenilaianPage')
            ->whereIn('borang', ['borang-proposal', 'borang-poster', 'borang-presentasi']);
        Route::inertia('/basisdata/{slug}', 'adm/basisdata/MasterDataCrudPage');
    });

    // Modul PEN (Fase 2): halaman menerima data sebagai props dan aksinya
    // route web (redirect + flash), bukan lagi /api/v1/pen. Middleware tiap
    // route sama dengan endpoint API yang digantikannya: data digerbangi
    // `permission:` saja (role custom boleh), dashboard `role:PEN`.
    Route::prefix('pen')->group(function () {
        Route::get('/dashboard', PenDashboardController::class)->middleware('role:PEN');
        Route::get('/template/{type}/unduh', [TemplateController::class, 'download']);

        Route::middleware('permission:view penelitian')->group(function () {
            Route::get('/penelitian', [PenelitianController::class, 'index']);
            Route::get('/abdimas', [PenelitianController::class, 'indexAbdimas']);
            Route::get('/penelitian/{id}', [PenelitianController::class, 'show'])->whereNumber('id');
            Route::get('/penelitian/{id}/surat-tugas', [PenelitianController::class, 'unduhSuratTugas']);
            Route::get('/penelitian/{id}/pengesahan', [PenelitianController::class, 'unduhPengesahan']);
            Route::get('/kesediaan-tim', [KesediaanTimController::class, 'index']);
            Route::get('/laporan-akhir', [LaporanAkhirController::class, 'index']);
            Route::get('/laporan-akhir/{id}/lembar-pengesahan', [LaporanAkhirController::class, 'unduhLembarPengesahan']);
            Route::get('/laporan-akhir/{id}/capaian/{targetId}/dokumen', [CapaianLuaranController::class, 'unduhDokumen']);
        });

        Route::middleware('permission:view subsidi apc')->get('/subsidi-apc', [SubsidiApcController::class, 'index']);
        Route::middleware('permission:view insentif')->get('/insentif-jurnal', [InsentifController::class, 'index']);
        Route::middleware('permission:view hki')->get('/hki', [HkiController::class, 'index']);

        Route::middleware('permission:create penelitian')->group(function () {
            Route::get('/penelitian/baru', [PenelitianController::class, 'create']);
            Route::get('/penelitian/{id}/edit', [PenelitianController::class, 'edit'])->whereNumber('id');
            Route::get('/abdimas/baru', [PenelitianController::class, 'createAbdimas']);
            Route::get('/abdimas/{id}/edit', [PenelitianController::class, 'editAbdimas'])->whereNumber('id');
            Route::post('/penelitian', [PenelitianController::class, 'store']);
            Route::put('/penelitian/{id}', [PenelitianController::class, 'update']);
            Route::delete('/penelitian/{id}', [PenelitianController::class, 'destroy']);
            Route::get('/penelitian/{id}/dokumen-proposal', [PenelitianController::class, 'lihatDokumenProposal']);
            Route::post('/penelitian/{id}/dokumen-proposal', [PenelitianController::class, 'uploadDokumenProposal']);
            Route::post('/penelitian/{id}/dokumen-proposal/final', [PenelitianController::class, 'finalDokumenProposal']);
            Route::put('/penelitian/{id}/dana-penyertaan', [PengesahanProposalController::class, 'updateDanaPenyertaan']);
            Route::post('/penelitian/{id}/lembar-pengesahan', [PengesahanProposalController::class, 'generate']);
            Route::post('/penelitian/{id}/lembar-pengesahan/final', [PengesahanProposalController::class, 'setFinal']);
            Route::put('/penelitian/{id}/rencana-target', [PenelitianController::class, 'updateRencanaTarget']);
            Route::post('/kesediaan-tim/{timId}/setuju', [KesediaanTimController::class, 'setuju']);
        });

        Route::middleware('permission:create subsidi apc')->post('/subsidi-apc', [SubsidiApcController::class, 'store']);
        Route::middleware('permission:create insentif')->post('/insentif-jurnal', [InsentifController::class, 'store']);
        Route::middleware('permission:create hki')->post('/hki', [HkiController::class, 'store']);

        Route::middleware('permission:submit revisi penelitian')->group(function () {
            Route::get('/revisi/{id}', [RevisiProposalController::class, 'show'])->whereNumber('id');
            Route::put('/penelitian/{id}/revisi/komentar/{komentarId}', [RevisiProposalController::class, 'saveRespon']);
            Route::get('/penelitian/{id}/revisi/dokumen', [RevisiProposalController::class, 'lihatDokumen']);
            Route::post('/penelitian/{id}/revisi/dokumen', [RevisiProposalController::class, 'uploadDokumen']);
            Route::post('/penelitian/{id}/revisi/final', [RevisiProposalController::class, 'final']);
        });

        Route::middleware('permission:submit monev penelitian')->group(function () {
            Route::get('/monev-hasil', [MonevHasilController::class, 'index']);
            Route::put('/monev-hasil/{id}/jawaban', [MonevHasilController::class, 'saveJawaban']);
            Route::post('/monev-hasil/{id}/kesimpulan', [MonevHasilController::class, 'saveKesimpulan']);
        });

        Route::middleware('permission:submit laporan akhir penelitian')->group(function () {
            Route::put('/kuesioner-penelitian/{detailId}', [KuesionerPenelitianController::class, 'saveJawaban']);
            Route::put('/laporan-akhir/{id}/dana-penyertaan', [LaporanAkhirController::class, 'updateDanaPenyertaan']);
            Route::post('/laporan-akhir/{id}/mahasiswa', [LaporanAkhirController::class, 'storeMahasiswa']);
            Route::put('/laporan-akhir/{id}/mahasiswa/{mahasiswaId}', [LaporanAkhirController::class, 'updateMahasiswa']);
            Route::delete('/laporan-akhir/{id}/mahasiswa/{mahasiswaId}', [LaporanAkhirController::class, 'destroyMahasiswa']);
            Route::post('/laporan-akhir/{id}/lembar-pengesahan', [LaporanAkhirController::class, 'generateLembarPengesahan']);
            Route::post('/laporan-akhir/{id}/lembar-pengesahan/final', [LaporanAkhirController::class, 'finalLembarPengesahan']);
            Route::put('/laporan-akhir/{id}/capaian/{targetId}', [CapaianLuaranController::class, 'update']);
            Route::post('/laporan-akhir/{id}/capaian/{targetId}/dokumen', [CapaianLuaranController::class, 'uploadDokumen']);
            Route::delete('/laporan-akhir/{id}/capaian/{targetId}/dokumen', [CapaianLuaranController::class, 'hapusDokumen']);
        });
    });

    Route::prefix('dkn')->middleware('role:DKN,ADM')->group(function () {
        Route::inertia('/dashboard', 'dkn/DekanDashboardPage');
        Route::inertia('/approval', 'dkn/DekanDashboardPage');
        Route::inertia('/pagu', 'dkn/PaguAnggaranPage');
        Route::inertia('/laporan-akhir', 'dkn/PersetujuanLaporanPage');
        Route::inertia('/monev', 'dkn/PenunjukanMonevPage');
        Route::inertia('/monitoring', 'dkn/MonitoringFakultasPage');
    });

    Route::prefix('rev')->middleware('role:REV,ADM')->group(function () {
        Route::inertia('/dashboard', 'rev/ReviewerDashboardPage');
        Route::inertia('/kesediaan', 'rev/KesediaanReviewerPage');
        Route::inertia('/penilaian', 'rev/PenilaianProposalPage');
        Route::inertia('/penilaian/{id}', 'rev/PenilaianProposalPage');
        Route::inertia('/revisi', 'rev/VerifikasiRevisiPage');
    });

    Route::prefix('rkt')->middleware('role:RKT,ADM')->group(function () {
        Route::inertia('/dashboard', 'rkt/RektoratDashboardPage');
        Route::inertia('/strategis', 'rkt/ApprovalStrategisPage');
        Route::inertia('/mbkm', 'rkt/MonitoringMbkmPage');
    });

    Route::prefix('akr')->middleware('role:AKR,ADM')->group(function () {
        Route::inertia('/dashboard', 'akr/AkreditasiDashboardPage');
        Route::inertia('/data-mining', 'akr/DataMiningBorangPage');
        Route::inertia('/luaran', 'akr/AkreditasiDashboardPage');
        Route::inertia('/export-lkps', 'akr/ExportLkpsPage');
    });

});
