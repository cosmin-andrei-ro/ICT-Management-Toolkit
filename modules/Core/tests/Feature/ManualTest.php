<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

use Modules\Core\Domain\Manual\Manual;
use Modules\Core\Domain\Manual\ManualAudience;
use Modules\Core\Domain\Models\Organization;
use Modules\Core\Domain\Models\User;

it('loads every page shipped by the enabled modules', function (): void {
    $pages = app(Manual::class)->pages();

    expect(array_keys($pages))->toContain('getting-started', 'access-and-roles', 'tickets', 'service-catalog', 'ticketing', 'sla', 'problems', 'ticketing-setup');

    foreach ($pages as $page) {
        expect($page->title)->not->toBe('')
            ->and(trim(strip_tags($page->html())))->not->toBe('');
    }
});

it('groups pages by audience, starting with everyone', function (): void {
    expect(array_keys(app(Manual::class)->contents()))->toBe(['everyone', 'agents', 'administrators']);
});

it('is readable without signing in', function (): void {
    $this->get(route('manual.index'))
        ->assertOk()
        ->assertSee(ManualAudience::Agents->label())
        ->assertSee(route('manual.show', 'getting-started'));

    $this->get(route('manual.show', 'ticketing'))
        ->assertOk()
        ->assertSee('Working in Ticketing')
        ->assertSee('<table>', escape: false);
});

it('returns 404 for unknown pages', function (string $slug): void {
    $this->get('/manual/'.$slug)->assertNotFound();
})->with(['nope', '..%2F.env', 'Getting-Started']);

it('strips raw HTML and unsafe links from Markdown', function (): void {
    $directory = sys_get_temp_dir().'/manual-test-'.bin2hex(random_bytes(4));
    mkdir($directory);
    file_put_contents($directory.'/99-unsafe.md', "---\ntitle: Unsafe\naudience: everyone\n---\n<script>alert(1)</script>\n\n[click](javascript:alert(1)) **bold**");

    $manual = new Manual;
    $manual->addDirectory($directory);
    $html = $manual->find('unsafe')?->html() ?? '';

    expect($html)->not->toContain('<script')
        ->not->toContain('javascript:')
        ->toContain('<strong>bold</strong>');

    unlink($directory.'/99-unsafe.md');
    rmdir($directory);
});

it('rejects pages without a valid front matter', function (): void {
    $directory = sys_get_temp_dir().'/manual-test-'.bin2hex(random_bytes(4));
    mkdir($directory);
    file_put_contents($directory.'/10-broken.md', "# No front matter\n");

    $manual = new Manual;
    $manual->addDirectory($directory);

    try {
        $manual->pages();
    } finally {
        unlink($directory.'/10-broken.md');
        rmdir($directory);
    }
})->throws(InvalidArgumentException::class);

it('offers the manual from the sign-in page and the application header', function (): void {
    $this->get(route('login'))->assertSee(route('manual.show', 'getting-started'));

    $organization = Organization::factory()->create();
    $this->actingAs(User::factory()->memberOf($organization)->create())
        ->get(route('home'))
        ->assertSee(route('manual.index'))
        ->assertSee(__('core::manual.title'));
});
