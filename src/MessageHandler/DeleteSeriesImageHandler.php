<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SeriesWasDeleted;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class DeleteSeriesImageHandler
{
    public function __construct(
        private ParameterBagInterface $parameterBag,
        private Filesystem $filesystem,
    ) {}

    public function __invoke(SeriesWasDeleted $message)
    {
        $coverImagePath = $message->series->getCoverImagePath();
        // O método remove pode receber um array de strings para remover
        // vários arquivos de uma vez.
        $this->filesystem->remove(
            [ // Início do array de paths para remoção.
                Path::join( // Método estático "join" da classe Path.
                    $this->parameterBag->get('cover_image_directory'),
                    DIRECTORY_SEPARATOR,
                    $coverImagePath
                )
            ] // Fim do array de paths para remoção.
        );
    }
}
