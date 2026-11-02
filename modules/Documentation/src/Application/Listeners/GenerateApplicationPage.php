<?php

// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

declare(strict_types=1);

namespace Modules\Documentation\Application\Listeners;

use Modules\Core\Domain\Events\ApplicationDocumented;
use Modules\Core\Domain\Links\ConfigurationItems;
use Modules\Core\Domain\Models\User;
use Modules\Core\Domain\Tenancy\OrganizationContext;
use Modules\Documentation\Application\Actions\PublishPage;
use Modules\Documentation\Application\Actions\SavePage;
use Modules\Documentation\Domain\Enums\SpaceVisibility;
use Modules\Documentation\Domain\Models\Page;
use Modules\Documentation\Domain\Models\PageLink;
use Modules\Documentation\Domain\Models\Space;

/**
 * The page of an application in the "Applications" space (CLAUDE.md §9, ADR
 * 0043): its summary is written between markers and replaced at every
 * version that comes into force; the rest of the page - the team's notes -
 * is kept. The summary was approved with the documentation, so it is
 * published at once even in a controlled space.
 */
final readonly class GenerateApplicationPage
{
    public const string START = '<!-- application-summary:start -->';

    public const string END = '<!-- application-summary:end -->';

    public function __construct(
        private OrganizationContext $context,
        private SavePage $save,
        private PublishPage $publish,
        private ConfigurationItems $items,
    ) {}

    public function handle(ApplicationDocumented $event): void
    {
        $this->context->runAs($event->organizationId, function () use ($event): void {
            $space = $this->space($event);
            $link = PageLink::query()->where('linked_type', 'applications.application')->where('linked_id', $event->applicationId)
                ->whereIn('page_id', Page::query()->where('space_id', $space->id)->select('id'))->first();
            $page = $link !== null ? Page::query()->find($link->page_id) : null;

            $saved = $this->save->handle(
                $page,
                $space,
                $page?->parent_id,
                $event->code.' · '.$event->name,
                $this->body($page?->body, $event),
                __('documentation::docs.applications.version_summary', ['version' => $event->version]),
                null,
                $event->actor,
            );

            if ($space->requires_approval && $saved->hasDraft()) {
                $this->publish->publish($saved, $saved->version, $event->actor);
            }

            if ($link === null) {
                $new = new PageLink;
                $new->forceFill(['page_id' => $saved->id, 'linked_type' => 'applications.application', 'linked_id' => $event->applicationId, 'title' => $event->code.' '.$event->name, 'created_by' => $event->actor->id])->save();
            }

            // The application's CMDB item lists its documentation page.
            if ($event->configurationItemId !== null && ! in_array($saved->id, $this->items->recordIdsLinkedTo([$event->configurationItemId], $saved->getMorphClass()), true)) {
                $this->items->link($saved, $event->configurationItemId, $event->actor);
            }
        });
    }

    private function space(ApplicationDocumented $event): Space
    {
        $space = Space::query()->where('purpose', Space::APPLICATIONS)->first()
            // An "Applications" space the organization made itself is adopted rather than duplicated.
            ?? Space::query()->whereNull('purpose')->whereRaw('lower(name) = ?', [mb_strtolower((string) __('documentation::docs.applications.space'))])->first();

        if ($space === null) {
            $space = new Space;
            $space->forceFill([
                'name' => __('documentation::docs.applications.space'),
                'description' => __('documentation::docs.applications.space_description'),
                'visibility' => SpaceVisibility::Staff,
            ]);
        }

        $space->forceFill(['purpose' => Space::APPLICATIONS])->save();
        // The people who keep an application's documentation write its notes.
        $owners = User::query()->whereKey([...$event->ownerIds, $event->actor->id])->pluck('id')->all();
        $space->owners()->syncWithoutDetaching($owners);

        return $space;
    }

    private function body(?string $current, ApplicationDocumented $event): string
    {
        $summary = self::START."\n\n".$this->summary($event)."\n\n".self::END;

        if ($current === null) {
            return $summary."\n\n## ".__('documentation::docs.applications.notes')."\n\n_".__('documentation::docs.applications.notes_help')."_\n";
        }

        $start = strpos($current, self::START);
        $end = strpos($current, self::END);

        // Markers removed by hand: the summary goes back on top, the text stays below.
        if ($start === false || $end === false || $end < $start) {
            return $summary."\n\n".ltrim($current);
        }

        return substr($current, 0, $start).$summary.substr($current, $end + strlen(self::END));
    }

    private function summary(ApplicationDocumented $event): string
    {
        $lines = ['> '.__('documentation::docs.applications.generated', ['version' => $event->version, 'url' => $event->documentUrl, 'application' => $event->applicationUrl])];

        if (trim($event->description) !== '') {
            $lines[] = '';
            $lines[] = self::text($event->description);
        }

        if ($event->criticality !== null) {
            $lines[] = '';
            $lines[] = '**'.__('documentation::docs.applications.criticality').':** '.self::text($event->criticality);
        }

        $lines = [...$lines, ...self::table(__('documentation::docs.applications.owners'), [__('documentation::docs.applications.role'), __('documentation::docs.applications.person')], array_map(fn (array $owner): array => [$owner['role'], $owner['name']], $event->owners))];
        $lines = [...$lines, ...self::table(__('documentation::docs.applications.support'), [__('documentation::docs.applications.item'), __('documentation::docs.applications.value')], array_map(fn (array $row): array => [$row['label'], $row['value']], $event->support))];

        if ($event->components !== []) {
            $lines[] = '';
            $lines[] = '## '.__('documentation::docs.applications.components');
            $lines[] = '';

            foreach ($event->components as $component) {
                $lines[] = '- '.self::text($component);
            }
        }

        $lines = [...$lines, ...self::table(__('documentation::docs.applications.processes'), [__('documentation::docs.applications.process'), 'RTO', 'RPO'], array_map(fn (array $process): array => [$process['name'], $process['rto'] ?? '—', $process['rpo'] ?? '—'], $event->processes))];

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $headings
     * @param  list<list<string>>  $rows
     * @return list<string>
     */
    private static function table(string $title, array $headings, array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $lines = ['', '## '.$title, '', '| '.implode(' | ', array_map(self::cell(...), $headings)).' |', '|'.str_repeat(' --- |', count($headings))];

        foreach ($rows as $row) {
            $lines[] = '| '.implode(' | ', array_map(self::cell(...), $row)).' |';
        }

        return $lines;
    }

    /**
     * Text from the documentation on one line, its Markdown signs kept literal.
     */
    private static function text(string $value): string
    {
        return addcslashes(trim(preg_replace('/\s+/', ' ', $value) ?? ''), '\\`*_[]#<>|');
    }

    private static function cell(string $value): string
    {
        return self::text($value);
    }
}
