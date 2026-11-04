<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Geminus\Admin\Config\AdminMenu;
use Geminus\Admin\Entities\AdminUser;

/**
 * @internal
 */
final class AdminMenuTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace;

    protected function tearDown(): void
    {
        auth()->logout();
        parent::tearDown();
    }

    public function testLegacySinglePermissionMenuStillChecksAuthorization(): void
    {
        $config          = config(AdminMenu::class);
        $originalItems   = $config->items;
        $config->items[] = ['permission' => 'users.view', 'route' => 'admin/users', 'label' => 'Legacy menu string', 'icon' => 'ti-users', 'active' => '*/admin/users*'];

        try {
            $this->loginAs('user');
            $page = $this->get('/en/admin/dashboard');
            $page->assertOK();
            $page->assertDontSee('Legacy menu string');
            auth()->logout();
            $this->loginAs('superadmin');
            $this->get('/en/admin/dashboard')->assertSee('Legacy menu string');
            $page = $this->get('/en/admin/users');
            $page->assertOK();
            $xpath  = $this->sidebarXPath($page->response()->getBody());
            $parent = '//li[a/span[text()="Legacy menu string"]]';
            $this->assertSame(1, $xpath->query($parent . '[contains(concat(" ", normalize-space(@class), " "), " nav-item ") and contains(concat(" ", normalize-space(@class), " "), " active ") and not(contains(concat(" ", normalize-space(@class), " "), " dropdown "))]')->length);
            $this->assertSame(1, $xpath->query($parent . '/a[contains(concat(" ", normalize-space(@class), " "), " nav-link ") and contains(concat(" ", normalize-space(@class), " "), " active ") and @aria-current="page" and @href="/en/admin/users"]')->length);
            $this->assertSame(0, $xpath->query($parent . '//*[contains(concat(" ", normalize-space(@class), " "), " dropdown-menu ")]')->length);
        } finally {
            $config->items = $originalItems;
        }
    }

    public function testModuleSubmenuVisibilityLinksAndActiveState(): void
    {
        $config          = config(AdminMenu::class);
        $originalItems   = $config->items;
        $config->items[] = [
            'permission' => ['users.view', 'users.create'],
            'label'      => 'Module submenu',
            'icon'       => 'ti-users',
            'children'   => [
                ['route' => 'admin/users', 'label' => 'Module list', 'active' => '*/admin/users'],
                ['route' => 'admin/users/create', 'label' => 'Module new', 'active' => '*/admin/users/create'],
            ],
        ];
        $config->items[] = [
            'permission' => 'users.view',
            'label'      => 'Single permission submenu',
            'icon'       => 'ti-users',
            'children'   => [
                ['route' => 'admin/users', 'label' => 'Single permission child', 'active' => '*/admin/users'],
            ],
        ];

        try {
            $this->loginAs('user');
            $page = $this->get('/en/admin/dashboard');
            $page->assertOK();
            $page->assertDontSee('Module submenu');
            $page->assertDontSee('Single permission submenu');
            $reader = auth()->user();
            $reader->addPermission('users.view');
            auth()->logout();
            auth()->login(auth()->getProvider()->findById($reader->id));
            $readerPage = $this->get('/en/admin/dashboard');
            $readerPage->assertOK();
            $readerPage->assertSee('Module submenu');
            $readerPage->assertSee('Module list');
            $readerPage->assertSee('Module new');
            $readerPage->assertSee('Single permission submenu');
            $readerPage->assertSee('Single permission child');
            $this->get('/en/admin/users/create')->assertRedirect();
            auth()->logout();
            $this->loginAs('admin');
            $this->assertFalse(auth()->user()->can('users.view'));
            $this->assertTrue(auth()->user()->can('users.create'));
            $this->get('/en/admin/dashboard')->assertSee('Module submenu');
            auth()->logout();
            $this->loginAs('superadmin');

            foreach (['en', 'zh-Hans', 'zh-Hant'] as $locale) {
                foreach (['dashboard' => null, 'users' => 1, 'users/create' => 2] as $path => $activeChild) {
                    $response = $this->get('/' . $locale . '/admin/' . $path);
                    $response->assertOK();
                    $xpath  = $this->sidebarXPath($response->response()->getBody());
                    $parent = '//li[contains(concat(" ", normalize-space(@class), " "), " dropdown ")][a/span[text()="Module submenu"]]';
                    $this->assertSame(1, $xpath->query($parent)->length);
                    $this->assertSame(1, $xpath->query($parent . '/a[contains(concat(" ", normalize-space(@class), " "), " nav-link ") and contains(concat(" ", normalize-space(@class), " "), " dropdown-toggle ") and @data-bs-toggle="dropdown" and @data-bs-auto-close="outside" and @role="button"]/i[contains(concat(" ", normalize-space(@class), " "), " ti-users ")]')->length);
                    $this->assertSame($activeChild === null ? 0 : 1, $xpath->query($parent . '[contains(concat(" ", normalize-space(@class), " "), " active ")]')->length);
                    $this->assertSame($activeChild === null ? 'false' : 'true', $xpath->query($parent . '/a')->item(0)->getAttribute('aria-expanded'));
                    $menu = $parent . '/div[contains(concat(" ", normalize-space(@class), " "), " dropdown-menu ")]';
                    $this->assertSame(1, $xpath->query($menu)->length);
                    $this->assertSame($activeChild === null ? 0 : 1, $xpath->query($menu . '[contains(concat(" ", normalize-space(@class), " "), " show ")]')->length);
                    $links = $xpath->query($menu . '/a[contains(concat(" ", normalize-space(@class), " "), " dropdown-item ")]');
                    $this->assertCount(2, $links);
                    $this->assertSame('/' . $locale . '/admin/users', $links->item(0)->getAttribute('href'));
                    $this->assertSame('/' . $locale . '/admin/users/create', $links->item(1)->getAttribute('href'));
                    $this->assertSame($activeChild === null ? 0 : 1, $xpath->query($menu . '/a[contains(concat(" ", normalize-space(@class), " "), " active ") and @aria-current="page"]')->length);
                    if ($activeChild !== null) {
                        $this->assertSame('page', $links->item($activeChild - 1)->getAttribute('aria-current'));
                    }
                }
            }
        } finally {
            $config->items = $originalItems;
        }
    }

    private function sidebarXPath(string $body): DOMXPath
    {
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $body);

        return new DOMXPath($document);
    }

    private function loginAs(string $group): void
    {
        $user        = new AdminUser(['username' => 'menu' . $group]);
        $user->email = 'menu' . $group . '@example.com';
        $user->setPassword('A-local-password-123!');
        $users = auth()->getProvider();
        $users->save($user);
        $user = $users->findById($users->getInsertID());
        $user->addGroup($group);
        auth()->login($user);
    }
}
