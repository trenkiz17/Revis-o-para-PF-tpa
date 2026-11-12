<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Produto extends Model
{
    protected $table = 'produtos';

    protected $fillable = [
        'nome',
        'categoria',
        'garantia_meses',
        'preco',
        'estoque',
        'vendedor_id',
    ];

    protected function casts(): array
    {
        return [
            'preco' => 'decimal:2',
            'garantia_meses' => 'integer',
            'estoque' => 'integer',
        ];
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function vendas(): BelongsToMany
    {
        return $this->belongsToMany(Venda::class, 'produto_venda')
            ->withPivot('quantidade', 'preco_unitario')
            ->withTimestamps();
    }
}
