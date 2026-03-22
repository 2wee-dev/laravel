<?php

namespace TwoWee\Laravel\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use TwoWee\Laravel\Resource;
use TwoWee\Laravel\TwoWee;

class MenuController extends Controller
{
    public function __construct(
        protected TwoWee $twoWee,
    ) {}

    public function main(): JsonResponse
    {
        $user = Auth::guard('twowee')->user();

        return response()->json($this->buildMenuData($user));
    }

    public function buildMenuData($user): array
    {
        $baseUrl = \TwoWee\Laravel\TwoWee::baseUrl();
        $defaultTab = config('twowee.menu.default_tab', 'Home');
        $tabOrder = config('twowee.menu.tab_order', []);

        // Collect visible resources
        $entries = [];
        $popupItems = [];  // group => popup_name => items[]

        foreach ($this->twoWee->getResources() as $slug => $resourceClass) {
            if (! $resourceClass::shouldShowInNavigation()) {
                continue;
            }

            $group = $resourceClass::getNavigationGroup() ?? $defaultTab;
            $sort = $resourceClass::getNavigationSort();
            $popup = $resourceClass::getNavigationPopup();

            $itemAction = [
                'type' => 'open_screen',
                'url' => $baseUrl . '/screen/' . $slug . '/list',
            ];

            // Add separator before this entry if flagged
            if ($resourceClass::hasNavigationSeparatorBefore()) {
                $entries[] = [
                    'group' => $group,
                    'sort' => $sort - 0.1,  // just before this entry
                    'label' => '',
                    'action' => ['type' => 'separator'],
                ];
            }

            if ($popup !== null) {
                // Collect into popup group — will be assembled after sorting
                $popupItems[$group][$popup][] = [
                    'sort' => $sort,
                    'label' => $resourceClass::getNavigationLabel(),
                    'action' => $itemAction,
                ];
            } else {
                $entries[] = [
                    'group' => $group,
                    'sort' => $sort,
                    'label' => $resourceClass::getNavigationLabel(),
                    'action' => $itemAction,
                ];
            }
        }

        // Build popup entries from collected items
        foreach ($popupItems as $group => $popups) {
            foreach ($popups as $popupName => $items) {
                // Sort items within the popup
                usort($items, fn ($a, $b) => $a['sort'] <=> $b['sort']);
                $minSort = $items[0]['sort'];

                $entries[] = [
                    'group' => $group,
                    'sort' => $minSort,
                    'label' => $popupName,
                    'action' => [
                        'type' => 'popup',
                        'items' => array_map(fn ($item) => [
                            'label' => $item['label'],
                            'action' => $item['action'],
                        ], $items),
                    ],
                ];
            }
        }

        // Add custom menu items from config
        foreach (config('twowee.menu.items', []) as $item) {
            $entries[] = [
                'group' => $item['group'] ?? $defaultTab,
                'sort' => $item['sort'] ?? 0,
                'label' => $item['label'],
                'action' => $item['action'],
            ];
        }

        // Sort entries: by sort value first, then alphabetically by label
        usort($entries, function ($a, $b) {
            return $a['sort'] <=> $b['sort'] ?: strcmp($a['label'], $b['label']);
        });

        // Group into tabs
        $groups = [];
        foreach ($entries as $entry) {
            $groups[$entry['group']][] = [
                'label' => $entry['label'],
                'action' => $entry['action'],
            ];
        }

        // Order tabs: explicit tab_order first, then remaining alphabetically
        $tabs = [];

        foreach ($tabOrder as $tabName) {
            if (isset($groups[$tabName])) {
                $tabs[] = ['label' => $tabName, 'items' => $groups[$tabName]];
                unset($groups[$tabName]);
            }
        }

        ksort($groups);
        foreach ($groups as $tabName => $items) {
            $tabs[] = ['label' => $tabName, 'items' => $items];
        }

        return array_merge(Resource::envelope($user), [
            'layout' => 'Menu',
            'screen_id' => '',
            'title' => 'Main Menu',
            'menu' => [
                'panel_title' => 'Main Menu',
                'top_left' => config('twowee.app_name') ?? config('app.name', 'Laravel'),
                'top_right' => $user?->name ?? null,
                'tabs' => $tabs,
            ],
            'sections' => [],
        ]);
    }
}
