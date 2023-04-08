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

# Configurando as traduções
O arquivo `/config/packages/translation.yaml` contém as configurações para a tradução de conteúdo:

```YAML
framework:
    # Padrão: <2 chars para idioma (min)>_<2 chars para país (maiús)>.
    default_locale: pt_BR # Antes o locale era "en".
    translator:
        # Onde fica o diretório com as traduções.
        default_path: '%kernel.project_dir%/translations'
    
    # Resto do código.
```
No diretório `/translations` colocamos os arquivos YAML com as mensagens. Esses arquivos seguem o padrão de nomenclatura `<nome_do_arquivo>.<locale>.yaml`:

```YAML
# /translations/messages.en.yaml
series.list: Series list
series.delete: Series deleted successfully
```

```YAML
# /translations/messages.pt_BR.yaml
series.list: Listagem de séries
series.delete: Série removida com sucesso
```

Finalmente, devemos configurar o roteamento no Symfony para adicionar o prefixo correspondente ao locale na URL. A configuração fica no arquivo `config/routes.yaml`
```YAML
controllers:
    resource: ../src/Controller/
    type: attribute
    # Toda rota vai ser prefixada com o locale definido (pt_BR, en etc).
    prefix: /{_locale}
# Resto do código.
```
Problema: uma vez que o prefixo é configurado nas rotas, é obrigatório fornecer o locale na URL.

# Para saber mais: testes

Nós mudamos as URLs de nosso sistema nesse vídeo, e vários de nossos testes fazem uso dessas URLs para acessarem a aplicação e realizarem suas verificações.

Mudanças em `/config/packages/security.yaml`

```YAML
security:
    # Resto do código
    access_control:
        # - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: "^/[A-z_]*/series$", roles: PUBLIC_ACCESS }
        - { path: "^/[A-z_]*/login$", roles: PUBLIC_ACCESS }
        - { path: "^/[A-z_]*/register$", roles: PUBLIC_ACCESS }
        # Resto do código
```

Mudanças no teste `AddButtonTest.php`:

```php
<?php

namespace App\Tests\E2E;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AddButtonTest extends WebTestCase
{
    private $locales = ['en', 'pt_BR'];

    public function testAddButtonDoesNotExistWhenUserIsNotLoggedIn(): void
    {
        $client = static::createClient();
        foreach ($this->locales as $locale) {
            // A mudança está no teste em vários locales.
            $crawler = $client->request('GET', '/' . $locale . '/series');
    
            $this->assertResponseIsSuccessful();
            $this->assertSelectorNotExists('.btn.btn-dark.mb-3');
        }
    }

    public function testAddButtonNotExistWhenUserIsLoggedIn()
    {
        $client = static::createClient();
        $container = static::getContainer();
        $userRepository = $container->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'email@example.com']);
        $client->loginUser($user);
        foreach ($this->locales as $locale) {
            // A mudança está no teste em vários locales.
            $crawler = $client->request('GET', '/' . $locale . '/series');

            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('.btn.btn-dark.mb-3');
        }
    }
}
```
# Para saber mais: permissão

Mudanças em `/config/packages/security.yaml`

Após nossas alterações nas URLs, as rotas que antes eram públicas não são mais. Com isso, nosso formulário de login e registro, inclusive, passam a ser inacessíveis.

Para corrigir isso, basta modificar o arquivo `config/packages/security.yaml` para que as rotas públicas sejam corretamente definidas.

```YAML
security:
    # Resto do código
    access_control:
        # - { path: ^/admin, roles: ROLE_ADMIN }

        # Solução proposta no curso:
        # - { path: ^/en|pt_BR/series$, roles: PUBLIC_ACCESS }
        # - { path: ^/en|pt_BR/(?!login|register), roles: ROLE_USER }

        # Minha solução anterior: 
        # - { path: "^/[A-z_]*/series$", roles: PUBLIC_ACCESS }
        # - { path: "^/[A-z_]*/login$", roles: PUBLIC_ACCESS }
        # - { path: "^/[A-z_]*/register$", roles: PUBLIC_ACCESS }

        # Mais uma alternativa: 
        - { path: "^/[A-z_]*/series$", roles: PUBLIC_ACCESS }
        - { path: "^/[A-z_]*/(?!login|register)$", roles: ROLE_USER }

        # Código anterior à adaptação:
        - { path: ^/series$, roles: PUBLIC_ACCESS }
        - { path: ^/(?!login|register), roles: ROLE_USER }
# Resto do código
```
# Traduzindo o projeto
Conteúdo dos arquivos de mensagem: 
```YAML
# translations\messages.pt_BR.yaml
series.list: Listagem de séries
series.delete: Série removida com sucesso

# translations\messages.en.yaml
series.list: Series list
series.delete: Series deleted successfully
```

