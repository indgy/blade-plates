<?php

namespace League\Plates\Template;

use Exception;
use League\Plates\Engine;
use League\Plates\Exception\TemplateNotFound;
use LogicException;
use Throwable;

/**
 * Container which holds template data and provides access to template functions.
 */
class Template
{
    const SECTION_MODE_REWRITE = 1;
    const SECTION_MODE_PREPEND = 2;
    const SECTION_MODE_APPEND = 3;

    /**
     * Set section content mode: rewrite/append/prepend
     * @var int
     */
    protected $sectionMode = self::SECTION_MODE_REWRITE;

    /**
     * Instance of the template engine.
     * @var Engine
     */
    protected $engine;

    /**
     * The name of the template.
     * @var Name
     */
    protected $name;

    /**
     * The data assigned to the template.
     * @var array
     */
    protected $data = array();

    /**
     * An array of section content.
     * @var array
     */
    protected $sections = array();

    /**
     * The name of the section currently being rendered.
     * @var string
     */
    protected $sectionName;

    /**
     * Whether the section should be appended or not.
     * @deprecated stayed for backward compatibility, use $sectionMode instead
     * @var boolean
     */
    protected $appendSection;

    /**
     * The name of the template layout.
     * @var string
     */
    protected $layoutName;

    /**
     * The data assigned to the template layout.
     * @var array
     */
    protected $layoutData;

    /**
     * Create new Template instance.
     * @param Engine $engine
     * @param string $name
     */
    public function __construct(Engine $engine, $name)
    {
        $this->engine = $engine;
        $this->name = new Name($engine, $name);

        $this->data($this->engine->getData($name));
    }

    /**
     * Magic method used to call extension functions.
     * @param  string $name
     * @param  array  $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return $this->engine->getFunction($name)->call($this, $arguments);
    }

    /**
     * Alias for render() method.
     * @throws \Throwable
     * @throws \Exception
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }

    /**
     * Assign or get template data.
     * @param  array $data
     * @return mixed
     */
    public function data(array $data = null)
    {
        if (is_null($data)) {
            return $this->data;
        }

        $this->data = array_merge($this->data, $data);
    }

    /**
     * Check if the template exists.
     * @return boolean
     */
    public function exists()
    {
        try {
            ($this->engine->getResolveTemplatePath())($this->name);
            return true;
        } catch (TemplateNotFound $e) {
            return false;
        }
    }

    /**
     * Get the template path.
     * @return string
     */
    public function path()
    {
        try {
            return ($this->engine->getResolveTemplatePath())($this->name);
        } catch (TemplateNotFound $e) {
            return $e->paths()[0];
        }
    }

