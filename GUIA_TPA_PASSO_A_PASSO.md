# Guia completo de estudo - Revisão de TPA (Laravel)

**Projeto:** Loja de Produtos  
**Objetivo:** entender o que foi implementado, explicar na prova e conseguir refazer os requisitos sozinho.  
**Atividade:** R1 a R8 + bônus de estoque (14 pontos máximos, conforme enunciado).

---

## 1. Entenda o que a atividade quer

A aplicação é uma loja com dois tipos de usuário:

- **Vendedor:** entra na conta, cadastra produtos e também pode comprar.
- **Cliente:** entra na conta e faz compras, mas **não** pode cadastrar produtos.
- **Visitante (sem login):** só vê a listagem pública, a página inicial e as informações.

O professor já forneceu as telas (arquivos `.blade.php`) e as rotas. Nós precisamos completar a parte que trabalha por trás das telas: banco de dados, modelos, controladores, login, autorização e compras.

**Fluxo completo:**

1. O usuário abre uma URL.
2. A **rota** (`routes/web.php`) encontra o método do **controller**.
3. Um **middleware** pode barrar a requisição por falta de login ou perfil incorreto.
4. O controller usa uma **model** (Eloquent/ORM) para consultar ou gravar no banco.
5. O controller envia os dados para a **view Blade**, que monta a página.

Pense no MVC:

- **Model:** representa os dados, por exemplo `Produto` e `Venda`.
- **View:** a tela que aparece no navegador (Blade).
- **Controller:** recebe a ação e decide o que fazer.

As *migrations* criam as tabelas. Os *seeders* colocam dados de exemplo. O *FormRequest* valida formulários. Os *middlewares* controlam quem pode acessar.

---

## 2. Como abrir e executar o projeto

### Preparação (Windows, no terminal do VS Code)

É necessário ter **PHP 8.2 ou superior**, **Composer** e o driver de banco `pdo_sqlite` habilitado no PHP. O projeto é Laravel 12. Depois de descompactar o ZIP, entre na pasta que contém o arquivo `artisan`.

Execute um comando por vez no **PowerShell**:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
if (!(Test-Path database/database.sqlite)) {
    New-Item -ItemType File database/database.sqlite | Out-Null
}
php artisan migrate:fresh --seed
php artisan serve
```

No navegador, abra: **http://localhost:8000/produtos**.

Se o `.env` já existir, **não precisa copiar** o `.env.example` novamente. Para conferir o driver, execute `php -m` e procure `pdo_sqlite`. Se não houver, habilite `extension=pdo_sqlite` no `php.ini` usado pelo seu terminal e reinicie o terminal.

**Atenção:** `migrate:fresh --seed` **apaga e recria as tabelas**, perdendo os cadastros/compras que estavam no banco. É ótimo para treino, mas não execute em um banco com dados importantes.

### Login de teste

| Usuário | E-mail | Tipo |
|---|---|---|
| Ana | `ana@loja.test` | vendedor |
| Bruno | `bruno@loja.test` | vendedor |
| Carla | `carla@loja.test` | cliente |
| Diego | `diego@loja.test` | cliente |

A senha para **todos** é `senha123`.

### Testes automáticos

Depois de instalar as dependências, você também pode executar:

```powershell
php artisan test --filter=RevisaoProdutosTest
```

Esse comando roda os testes que adicionei em `tests/Feature/RevisaoProdutosTest.php`. Eles conferem login, permissões, cadastro, compra, isolamento das compras e estoque.

---

## 3. R1 - Listagem pública de produtos (2,0 pontos)

### O que o professor pediu?

Criar a tabela `produtos`, a classe `Produto` e mostrar os itens na listagem, inclusive o nome de quem cadastrou.

### Arquivos importantes

- `database/migrations/2026_11_05_000001_create_produtos_table.php`
- `app/Models/Produto.php`
- `app/Http/Controllers/ProdutoController.php`

### Entenda as migrations

```php
Schema::create('produtos', function (Blueprint $table) {
    $table->id();
    $table->string('nome');
    $table->string('categoria');
    $table->unsignedTinyInteger('garantia_meses');
    $table->decimal('preco', 8, 2);
    $table->unsignedInteger('estoque');
    $table->foreignId('vendedor_id')->constrained('users');
    $table->timestamps();
});
```

- `id()` cria o identificador de cada produto.
- `string()` guarda texto.
- `decimal('preco', 8, 2)` guarda números com **8 dígitos ao todo**, sendo **2 depois da vírgula/ponto**.
- `unsignedInteger()` guarda inteiro sem negativos.
- `foreignId('vendedor_id')` aponta para a tabela `users`.
- `timestamps()` cria `created_at` e `updated_at`.

### Entenda o relacionamento

Na model `Produto`:

```php
public function vendedor(): BelongsTo
{
    return $this->belongsTo(User::class, 'vendedor_id');
}
```

`belongsTo` significa: **cada produto pertence a um vendedor** (um usuário). O banco liga os dois por `vendedor_id`. Assim a view consegue usar `$produto->vendedor->name` para mostrar o nome.

### Entenda o Controller

```php
$produtos = Produto::with('vendedor')->orderBy('nome')->get();
return view('produtos.index', compact('produtos'));
```

- `Produto::` acessa a model, que usa Eloquent/ORM.
- `with('vendedor')` carrega antecipadamente os vendedores e evita consultas repetidas.
- `orderBy('nome')` ordena alfabeticamente.
- `get()` executa a consulta e devolve uma coleção.
- `view('produtos.index', ...)` abre a tela e leva a variável `$produtos`.
- `compact('produtos')` equivale a `['produtos' => $produtos]`.

**Teste:** entre em `/produtos` sem login. Devem aparecer 5 itens iniciais e os nomes Ana/Bruno.

---

## 4. R2 - Login e logout (1,5 ponto)

### Arquivo importante

`app/Http/Controllers/LoginController.php`

### Como o login funciona

```php
$credenciais = $request->validate([
    'email' => ['required', 'email'],
    'password' => ['required', 'string'],
]);

