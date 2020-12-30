<?php

namespace Generator\Generators\Admin\Module\Menu;

use Hurah\Types\Type\Icon;
use Hurah\Types\Type\Path;
use Hurah\Types\Type\PlainText;
use Generator\Generators\Admin\Module\Menu\Item\ItemConfig;

interface MenuConfigInterface {

    public function getIcon(): Icon;

    public function hasSubmenu(): bool;

    public function getTitle(): PlainText;

    /**
     * @return ItemConfig[]
     */
    public function getMenu(): array;

    public function isEmpty() : bool;
    public function count() : int;
    public function location(): Path;
    /*
    public function getCustom(): string;
    public function getModule(): string;
    */
}
