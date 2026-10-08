<?php

namespace App\Http\Controllers\Web\Peneliti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Peneliti\StoreLaporanMahasiswaRequest;
use App\Http\Requests\Peneliti\UpdateDanaPenyertaanRequest;
use App\Models\Penelitian;
use App\Models\Periode;
use App\Services\Proposal\DocxToPdfConverter;
use App\Services\Proposal\LaporanAkhirGate;
use App\Services\Proposal\LaporanAkhirPanel;
use App\Services\Proposal\PengesahanLaporanAkhirDocxService;
use App\Support\MasterData\MasterDataOptions;
use Closure;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * Padanan pen/myphp/hasilpenelitian.php legacy (panel "Laporan Akhir"):
 * daftar penelitian LOLOS milik tim, lalu jendela KELENGKAPAN LAPORAN AKHIR
 * untuk ketua - dana penyertaan, mahasiswa terlibat, dan lembar pengesahan
 * laporan akhir (generate -> set final -> unduh setelah disetujui Dekan).
 *
 * Jendela yang terbuka ditandai query string (`?kuesioner=1`,
 * `?kelengkapan={id}`, `?capaian={id}`), jadi redirect sesudah aksi
 * memuat ulang isi jendela yang sama.
 */
class LaporanAkhirController extends Controller
{
    public function __construct(private LaporanAkhirGate $gate, private LaporanAkhirPanel $panel) {}

    public function index(Request $request, MasterDataOptions $options): Response
    {
        $user = $request->user();
        $periode = $request->filled('kdperiode')
            ? Periode::where('KODEPERIODE', $request->query('kdperiode'))->firstOrFail()
            : Periode::where('ISAKTIF', 1)->firstOrFail();

        return Inertia::render('pen/LaporanAkhirPage', [
            'periodeList' => fn () => $options->periode(),
            'laporan' => fn () => $this->daftar($request, $periode),
            'kuesioner' => fn () => $request->boolean('kuesioner')
                ? ($this->panel->kuesioner($user, $periode) ?? ['error' => 'Belum ada soal kuesioner aktif'])
                : null,
            'kelengkapan' => fn () => $this->jendela($request, 'kelengkapan', fn (int $id) => $this->panel->kelengkapan($this->gate->ketuaDenganKuesioner($user, $id))),
            'capaian' => fn () => $this->jendela($request, 'capaian', fn (int $id) => $this->panel->capaian($this->gate->ketuaDenganLembarFinal($user, $id))),
        ]);
    }

    public function updateDanaPenyertaan(UpdateDanaPenyertaanRequest $request, int $id): RedirectResponse
    {
        $penelitian = $this->gate->ketuaDenganKuesioner($request->user(), $id);

        $penelitian->update([
            'LBRPENGESAHANLAPHASIL_DANAMITRA' => $request->input('danaMitra'),
            'LBRPENGESAHANLAPHASIL_DANAINKIND' => $request->input('danaInkind'),
        ]);

        return back()->with('success', 'Dana Penyertaan sudah diupdate');
    }

    public function storeMahasiswa(StoreLaporanMahasiswaRequest $request, int $id): RedirectResponse
    {
        $this->belumFinal($request, $id)->mahasiswa()->create(['NIM' => $request->input('nim')]);

        return back()->with('success', 'Mahasiswa ditambahkan');
    }

    public function updateMahasiswa(StoreLaporanMahasiswaRequest $request, int $id, int $mahasiswaId): RedirectResponse
    {
        $this->belumFinal($request, $id)->mahasiswa()->findOrFail($mahasiswaId)->update(['NIM' => $request->input('nim')]);

        return back()->with('success', 'Mahasiswa diperbarui');
    }

    public function destroyMahasiswa(Request $request, int $id, int $mahasiswaId): RedirectResponse
    {
        $this->belumFinal($request, $id)->mahasiswa()->findOrFail($mahasiswaId)->delete();

        return back()->with('success', 'Mahasiswa dihapus');
    }

    public function generateLembarPengesahan(Request $request, int $id, PengesahanLaporanAkhirDocxService $docx): RedirectResponse
    {
        $penelitian = $this->belumFinal($request, $id);
        $namaFile = "lbrpengesahan_lapakhir_{$penelitian->id}.docx";

        $sementara = $docx->build($penelitian);
        Storage::disk('legacy_res')->put(LaporanAkhirPanel::FOLDER_LEMBAR.'/'.$namaFile, file_get_contents($sementara));
        @unlink($sementara);

        $penelitian->update([
            'LBRPENGESAHANLAPHASIL_NAMAFILE' => $namaFile,
            'LBRPENGESAHANLAPHASIL_QRCODE' => uniqid().$penelitian->id.uniqid(),
        ]);

        return back()->with('success', 'Lembar pengesahan laporan akhir dibuat');
    }