if (Auth::attempt($credenciais)) {
    $request->session()->regenerate();
    return redirect()->intended(route('produtos.index'));
}

return back()->withErrors(['email' => 'E-mail ou senha incorretos.'])
    ->onlyInput('email');
```

1. `$request` é o que chegou do formulário.
2. `validate()` verifica se os campos vieram preenchidos e com formato correto.
3. `Auth::attempt()` confere e-mail e senha com o banco.
4. Se estiver certo, regenera a sessão por segurança e redireciona.
5. Se estiver errado, `back()` volta e `withErrors()` manda a mensagem de erro.

### Como o logout funciona

`Auth::logout()` desconecta; `session()->invalidate()` encerra a sessão antiga; `session()->regenerateToken()` renova o token CSRF.

**Teste:** login da Carla com senha errada deve falhar; com `senha123` deve funcionar; depois clique em Sair.

---

## 5. R3 - Exigir login com middleware `auth` (1,0 ponto)

### Arquivo importante

`routes/web.php`

Um *middleware* é como um porteiro. Ele verifica um requisito **antes** de permitir a execução do controller.

```php
Route::middleware('auth')->group(function () {
    Route::get('/compras', [CompraController::class, 'index'])
        ->name('compras.index');
    Route::get('/compras/create', [CompraController::class, 'create'])
        ->name('compras.create');
    Route::post('/compras', [CompraController::class, 'store'])
        ->name('compras.store');
});
```

`auth` é um middleware pronto do Laravel: quem não está logado vai para o login. A mesma proteção foi usada no cadastro de produtos, junto com `vendedor`.

**Cuidado:** a rota `GET /produtos` NÃO pode receber `auth`, porque a listagem é pública.

**Teste:** sem login, cadastrar produto, comprar e minhas compras devem redirecionar ao login; visualizar produtos continua liberado.

---

## 6. R4 - Só vendedor pode cadastrar (2,0 pontos)

### Arquivos importantes

- `app/Http/Middleware/VerificarVendedor.php`
- `bootstrap/app.php`
- `routes/web.php`

Código do middleware:

```php
if ($request->user()?->tipo !== 'vendedor') {
    abort(403);
}
return $next($request);
```

- `$request->user()` recupera o usuário logado.
- `?->` acessa `tipo` sem provocar erro se não houver usuário.
- `!==` significa **diferente de**, comparando tipo e valor.
- `abort(403)` mostra acesso negado.
- `$next($request)` permite continuar para o controller.

Em `bootstrap/app.php`, o código `alias(['vendedor' => VerificarVendedor::class])` registra o apelido `vendedor`.

Em `routes/web.php`, o grupo `['auth', 'vendedor']` faz duas verificações: **1º login, 2º perfil de vendedor**.

**Teste:** Carla tenta `/produtos/create` e recebe **403**; Ana entra na mesma tela e consegue cadastrar.

---

## 7. R5 - Cadastro com validação (2,0 pontos)

### Arquivos importantes

- `app/Http/Requests/StoreProdutoRequest.php`
- `app/Http/Controllers/ProdutoController.php`

O *FormRequest* é uma classe dedicada a validar os dados. Nele estão estas regras:

| Campo | Regra | Exemplo correto | Exemplo incorreto |
|---|---|---|---|
| `nome` | obrigatório, texto, até 255 caracteres | Liquidificador | vazio |
| `categoria` | uma das quatro opções | Casa | Veículos |
| `garantia_meses` | inteiro entre 0 e 36 | 12 | 48 |
| `preco` | número maior que 0, até 2 decimais | 149.90 | 0 |
| `estoque` | inteiro a partir de 0 | 7 | -1 |

**Pegadinha de prova:** `garantia_meses = 0` é **válido**, assim como `estoque = 0`. Mas `preco = 0` é **inválido**.

No controller:

```php
public function store(StoreProdutoRequest $request)
{
    $dados = $request->validated();
    $dados['vendedor_id'] = Auth::id();
    Produto::create($dados);
    return redirect()->route('produtos.index');
}
```

- O Laravel valida **antes** de entrar no corpo desse método.
- `validated()` traz apenas os campos aceitos.
- `Produto::create()` faz um INSERT no banco por meio de Eloquent.

Na model, `fillable` contém os campos que podem ser preenchidos via `create()`.

**Teste:** envie vazio (erros); garantia `48` (erro); preço `0` (erro); depois cadastre `Liquidificador / Casa / 12 / 149.90 / 7`.

---

## 8. R6 - O produto pertence ao vendedor logado (1,0 ponto)

### A linha principal

```php
$dados['vendedor_id'] = Auth::id();
```

`Auth::id()` devolve o ID do usuário autenticado. Se Ana está logada, o produto recebe o ID da Ana; se Bruno está logado, recebe o ID do Bruno.

**Por que isso é importante?** Se o formulário enviasse `vendedor_id`, alguém poderia alterar esse campo e cadastrar um produto no nome de outra pessoa. Por isso, essa informação é criada pelo **servidor**, nunca aceita do navegador.

**Teste:** cadastre produtos com Ana e Bruno e confira o nome dos respectivos vendedores na listagem.

---

## 9. R7 - Registrar compras e tabela pivô (2,5 pontos)

### Arquivos importantes

- `database/migrations/2026_11_05_000002_create_vendas_table.php`
- `database/migrations/2026_11_05_000003_create_produto_venda_table.php`
- `app/Models/Venda.php`
- `app/Http/Controllers/CompraController.php`

### Por que há duas tabelas além dos produtos?

`vendas` guarda **quem comprou** e quando. A tabela `produto_venda` guarda **quais produtos fazem parte de cada compra**, a quantidade e o preço no momento da compra.

Exemplo:

**Tabela vendas**

| id | cliente_id |
|---|---|
| 1 | 3 (Carla) |

**Tabela produto_venda**

| venda_id | produto_id | quantidade | preco_unitario |
|---|---|---|---|
| 1 | 1 | 2 | 129.90 |
| 1 | 3 | 1 | 299.90 |

Uma venda pode ter muitos produtos, e um produto pode aparecer em várias vendas. Isso é relacionamento **N:M (muitos para muitos)**.

### O relacionamento Eloquent

```php
public function produtos(): BelongsToMany
{
    return $this->belongsToMany(Produto::class, 'produto_venda')
        ->withPivot('quantidade', 'preco_unitario')
        ->withTimestamps();
}
```

- `belongsToMany()` define a relação muitos para muitos.
- `withPivot(...)` pede para trazer as colunas extras da tabela intermediária.
- `withTimestamps()` preenche datas dessa tabela.

### Como o formulário envia quantidades

O campo HTML tem nome `itens[ID]`. Se a pessoa seleciona 2 unidades do produto 1 e 1 unidade do produto 3, o PHP recebe algo parecido com:

```php
[
    'itens' => [
        1 => 2,
        2 => 0,
        3 => 1,
    ],
]
```

A lógica de `CompraController@store` segue esta sequência:

1. Valida `itens` como array e cada quantidade como inteiro de **0 para cima**.
2. Filtra as quantidades **maiores que 0**.
3. Se tudo for 0, mostra erro e não cria uma venda vazia.
4. Procura cada produto e confere se há estoque.
5. Cria uma `Venda` com o ID do cliente logado.
6. Usa `attach()` para adicionar cada produto com a quantidade e o preço unitário.
7. Reduz o estoque e redireciona para Minhas compras.

Trecho fundamental:

```php
$venda = Venda::create(['cliente_id' => Auth::id()]);

