<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngestionCheckpoint extends Model
{
    /**
     * Os atributos que podem ser atribuídos em massa.
     *
     * @var list<string>
     */
    protected $fillable = [
        'file_path',
        'fingerprint',
        'byte_offset',
        'processed_lines',
    ];

    /**
     * Retorna os atributos que devem ser convertidos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'byte_offset' => 'integer',
            'processed_lines' => 'integer',
        ];
    }
}
