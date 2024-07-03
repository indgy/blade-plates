<?php

namespace League\Plates\Template\ResolveTemplatePath;

use League\Plates\Exception\TemplateNotFound;
use League\Plates\Template\Name;
use League\Plates\Template\ResolveTemplatePath;

/** Resolves the path from the logic in the Name class which resolves via folder lookup, and then the default directory */
final class ResolveCachePath implements ResolveTemplatePath
{
    public function __invoke(Name $name): string {
        // @TODO un-hardcode this
        return path(sprintf("storage/views/%s", md5($name->getPath())));
    }
}