$venda->produtos()->attach($produto->id, [
    'quantidade' => $quantidade,
    'preco_unitario' => $produto->preco,
]);
```

**Por que guardar `preco_unitario` na pivô?** Porque, se o vendedor mudar o preço amanhã, a compra de ontem precisa continuar mostrando **quanto o cliente pagou na época**.

**Teste:** compre 2 unidades de um produto, 1 de outro e deixe o resto em 0. A venda deve ter **exatamente dois itens**.

---

## 10. R8 - Mostrar apenas as minhas compras (2,0 pontos)

### Arquivo importante

`app/Http/Controllers/CompraController.php`, método `index()`.

```php
$vendas = Venda::where('cliente_id', Auth::id())
    ->with('produtos')
    ->latest()
    ->get();

return view('compras.index', compact('vendas'));
```

- `where('cliente_id', Auth::id())` filtra as vendas do usuário autenticado.
- `with('produtos')` já traz os itens da venda.
- `latest()` ordena pelas compras mais recentes.
- `get()` consulta o banco.

Na view, `$produto->pivot->quantidade` e `$produto->pivot->preco_unitario` recuperam os dados da relação. **Subtotal = quantidade × preço unitário**. **Total = soma dos subtotais**.

**Teste:** Carla compra e vê o pedido. Ao entrar como Diego, as compras da Carla **não podem aparecer**.

---

## 11. Bônus - Controle de estoque (2,0 pontos)

### As duas regras principais

1. Não deixar comprar mais unidades do que o estoque disponível.
2. Quando a compra dá certo, diminuir o estoque.

No controller:

```php
if ($quantidade > $produto->estoque) {
    throw ValidationException::withMessages([
        'itens' => 'Estoque insuficiente.',
    ]);
}

