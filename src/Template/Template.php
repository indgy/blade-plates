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
        $path = ($this->engine->getResolveTemplatePath())($this->name);

        try {
            $level = ob_get_level();
            ob_start();

            // (function() {
            //     extract($this->data);
            //     include func_get_arg(0);
            // })($path);

            // @TODO use cache/templates to save de-Bladed files to cache and include from there
            (function () {
                extract($this->data);
                $content = file_get_contents(func_get_arg(0));
                $content = $this->deBlade($content);
                eval ('?>' . $content);
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
     * Converts Blade syntax to Plates syntax.
     * @param  string      $string
     * @return string
     */
    protected function deBlade(string $string): string
    {
        $map = [

            // replace @extends with layout call
            '/@extends\s?\((.*?)\)/i' => '<?php $this->layout($1); ?>',
            // include is a straight swap
            '/@include\s?\((.*?)\)/i' => '<?php $this->include($1); ?>',
            // section is a straight swap
            '/@section\s?\((.*?)\)/i' => '<?php echo $this->section($1); ?>',
            // section is the equivalent of yield
            '/@yield\s?\((.*?)\)/i' => '<?php echo $this->section($1); ?>',

            // unescape
            '/@@(break|continue|foreach|verbatim|endverbatim)/i' => '@$1',

            # remove blade comments
            '/{{--[\s\S]*?--}}/i' => '',

            # echo with a default
            '/{{\s*(.+?)\s+or\s+(.+?)\s*}}/i' => '<?php echo (isset($1)) ? $this->e($1) : $2; ?>',

            # echo an escaped variable, ignoring @{{ var }} for js frameworks
            '/(?<![@]){{\s*(.*?)\s*}}/i' => '<?php echo $this->e($1); ?>',
            # output for js frameworks
            '/@{{\s*(.*?)\s*}}/i' => '{{ $1 }}',

            # echo an unescaped variable
            '/{!!\s*(.+?)\s*!!}/i' => '<?php echo $1; ?>',

            # variable display mutators, wrap these in $this->e() escape function as necessary
            '/@csrf(?![\s(])/i' => '<input type="hidden" name="_csrf_token" value="$csrf">',
            '/@csrf\(([^()]+),\s*["\']([^"\']+)["\']\)/i' => '<input type="hidden" name="$2" value="$1">',
            '/@csrf\s?\((.*?)\)/i' => '<input type="hidden" name="_csrf_token" value="$1">',
            '/@json\((.*?)\s?,\s?(.*?)\s?,\s?(.*?)\s?\)/i' => '<?php echo json_encode($1, $2, $3); ?>',
            '/@json\((.*?)\s?,\s?(.*?)\s?\)/i' => '<?php echo json_encode($1, $2, 512); ?>',
            '/@json\((.*?)\s?\)/i' => '<?php echo json_encode($1, 15, 512); ?>',
            '/@js\((.*?)\s?,\s?(.*?)\s?,\s?(.*?)\s?\)/i' => '<?php echo js($1, $2, $3); ?>',
            '/@js\((.*?)\s?,\s?(.*?)\s?\)/i' => '<?php echo js($1, $2); ?>',
            '/@js\((.*?)\s?\)/i' => '<?php echo js($1); ?>',
            '/@method\s?\((.*?)\)/i' => '<input type="hidden" name="_METHOD" value="$1">',
            '/@method\s?\((.*?)\s?,\s?(.*?)\s?\)/i' => '<input type="hidden" name="$2" value="$1">',
            '/@lower\s?\((.*?)\)/i' => '<?php echo $this->e(strtolower($1)); ?>',
            '/@upper\s?\((.*?)\)/i' => '<?php echo $this->e(strtoupper($1)); ?>',
            '/@ucfirst\s?\((.*?)\)/i' => '<?php echo $this->e(ucfirst(strtolower($1))); ?>',
            '/@ucwords\s?\((.*?)\)/i' => '<?php echo $this->e(ucwords(strtolower($1))); ?>',
            '/@(format|sprintf)\s?\((.*?)\)/i' => '<?php echo $this->e(sprintf($2)); ?>',

            # wordwrap has multiple parameters
            '/@wrap\s?\((.*?)\)/i' => '<?php echo $this->e(wordwrap($1)); ?>',
            '/@wrap\s?\((.*?)\s*,\s*(.*?)\)/i' => '<?php echo $this->e(wordwrap($1, $2)); ?>',
            '/@wrap\s?\((.*?)\s*,\s*(.*?)\s*,\s*(.*?)\)/i' => '<?php echo $this->e(wordwrap($1, $2, $3)); ?>',

            # set and unset statements
            '/@set\(([\'"])(\w+)\1,\s*([\'"])(.*?)\3\)/i' => '<?php $\2 = "\4"; ?>',
            '/@unset\s?\(\$(\w+)\)/i' => '<?php unset($\1); ?>',

            # isset statement
            '/@isset\s?\((.*?)\)/i' => '<?php if (isset($1)): ?>',
            '/@endisset/i' => '<?php endif; ?>',

            # has statement
            '/@has\s?\((.*?)\)/i' => '<?php if (isset($1) && ! empty($1)): ?>',
            '/@endhas/i' => '<?php endif; ?>',

            # handle special unless statement
            '/@unless\s?\((.*?)\)(\s+?)/i' => '<?php if (!($1)): ?>$2',
            '/@endunless/i' => '<?php endif; ?>',

            # special empty statement
            '/@empty\s?\((.*?)\)/i' => '<?php if (empty($1)): ?>',
            '/@endempty/i' => '<?php endif; ?>',

            # switch statement
            '/@switch\s*\((.*?)\)\s*@case\s*\((.*?)\)/s' => "<?php switch($1):\ncase ($2): ?>",
            '/@case\((.*?)\)/i' => '<?php case ($1): ?>',
            '/@default/i' => '<?php default: ?>',
            '/@continue\(\s*(.*)\s*\)/i' => '<?php if($1): continue; endif; ?>',
            '/@continue/i' => '<?php continue; ?>',
            '/@break\(\s*([0-9])\s*\)/i' => '<?php break $1; ?>',
            '/@break\(\s*(.*)\s*\)/i' => '<?php if($1) break; ?>',
            '/@break/i' => '<?php break; ?>',
            '/@endswitch/i' => '<?php endswitch; ?>',

            # handle loops and control structures
            '/@foreach\s?\( *(.*?) *as *(.*?) *\)/i' => '<?php $loop = loop(count($1), $loop ?? null); foreach($1 as $2): ?>',
            '/@endforeach\s*/i' => '<?php $loop->increment(); endforeach; $loop = $loop->parent(); ?>',

            # handle special forelse loop
            '/@forelse\s?\(\s*(\S*)\s*as\s*(\S*)\s*\)(\s*)/i' => "<?php if(!empty($1)): \$loop = loop(count($1), \$loop ?? null); foreach($1 as $2): ?>\n",
            '/@empty(?![\s(])/' => "<?php \$loop->increment(); endforeach; \$loop = \$loop->parent(); ?>\n<?php else: ?>",
            '/@endforelse/' => '<?php endif; ?>',

            // this comes last so it does not match others above
            '/@for\s?(\(.+\s*=\s*(\d+);\s*.+;\s*.+\s*\))/i' => '<?php $loop = loop(\\2, $loop ?? null); for\\1: ?>',
            '/@endfor/' => '<?php $loop->increment(); endfor; $loop = $loop->parent(); ?>',

            # each statements
            # eachelse matches first
            '/@each\s?\((.*)\s*,\s*(.*)\s*,\s*[(\'|\")](.*)[(\'|\")],\s*(.*)\s*\)/i' => "<?php if (!empty($2)): \$loop = loop(count($2), \$loop ?? null); foreach($2 as \$$3): ?>\n@include($1)\n<?php \$loop->increment(); endforeach; \$loop = \$loop->parent(); ?>\n<?php else: ?>\n@include($4)\n<?php endif; ?>",
            '/@each\s?\((.*)\s*,\s*(.*)\s*,\s*[(\'|\")](.*)[(\'|\")]\s*\)/i' => "<?php \$loop = loop(count($2), \$loop ?? null); foreach($2 as \$$3): ?>\n@include($1)\n<?php \$loop->increment(); endforeach; \$loop = \$loop->parent(); ?>",

            // while
            '/@while\s?\((.*)\)/i' => '<?php $loop = loop(count($1), $loop ?? null); while ($1): ?>',
            '/@endwhile/' => '<?php $loop->increment(); endwhile; $loop = $loop->parent(); ?>',

            # control structures
            '/@(if|elseif)\s?\((.*)\)/i' => '<?php $1 ($2): ?>',
            '/@else/i' => '<?php else: ?>',
            '/@endif/' => '<?php endif; ?>',

            # swap out @php and @endphp
            '/@php\((.*)\)/i' => '<?php ($1); ?>',
            '/@php/i' => '<?php',
            '/@endphp/i' => '; ?>',

        ];

        return preg_replace(array_keys($map), array_values($map), $string);
    }
}
