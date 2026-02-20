# API Reference - Capell Blog

Pointers to key classes. Browse `./src` for full API.

- Service Provider
    - `src/Providers/BlogServiceProvider.php`
- Models
    - `src/Models/Article.php`
    - `src/Models/Tag.php`
    - Model concern: `src/Models/Concerns/HasTags.php`
- Filament resources
    - Articles: `src/Filament/Resources/Articles`
    - Tags: `src/Filament/Resources/Tags`
- Livewire pages
    - `src/Livewire/Page/BlogPage.php`
    - `src/Livewire/Page/ArchivePage.php`
    - `src/Livewire/Page/TagPage.php`
- View components (widgets)
    - `src/View/Components/Widget/Page/*`
    - `src/View/Components/Widget/Tag/*`
- Actions
    - `src/Actions/CreateBlogPagesAction.php`
    - `src/Actions/InstallPackageAction.php`
    - `src/Actions/GetArticleLayoutAction.php`
- Services
    - Loaders and sitemap: `src/Services/*`
- Enums
    - `src/Enums/*`
- Listener
    - `src/Listeners/AddBlogPagesToNavigation.php`
- Commands
    - `src/Commands/InstallCommand.php`
    - `src/Commands/CreateBlogPagesCommand.php`
    - `src/Commands/DemoCommand.php`

Quick links:

- Source directory: `./src`
- Database reference: `./docs/Database.md`