    public function finalLembarPengesahan(Request $request, int $id, PengesahanLaporanAkhirDocxService $docx): RedirectResponse
    {
        $penelitian = $this->belumFinal($request, $id);
        $path = $this->panel->lembarPath($penelitian);

        abort_if(! $path, 422, 'Lembar pengesahan belum dibuat. Klik GENERATE DOKUMEN terlebih dulu.');

        $docx->stempel($path, ketuaSudahTtd: true);
        $penelitian->update(['LBRPENGESAHANLAPHASIL_ISFINAL' => 1]);

        return back()->with('success', 'Lembar pengesahan berstatus final');
    }

    /**
     * `?preview=1` -> pratinjau PDF (padanan cekFellembarpengesahan, kapan
     * saja). Tanpa itu -> unduh docx, hanya setelah disetujui Dekan
     * (padanan cekPengesahandisetujuidekan + donlotFellembarpengesahan).
     */
    public function unduhLembarPengesahan(Request $request, int $id, DocxToPdfConverter $pdfConverter): BinaryFileResponse
    {
        $penelitian = $this->gate->ketuaDenganKuesioner($request->user(), $id);
        $path = $this->panel->lembarPath($penelitian);

        abort_if(! $path, 404, 'Lembar pengesahan belum dibuat');

        if ($request->boolean('preview')) {
            try {
                return response()->file($pdfConverter->convert($path), ['Content-Type' => 'application/pdf'])->deleteFileAfterSend();
            } catch (RuntimeException $e) {
                abort(503, 'Konversi PDF belum tersedia di server');
            }
        }

        abort_if(! $penelitian->ISDEKANAPPROVELAPORANAKHIR, 422, 'DOKUMEN TIDAK BISA DIUNDUH: BELUM DISETUJUI DEKAN.');

        return response()->download($path, 'LembarPengesahanHasilPenelitian.docx');
    }

    /**
     * @return array<string, mixed>
     */
    private function daftar(Request $request, Periode $periode): array
    {
        $user = $request->user();
        $aliases = $user->kodepersonAliases();

        $items = $this->gate->query($user)
            ->where('PERIODEKEGIATAN_TAHUN', $periode->TAHUN)
            ->with(['skim', 'tim'])
            ->orderBy('TGLMULAI')
            ->get()
            ->map(fn (Penelitian $penelitian) => [
                'id' => $penelitian->id,
                'judul' => $penelitian->JUDULPENELITIAN,
                'tahun' => $penelitian->PERIODEKEGIATAN_TAHUN,
                'skim' => $penelitian->skim?->NAMASKIM,
                'peran' => $penelitian->tim->first(fn ($tim) => in_array($tim->NIKNIDN, $aliases, true))?->PERAN,
                'isLembarPengesahanFinal' => (bool) $penelitian->LBRPENGESAHANLAPHASIL_ISFINAL,
                'isDisetujuiDekan' => (bool) $penelitian->ISDEKANAPPROVELAPORANAKHIR,
                'statusKetuntasan' => $penelitian->STATUSKETUNTASANPENELITIAN,
            ]);

        return [
            'kdperiode' => $periode->KODEPERIODE,
            'isKuesionerSelesai' => $this->gate->kuesionerSelesai($user, $periode),
            'items' => $items,
        ];
    }

    /**
     * Isi jendela untuk `?{nama}={id}`; pesan penolakan gate (bukan ketua,
     * kuesioner belum lengkap, dst.) dikirim sebagai `error`.
     *
     * @param  Closure(int): array<string, mixed>  $isi
     * @return array<string, mixed>|null
     */
    private function jendela(Request $request, string $nama, Closure $isi): ?array
    {
        if (! $request->filled($nama)) {
            return null;
        }

        try {
            return $isi($request->integer($nama));
        } catch (ModelNotFoundException) {
            return ['error' => 'Data tidak ditemukan.'];
        } catch (HttpExceptionInterface $e) {
            return ['error' => $e->getMessage() ?: 'Data tidak dapat dibuka.'];
        }
    }

    private function belumFinal(Request $request, int $id): Penelitian
    {
        $penelitian = $this->gate->ketuaDenganKuesioner($request->user(), $id);

        abort_if((bool) $penelitian->LBRPENGESAHANLAPHASIL_ISFINAL, 422, 'Lembar pengesahan sudah final dan tidak dapat diubah lagi');

        return $penelitian;
    }
}
