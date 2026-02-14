<?php

namespace DevJM\DependentFilter;

use Laravel\Nova\ResourceTool;

class DependentFilterTool extends ResourceTool
{
    /**
     * Get the displayable name of the resource tool.
     *
     * @return string
     */
    public function name()
    {
        return 'Dependent Filter';
    }

    /**
     * Get the component name for the resource tool.
     *
     * @return string
     */
    public function component()
    {
        return 'dependent-filter';
    }
}
