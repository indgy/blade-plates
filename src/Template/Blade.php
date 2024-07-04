<?php

namespace League\Plates\Template;

class Blade
{
    /**
     * Converts Blade syntax to Plates syntax.
     * @param  string      $string
     * @return string
     */
    protected function convert(string $string): string
    {
        $map = [

            // Convert Blade to Plates

            // Set the template's layout.
            '/@extends\s?\((.*?)\)/i' => '<?php $this->layout($1); ?>',
            // Start a new section block.
            '/@section\s?\((.*?)\)/i' => '<?php $this->start($1); ?>',
            // Start a new section block in APPEND mode
            '/@push\s?\((.*?)\)/i' => '<?php $this->push($1); ?>',
            // Start a new section block in PREPEND mode.
            '/@prepend\s?\((.*?)\)/i' => '<?php $this->unshift($1); ?>',
            // Stop the current section block.
            '/@end(section|preprend|push)/i' => '<?php $this->stop(); ?>',
            // Returns the content for a section block, or a default
            '/@(yield|stack)\s?\((.*?)\)/i' => '<?= $this->section($1); ?>',
            // Output a rendered template.
            '/@include\s?\((.*?)\)/i' => '<?php $this->insert($1); ?>',
            // Fetch a rendered template.
            // NOT SUPPORTED - use $var = $this->fetch(name, data)

            // Convert Blade to PHP

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