<?php

namespace League\Plates\Template;


class Loop
{
    private int $count;
    private int $depth;
    private int $index;
    private null|Loop $parent;


    public function __construct(int $count, Loop $parent = null)
    {
        $this->index = 0;
        $this->count = $count;
        $this->parent = $parent;
        $this->depth = (is_null($parent)) ? 0 : $parent->depth + 1;
    }
    // Increment the counter
    public function increment(): void
    {
        $this->index++;
    }
    // The index of the current loop iteration (starts at 0).
    public function index(): int
    {
        return $this->index;
    }
    // The current loop iteration (starts at 1).
    public function iteration(): int
    {
        return 1 + $this->index;
    }
    // The iterations remaining in the loop.
    public function remaining(): int
    {
        return $this->count - (1 + $this->index);
    }
    // The total number of items in the array being iterated.
    public function count(): int
    {
        return $this->count;
    }
    // Whether this is the first iteration through the loop.
    public function first(): bool
    {
        return ($this->index === 0);
    }
    // Whether this is the last iteration through the loop.
    public function last(): bool
    {
        return ((1 + $this->index) === $this->count);
    }
    // Whether this is an even iteration through the loop
    public function even(): bool
    {
        return ($this->index % 2);
    }
    // Whether this is an odd iteration through the loop.
    public function odd(): bool
    {
        return !$this->even();
    }
    // The nesting level of the current loop.
    public function depth(): int
    {
        return $this->depth;
    }
    // When in a nested loop, returns the parent's loop variable.
    public function parent(): null|Loop
    {
        return $this->parent;
    }

    public function __get($name): mixed
    {
        if (!method_exists($this, $name) or $name === 'increment') {
            throw new BladeException(sprintf("Call to unknown property '%s' on Loop", $name));
        }

        return $this->$name();
    }
}
