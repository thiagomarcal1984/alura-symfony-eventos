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
            $crawler = $client->request('GET', '/' . $locale . '/series');

            $this->assertResponseIsSuccessful();
            $this->assertSelectorExists('.btn.btn-dark.mb-3');
        }
    }
}
