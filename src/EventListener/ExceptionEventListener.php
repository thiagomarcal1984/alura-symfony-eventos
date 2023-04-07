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
#[AsEventListener(method: 'myMethod')]
// 3. Usando o método mágico __invoke(ExceptionEvent $event)
// #[AsEventListener()]
class ExceptionEventListener
{
    private function ouvir(string $origem, $event): void 
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

    private function ouvirAntigo(string $origem, $event): void 
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
