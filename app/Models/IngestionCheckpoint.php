<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IngestionCheckpoint extends Model
{
    /**
     * The attributes that are mass assignable.
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
     * Get the attributes that should be cast.
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
