<?php

use App\Http\Controllers\CompraController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\ProdutoController;
use Illuminate\Support\Facades\Route;

// Paginas publicas
Route::view('/', 'home')->name('home');
Route::view('/sobre', 'sobre')->name('sobre');
Route::get('/produtos', [ProdutoController::class, 'index'])->name('produtos.index');

// Autenticacao
Route::get('/login', [LoginController::class, 'create'])->name('login');
Route::post('/login', [LoginController::class, 'attempt'])->name('login.attempt');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// R3 + R4: primeiro precisa estar logado; depois precisa ser vendedor.
Route::middleware(['auth', 'vendedor'])->group(function () {
    Route::get('/produtos/create', [ProdutoController::class, 'create'])->name('produtos.create');
    Route::post('/produtos', [ProdutoController::class, 'store'])->name('produtos.store');
});

// R3: clientes e vendedores logados podem comprar e consultar suas compras.
Route::middleware('auth')->group(function () {
    Route::get('/compras', [CompraController::class, 'index'])->name('compras.index');
    Route::get('/compras/create', [CompraController::class, 'create'])->name('compras.create');
    Route::post('/compras', [CompraController::class, 'store'])->name('compras.store');
});
