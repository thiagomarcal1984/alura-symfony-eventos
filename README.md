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
# Detalhes de eventos

`As exceções são apenas um dos tipos de evento capturados pelo Symfony`. A documentação também menciona outros tipos de evento, como `kernel.request`, `kernel.view`, `kernel.controller` etc. Veja a documentação: https://symfony.com/doc/current/reference/events.html 

## Event Listeners
Formas diferentes para definir Event Listeners, segundo a documentação: https://symfony.com/doc/current/event_dispatcher.html#creating-an-event-listener

1. Usar o atributo `method` no par `{ name, method }` no YAML (padrão);
2. Usar o atributo `event` no par `{ name, event }`, onde `event` é o nome do método precedido por `on` e o nome do método em PascalCase (só iniciais maiúsculas); ou
3. Usar o método mágico `__invoke(ExceptionEvent $event)` e inserir apenas o nome do listener: `{ name: kernel.event_listener }`. ***Observação:*** o método `__invoke` necessariamente precisa receber o parâmetro abstrato `ExceptionEvent $event`. Parâmetros mais concretos quebram a aplicação (POR QUE???).

A partir do PHP 8.1 podemos substituir, na classe do Event Listener, as configurações em `services.yaml` pelo atributo `#[AsEventListener]`.

Código da classe `ExceptionEventListener`:
```php
<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

// Usando o PHP 8, uma das anotações abaixo substituem 
// as configurações feitas no arquivo services.yaml:

// 1. Usando o evento em 'on' + PascalCase.
// #[AsEventListener(event: 'kernel.exception')]
// 2. Usando o método em camelCase
#[AsEventListener(method: 'myMethod')]
// 3. Usando o método mágico __invoke(ExceptionEvent $event)
// #[AsEventListener()]
class ExceptionEventListener
{
    private function ouvir(string $origem, $event): void 
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
        $response->setContent("<b>$origem: </b>" . $errorMessage);
        $response->setStatusCode(501); // 501 é só pra perceber a variação.

        // Problema: o Symfony Profiler não aparece.
        $event->setResponse($response);
    }
    
    public function onKernelException(ExceptionEvent $event)
    {
        $this->ouvir("onKernelException (on + método em PascalCase)", $event);
    }

    public function myMethod(ExceptionEvent $event)
    {
        $this->ouvir("Método personalizado \"myMethod\"", $event);
    }
    public function __invoke(ExceptionEvent $event)
    {
        $this->ouvir("Método mágico __invoke(ExceptionEvent \$event)", $event);
    }
}
```

## Event Subscribers
Há duas formas de ouvir eventos: usando um Event Listener desenvolvido em uma das 3 maneiras mencionadas ou usando um Event Subscriber. A diferença entre um Event Listener e o Event Subscriber é que o último pode executar mais de um método para tratar um mesmo evento, enquanto o Event Listener só trata um evento por vez. Subscribers são mais fáceis de reusar (a lógica para tratar os eventos ficam concentradas na classe, portanto os subscribers conhecem os eventos que tratam); Listeners são mais fáceis de habilitar/desabilitar (estruturas condicionais podem ser usadas para os listeners, o que não acontece com o subscribers).

Os Event Subscribers tem duas características na sua implementação:
1. Implementam a interface `Symfony\Component\EventDispatcher\EventSubscriberInterface`;
2. O método implementado `getSubscribedEvents()` deve ser ***estático***.

Veja mais na documentação: https://symfony.com/doc/current/event_dispatcher.html#creating-an-event-subscriber 

Exemplo de EventSubscriber (que NÃO precisam de configuração em `services.yaml` para funcionar, basta apenas *declarar*):
```php
<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class ExemploEventSubscriber implements EventSubscriberInterface
// Use esta interface: Symfony\Component\EventDispatcher\EventSubscriberInterface;
// Não esta: Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;

{
    
    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => [
                ['meuMetodoNoSubscriber', 10],
                // ['meuSegundoMetodoNoSubsriber', 0], // Outro método com prioridade menor
                // ['meuSegundoMetodoNoSubsriber', -20], // Mais um método com prioridade menor ainda.
            ]
        ];
    }

    public function meuMetodoNoSubscriber(ExceptionEvent $event) 
    {
        $errorMessage =  $event->getThrowable()->getMessage();

        $response = new Response();
        $response->setContent("<b>Usando \"ExemploEventSubscriber->meuMetodoNoSubscriber: \"</b>" . $errorMessage);
        $response->setStatusCode(501); // 501 é só pra perceber a variação.
        $event->setResponse($response);
    }
}
```

