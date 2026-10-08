<?php

namespace App\Services\Proposal;

use App\Http\Resources\PenelitianResource;
use App\Models\Penelitian;
use Illuminate\Support\Facades\Storage;

/**
 * Lembar pengesahan proposal mengikuti urutan legacy
 * (permohonanpenelitian.php GENFELLEMBARPENGESAHAN ->
 * SETFINALFELLEMBARPENGESAHAN -> persetujuan Dekan): tanda tangan ketua
 * baru muncul setelah SET FINAL, tanda tangan Dekan setelah disetujui.
 * Tanda tangan berupa QR (requirement LPPM, lihat PengesahanQrService).
 */
class LembarPengesahanProposalService
{
    private const FOLDER = 'proposal';

    public function __construct(private PengesahanDocxService $docx, private KetuaLppmResolver $ketuaLppm) {}

    /** @return string path ke file DOCX sementara sesuai status saat ini */
    public function render(Penelitian $penelitian): string
    {
        $penelitian->loadMissing(PenelitianResource::EAGER_RELATIONS);
        $data = (new PenelitianResource($penelitian))->resolve();

        return $this->docx->build([
            'skim' => (string) $data['skimNama'],
            'judul' => (string) $data['judul'],
            'bidang' => $data['bidangFokus'] ?? '',
            'biaya' => number_format((float) $data['biayaUsulan']),
            'ketua' => $data['pengesahan']['ketua'] + ['prodi' => $data['prodiNama'] ?? ''],
            'ketuaTtd' => (bool) $penelitian->LBRPENGESAHANPROPOSAL_ISFINAL,
            'anggota' => collect($data['anggotaDosen'])->map(fn ($a) => ['nik' => $a['npp'] ?? '', 'nama' => $a['nama']])->all(),
            'dekan' => $data['pengesahan']['dekan'],
            'lppm' => $this->ketuaLppm->resolve(),
        ]);
    }

    /**
     * Render ulang dan simpan ke `res/proposal/lbrpengesahan_proposal_{id}.docx`.
     */
    public function simpan(Penelitian $penelitian): string
    {
        $namaFile = "lbrpengesahan_proposal_{$penelitian->id}.docx";
        $sementara = $this->render($penelitian);

        Storage::disk('legacy_res')->put(self::FOLDER.'/'.$namaFile, file_get_contents($sementara));
        @unlink($sementara);

        return $namaFile;
    }
}