    /**
     * Render the template and layout.
     * @param  array  $data
     * @throws \Throwable
     * @throws \Exception
     * @return string
     */
    public function render(array $data = array())
    {
        $this->data($data);

        // get cached template path
        $path = ($this->engine->getResolveCachePath())($this->name);
        // we always need a cached template
        if ( ! file_exists($path) or $this->engine->useCache() === false) {
            // fetch content from template
            $content = file_get_contents(($this->engine->getResolveTemplatePath())($this->name));
            // store compiled content in cache path
            file_put_contents($path, (new Blade)->convert($content));
        }

        try {
            $level = ob_get_level();
            ob_start();

            (function() {
                extract($this->data);
                include func_get_arg(0);
            })($path);

            $content = ob_get_clean();

            if (isset($this->layoutName)) {
                $layout = $this->engine->make($this->layoutName);
                $layout->sections = array_merge($this->sections, array('content' => $content));
                $content = $layout->render($this->layoutData);
            }

            return $content;
        } catch (Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }

            throw $e;
        }
    }

    /**
     * Set the template's layout.
     * @param  string $name
     * @param  array  $data
     * @return null
     */
    public function layout($name, array $data = array())
    {
        $this->layoutName = $name;
        $this->layoutData = $data;
    }

    /**
     * Start a new section block.
     * @param  string  $name
     * @return null
     */
    public function start($name)
    {
        if ($name === 'content') {
            throw new LogicException(
                'The section name "content" is reserved.'
            );
        }

        if ($this->sectionName) {
            throw new LogicException('You cannot nest sections within other sections.');
        }

        $this->sectionName = $name;

        ob_start();
    }

    /**
     * Start a new section block in APPEND mode.
     * @param  string $name
     * @return null
     */
    public function push($name)
    {
        $this->appendSection = true; /* for backward compatibility */
        $this->sectionMode = self::SECTION_MODE_APPEND;
        $this->start($name);
    }

    /**
     * Start a new section block in PREPEND mode.
     * @param  string $name
     * @return null
     */
    public function unshift($name)
    {
        $this->appendSection = false; /* for backward compatibility */
        $this->sectionMode = self::SECTION_MODE_PREPEND;
        $this->start($name);
    }

    /**
     * Stop the current section block.
     * @return null
     */
    public function stop()
    {
        if (is_null($this->sectionName)) {
            throw new LogicException(
                'You must start a section before you can stop it.'
            );
        }

        if (!isset($this->sections[$this->sectionName])) {
            $this->sections[$this->sectionName] = '';
        }

        switch ($this->sectionMode) {

            case self::SECTION_MODE_REWRITE:
                $this->sections[$this->sectionName] = ob_get_clean();
                break;

            case self::SECTION_MODE_APPEND:
                $this->sections[$this->sectionName] .= ob_get_clean();
                break;

            case self::SECTION_MODE_PREPEND:
                $this->sections[$this->sectionName] = ob_get_clean().$this->sections[$this->sectionName];
                break;

        }
        $this->sectionName = null;
        $this->sectionMode = self::SECTION_MODE_REWRITE;
        $this->appendSection = false; /* for backward compatibility */
    }

    /**
     * Alias of stop().
     * @return null
     */
    public function end()
    {
        $this->stop();
    }

    /**
     * Returns the content for a section block.
     * @param  string      $name    Section name
     * @param  string      $default Default section content
     * @return string|null
     */
    public function section($name, $default = null)
    {
        if (!isset($this->sections[$name])) {
            return $default;
        }

        return $this->sections[$name];
    }

    /**
     * Fetch a rendered template.
     * @param  string $name
     * @param  array  $data
     * @return string
     */
    public function fetch($name, array $data = array())
    {
        return $this->engine->render($name, $data);
    }

    /**
     * Output a rendered template.
     * @param  string $name
     * @param  array  $data
     * @return null
     */
    public function insert($name, array $data = array())
    {
        echo $this->engine->render($name, $data);
    }

    /**
     * Output a rendered template if the file exists.
     * @param  string $name
     * @param  array  $data
     * @return null
     */
    public function insertIf(string $name, array $data = array())
    {
        if ($this->engine->exists($name)) {
            echo $this->engine->render($name, $data);
        }
    }

    /**
     * Output the first rendered template that exists.
     * @param  array $names
     * @param  array  $data
     * @return null
     */
    public function insertFirst(array $names, array $data = array())
    {
        foreach ($names as $name) {
            if ($this->engine->exists($name)) {
                echo $this->engine->render($name, $data);
            }
        }
    }

    /**
     * Output a rendered template if the logic is true.
     * @param  bool   $logic
     * @param  string $name
     * @param  array  $data
     * @return null
     */
    public function insertWhen(bool $logic, string $name, array $data = array())
    {
        if ($logic) {
            echo $this->engine->render($name, $data);
        }
    }

    /**
     * Output a rendered template if the logic is false.
     * @param  bool   $logic
     * @param  string $name
     * @param  array  $data
     * @return null
     */
    public function insertUnless(bool $logic, string $name, array $data = array())
    {
        if ( ! $logic) {
            echo $this->engine->render($name, $data);
        }
    }

    /**
     * Apply multiple functions to variable.
     * @param  mixed  $var
     * @param  string $functions
     * @return mixed
     */
    public function batch($var, $functions)
    {
        foreach (explode('|', $functions) as $function) {
            if ($this->engine->doesFunctionExist($function)) {
                $var = call_user_func(array($this, $function), $var);
            } elseif (is_callable($function)) {
                $var = call_user_func($function, $var);
            } else {
                throw new LogicException(
                    'The batch function could not find the "' . $function . '" function.'
                );
            }
        }

        return $var;
    }

    /**
     * Escape string.
     * @param  string      $string
     * @param  null|string $functions
     * @return string
     */
    public function escape($string, $functions = null)
    {
        static $flags;

        if (!isset($flags)) {
            $flags = ENT_QUOTES | (defined('ENT_SUBSTITUTE') ? ENT_SUBSTITUTE : 0);
        }

        if ($functions) {
            $string = $this->batch($string, $functions);
        }

        return htmlspecialchars($string ?? '', $flags, 'UTF-8');
    }

    /**
     * Alias to escape function.
     * @param  string      $string
     * @param  null|string $functions
     * @return string
     */
    public function e($string, $functions = null)
    {
        return $this->escape($string, $functions);
    }
    /**
     * Return a new loop instance, optionally nesting the parent Loop instance.
     * @param  int         $count
     * @param  null|Loop   $parent
     * @return Loop
     */
    public function loop(int $count, ?Loop $parent = null): Loop
    {
        return new Loop($count, $parent);
    }
    /**
     * Returns the true if field_name exists in the errors array
     * @param   string    $field_name
     * @return  bool
     */
    public function error(string $field_name): bool
    {
        return isset($this->data['errors'][$field_name]);
    }
    /**
     * Accepts an array of classes where the array key contains the class or classes you wish to add,
     * while the value is a boolean expression.
     * Returns a space separated string.
     * @param   array       $items
     * @return  string
     */
    public function class(array $items = []): string
    {
        $items = array_filter($arr, function($v, $k) {
            return $v === true;
        }, ARRAY_FILTER_USE_BOTH);

        return join(' ', $items);
    }
    /**
     * Accepts an array of styles where the array key contains the style or styles you wish to add,
     * while the value is a boolean expression.
     * Returns a semi-colon deliminated string
     * @param   array       $items
     * @return  string
     */
    public function style(array $items = []): string
    {
        $items = array_filter($arr, function($v, $k) {
            return $v === true;
        }, ARRAY_FILTER_USE_BOTH);

        return join('; ', $items);
    }
    /**
     * Returns the html checked attribute if logic is true
     * @param   mixed       $logic
     * @return  string
     */
    public function checked(mixed $logic): string
    {
        return (true == $logic)
            ? 'checked="checked"'
            : '';
    }
    /**
     * Returns the html disabled attribute if logic is true
     * @param   mixed       $logic
     * @return  string
     */
    public function disabled(mixed $logic): string
    {
        return (true == $logic)
            ? 'disabled="disabled"'
            : '';
    }
    /**
     * Returns the html selected attribute if logic is true
     * @param   mixed       $logic
     * @return  string
     */
    public function selected(mixed $logic): string
    {
        return (true == $logic)
            ? 'selected'
            : '';
    }
    /**
     * Returns the html readonly attribute if logic is true
     * @param   mixed       $logic
     * @return  string
     */
    public function readonly(mixed $logic): string
    {
        return (true == $logic)
            ? 'readonly'
            : '';
    }
    /**
     * Returns the html required attribute if logic is true
     * @param   mixed       $logic
     * @return  string
     */
    public function required(mixed $logic): string
    {
        return (true == $logic)
            ? 'required'
            : '';
    }
}
