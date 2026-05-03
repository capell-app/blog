<?php

declare(strict_types=1);

use Capell\Blog\Tests\BlogTestCase;

pest()->extend(BlogTestCase::class)->group('blog')->in(__DIR__);