Aplicando a tradução na rota `app_delete_series` em `SeriesController`:

```php
// Resto do código
use Symfony\Contracts\Translation\TranslatorInterface;

class SeriesController extends AbstractController
{
    public function __construct(
        // Resto do código
        private TranslatorInterface $translator,
    )
    {}
    
    // Resto do código
        #[Route(
        '/series/delete/{series}',
        name: 'app_delete_series',
        methods: ['DELETE'],
    )]
    public function deleteSeries(Series $series): Response
    {
        $this->seriesRepository->remove($series, true);
        $this->messenger->dispatch(new SeriesWasDeleted($series));

        // O método trans recebe como primeiro parâmetro o identificador
        // do texto comum nos arquivos messages.idioma.yaml:
        $this->addFlash('success', $this->translator->trans('series.delete'));

        return $this->redirectToRoute('app_series');
    }

    // Resto do código
}
```
Código de `/templates/series/index.html.twig` com o texto passível de tradução (note a função `trans` envolvendo o identificador do texto comum nos arquivos `messages.idioma.yaml`):
```HTML
{# Resto do código #}
{% block title %}
    {% trans %}series.list{% endtrans %}
{% endblock %}
{# Resto do código #}
```
# Redirecionando com locale
O redirecionamento das URL com o prefixo do locale é muito fácil. Basta retornar as rotas ao invés da URL hard-coded nos métodos (`return $this->redirectToRoute('nome_da_rota');`):

```php
// Resto do código
class SeriesController extends AbstractController
{
    // Resto do código
    #[Route(
        '/series/create', 
        name: 'app_add_series', 
        methods: ['POST']
    )]
    public function addSeries(Request $request): Response
    {
        // Resto do código
        return $this->redirectToRoute('app_series');
    }

    // Resto do código
    #[Route(
        '/series/delete/{series}', 
        name: 'app_delete_series', 
        methods: ['DELETE'],
    )]
    public function deleteSeries(Series $series): Response
    {
        // Resto do código
        return $this->redirectToRoute('app_series');
    }

    // Resto do código
    #[Route(
        '/series/edit/{series}', 
        name: 'app_store_series_changes', 
        methods: ['PATCH']
    )]
    public function storeSeriesChanges(Series $series, Request $request): Response
    {
        // Resto do código
        return $this->redirectToRoute('app_series');
    }
}
```

# Identificando o idioma
Se o idioma não for fornecido na URL, o Event Listener pode tratar a requisição e redirecionar para o idioma presente no cabeçalho de requisição `Accept-Language`.

Código resumido de `ExceptionEventListener.php`:

```php
<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// Usando o PHP 8, uma das anotações abaixo substituem 
// as configurações feitas no arquivo services.yaml:

// 1. Usando o evento em 'on' + PascalCase.
// #[AsEventListener(event: 'kernel.exception')]
// 2. Usando o método em camelCase
// #[AsEventListener(method: 'myMethod')]
// 3. Usando o método mágico __invoke(ExceptionEvent $event)
#[AsEventListener()]
class ExceptionEventListener
{
    private function __invoke(ExceptionEvent $event): void 
    {
        $error = $event->getThrowable();
        if (!$error instanceof NotFoundHttpException) {
            return;
        }
        $request = $event->getRequest();
        $acceptLanguageHeader = $request->headers->get('Accept-Language');
        // Conteúdo do cabeçalho Accept-Language: 
        // pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7,it;q=0.6
        // O parâmetro "q" é a relevância

        $languages = explode(',', $acceptLanguageHeader);
        // [pt-BR, pt;q=0.9, en-US;q=0.8, en;q=0.7, it;q=0.6]
        $language = explode(';', $languages[0])[0]; // Retorna 'pt-BR'
        $language = str_replace('-', '_', $language); // Troca traço por underline.

        // Se o código fosse 
        // $language = explode(';', $languages[1])[0]; // Segundo resultado.
        // Retornaria 'pt', sem o 'q=0.9'.

        if (!str_starts_with($request->getPathInfo(), '/$language')) {
            // Se o path não começa com o idioma que está no header Accept-Language,
            // a resposta redireciona para o path prefixado com o idioma.
            $response = new Response(status: 302); // Status para Redirecionamento.
            $response
                ->headers
                ->add(['Location' => "/$language" . $request->getPathInfo()]);
            $event->setResponse($response);
        }

    }
}
```

