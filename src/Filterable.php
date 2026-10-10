<?php

namespace tpModelFilter;

/** Adds the filter() local scope and filter resolution hooks to a model. */
trait Filterable
{
    /**
     * ThinkPHP local scope entry point. Supports a model default filter and a
     * per-call override, following EloquentFilter's precedence rules.
     *
     * @param object $query
     * @param array $input
     * @param string|null $filter Optional filter class or class basename
     * @return object
     */
    public function scopeFilter($query, array $input, $filter = null)
    {
        $class = $this->resolveModelFilterClass($filter);

        if (!class_exists($class)) {
            throw new \InvalidArgumentException(sprintf('Filter class "%s" was not found.', $class));
        }

        if (!is_subclass_of($class, ModelFilter::class)) {
            throw new \InvalidArgumentException(sprintf(
                'Filter class "%s" must extend %s.',
                $class,
                ModelFilter::class
            ));
        }

        $modelFilter = new $class($query, $input);

        return $modelFilter->handle();
    }

    /** Override this on a model to select a default custom filter class. */
    public function modelFilter()
    {
        return null;
    }

    /** Explicitly mark the filter class returned by modelFilter(). */
    protected function provideFilter($class)
    {
        return $class;
    }

    /** Override the default namespace when filters live outside app\modelFilter. */
    protected function modelFilterNamespace()
    {
        return '\\app\\modelFilter\\';
    }

    protected function resolveModelFilterClass($override = null)
    {
        $class = $override;

        if ($class === null && method_exists($this, 'modelFilter')) {
            $class = $this->modelFilter();
        }

        if ($class === null || $class === '') {
            $class = $this->modelFilterNamespace() . $this->filterModelName() . 'Filter';
        } elseif (strpos($class, '\\') === false) {
            $class = $this->modelFilterNamespace() . $class;
        }

        return ltrim($class, '\\');
    }

    protected function filterModelName()
    {
        $class = get_class($this);
        $position = strrpos($class, '\\');

        return $position === false ? $class : substr($class, $position + 1);
    }
}
