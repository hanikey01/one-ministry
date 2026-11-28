<?php

namespace Tests\Unit;

use App\Services\NavigationLayout;
use PHPUnit\Framework\TestCase;

class NavigationLayoutTest extends TestCase
{
    public function test_it_builds_an_ordered_three_level_navigation_tree(): void
    {
        $tree = (new NavigationLayout)->toTree([
            ['key' => 'root', 'parent' => null, 'label' => 'Root'],
            ['key' => 'child', 'parent' => 'root', 'label' => 'Child'],
            ['key' => 'grandchild', 'parent' => 'child', 'label' => 'Grandchild'],
            ['key' => 'second', 'parent' => null, 'label' => 'Second'],
        ]);

        $this->assertSame(['root', 'second'], array_column($tree, 'key'));
        $this->assertSame('child', $tree[0]['children'][0]['key']);
        $this->assertSame('grandchild', $tree[0]['children'][0]['children'][0]['key']);
        $this->assertSame(2, $tree[0]['children'][0]['children'][0]['depth']);
    }

    public function test_orphans_and_cycles_remain_visible_at_the_root(): void
    {
        $tree = (new NavigationLayout)->toTree([
            ['key' => 'orphan', 'parent' => 'missing', 'label' => 'Orphan'],
            ['key' => 'one', 'parent' => 'two', 'label' => 'One'],
            ['key' => 'two', 'parent' => 'one', 'label' => 'Two'],
        ]);

        $this->assertSame(['orphan', 'one', 'two'], array_column($tree, 'key'));
        $this->assertSame([[], [], []], array_column($tree, 'children'));
    }
}
