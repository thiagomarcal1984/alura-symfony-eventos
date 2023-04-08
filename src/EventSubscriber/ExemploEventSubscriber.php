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
    
    public static function getSubscribedEvents(): array
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
        $response->setContent(
            "<b>Usando \"ExemploEventSubscriber->meuMetodoNoSubscriber: \"</b>" . $errorMessage
        );
        $response->setStatusCode(501); // 501 é só pra perceber a variação.
        
        // Comentar esta linha abaixo para manter a resposta nos listeners.
        // $event->setResponse($response); 
        // Se a linha acima for executada, os outros listeners não vão dar resposta.
    }
}
