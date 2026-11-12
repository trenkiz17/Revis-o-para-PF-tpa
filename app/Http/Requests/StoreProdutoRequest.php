<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProdutoRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A rota ja exige usuario autenticado com perfil vendedor.
        return true;
    }

    public function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'categoria' => ['required', Rule::in(['Eletrônicos', 'Vestuário', 'Casa', 'Alimentos'])],
            'garantia_meses' => ['required', 'integer', 'between:0,36'],
            'preco' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:999999.99'],
            'estoque' => ['required', 'integer', 'min:0'],
        ];
    }
}
