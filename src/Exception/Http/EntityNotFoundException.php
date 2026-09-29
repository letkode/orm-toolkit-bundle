<?php

declare(strict_types=1);

namespace Letkode\OrmToolkitBundle\Exception\Http;

use Letkode\HttpExceptionBundle\Exception\EntityNotFoundException;

/*
 * @deprecated Use Letkode\HttpExceptionBundle\Exception\EntityNotFoundException. This name is kept as an
 *             alias of it so existing `catch` blocks and `new` calls keep working.
 */
class_alias(EntityNotFoundException::class, 'Letkode\OrmToolkitBundle\Exception\Http\EntityNotFoundException');