# Para saber mais: getPreferredLanguage
Tem como obter o idioma sem quebrar tanta string: `$request->getPreferredLanguage()`.

```PHP
<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// Usando o PHP 8, uma das anotações abaixo substituem 
// as configurações feitas no arquivo services.yaml:

// 1. Usando o evento em 'on' + PascalCase.
// #[AsEventListener(event: 'kernel.exception')]
// 2. Usando o método em camelCase
// #[AsEventListener(method: 'myMethod')]
// 3. Usando o método mágico __invoke(ExceptionEvent $event)
#[AsEventListener()]
class ExceptionEventListener
{
    private function __invoke(ExceptionEvent $event): void 
        $error = $event->getThrowable();
        if (!$error instanceof NotFoundHttpException) {
            return;
        }
        $request = $event->getRequest();

        $language = $request->getPreferredLanguage();

        if (!str_starts_with($request->getPathInfo(), '/$language')) {
            $response = new Response(status: 302); // Status para Redirecionamento.
            $response
                ->headers
                ->add(['Location' => "/$language" . $request->getPathInfo()]);
            $event->setResponse($response);
        }
}
```
# Para saber mais: filtro no Twig
Além de usarmos a sintaxe `{% trans %} texto a ser traduzido {% endtrans %}` no Twig, podemos também usar `trans` como um filtro. Isso é especialmente útil quando precisamos traduzir o conteúdo de variáveis.

Ex. de tradução da variável `$message`:
```HTML
{{ message|trans }}
```
Alteração em `/templates/series/index.html.twig`:
```HTML
{# Resto do código#}
    {% block title %}
        {# {% trans %}series.list{% endtrans %} #}
        {{ 'series.list' | trans }}
    {% endblock %}
{# Resto do código#}
```
Perceba que `series.list` é uma string, não um objeto. A string é enviada por parâmetro à função `trans` no Twig.

# Para saber mais: traduções com parâmetros

Documentação: https://symfony.com/doc/6.1/translation/message_format.html 

Passos:
1. Ative a extensão `intl` no `php.ini` (remova o comentário em `extension=intl`);
2. Crie os arquivos com as mensagens com o padrão `message+intl-icu.<idioma>.yaml`. Os nomes dos parâmetros dentro dos arquivos YAML são envolvidos por chaves {};
3. Use o método `$translator->trans('nome da entrada no arquivo das mensagens', $arrayKeyValue)` no código PHP ou a função trans `{{ 'mensagem.message_key' | trans({'key': valor}) }}` no códgio do Twig.

> A versão 6.2 do Symfony não tem mais o formato de mensagem ICU (International Components for Unicode).

## Anotações do curso
No exemplo da mensagem de nova série, o arquivo de tradução (que deve ser renomeado para `messages+intl-icu.pt_BR.yaml`) teria a seguinte linha:
```YAML
series.added.msg: Série {name} adicionada com sucesso
```

E para usarmos a tradução em nosso código PHP, faríamos:

```php
$this->translator->trans('series.added.msg', ['name' => $series->getName()])
```
Já no Twig, faríamos:
```BASH
{{ 'series.added.msg'|trans({'name': series.name}) }}
```
## Meu código

1. Ativar a extensão `intl` no arquivo `php.ini`:
```
; extension=intl ; remova o ponto e vírgula para descomentar.
extension=intl
```

2. Crie os arquivos de mensagem com o sufixo `+intl-icu`:
```YAML
# translations\messages+intl-icu.pt_BR.yaml
series.list: Listagem de séries
series.insert: Série "{nome}" inserida com sucesso
series.update: Série "{nome}" atualizada com sucesso
series.delete: Série removida com sucesso

# translations\messages+intl-icu.en.yaml
series.list: Series list
series.insert: Series "{nome}" inserted successfully
series.update: Series "{nome}" updated successfully
series.delete: Series deleted successfully
```

