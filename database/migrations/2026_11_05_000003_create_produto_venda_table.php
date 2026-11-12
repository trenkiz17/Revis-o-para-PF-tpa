<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('produto_venda', function (Blueprint $table) {
            $table->foreignId('venda_id')->constrained('vendas')->cascadeOnDelete();
            $table->foreignId('produto_id')->constrained('produtos');
            $table->unsignedInteger('quantidade');
            $table->decimal('preco_unitario', 8, 2);
            $table->timestamps();

            $table->primary(['venda_id', 'produto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produto_venda');
    }
};
