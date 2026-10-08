<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MasterDataCrudRequest;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller generik untuk seluruh submenu "Basis Data" (lihat
 * config/master_data.php) - satu controller dipakai bersama oleh 17 entity
 * lewat parameter route {slug}, supaya tidak perlu 17 controller nyaris
 * identik. Kapabilitas per entity (field yang boleh diisi, read-only atau
 * tidak, master-detail atau tidak, scope per induk) sepenuhnya diatur oleh
 * registry, bukan oleh cabang kode di sini.
 */
class MasterDataCrudController extends Controller
{
    protected function config(string $slug): array
    {
        $config = config("master_data.{$slug}");

        if (! $config) {
            throw new NotFoundHttpException("Entity basis data '{$slug}' tidak dikenal.");
        }

        return $config;
    }

    protected function assertWritable(array $config): void
    {
        if (! empty($config['readOnly'])) {
            abort(403, 'Data ini hanya bisa dilihat, tidak bisa diubah lewat menu Basis Data.');
        }
    }

    public function index(Request $request, string $slug)
    {
        $config = $this->config($slug);
        $model = $config['model'];

        $query = $model::query();

        if (! empty($config['scope'])) {
            $request->validate([$config['scope']['param'] => 'required|string']);
            $query->where($config['scope']['column'], $request->query($config['scope']['param']));
        }

        // Entity master-detail (Borang Penilaian) selalu ikut kirim baris
        // detail-nya supaya frontend tidak perlu endpoint GET terpisah untuk
        // itu - satu-satunya cara mutasi tetap lewat replaceDetails().
        if (! empty($config['details'])) {
            $query->with(['detail' => fn ($q) => $q->orderBy($config['details']['orderBy'])]);
        }

        $list = $query->orderBy($config['orderBy'])->get();

        return ApiResponse::success($list);
    }

    public function store(MasterDataCrudRequest $request, string $slug)
    {
        $config = $this->config($slug);
        $this->assertWritable($config);

        $data = $request->only(array_keys($config['fillable']));

        if (! empty($config['scope'])) {
            $data[$config['scope']['column']] = $request->input($config['scope']['param']);
        }

        $model = $config['model'];
        $record = $model::create($data);

        return ApiResponse::success($record, 'Data berhasil ditambahkan', 201);
    }

    public function update(MasterDataCrudRequest $request, string $slug, int $id)
    {
        $config = $this->config($slug);
        $this->assertWritable($config);

        $model = $config['model'];
        $record = $model::findOrFail($id);

        $data = $request->only(array_keys($config['fillable']));
        $record->update($data);

        return ApiResponse::success($record, 'Data berhasil diperbarui');
    }

    public function destroy(string $slug, int $id)
    {
        $config = $this->config($slug);
        $this->assertWritable($config);

        $model = $config['model'];
        $record = $model::findOrFail($id);

        DB::transaction(function () use ($config, $record) {
            if (! empty($config['details'])) {
                $config['details']['model']::where($config['details']['parentKey'], $record->id)->delete();
            }

            $record->delete();
        });

        return ApiResponse::success(null, 'Data berhasil dihapus');
    }

    /**
     * Ganti seluruh baris detail milik satu master row (delete-then-insert),
     * padanan legacy soalpenilaian*.php addData()/editData() yang selalu
     * mengosongkan detail lama lalu menyimpan ulang semuanya - lihat catatan
     * di rencana implementasi.
     */
    public function replaceDetails(Request $request, string $slug, int $id)
    {
        $config = $this->config($slug);
        $this->assertWritable($config);

        if (empty($config['details'])) {
            throw new NotFoundHttpException("Entity '{$slug}' tidak punya detail.");
        }

        $detail = $config['details'];
        $model = $config['model'];
        $record = $model::findOrFail($id);

        $rows = $request->validate([
            'rows' => 'present|array',
            'rows.*' => 'array',
        ])['rows'];

        foreach ($rows as $row) {
            foreach (array_keys($detail['fillable']) as $column) {
                if (! array_key_exists($column, $row)) {
                    abort(422, "Kolom detail '{$column}' wajib diisi.");
                }
            }
        }

        DB::transaction(function () use ($detail, $record, $rows) {
            $detail['model']::where($detail['parentKey'], $record->id)->delete();

            foreach ($rows as $row) {
                $detail['model']::create([
                    $detail['parentKey'] => $record->id,
                    ...array_intersect_key($row, $detail['fillable']),
                ]);
            }
        });

        return ApiResponse::success(
            $detail['model']::where($detail['parentKey'], $record->id)->orderBy($detail['orderBy'])->get(),
            'Detail berhasil disimpan'
        );
    }

    /**
     * Padanan legacy rencanatarget.php COPYFROM: salin seluruh baris
     * tabelrencanatarget dari satu skim ke skim lain.
     */
    public function copyScoped(Request $request, string $slug)
    {
        $config = $this->config($slug);
        $this->assertWritable($config);

        if (empty($config['scope'])) {
            throw new NotFoundHttpException("Entity '{$slug}' tidak mendukung salin data.");
        }

        $data = $request->validate([
            'src' => 'required|string',
            'dst' => 'required|string',
        ]);

        $model = $config['model'];
        $column = $config['scope']['column'];
        $fillableColumns = array_keys($config['fillable']);

        DB::transaction(function () use ($model, $column, $fillableColumns, $data) {
            $rows = $model::where($column, $data['src'])->get();

            foreach ($rows as $row) {
                $model::create([
                    $column => $data['dst'],
                    ...array_intersect_key($row->getAttributes(), array_flip($fillableColumns)),
                ]);
            }
        });

        return ApiResponse::success(
            $model::where($column, $data['dst'])->orderBy($config['orderBy'])->get(),
            'Data berhasil disalin'
        );
    }
}
