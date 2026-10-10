<?php

namespace tpModelFilter;

/**
 * Base class for request-driven query filters.
 *
 * Query operations are delegated to the framework query object. Keeping the
 * query untyped lets this package work with the different ORM implementations
 * shipped with ThinkPHP 5 through 8.
 */
abstract class ModelFilter
{
    /** @var object */
    protected $query;

    /** @var array */
    protected $input = [];

    /** @var array */
    protected $blacklist = [];

    /** @var bool */
    protected $allowedEmptyFilters = false;

    /** @var bool */
    protected $drop_id = true;

    /** @var bool */
    protected $camel_cased_methods = true;

    /**
     * @param object $query Framework query or model query object
     * @param array $input Request filter values
     */
    public function __construct($query, array $input = [])
    {
        if (!is_object($query)) {
            throw new \InvalidArgumentException('The filter query must be an object.');
        }

        $this->query = $query;
        $this->input = $this->allowedEmptyFilters ? $input : $this->removeEmptyInput($input);
    }

    /** Apply setup and input filters, then return the underlying query. */
    public function handle()
    {
        if (method_exists($this, 'setup')) {
            $this->setup();
        }

        $this->filterInput();

        return $this->query;
    }

    /** Run a matching public filter method for each input entry. */
    public function filterInput()
    {
        foreach ($this->input as $key => $value) {
            $method = $this->getFilterMethod($key);

            if ($this->methodIsCallable($method)) {
                $this->{$method}($value);
            }
        }

        return $this;
    }

    /** Resolve an input key to the corresponding filter method name. */
    public function getFilterMethod($key)
    {
        $key = (string) $key;
        $method = str_replace('.', '', $key);

        if ($this->drop_id) {
            $method = preg_replace('/_id$/', '', $method);
        }

        return $this->camel_cased_methods ? $this->camelCase($method) : $method;
    }

    /** Return all retained input, or one value with an optional default. */
    public function input($key = null, $default = null)
    {
        if ($key === null) {
            return $this->input;
        }

        return array_key_exists($key, $this->input) ? $this->input[$key] : $default;
    }

    /** Add or replace input, useful when setup derives additional filters. */
    public function push($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $name => $item) {
                $this->setInput($name, $item);
            }
        } else {
            $this->setInput($key, $value);
        }

        return $this;
    }

    /** Remove empty strings, nulls, and empty arrays unless explicitly allowed. */
    public function removeEmptyInput(array $input)
    {
        if ($this->allowedEmptyFilters) {
            return $input;
        }

        return array_filter($input, function ($value) {
            return $value !== '' && $value !== null && !(is_array($value) && count($value) === 0);
        });
    }

    public function blacklistMethod($method)
    {
        if (!in_array($method, $this->blacklist, true)) {
            $this->blacklist[] = $method;
        }

        return $this;
    }

    public function whitelistMethod($method)
    {
        $this->blacklist = array_values(array_filter($this->blacklist, function ($name) use ($method) {
            return $name !== $method;
        }));

        return $this;
    }

    public function methodIsBlacklisted($method)
    {
        return in_array($method, $this->blacklist, true);
    }

    /** Only public methods on the concrete filter can receive request data. */
    public function methodIsCallable($method)
    {
        if ($method === 'setup' || $this->methodIsBlacklisted($method) || !method_exists($this, $method) ||
            method_exists(self::class, $method)) {
            return false;
        }

        $reflection = new \ReflectionMethod($this, $method);

        return $reflection->isPublic();
    }

    public function dropIdSuffix($enabled = null)
    {
        if ($enabled === null) {
            return $this->drop_id;
        }

        $this->drop_id = (bool) $enabled;

        return $this;
    }

    public function convertToCamelCasedMethods($enabled = null)
    {
        if ($enabled === null) {
            return $this->camel_cased_methods;
        }

        $this->camel_cased_methods = (bool) $enabled;

        return $this;
    }

    /**
     * Proxy query-builder calls while preserving the filter context for fluent
     * query results. Terminal calls such as select()/find() return their value.
     */
    public function __call($method, $arguments)
    {
        if (!is_callable([$this->query, $method])) {
            throw new \BadMethodCallException(sprintf(
                'Method %s::%s does not exist.',
                is_object($this->query) ? get_class($this->query) : 'query',
                $method
            ));
        }

        $result = call_user_func_array([$this->query, $method], $arguments);

        if (is_object($result) && is_a($result, get_class($this->query))) {
            return $this;
        }

        return $result;
    }

    /** Common LIKE helpers, with values passed through normal ORM binding. */
    public function whereLike($column, $value, $boolean = 'and')
    {
        $method = strtolower($boolean) === 'or' ? 'whereOr' : 'where';

        return $this->{$method}($column, 'like', '%' . $value . '%');
    }

    public function whereBeginsWith($column, $value, $boolean = 'and')
    {
        $method = strtolower($boolean) === 'or' ? 'whereOr' : 'where';

        return $this->{$method}($column, 'like', $value . '%');
    }

    public function whereEndsWith($column, $value, $boolean = 'and')
    {
        $method = strtolower($boolean) === 'or' ? 'whereOr' : 'where';

        return $this->{$method}($column, 'like', '%' . $value);
    }

    protected function includeFilterInput($key, $value)
    {
        return $this->allowedEmptyFilters ||
            ($value !== '' && $value !== null && !(is_array($value) && count($value) === 0));
    }

    private function setInput($key, $value)
    {
        if ($this->includeFilterInput($key, $value)) {
            $this->input[$key] = $value;
        } else {
            unset($this->input[$key]);
        }
    }

    private function camelCase($value)
    {
        $value = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $value);
        $value = str_replace(['-', '_', ' '], ' ', $value);
        $value = strtolower($value);
        $value = ucwords($value);

        return lcfirst(str_replace(' ', '', $value));
    }
}
