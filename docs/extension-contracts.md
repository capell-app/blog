# Worked extension examples

These developer-facing recipes are kept beside the package contract. Replace the example values with the site-specific records and data objects used by the calling workflow.

<!-- example: action install -->

```php
<?php
declare(strict_types=1);
$inputs = []; // Supply the arguments required by the action handle() method.
resolve(\Capell\Blog\Actions\InstallBlogPackageAction::class)->handle(...$inputs);
```

<!-- example: action sanitizeBlogHtml -->

```php
<?php
declare(strict_types=1);
$inputs = []; // Supply the arguments required by the action handle() method.
resolve(\Capell\Blog\Actions\SanitizeBlogHtmlAction::class)->handle(...$inputs);
```