## Event Dispatcher

Dispatchers são objetos que mantém um registro de Listeners e de Subscribers. Um listener pode tratar vários eventos, que podem ser tratados por vários listeners. Durante a codificação, podemos criar um objeto da classe `Symfony\Component\EventDispatcher\EventDispatcher`. Uma vez criado, podemos adicionar listeners e/ou subscribers por meio dos métodos
https://symfony.com/doc/current/components/event_dispatcher.html

Algoritmo simplificado da criação e uso do Event Dispatcher
```php
use Symfony\Component\EventDispatcher\EventDispatcher;

// Criação do Dispatcher
/** @var EventDispatcherInterface $dispatcher */
$dispatcher = new EventDispatcher();

$listener = new AcmeListener();
// Acrescentando um Listener ao Dispatcher:
$dispatcher->addListener(
    // string $eventName
    'acme.foo.action', // dot.case do camelCase de AcmeFooActionEvent.
    // callable $eventListener. Neste caso, um array com o nome do 
    // objeto listener seguido do nome do método que vai tratar o evento.
    [$varListener, 'metodoDoListenerQueVaiTratarEvento'],
    // int $priority. Quanto maior, mais cedo é executado.
    $intPrioridade
);

// Acrescentando um Subscriber ao Distpacher:
$subscriber = new MyEventSubscriber();
$dispatcher->addSubscriber($subscriber); 
// Mais fácil, né? As prioridades são definidos na classe do Subscriber.

// Criação do evento que será tratado tanto pelo 
// Listener quanto pelo Subscriber:
$event = new AcmeFooActionEvent();

// Disparo do evento para tratamento pelos listeners e subscribers.
$dispatcher->dispatch($event);
```
## Descobrindo (debugando) quais classes tratam os eventos

Execute o comando: `php .\bin\console debug:event-dispatcher nome.do.evento`:
```
php .\bin\console debug:event-dispatcher kernel.exception

Registered Listeners for "kernel.exception" Event
=================================================

 ------- ---------------------------------------------------------------------------------- ---------- 
  Order   Callable                                                                           Priority  
 ------- ---------------------------------------------------------------------------------- ---------- 
  #1      App\EventSubscriber\ExemploEventSubscriber::meuMetodoNoSubscriber()                10
  #2      App\EventListener\ExceptionEventListener::myMethod()                               0
  #3      Symfony\WebpackEncoreBundle\EventListener\ExceptionListener::onKernelException()   0
  #4      Symfony\Component\HttpKernel\EventListener\ErrorListener::logKernelException()     0
  #5      Symfony\Component\HttpKernel\EventListener\ProfilerListener::onKernelException()   0
  #6      Symfony\Component\HttpKernel\EventListener\RouterListener::onKernelException()     -64
  #7      Symfony\Component\HttpKernel\EventListener\ErrorListener::onKernelException()      -128
 ------- ---------------------------------------------------------------------------------- ----------
```

# Events vs Messenger
EventListeners/EventSubscribers tratam os eventos de forma **síncrona** (o invocador do evento **pára** após disparar o evento); já os messengers tratam os eventos de forma **assíncrona** (o invocador do evento **NÃO pára** após disparar o evento).

De preferência, `use o messenger`. Para mudar o tratamento de eventos de síncrono para assíncrono e vice versa, basta modificar o transporte no arquivo `messenger.yaml`. O messenger é um componente mais recente do Symfony, antigamente tínhamos apenas os event listeners/subscribers.

Mas quando é bom usar Event Listeners/Subscribers? Quando o código necessariamente depender da resposta do tratamento do evento (nesse caso, com certeza precisaremos de um código **síncrono**).
