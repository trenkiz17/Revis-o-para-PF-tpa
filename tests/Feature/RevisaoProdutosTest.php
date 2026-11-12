<?php

namespace Tests\Feature;

use App\Models\Produto;
use App\Models\User;
use App\Models\Venda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RevisaoProdutosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_listagem_e_publica_e_exibe_vendedores(): void
    {
        $this->get(route('produtos.index'))
            ->assertOk()
            ->assertSee('Fone Bluetooth')
            ->assertSee('Ana Vendedora')
            ->assertSee('Bruno Vendedor');
    }

    public function test_login_rejeita_senha_incorreta_e_aceita_correta(): void
    {
        $this->post(route('login.attempt'), [
            'email' => 'carla@loja.test',
            'password' => 'errada',
        ])->assertSessionHasErrors('email');

        $this->post(route('login.attempt'), [
            'email' => 'carla@loja.test',
            'password' => 'senha123',
        ])->assertRedirect(route('produtos.index'))
          ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs(User::where('email', 'carla@loja.test')->first());
    }

    public function test_logout_desconecta(): void
    {
        $this->actingAs($this->usuario('carla@loja.test'));
        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_paginas_privadas_exigem_login(): void
    {
        foreach (['produtos.create', 'compras.create', 'compras.index'] as $rota) {
            $this->get(route($rota))->assertRedirect(route('login'));
        }
    }

    public function test_cliente_nao_cadastra_e_vendedor_pode_abrir_formulario(): void
    {
        $this->actingAs($this->usuario('carla@loja.test'));
        $this->get(route('produtos.create'))->assertForbidden();
        $this->post(route('produtos.store'), $this->dadosValidos())->assertForbidden();

        $this->actingAs($this->usuario('ana@loja.test'));
        $this->get(route('produtos.create'))->assertOk();
    }

    public function test_formulario_valida_dados_invalidos(): void
    {
        $this->actingAs($this->usuario('ana@loja.test'));

        $this->post(route('produtos.store'), [])->assertSessionHasErrors([
            'nome', 'categoria', 'garantia_meses', 'preco', 'estoque',
        ]);

        $this->post(route('produtos.store'), array_merge($this->dadosValidos(), [
            'garantia_meses' => 48,
            'preco' => 0,
        ]))->assertSessionHasErrors(['garantia_meses', 'preco']);
    }

    public function test_produto_cadastrado_pertence_ao_vendedor_logado(): void
    {
        $ana = $this->usuario('ana@loja.test');
        $bruno = $this->usuario('bruno@loja.test');
        $this->actingAs($ana);

        $this->post(route('produtos.store'), $this->dadosValidos() + [
            'vendedor_id' => $bruno->id,
        ])->assertRedirect(route('produtos.index'));

        $this->assertDatabaseHas('produtos', [
            'nome' => 'Liquidificador',
            'vendedor_id' => $ana->id,
        ]);
    }

    public function test_compra_grava_pivo_quantidades_preco_e_reduz_estoque(): void
    {
        $this->actingAs($this->usuario('carla@loja.test'));
        $um = Produto::where('nome', 'Fone Bluetooth')->firstOrFail();
        $dois = Produto::where('nome', 'Café em Grãos 1 kg')->firstOrFail();
        $zero = Produto::where('nome', 'Jogo de Panelas')->firstOrFail();

        $this->post(route('compras.store'), ['itens' => [
            $um->id => 2,
            $dois->id => 1,
            $zero->id => 0,
        ]])->assertRedirect(route('compras.index'))
          ->assertSessionHas('sucesso', 'Compra realizada!');

        $venda = Venda::with('produtos')->firstOrFail();
        $this->assertSame(2, $venda->produtos->count());
        $this->assertSame($this->usuario('carla@loja.test')->id, $venda->cliente_id);

        $this->assertDatabaseHas('produto_venda', [
            'venda_id' => $venda->id,
            'produto_id' => $um->id,
            'quantidade' => 2,
            'preco_unitario' => '129.90',
        ]);
        $this->assertDatabaseMissing('produto_venda', [
            'venda_id' => $venda->id,
            'produto_id' => $zero->id,
        ]);
        $this->assertSame(13, $um->fresh()->estoque);
        $this->assertSame(19, $dois->fresh()->estoque);
    }

    public function test_somente_minhas_compras_sao_listadas(): void
    {
        $this->actingAs($this->usuario('carla@loja.test'));
        $produto = Produto::firstOrFail();
        $this->post(route('compras.store'), ['itens' => [$produto->id => 1]]);

        $this->get(route('compras.index'))->assertOk()->assertSee('Compra #1');

        $this->actingAs($this->usuario('diego@loja.test'));
        $this->get(route('compras.index'))
            ->assertOk()
            ->assertDontSee('Compra #1')
            ->assertSee('Você ainda não fez nenhuma compra.');
    }

    public function test_estoque_insuficiente_nao_cria_venda_nem_muda_estoque(): void
    {
        $this->actingAs($this->usuario('carla@loja.test'));
        $produto = Produto::firstOrFail();
        $estoque = $produto->estoque;

        $this->post(route('compras.store'), [
            'itens' => [$produto->id => $estoque + 1],
        ])->assertSessionHasErrors('itens');

        $this->assertDatabaseCount('vendas', 0);
        $this->assertSame($estoque, $produto->fresh()->estoque);
    }

    public function test_carrinho_so_com_zeros_nao_cria_venda(): void
    {
        $this->actingAs($this->usuario('carla@loja.test'));
        $produto = Produto::firstOrFail();
        $this->post(route('compras.store'), [
            'itens' => [$produto->id => 0],
        ])->assertSessionHasErrors('itens');
        $this->assertDatabaseCount('vendas', 0);
    }

    private function usuario(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    private function dadosValidos(): array
    {
        return [
            'nome' => 'Liquidificador',
            'categoria' => 'Casa',
            'garantia_meses' => 12,
            'preco' => '149.90',
            'estoque' => 7,
        ];
    }
}
