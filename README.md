# MiTemplate
MiTemplate permite separar a lógica de programação (PHP) da estrutura visual (HTML, XML, CSS, entre outros), facilitando a manutenção e organização do código.

O MiTemplate é baseado no excelente projeto [Template](https://github.com/raelgc/template) (veja [CRÉDITOS](CREDITS.txt)), com diversas melhorias.

## Funcionalidades

- Suporte a objetos
- Detecção automática de blocos
- Limpeza automática de blocos filhos
- Aviso ao chamar um bloco inexistente
- Aviso ao detectar um bloco malformado
- Aviso ao definir uma variável inexistente
- Adicionar Comentários no template
- Permite exibir ou retornar o conteúdo
- Permite a minificação do código-fonte
- Outras funcionalidades menores

## Documentação

### Instalando

```bash
composer require mugomes/mitemplate
```

### Adicionar Arquivos

Exemplo em PHP:

```php
use MiTemplate\MiTemplate;

include_once(__DIR__ . '/vendor/autoload.php');

$tpl = new MiTemplate('layout.html');
$tpl->addFile('menutopo', 'menutopo.html');
```

Exemplo em HTML:

```html
<div>{{menutopo}}</div>
<p>Minha Página</p>
```

### Exibir ou Retornar o conteúdo

Para exibir ou retornar o conteúdo você pode usar o método show, conforme o exemplo abaixo:

```php
// Para exibir direto
$tpl->show();

// Para retornar
$html = $tpl->show(true);
echo $html;
```

### Minificar o conteúdo

Para minificar o conteúdo você pode usar os seguintes métodos.

```php
$tpl->enableMinify();

// Não é obrigatório o uso do optionMinify, pois por padrão as opções collapse_whitespace e disable_comments já vem ativas, caso precise acrescentar ou modificar as opções, informe-as novamente conforme o exemplo abaixo.

$tpl->optionMinify([
    'collapse_whitespace' => true,
    'disable_comments' => false
]);

$tpl->show();
```

Consulte a documentação do [tiny-html-minifier](https://github.com/pfaciana/tiny-html-minifier/tree/v3.0.0) para mais opções de minificação.

### Variaveis

Para exibir um conteúdo na página você pode usar as chaves duplicadas, com ou sem espaço para a variável personalizada.

```html
{{variavel}} ou {{sua variavel}}
```

### Blocos

Os blocos permitem você trabalhar com conteúdo em loop. Assim como as variáveis, os blocos permitem espaço.

Exemplo em PHP:

```php
$tpl->set('sua variavel', 'Exemplo de Bloco com Espaço');
$tpl->block('SEU BLOCO');

$tpl->set('variavel', 'Exemplo em Loop');
$tpl->block('BLOCO');

for ($i = 0; $i < 10; $i++) {
    $tpl->set('variavel1', $i);
    for ($i = 0; $i < 10; $i++) {
        $tpl->set('variavel2', $i);
        $tpl->block('BLOCO2');
    }
    $tpl->block('BLOCO1');
}
```

Exemplo em HTML:

```html
[BEGIN SEU BLOCO]
    {{sua variavel}}
[END SEU BLOCO]

[BEGIN BLOCO]
    {{variavel}}
[END BLOCO]

[BEGIN BLOCO1]
    <div>
        {{variavel1}}
        [BEGIN BLOCO2]
            <div>{{variavel2}}</div>
        [END BLOCO2]
        {{variavel1}}
    </div>
[END BLOCO1]
```

### Finally

Caso os blocos não sejam acionados, o conteúdo que estiver entre o END e o FINALLY será exibido.

```html
[BEGIN BLOCO]
    {{variavel}}
[END BLOCO]

<div>Nenhum conteúdo encontrado</div>

[FINALLY BLOCO]
```

## Requerimento

- PHP 8.4 ou superior

## Support

- GitHub: https://github.com/sponsors/mugomes/
- More: https://www.mugomes.com.br/apoie.html

## License

The MiTemplate is provided under:

[SPDX-License-Identifier: LGPL-2.1-only](https://github.com/mugomes/mitemplate/blob/main/LICENSE)

Beign under the terms of the GNU Lesser General Public License version 2.1 only.

All contributions to the MiTemplate are subject to this license.