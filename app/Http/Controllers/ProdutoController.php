<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProdutoRequest;
use App\Models\Produto;
use Illuminate\Support\Facades\Auth;

class ProdutoController extends Controller
{
    public function index()
    {
        // R1: with('vendedor') carrega quem cadastrou cada produto.
        $produtos = Produto::with('vendedor')->orderBy('nome')->get();

        return view('produtos.index', compact('produtos'));
    }

    public function create()
    {
        return view('produtos.create');
    }

    public function store(StoreProdutoRequest $request)
    {
        // R5: apenas os campos aprovados pela validacao.
        $dados = $request->validated();

        // R6: o dono vem da sessao, nunca do formulario.
        $dados['vendedor_id'] = Auth::id();

        Produto::create($dados);

        return redirect()->route('produtos.index')->with('sucesso', 'Produto cadastrado!');
    }
}