3. Adaptação dos métodos CRUD em `SeriesController`:
```PHP
// Resto do código
class SeriesController extends AbstractController
{
    // Resto do código
    #[Route('/series/create', name: 'app_add_series', methods: ['POST'])]
    public function addSeries(Request $request): Response
    {
        // Resto do código
        $this->addFlash(
            'success',
            $this->translator->trans(
                'series.insert', 
                ['nome' => $seriesForm->getData()->seriesName]
            )
        );

        return $this->redirectToRoute('app_series');
    }

    // Resto do código
    #[Route('/series/delete/{series}',name: 'app_delete_series',methods: ['DELETE'])]
    public function deleteSeries(Series $series): Response
    {
        // Resto do código
        $this->addFlash(
            'success',
            $this->translator->trans(
                'series.delete', 
                ['nome' => $series->getName()]
            )
        );

        return $this->redirectToRoute('app_series');
    }

    // Resto do código
    #[Route('/series/edit/{series}', name: 'app_store_series_changes', methods: ['PATCH'])]
    public function storeSeriesChanges(Series $series, Request $request): Response
    {
        // Resto do código
        $this->addFlash(
            'success',
            $this->translator->trans(
                'series.update', 
                ['nome' => $seriesForm->getData()->seriesName]
            )
        );

        return $this->redirectToRoute('app_series');
    }

    // Resto do código
}
```

4. Uso do pipe `trans` no Twig (na verdade não foi necessário no código):
```BASH
{% block content%}
    {{ 'message.message_key' | trans({'key': value}) }}
{% endblock %}
```

# Para saber mais: problema
Se você tentar acessar a URL `/en/teste` em nosso sistema, vai perceber que estamos redirecionando o usuário para: `/pt_BR/en/teste`. Isso porque a URL não começa com nosso idioma, mas sim com um idioma válido em nosso sistema.

Para melhorar essa verificação e prevenir que esse erro ocorra, você pode verificar se a URL começa com qualquer um dos idiomas válidos. Algo como:

```php
public function startsWithValidLanguage(Request $request): bool
{
    $validLanguages = ['en', 'pt_BR'];
    foreach ($validLanguages as $language) {
        if (str_starts_with($request->getPathInfo(), "/$language")) {
            return true;
        }
    }

    return false;
}
```
E ao invés de chamar `!str_starts_with($request->getPathInfo(), "/$language")` você chamaria `!$this->startsWithValidLanguage($request)`.

Para tornar esse código ainda mais flexível, você pode extrair os idiomas válidos para um parâmetro em `services.yaml`.

Inclusive, essa lista de idiomas válidos pode (e deve) ser usada para definir uma validação em nosso arquivo de rotas. Isso pode ser feito através do parâmetro requirements de nossa configuração de rota. Algo como:

```YAML
# config/routes.yaml
controllers:
    resource:
        path: ../src/Controller/
        namespace: App\Controller
    type: attribute
    prefix: /{_locale}
    requirements:
        _locale: en|pt_BR
```

Meu código:
```php
#[AsEventListener(method: 'myMethod')]
class ExceptionEventListener
{
    public function startsWithValidLanguage(Request $request): bool
    {
        $validLanguages = ['en', 'pt_BR'];
        foreach ($validLanguages as $language) {
            if (str_starts_with($request->getPathInfo(), "/$language")) {
                return true;
            }
        }

        return false;
    }
    
    private function myMethod(ExceptionListener $event): void 
    {
        $error = $event->getThrowable();
        if (!$error instanceof NotFoundHttpException) {
            return;
        }
        $request = $event->getRequest();
        $language = $request->getPreferredLanguage();

        if (!$this->startsWithValidLanguage($request)) {
            $response = new Response(status: 302);
            $response
                ->headers
                ->add(['Location' => "/$language" . $request->getPathInfo()]);
            $event->setResponse($response);
        }
    }
}
```

# Importância de logs
Logs são entradas (geralmente me um arquivo de texto) que servem de material para diagnosticar a saúde do sistema. Eles são nivelados em severidades. A PSR-3 descreve 8 níveis (do mais grave ao menos grave): EMERGENCY, ALERT, CRITICAL, ERROR, WARNING, NOTICE, INFO, e DEBUG. 

> Este commit altera o parâmetro `nome` nos arquivos `SeriesController`, `translations\messages+intl-icu.pt_BR.yaml` e `translations\messages+intl-icu.en.yaml`. As mudanças nesses arquivos são de pouca importância. Os arquivos anteriores de mensagem (`translations\messages.pt_BR.yaml` e `translations\messages.en.yaml`) não são mais necessários .