$produto->decrement('estoque', $quantidade);
```

`decrement('estoque', 2)` significa: **subtraia 2 da coluna estoque** no banco.

### Por que usar transação?

A compra envolve mais de uma gravação: criar venda, criar itens na pivô e atualizar estoque. `DB::transaction(...)` garante que essas etapas sejam tratadas como **uma única operação**. Se uma falhar, as alterações são desfeitas (rollback). Assim não sobra uma compra incompleta.

O código também usa `lockForUpdate()` para proteger a leitura do estoque em bancos que suportam bloqueio de linhas durante transações.

**Teste:** tente comprar mais que o estoque: deve dar erro e não criar venda. Depois compre 1 unidade: o estoque precisa cair em 1.

---

## 12. Perguntas prováveis da prova (com respostas curtas)

**1. O que é MVC?**  
É separar o sistema em Model (dados), View (tela) e Controller (lógica da requisição), para organizar e facilitar a manutenção.

**2. O que é Eloquent/ORM?**  
É o recurso do Laravel que permite consultar e gravar dados usando classes PHP, como `Produto::create()` e `Produto::with()->get()`, sem escrever SQL manualmente para essas operações.

**3. O que é uma migration?**  
É um arquivo que define a estrutura do banco de dados, suas tabelas, colunas e chaves.

**4. O que é um seeder?**  
É um arquivo que preenche o banco com dados iniciais/de teste.

**5. O que faz `belongsTo`?**  
Representa que um registro pertence a outro; aqui, um produto pertence a um vendedor.

**6. O que faz `belongsToMany`?**  
Representa o relacionamento muitos para muitos. Uma venda tem vários produtos, e um produto pode estar em várias vendas.

**7. Para que serve a tabela pivô?**  
Liga vendas a produtos e guarda informações extras da relação, como quantidade e preço unitário.

**8. Qual a diferença entre `with()` e `withPivot()`?**  
`with()` carrega antecipadamente relacionamentos; `withPivot()` determina quais campos extras da tabela pivô ficam disponíveis.

**9. Para que serve `attach()`?**  
Para inserir a ligação entre uma venda e um produto na tabela pivô, inclusive os campos extras.

**10. Qual a diferença entre `auth` e `vendedor`?**  
`auth` verifica se está logado. `vendedor` verifica se o usuário tem o perfil vendedor.

**11. O que significa erro 403?**  
Acesso proibido: a pessoa não tem permissão para aquela operação.

**12. Por que validar no servidor e não só no HTML?**  
Porque alguém pode alterar ou ignorar as validações do navegador e enviar dados diretamente para o servidor.

**13. Por que `vendedor_id` usa `Auth::id()`?**  
Para impedir que alguém escolha outro vendedor pelo formulário; o sistema determina o dono de forma segura.

**14. Para que serve `DB::transaction()`?**  
Para evitar dados incompletos: se algum passo da compra falhar, o banco desfaz as alterações daquela operação.

**15. O que faz `decrement()`?**  
Diminui numericamente um valor no banco, por exemplo o estoque após a compra.

**16. Por que o preço da venda também está na pivô?**  
Para preservar o valor efetivamente cobrado, mesmo que o preço atual do produto mude depois.

---

## 13. Checklist: simule a correção do professor

- [ ] Sem login, `/produtos` lista os produtos e os vendedores.
- [ ] Login errado mostra mensagem de erro.
- [ ] Login correto mostra o nome/tipo no topo; Sair desconecta.
- [ ] Sem login, cadastro e compras redirecionam ao login.
- [ ] Cliente logado recebe 403 ao cadastrar produtos.
- [ ] Vendedor logado abre o formulário.
- [ ] Formulário vazio acusa erros nos campos.
- [ ] Garantia `48` e preço `0` não passam.
- [ ] Produto válido é salvo e aparece com o vendedor correto.
- [ ] Compra com quantidades `2`, `1`, `0` cria apenas 2 itens.
- [ ] Minhas compras mostra quantidades, preço, subtotais e total.
- [ ] Diego não vê as compras da Carla.
- [ ] Tentativa de comprar mais que o estoque é rejeitada.
- [ ] Compra válida diminui o estoque.

### Problemas comuns

- **`vendor/autoload.php` ausente:** execute `composer install` dentro da pasta do projeto.
- **`No application encryption key has been specified`:** configure o `.env` e execute `php artisan key:generate`.
- **`could not find driver` no SQLite:** ative a extensão PHP `pdo_sqlite`.
- **`Database file ... does not exist`:** crie `database/database.sqlite` e confirme `DB_CONNECTION=sqlite` no `.env`.
- **`Table ... does not exist`:** execute as migrations (`php artisan migrate:fresh --seed` no banco de treino).
- **Erro 419 ao enviar formulário:** significa problema de sessão ou CSRF; as views originais já têm `@csrf`, então confira seu `.env` e cookies.
- **403 mesmo logado:** confira o `tipo` da conta. Cliente não pode cadastrar.

---

## 14. Mapa dos arquivos feitos / alterados

| Requisito | Arquivos principais |
|---|---|
| R1 | `app/Models/Produto.php`, migration de produtos, `ProdutoController.php` |
| R2 | `LoginController.php` |
| R3 | `routes/web.php` |
| R4 | `VerificarVendedor.php`, `bootstrap/app.php`, `routes/web.php` |
| R5 | `StoreProdutoRequest.php`, `ProdutoController.php` |
| R6 | `ProdutoController.php` (linha com `Auth::id()`) |
| R7 | `Venda.php`, migrations de vendas/pivô, `CompraController.php` |
| R8 | `CompraController.php` (`index()`) |
| Bônus | `CompraController.php` (validação de estoque, transação, decrement) |

**Importante:** este ZIP contém **todo o projeto**, não apenas os arquivos PHP que mudaram. As views, seeders, rotas nomeadas e os contratos do professor foram preservados. A pasta `vendor` não estava no material original e deve ser gerada por `composer install`.

**Observação de conferência:** a sintaxe dos arquivos PHP foi validada com `php -l`. O funcionamento completo dos testes automáticos depende das dependências do Composer e da extensão SQLite no computador onde o projeto for executado.
