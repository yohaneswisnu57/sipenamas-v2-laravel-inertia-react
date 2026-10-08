<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi generik untuk MasterDataCrudController, aturan diambil dari
 * config/master_data.php (satu entri per slug "Basis Data").
 */
class MasterDataCrudRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $config = config('master_data.'.$this->route('slug'));
        $rules = $config['fillable'] ?? [];

        if (! empty($config['uniqueKey'])) {
            $table = (new $config['model'])->getTable();
            $column = $config['uniqueKey'];

            $unique = Rule::unique($table, $column);
            if ($id = $this->route('id')) {
                $unique->ignore($id);
            }

            $rules[$column] = array_filter(explode('|', $rules[$column] ?? 'required|string'));
            $rules[$column][] = $unique;
        }

        if (! empty($config['scope'])) {
            $rules[$config['scope']['param']] = 'required|string';
        }

        return $rules;
    }
}
