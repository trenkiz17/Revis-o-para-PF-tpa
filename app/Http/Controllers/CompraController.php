<?php

namespace App\Http\Controllers;

use App\Models\Produto;
use App\Models\Venda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompraController extends Controller
{
    public function index()
    {
        // R8: ninguem pode ver as compras de outro usuario.
        $vendas = Venda::where('cliente_id', Auth::id())
            ->with('produtos')
            ->latest()
            ->get();

        return view('compras.index', compact('vendas'));
    }

    public function create()
    {
        $produtos = Produto::orderBy('nome')->get();

        return view('compras.create', compact('produtos'));
    }

    public function store(Request $request)
    {
        // R7: o formulario envia itens[id_do_produto] = quantidade.
        $dados = $request->validate([
            'itens' => ['required', 'array'],
            'itens.*' => ['required', 'integer', 'min:0'],
        ]);

        // Quantidade 0 nao vira item na venda.
        $itens = collect($dados['itens'])
            ->filter(fn ($quantidade) => (int) $quantidade > 0);

        if ($itens->isEmpty()) {
            throw ValidationException::withMessages([
                'itens' => 'Selecione pelo menos um produto com quantidade maior que zero.',
            ]);
        }

        // R7 + bonus: tudo acontece junto; se algo falhar, nada e salvo.
        DB::transaction(function () use ($itens) {
            $produtos = [];

            foreach ($itens as $produtoId => $quantidade) {
                // Bloqueio evita que duas compras consumam o mesmo estoque ao mesmo tempo
                // em bancos que suportam SELECT ... FOR UPDATE.
                $produto = Produto::whereKey($produtoId)->lockForUpdate()->first();

                if (! $produto) {
                    throw ValidationException::withMessages([
                        'itens' => "O produto de codigo {$produtoId} nao existe.",
                    ]);
                }

                if ($quantidade > $produto->estoque) {
                    throw ValidationException::withMessages([
                        'itens' => "Estoque insuficiente para {$produto->nome}. Disponivel: {$produto->estoque}.",
                    ]);
                }

                $produtos[$produtoId] = $produto;
            }

            // So criamos a venda depois que TODOS os itens foram conferidos.
            $venda = Venda::create(['cliente_id' => Auth::id()]);

            foreach ($itens as $produtoId => $quantidade) {
                $produto = $produtos[$produtoId];

                // A tabela produto_venda guarda quantidade e preco na data da compra.
                $venda->produtos()->attach($produto->id, [
                    'quantidade' => $quantidade,
                    'preco_unitario' => $produto->preco,
                ]);

                // Bonus: reduz o estoque so quando a venda foi registrada.
                $produto->decrement('estoque', $quantidade);
            }
        });

        return redirect()->route('compras.index')->with('sucesso', 'Compra realizada!');
    }
}
