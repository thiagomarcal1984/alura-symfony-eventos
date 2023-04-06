# Lidando com exceções
O método `editSeriesForm` em `SeriesController` não funciona. A partir dele vamos estudar como ouvir as exceções disparadas dentro do Symfony.

Passos:
1. Defina a classe que vai ouvir os eventos;
2. Acrescente a classe como mais um serviço em `services.yaml` sob o elemento `services`;
3. Sob a definição da classe, acrescente o elemento `tags`;
4. Acrescente uma entrada para cada para listener/evento envolvidas por chaves e precedida de um traço: `- { name: pacote.nome_do_listener, event: pacote.nome_do_evento }`. Perceba o formato `dot.case` para cada entrada;
5. Implemente as funções que representam o evento. Perceba o formato `camelCase` para cada função, com o prefixo `on`: `public function onPacoteNomeDoEvento(ExceptionEvent $event)`.

Conteúdo do arquivo `services.yaml`:

```yaml
## Resto do código
services:
    ## Resto do código
    App\EventListener\ExceptionEventListener:
        tags: 
            # As tags associando eventos aos seus listeners seguem o padrão abaixo, em camelCase:
            # - { name: Pacote\NomeDoListener, event: Pacote\EventoEscutado }
            # - { name: HttpKernel\EventListener, event: Kernel\Exception }
 
            # A ideia é converter o padrão acima para dot.case neste arquivo YAML:
            - { name: kernel.event_listener, event: kernel.exception}
            
            # Para que o evento declarado seja escutado, a classe ExceptionEventListener
            # deve implementar uma função seguind o padrão "on" + "Pacote" + "Classe":
            
            # public function onPadraoClasse(ExceptionEvent $event) {}
            # public function onKernelException(ExceptionEvent $event) {}
```

Conteúdo do listener `ExceptionEventListener`:
```php
<?php

namespace App\EventListener;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

class ExceptionEventListener
{
    public function onKernelException(ExceptionEvent $event)
    {
        // ^^Este echo aparece entre a barra de endereços do browser e 
        // o cabeçalho preto com o ícone do Symfony. Bem discretamente.
        // echo "<h1>" . $event->getThrowable()->getMessage() . "</h1>";

        $errorMessage =  $event->getThrowable()->getMessage();

        // $response = $event->getResponse(); // Não funciona, retorna null.
        /** 
         * Por que esse tipo de erro pode acontecer se o método getResponse existe?
         * Em PHP existe o conceito de nullable types, como já falamos em cursos 
         * anteriores. Um tipo de retorno ?Response, por exemplo, indica que esse 
         * retorno pode ser um objeto do tipo Response ou null. É exatamente o 
         * caso do método getResponse.
         */

        $response = new Response();
        $response->setContent($errorMessage);
        $response->setStatusCode(501); // 501 é só pra perceber a variação.

        // Problema: o Symfony Profiler não aparece.
        $event->setResponse($response);
    }
}
```