# Symphony Logger
Os logs gerados pelo Symfony tem o seguinte padrão: `[data e hora] gerador_do_log.severidade: Mensagem`. O gerador dos logs da aplicação se chama `app`:
```BASH
[2023-04-08T16:14:12.508287+00:00] security.DEBUG: Authenticator does not support the request. {"firewall_name":"main","authenticator":"Symfony\\Component\\Security\\Http\\Authenticator\\FormLoginAuthenticator"} []
[2023-04-08T16:14:12.528611+00:00] doctrine.DEBUG: Executing statement: SELECT t0.id AS id_1, t0.number AS number_2, t0.series_id AS series_id_3 FROM season t0 WHERE t0.id = ? (parameters: array{"1":"2"}, types: array{"1":1}) {"sql":"SELECT t0.id AS id_1, t0.number AS number_2, t0.series_id AS series_id_3 FROM season t0 WHERE t0.id = ?","params":{"1":"2"},"types":{"1":1}} []
[2023-04-08T16:14:12.542010+00:00] app.INFO: Mais de dois episódios marcados como assistidos. [] []
[2023-04-08T16:14:12.542373+00:00] doctrine.DEBUG: Executing statement: SELECT t0.id AS id_1, t0.watched AS watched_2, t0.number AS number_3, t0.season_id AS season_id_4 FROM episode t0 WHERE t0.season_id = ? (parameters: array{"1":2}, types: array{"1":1}) {"sql":"SELECT t0.id AS id_1, t0.watched AS watched_2, t0.number AS number_3, t0.season_id AS season_id_4 FROM episode t0 WHERE t0.season_id = ?","params":{"1":2},"types":{"1":1}} []
[2023-04-08T16:14:12.549902+00:00] php.DEBUG: User Warning: Configure the "curl.cainfo", "openssl.cafile" or "openssl.capath" php.ini setting to enable the CurlHttpClient {"exception":{"Symfony\\Component\\ErrorHandler\\Exception\\SilencedErrorContext":{"severity":512,"file":"D:\\alura\\symfony-eventos\\vendor\\symfony\\http-client\\HttpClient.php","line":57,"trace":[{"file":"D:\\alura\\symfony-eventos\\var\\cache\\dev\\ContainerWgKDnMv\\App_KernelDevDebugContainer.php","line":1071,"function":"create","class":"Symfony\\Component\\HttpClient\\HttpClient","type":"::"}],"count":1}}} []
```
Perceba que há dois pares de colchetes no fim do log. Eles correspondem a `contexto` e `parâmetros`.


```BASH
[2023-04-08T16:25:51.324554+00:00] security.DEBUG: Authenticator does not support the request. {"firewall_name":"main","authenticator":"Symfony\\Component\\Security\\Http\\Authenticator\\FormLoginAuthenticator"} []
[2023-04-08T16:25:51.346807+00:00] doctrine.DEBUG: Executing statement: SELECT t0.id AS id_1, t0.number AS number_2, t0.series_id AS series_id_3 FROM season t0 WHERE t0.id = ? (parameters: array{"1":"3"}, types: array{"1":1}) {"sql":"SELECT t0.id AS id_1, t0.number AS number_2, t0.series_id AS series_id_3 FROM season t0 WHERE t0.id = ?","params":{"1":"3"},"types":{"1":1}} []
[2023-04-08T16:25:51.363170+00:00] app.INFO: Mais de dois episódios marcados como assistidos. {"numeros_episodios":4} []
```

Código em `EpisodesController` para escrever no log:
```php
// Resto do código
use Psr\Log\LoggerInterface;

class EpisodesController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}
    // Resto do código
    
    #[Route('/season/{season}/episodes', name: 'app_watch_episodes', methods: ['POST'])]
    public function watch(Season $season, Request $request): Response
    {
        $watchedEpisodes = array_keys($request->request->all('episodes'));
        if (count($watchedEpisodes) > 2) {
            $this->logger->info(
                message: "Mais de dois episódios marcados como assistidos.", 
                context: ['numeros_episodios' => count($watchedEpisodes)]
            );
        }
        // Resto do código
    }

    // Resto do código
}
```
> O arquivo `ExemploEventSubscriber` precisou de uma mudança na assinatura do método `getSubscribedEvents()`. O profiles do Symfony recomendou que o método deve retornar um array: 
> ```php
> // Resto do código
> class ExemploEventSubscriber implements EventSubscriberInterface
> // Use esta interface: Symfony\Component\EventDispatcher\EventSubscriberInterface;
> // Não esta: Doctrine\Bundle\DoctrineBundle\EventSubscriber\EventSubscriberInterface;
> {
>     public static function getSubscribedEvents(): array
>     {
>         // Resto do código
>     }
>     // Resto do código
> }
> ```
