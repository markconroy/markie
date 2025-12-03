# AGENTS.md

AI coding guide for Mark.ie (markconroy/markie) Drupal project.

## AI Response Requirements

**Communication style:**
- Code over explanations - provide implementations, not descriptions
- Be direct, skip preambles
- Assume Drupal expertise - no over-explaining basics
- Suggest better approaches with code
- Show only changed code sections with minimal context
- Complete answers in one response when possible
- Use Drupal APIs, not generic PHP
- Ask if requirements are ambiguous

**Response format:**
- Production-ready code with imports/dependencies
- Inline comments explain WHY, not WHAT
- Include full file paths
- Proper markdown code blocks

## Project Overview

- **Platform**: Drupal 11 (single site)
- **Context**: Personal website and blog for Mark Conroy - frontend developer and speaker
- **Architecture**: Traditional Drupal with custom theme, JSON:API enabled for headless capabilities
- **Security**: Standard Drupal security practices
- **Languages**: English only (single language)
- **Custom Entities**: None (uses standard Drupal content types)
- **Role System**: Standard Drupal roles (Anonymous, Authenticated, Administrator)
- **Use Cases**: Blog articles, speaking engagements, static pages, video content



### AI Integration
Provider: OpenAI | Modules: `drupal/ai`, `drupal/ai_provider_openai`
Uses: content generation, AI-enhanced editing in CKEditor
API keys in `.ddev/.env`



## Date Verification Rule

**CRITICAL**: Before writing dates to `.md` files, run `date` command first.
Never use example dates (e.g., "2024-01-01") - always use actual system date.

## Git Workflow

**Branches**: `main` (prod), `staging` (test), `feature/*`, `bugfix/*`, `hotfix/*`, `release/*`

**Flow**: `staging` → `feature/name` → PR → merge to `staging` → eventually `main`

**Hotfix**: `main` → `hotfix/name` → merge to `main` + `staging`, tag release

**Commit format**: `[type]: description` (max 50 chars)
Types: `feat`, `fix`, `docs`, `style`, `refactor`, `perf`, `test`, `chore`, `config`

**Before PR**: Run `phpcs`, `phpstan`, tests, `drush cex`

**Don'ts**: No direct commits to main/staging, no `--force` on shared branches, no credentials in code

**.gitignore essentials**:
```gitignore
web/core/
web/modules/contrib/
web/themes/contrib/
vendor/
web/sites/*/files/
web/sites/*/settings.local.php
.ddev/
node_modules/
.env
```

## Development Environment

**Web root**: `web/`

### Setup
```bash
git clone git@github.com:markconroy/markie.git && cd markie
ddev start && ddev drush cr
```

**DDEV**: `ddev ssh` (container), `ddev describe` (info), `ddev drush [cmd]`

### Custom DDEV Commands
Location: `.ddev/commands/host/[name]`
**WARNING**: Don't use `## #ddev-generated` comments - they break command recognition.

### Drush Commands

```bash
# Core commands
ddev drush status                    # Status check
ddev drush cr                        # Cache rebuild
ddev drush cex                       # Config export
ddev drush cim                       # Config import
ddev drush updb                      # Database updates

# Database & PHP eval
ddev drush sql:query "SELECT * FROM node_field_data LIMIT 5;"
ddev drush php:eval "echo 'Hello World';"

# Test services and entities
ddev drush php:eval "var_dump(\Drupal::hasService('entity_type.manager'));"
ddev drush php:eval "\$node = \Drupal::entityTypeManager()->getStorage('node')->load(1); var_dump(\$node->getTitle());"
ddev drush php:eval "var_dump(\Drupal::config('system.site')->get('name'));"
ddev drush php:eval "var_dump(\Drupal::service('custom_module.service_name'));"

# Quick setup (pull from platform)
ddev pull platform -y && ddev drush cim -y && ddev drush cr && ddev drush uli
```

### Composer
```bash
ddev composer outdated 'drupal/*'                    # Check updates
ddev composer update drupal/[module] --with-deps     # Update module
ddev composer require drupal/core:X.Y.Z drupal/core-recommended:X.Y.Z --update-with-all-dependencies  # Core update
```

**Scripts** in `composer.json`: `build`, `deploy`, `test`, `phpcs`, `phpstan`

### Environment Variables
Store in `.ddev/.env` (gitignored). Access: `$_ENV['VAR']`. Restart DDEV after changes.

### Patches
Structure: `./patches/{core,contrib/[module],custom}/`

In `composer.json` → `extra.patches`:
```json
"drupal/module": {"#123 Fix": "patches/contrib/module/fix.patch"}
```
Sources: local files, Drupal.org issue queue, GitHub PRs
Always include issue numbers in descriptions. Monitor upstream for merged patches.

## Code Quality Tools

```bash
# PHPStan - static analysis
ddev exec vendor/bin/phpstan analyze web/modules/custom --level=1

# PHPCS - coding standards check
ddev exec vendor/bin/phpcs --standard=Drupal web/modules/custom/

# PHPCBF - auto-fix coding standards
ddev exec vendor/bin/phpcbf --standard=Drupal web/modules/custom/

# Rector - code modernization (run in container)
ddev ssh && vendor/bin/rector process web/modules/custom --dry-run

# Upgrade Status - Drupal compatibility check
ddev drush upgrade_status:analyze --all
```

**Config files**: `phpstan.neon`, `phpcs.xml`, `rector.php`
**Run before**: commits, PRs, Drupal upgrades

## Testing

```bash
# PHPUnit
ddev exec vendor/bin/phpunit web/modules/custom
ddev exec vendor/bin/phpunit web/modules/custom/[module]/tests/src/Unit/MyTest.php
ddev exec vendor/bin/phpunit --coverage-html coverage web/modules/custom

# Codeception
ddev exec vendor/bin/codecept run [acceptance|functional|unit]
ddev exec vendor/bin/codecept run --steps --debug --html

# Debug failed tests
ddev exec vendor/bin/phpunit --testdox --verbose [test-file]
```

**Drupal test types** (in `tests/src/`): `Unit/` (isolated), `Kernel/` (minimal bootstrap), `Functional/` (full Drupal), `FunctionalJavascript/`

**Codeception structure**: `tests/{acceptance,functional,unit,_support,_data,_output}/`, config: `codeception.yml`

## Debugging

```bash
# Xdebug
ddev xdebug on|off                              # Toggle (disable when not debugging for perf)

# Container & DB access
ddev ssh                                         # Web container
ddev mysql                                       # MySQL CLI
ddev mysql -e "SELECT..."                        # Direct query
ddev export-db --file=backup.sql.gz             # Export
ddev import-db --file=backup.sql.gz             # Import

# Logs
ddev logs -f                                     # Container logs (follow)
ddev drush watchdog:show --count=50 --severity=Error

# State
ddev drush state:get|set|delete [key] [value]
```

**IDE**: PhpStorm (port 9003), VS Code (PHP Debug extension)
**Tips**: `ddev describe` (URLs/services), `ddev debug` (DDEV issues), Twig debug in `development.services.yml`

## Performance

```bash
# Cache
ddev drush cr                                    # Rebuild all
ddev drush cache:clear [render|dynamic_page_cache|config]

# Redis (if enabled)
ddev redis-cli INFO stats|memory
ddev redis-cli FLUSHALL                          # Clear Redis

# DB performance
ddev mysql -e "SELECT table_name, round(((data_length+index_length)/1024/1024),2) 'MB' FROM information_schema.TABLES WHERE table_schema=DATABASE() ORDER BY (data_length+index_length) DESC;"
ddev mysql -e "SHOW VARIABLES LIKE 'slow_query%';"
ddev drush sql:query "OPTIMIZE TABLE cache_bootstrap, cache_config, cache_data, cache_default, cache_discovery, cache_dynamic_page_cache, cache_entity, cache_menu, cache_render;"
```

**Optimization**: Enable page cache + dynamic page cache, CSS/JS aggregation, Redis/Memcache, CDN for assets, image styles with lazy loading

## Code Standards

### Core Principles

- **SOLID/DRY**: Follow SOLID principles, extract repeated logic
- **PHP 8.1+**: Use strict typing: `declare(strict_types=1);`
- **Drupal Standards**: PSR-12 based, English comments only

### Module Structure

Location: `/web/modules/custom/[module_name]/`
Naming: `markie_[descriptive_name]` or just `[descriptive_name]` - use `markie` prefix for project-specific modules

```
[module_name]/
├── [module_name].{info.yml,module,install,routing.yml,permissions.yml,services.yml,libraries.yml}
├── src/                          # PSR-4: \Drupal\[module_name]\[Subdir]\ClassName
│   ├── Entity/                   # Custom entities
│   ├── Form/                     # Forms (ConfigFormBase, FormBase)
│   ├── Controller/               # Route controllers
│   ├── Plugin/{Block,Field/FieldWidget,Field/FieldFormatter}/
│   ├── Service/                  # Custom services
│   └── EventSubscriber/          # Event subscribers
├── templates/                    # Twig templates
├── css/ & js/                    # Assets
```

**PSR-4**: `src/Form/MyForm.php` → `\Drupal\my_module\Form\MyForm`

### Entity Development Patterns

```php
// 1. Constants instead of magic numbers (in .module)
define('ENTITY_STATUS_DRAFT', 0);
define('ENTITY_STATUS_PUBLISHED', 1);

// 2. Getter methods instead of direct field access
public function getStatus(): int {
  return (int) $this->get('status')->value;
}

// 3. Safe migrations with backward compatibility
function [module]_update_XXXX() {
  $manager = \Drupal::entityDefinitionUpdateManager();
  $field = $manager->getFieldStorageDefinition('field_name', 'entity_type');
  if ($field) {
    $new_def = BaseFieldDefinition::create('field_type')->setSettings([...]);
    $manager->updateFieldStorageDefinition($new_def);
    drupal_flush_all_caches();
    \Drupal::logger('module')->info('Migration completed.');
  }
}
```

**Migration safety**: Backup DB, test on staging, ensure backward compatibility, log changes, have rollback plan.

### Drupal Best Practices

```php
// Database API - always use placeholders, never raw SQL
$query = \Drupal::database()->select('node_field_data', 'n')
  ->fields('n', ['nid', 'title'])->condition('status', 1)->range(0, 10);
$results = $query->execute()->fetchAll();

// Dependency Injection - avoid \Drupal:: static calls in classes
class MyService {
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}
}

// Caching - use tags and contexts
$build = [
  '#markup' => $content,
  '#cache' => ['tags' => ['node:' . $nid], 'contexts' => ['user'], 'max-age' => 3600],
];
\Drupal\Core\Cache\Cache::invalidateTags(['node:' . $nid]);
\Drupal::cache()->set($cid, $data, time() + 3600, ['my_module']);
```

```php
// Queue API - for heavy operations
$queue = \Drupal::queue('my_module_processor');
$queue->createItem(['data' => $data]);
// QueueWorker plugin: @QueueWorker(id="...", cron={"time"=60})

// Entity System - always use entity type manager
$storage = \Drupal::entityTypeManager()->getStorage('node');
$node = $storage->load($nid);
$query = $storage->getQuery()
  ->condition('type', 'article')->condition('status', 1)
  ->accessCheck(TRUE)->sort('created', 'DESC')->range(0, 10);
$nids = $query->execute();

// Form API - extend FormBase, implement getFormId(), buildForm(), validateForm(), submitForm()
$form['field'] = ['#type' => 'textfield', '#title' => $this->t('Name'), '#required' => TRUE];
$form_state->setErrorByName('field', $this->t('Error message.'));

// Translation - always use t() for user-facing strings
$this->t('Hello @name', ['@name' => $name]);

// Config API
$config = \Drupal::config('my_module.settings')->get('key');
\Drupal::configFactory()->getEditable('my_module.settings')->set('key', $value)->save();

// Permissions
user_role_grant_permissions($role_id, ['permission']);
user_role_revoke_permissions($role_id, ['permission']);
```

### Code Style

- Type declarations/hints required, PHPDoc for classes/methods
- Align `=>` in arrays, `=` in variable definitions
- Controllers: final classes, DI, keep thin
- Services: register in `services.yml`, single responsibility
- Logging: `\Drupal::logger('module')->notice('message')`
- Entity updates: always use update hooks in `.install`, maintain backward compatibility

## Directory Structure

**Key paths**: `/web/` (or `docroot/`), `/web/modules/custom/`, `/config/sync/`, `/web/sites/default/settings.php`, `/patches/`, `/tests/`

**Development paths**: routes → `routing.yml`, forms → `src/Form/`, entities → `src/Entity/`, permissions → `permissions.yml`, updates → `.install`

## Multilingual Configuration

```bash
# Setup
ddev drush pm:enable language locale content_translation config_translation
ddev drush language:add pl && ddev drush language:add es
ddev drush locale:check && ddev drush locale:update

# Enable content translation
ddev drush config:set language.content_settings.node.article third_party_settings.content_translation.enabled true
```

**Detection**: Configure at `/admin/config/regional/language/detection` - use URL prefix (`/en/`, `/pl/`) for SEO

**Custom entities**: Add `translatable = TRUE` to `@ContentEntityType`, use `->setTranslatable(TRUE)` on fields

**In code**: `$this->t('Hello @name', ['@name' => $name])` | **In Twig**: `{{ 'Hello'|trans }}`

**Common issues**: Missing translations → `locale:update`, content not translatable → check Language settings tab

## Configuration Management

```bash
ddev drush cex                    # Export config
ddev drush cim                    # Import config
ddev drush config:status          # Show differences
```





## Security

**Principles**: HTTPS required, sanitize input, use DB abstraction (no raw SQL), env vars for secrets, proper access checks

```bash
# Security updates
ddev drush pm:security
ddev composer update drupal/core-recommended --with-dependencies
ddev composer update --security-only

# Audit
ddev drush role:perm:list
ddev drush watchdog:show --severity=Error --count=100
```

**Hardening**: `chmod 444 settings.php`, `chmod 755 sites/default/files`, disable PHP in files dir

**Code**: Use placeholders in queries, `Html::escape()` for output, `$account->hasPermission()` for access, Form API for validation

## Headless/API-First Development

### JSON:API (Core)

```bash
ddev drush pm:enable jsonapi
# Optional: ddev composer require drupal/jsonapi_extras
```

**Endpoints**:
```
GET  /jsonapi/node/article                                    # List all
GET  /jsonapi/node/article/{uuid}?include=field_image,uid    # With relations
GET  /jsonapi/node/article?filter[status]=1&sort=-created&page[limit]=10
POST /jsonapi/node/article  (Content-Type: application/vnd.api+json, Authorization: Bearer {token})
```

### GraphQL

```bash
ddev composer require drupal/graphql drupal/graphql_compose
ddev drush pm:enable graphql graphql_compose
# Explorer at /admin/config/graphql
```

### Authentication (Simple OAuth)

```bash
ddev composer require drupal/simple_oauth && ddev drush pm:enable simple_oauth
openssl genrsa -out keys/private.key 2048 && openssl rsa -in keys/private.key -pubout -out keys/public.key
# POST /oauth/token with grant_type, client_id, client_secret, username, password
# Use: Authorization: Bearer {access_token}
```

### CORS (in services.yml)

```yaml
cors.config:
  enabled: true
  allowedOrigins: ['http://localhost:3000']
  allowedMethods: ['GET', 'POST', 'PATCH', 'DELETE', 'OPTIONS']
  allowedHeaders: ['*']
  supportsCredentials: true
```

### Architecture Patterns

- **Fully Decoupled**: Drupal API + React/Vue/Next.js frontend
- **Progressively Decoupled**: Drupal pages + JS framework for interactive components
- **Hybrid**: Mix of Drupal templates and API-driven sections

### API Best Practices

OAuth tokens (not basic auth), rate limiting, HTTPS, validate input, API documentation (`drupal/openapi`)

## SEO & Structured Data

### Core Modules

```bash
ddev composer require drupal/metatag drupal/pathauto drupal/simple_sitemap drupal/redirect drupal/schema_metatag
ddev drush pm:enable metatag metatag_open_graph metatag_twitter_cards pathauto simple_sitemap redirect schema_metatag
ddev drush simple-sitemap:generate    # Generate sitemap at /sitemap.xml
ddev drush pathauto:generate          # Generate URL aliases
```

### Schema.org & Open Graph

Configure at `/admin/config/search/metatag/global`:
- **Organization**: `@type: Organization`, name, url, logo, sameAs
- **Article**: `@type: Article`, headline `[node:title]`, datePublished, author, image
- **Open Graph**: og:title, og:description, og:image, og:url
- **Twitter Cards**: twitter:card `summary_large_image`, twitter:title, twitter:image

### Multilingual SEO

```bash
ddev composer require drupal/hreflang && ddev drush pm:enable hreflang
```
Twig: `{% for lang in languages %}<link rel="alternate" hreflang="{{ lang.id }}" href="..."/>{% endfor %}`

### Performance

CSS/JS aggregation, BigPipe, WebP images, lazy loading, responsive image styles

**Testing**: Google Rich Results Test, Facebook Sharing Debugger, PageSpeed Insights

### SEO Checklist

On-page: title tags (50-60 chars), meta descriptions (150-160), H1 unique, clean URLs, alt attributes
Technical: sitemap submitted, robots.txt, canonical URLs, Schema.org, HTTPS, Core Web Vitals
Multilingual: hreflang tags, language-specific sitemaps, canonical per language

## Frontend Development

**JS Aggregation Issues**: Missing `.libraries.yml` deps, wrong load order, `drupalSettings` unavailable → Add deps (`core/jquery`, `core/drupal`, `core/drupalSettings`, `core/once`), use `once()` not `.once()`, test with aggregation enabled

**CSS**: BEM naming, SCSS/SASS, organize by component, use SDC when applicable

<!--
THEME DISCOVERY FOR AI/LLM:
1. cat config/sync/system.theme.yml → active theme
2. cat web/themes/custom/[theme]/[theme].info.yml → config, base themes
3. find web/themes/custom/[theme] -name "*.twig" → templates
4. grep "function.*_preprocess" [theme].theme → preprocess hooks
5. ls components/ → SDC components
6. ddev drush sdc:list → list all components

Theme files: [theme].info.yml (definition), [theme].libraries.yml (assets), [theme].theme (hooks), templates/ (Twig), components/ (SDC)
-->

**Theme location**: `/web/themes/custom/markconroy/`

### Build Commands

```bash
cd web/themes/custom/markconroy
# Note: This theme does not use npm/gulp build process
# CSS and JS are managed directly in the theme
# Libraries defined in markconroy.libraries.yml
```

### Libraries (`markconroy.libraries.yml`)

```yaml
base:
  css: { theme: { css/base.css: {} } }
  js: { js/base.js: {} }
  dependencies: [core/drupal, core/jquery, core/drupalSettings, core/once]

course-signup-popup-form:
  js: { js/course-signup-popup-form.js: {} }

full-page:
  js: { js/full-page.js: {} }

course-signup-inline-form:
  js: { js/course-signup-inline-form.js: {} }
```

### Twig Templates

**Enable debugging** (`development.services.yml`): `twig.config: { debug: true, auto_reload: true, cache: false }`

**Naming**: `node--[type]--[view-mode].html.twig`, `paragraph--[type].html.twig`, `block--[type].html.twig`, `field--[name]--[entity].html.twig`

**Override**: Enable debug → view source for suggestions → copy from core/themes → place in templates/ → `ddev drush cr`

**Template directory structure**:
```
templates/
├── block/           # Block templates
├── content/         # Node templates
├── field/           # Field templates
├── form/            # Form element templates
├── layout/          # Layout templates
├── misc/            # Miscellaneous templates
├── navigation/      # Menu and navigation
├── paragraph/       # Paragraph templates
└── views/           # Views templates
```

### Preprocess Functions (`markconroy.theme`)

```php
function markconroy_preprocess_image_widget(array &$variables) {
  // Prevents image widget templates from rendering preview container HTML
  // to users without permission
  $data = &$variables['data'];
  if (isset($data['preview']['#access']) && $data['preview']['#access'] === FALSE) {
    unset($data['preview']);
  }
}

function markconroy_preprocess_page(array &$variables) {
  $variables['#attached']['library'][] = 'markconroy/course-signup-popup-form';
}

function markconroy_preprocess_node(array &$variables) {
  if ($variables['view_mode'] === 'full') {
    $variables['#attached']['library'][] = 'markconroy/full-page';
    if ($variables['node']->getType() === 'article') {
      $variables['#attached']['library'][] = 'markconroy/course-signup-inline-form';
    }
  }
}

function markconroy_theme_suggestions_user_alter(array &$suggestions, array $variables) {
  if (isset($variables['elements']['#view_mode'])) {
    $suggestions[] = 'user__' . $variables['elements']['#view_mode'];
  }
}
```

### Single Directory Components (SDC)

**Note**: This theme does not currently use SDC. All templates are in the traditional `templates/` directory structure.

### Troubleshooting

```bash
rm -rf node_modules package-lock.json && npm install   # Reset deps
rm -rf dist/ css/ js/compiled/                          # Clear build cache
```

**Performance**: Minify for prod, imagemin, critical CSS, font-display:swap, CSS/JS aggregation, AdvAgg module

## Environment Indicators

- **Visual verification**: Check indicators display correctly on all pages
- **Color scheme**: GREEN (Local), BLUE (DEV), ORANGE (STG), RED (PROD)
- **Never commit "LOCAL"** as value in `environment_indicator.indicator.yml` for production! Always use "PROD" and red color.

## Documentation

**MANDATORY**: Document work in "Tasks and Problems" section. Use real date (`date` command). Document: modules, fixes, config changes, optimizations, problems/solutions.

## Common Tasks

### New Module
```bash
# Create /web/modules/custom/[name]/ with:
# - [name].info.yml (name, type:module, core_version_requirement:^11, package:Custom)
# - [name].module (hooks), .routing.yml, .permissions.yml, .services.yml as needed
# Use 'markie_' prefix for project-specific modules
ddev drush pm:enable [name] && ddev drush cr
```

### Update Core
```bash
ddev export-db --file=backup.sql.gz                                    # Backup
ddev composer update drupal/core-recommended drupal/core-composer-scaffold --with-dependencies
ddev drush updb && ddev drush cr                                       # Updates + cache
```

### Database Migration
```php
// In [module].install
function [module]_update_10001() {
  // Use EntityDefinitionUpdateManager for field changes
  // Check field exists, update displays, log completion
  drupal_flush_all_caches();
}
```

### Tests
```bash
ddev exec vendor/bin/phpunit web/modules/custom/[module]/tests   # PHPUnit
ddev exec vendor/bin/codecept run                                 # Codeception
# Dirs: tests/src/Unit/, Kernel/, Functional/; tests/acceptance/
```

### Permissions
```bash
ddev drush role:perm:list [role]                                  # List
# PHP: user_role_grant_permissions($role_id, ['perm1']); drupal_flush_all_caches();
```

## Troubleshooting

### Quick Fixes
```bash
ddev drush cr                                                     # Clear cache
ddev restart                                                      # Restart containers
ddev xdebug on|off                                               # Debug mode
ddev drush watchdog:show --count=50                              # Check logs
```

### Cache Not Clearing
```bash
ddev drush cr                                                     # Standard
rm -rf web/sites/default/files/php/twig/* && ddev drush cr       # Twig
ddev drush sql:query "TRUNCATE cache_render;" && ddev drush cr   # Nuclear
```

### Database Issues
```bash
ddev drush sql:cli                     # Check connection (SELECT 1;)
ddev drush updb && ddev drush entity:updates   # Pending updates
ddev mysql -e "REPAIR TABLE [name];"   # Repair table
```

### DDEV Issues
```bash
ddev restart                           # Soft restart
ddev stop && ddev start                # Full restart
ddev delete -O && ddev start           # Recreate containers
ddev logs                              # View logs
```

### Module Installation
```bash
ddev composer why-not drupal/[module]  # Check deps
ddev composer require drupal/[module] && ddev drush pm:enable [module]
ddev drush updb && ddev drush entity:updates   # Schema issues
```

### Permissions
```bash
ddev exec chmod -R 775 web/sites/default/files
ddev exec chmod 444 web/sites/default/settings.php
```

### WSOD (White Screen)
```bash
ddev drush config:set system.logging error_level verbose
ddev logs && ddev drush watchdog:show --count=50
ddev exec tail -f /var/log/php/php-fpm.log    # Check fatal errors
```

### Config Import Fails
```bash
ddev drush config:status              # Check status
ddev drush config:set system.site uuid [correct-uuid]  # UUID mismatch
```

### Memory Issues
```bash
echo "memory_limit = 512M" >> .ddev/php/php.ini && ddev restart
# Or: ddev exec php -d memory_limit=1G vendor/bin/drush [cmd]
```

## Additional Resources

- **Project Documentation**: `.cursor/TASKS_AND_PROBLEMS.md`
- **Drupal Documentation**: https://www.drupal.org/docs
- **DDEV Documentation**: https://ddev.readthedocs.io/

---

<!--
===========================================
PROJECT-SPECIFIC SECTIONS BELOW
Add sections specific to your project here
===========================================
-->

## Custom Modules

### markie Module

**Location**: `/web/modules/custom/markie/`
**Purpose**: Small hooks and utility functions for site-specific customizations

**Key functions**:
```php
// markie_preprocess_media() - Adds data attributes to media elements
// - data-media-source (media type)
// - data-media-id (media entity ID)
// - data-media-source-file-id (source field value)

// markie_preprocess_user() - Security: blocks anonymous access to user profiles
// - Prevents anonymous users from viewing user profile pages
// - Returns 403 on entity.user.canonical route for anonymous users
```

**When to modify**: Add site-specific preprocess hooks, custom logic that doesn't fit elsewhere

## Theme-Specific Features

### Custom Libraries & Forms

**Course Signup Forms** (external eomail5.com integration):

1. **Popup Form** (`course-signup-popup-form`)
   - Loaded on all pages via `markconroy_preprocess_page()`
   - External JS: `https://eomail5.com/form/2acee644-8334-11f0-b9ec-595e60d01c50.js`
   - Async loading for performance

2. **Inline Form** (`course-signup-inline-form`)
   - Loaded only on Article nodes in full view mode
   - Custom styling in `css/components/course-form.css`
   - External JS: `https://eomail5.com/form/02e55ac2-7205-11f0-afe3-6f416b030533.js`

**View Mode Libraries**:
- `full-page` - Loaded on all full view mode content
- `teaser` - Teaser/card display styles
- `homepage` - Homepage-specific styles

### Template Organization

Custom template directories in `/web/themes/custom/markconroy/templates/`:
- `content-edit/` - Edit mode templates
- `dataset/` - Dataset/data visualization templates
- Standard dirs: `block/`, `content/`, `field/`, `form/`, `layout/`, `navigation/`, `user/`, `views/`

## Platform & Deployment

### Platform.sh Integration

**Module**: `platformsh/config-reader` (installed)
**Commands**:
```bash
ddev pull platform -y           # Pull DB and files from Platform.sh
ddev push platform              # Push code to Platform.sh
```

**Workflow**: Develop locally with DDEV → push to Platform.sh staging → deploy to production

### Environment Variables

**System**: Uses `vlucas/phpdotenv` via `load.environment.php`
**Pattern**: Auto-loads `.env` file at project root (gitignored)
**Usage**: Environment vars available via `$_ENV[]`, `getenv()`, `$_SERVER[]`

**Example `.env` entries**:
```bash
OPENAI_API_KEY=sk-...
PLATFORM_PROJECT_ID=...
```

**Location**: Create `.env` in project root (see `.env.example` if exists)

## Installed Helper Modules

### Development & Content

**Stage File Proxy** (`drupal/stage_file_proxy`)
- Proxies missing files from production to local/staging environments
- Prevents need to sync large files directory
- Configure at `/admin/config/system/stage_file_proxy`

**Twig Tweak** (`drupal/twig_tweak`)
- Helper functions in Twig templates
- Usage: `{{ drupal_view('view_name') }}`, `{{ drupal_field('field_name', 'node', 1) }}`
- Docs: https://www.drupal.org/docs/contributed-modules/twig-tweak

**Highlight.js** (`drupal/highlight_js`)
- Syntax highlighting for code blocks in content
- Relevant for technical blog posts
- Auto-detects language or use language hints

### Media & Images

**Image Widget Crop** (`drupal/image_widget_crop` + `drupal/crop`)
- Custom image cropping per image style
- UI in media/image fields for manual cropping
- Configure crop types at `/admin/config/media/crop`

### Backup & Maintenance

**Backup Migrate** (`drupal/backup_migrate`)
- Database and file backups
- **Scheduled**: Daily backups configured (see `config/sync/backup_migrate.backup_migrate_schedule.daily_schedule.yml`)
- Sources: Default DB, entire site, public/private files
- Destination: Private files
- UI: `/admin/config/development/backup_migrate`

```bash
# Manual backup via Drush
ddev drush bam:backup default_db private_files

# Restore backup
ddev drush bam:restore private_files [backup-id]
```

### Privacy & Compliance

**Klaro Cookie Consent** (`drupal/klaro`)
- GDPR-compliant cookie consent management
- Configure services at `/admin/config/system/klaro`
- Manages consent for analytics, marketing, external embeds
- Docs: https://www.drupal.org/docs/contributed-modules/klaro

## Speaking Content Type Workflow

**Use Case**: Document conference talks, presentations, and speaking engagements

**Fields**:
- `body` - Presentation description/notes
- `field_speaking_conference` - Reference to Conference taxonomy
- `field_speaking_date` - Date of presentation
- `field_speaking_where` - Location/venue

**Taxonomy**: Conference vocabulary for categorizing talks by event

**Display**: Custom templates in `templates/content/` for speaking nodes

<!--
===========================================
HOW TO DISCOVER FULL ENTITY STRUCTURE - GUIDE FOR AI/LLM
===========================================

Use this guide to discover the complete entity structure of a Drupal project.
Priority order: 1) Config YAML files (most complete), 2) Drush commands, 3) PHP evaluation

### 1. CONFIG YAML FILES (Primary Source)

Default config directory: `./config/sync/` (see "Directory Structure" section for verification commands and multisite paths)

**Entity Type Config File Patterns:**

| Entity Type | Config File Pattern | Example |
|-------------|---------------------|---------|
| Content Types | `node.type.*.yml` | `node.type.article.yml` |
| Field Storage | `field.storage.*.yml` | `field.storage.node.field_image.yml` |
| Field Instance | `field.field.*.yml` | `field.field.node.article.field_image.yml` |
| Paragraph Types | `paragraphs.paragraphs_type.*.yml` | `paragraphs.paragraphs_type.text.yml` |
| Media Types | `media.type.*.yml` | `media.type.image.yml` |
| Taxonomy Vocabularies | `taxonomy.vocabulary.*.yml` | `taxonomy.vocabulary.tags.yml` |
| View Modes | `core.entity_view_mode.*.yml` | `core.entity_view_mode.node.teaser.yml` |
| Form Modes | `core.entity_form_mode.*.yml` | `core.entity_form_mode.node.default.yml` |
| View Display | `core.entity_view_display.*.yml` | `core.entity_view_display.node.article.default.yml` |
| Form Display | `core.entity_form_display.*.yml` | `core.entity_form_display.node.article.default.yml` |

**Commands to list config files:**
```bash
# List all content type configs
ls config/sync/node.type.*.yml

# List all field storage configs
ls config/sync/field.storage.*.yml

# List all paragraph type configs
ls config/sync/paragraphs.paragraphs_type.*.yml

# Read specific config file
cat config/sync/node.type.article.yml
```

### 2. DRUSH COMMANDS

```bash
# List all entity types
ddev drush entity:info

# List bundles for entity type
ddev drush entity:bundle-info node
ddev drush entity:bundle-info paragraph
ddev drush entity:bundle-info media
ddev drush entity:bundle-info taxonomy_term

# List fields for entity type and bundle
ddev drush field:list node article
ddev drush field:list paragraph text

# Get field info
ddev drush field:info node article field_image

# Export all config
ddev drush config:export

# Get specific config
ddev drush config:get node.type.article

# List all config
ddev drush config:list | grep node.type
```

### 3. PHP/DRUSH PHP:EVAL

For programmatic access to field definitions:

```bash
# Get all fields for content type
ddev drush php:eval "
\$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions('node', 'article');
foreach (\$fields as \$name => \$field) {
  echo \$name . ' - ' . \$field->getLabel() . ' (' . \$field->getType() . ')' . PHP_EOL;
}
"

# Get field settings
ddev drush php:eval "
\$field = \Drupal\field\Entity\FieldConfig::loadByName('node', 'article', 'field_image');
print_r(\$field->getSettings());
"

# Get all bundles for entity type
ddev drush php:eval "
\$bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');
foreach (\$bundles as \$id => \$info) {
  echo \$id . ' - ' . \$info['label'] . PHP_EOL;
}
"

# Export full entity structure as JSON
ddev drush php:eval "
\$entity_types = ['node', 'paragraph', 'media', 'taxonomy_term'];
\$result = [];
foreach (\$entity_types as \$entity_type) {
  \$bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo(\$entity_type);
  foreach (\$bundles as \$bundle_id => \$bundle_info) {
    \$fields = \Drupal::service('entity_field.manager')->getFieldDefinitions(\$entity_type, \$bundle_id);
    \$field_list = [];
    foreach (\$fields as \$name => \$field) {
      \$field_list[\$name] = [
        'label' => (string) \$field->getLabel(),
        'type' => \$field->getType(),
        'required' => \$field->isRequired(),
      ];
    }
    \$result[\$entity_type][\$bundle_id] = [
      'label' => \$bundle_info['label'],
      'fields' => \$field_list,
    ];
  }
}
echo json_encode(\$result, JSON_PRETTY_PRINT);
"
```

### RECOMMENDED WORKFLOW

1. **First**: Check if `config/sync/` directory exists and list YAML files
2. **Second**: Use `ddev drush entity:bundle-info [type]` for quick overview
3. **Third**: Use `ddev drush field:list [type] [bundle]` for field details
4. **Fourth**: Use `php:eval` for complex queries or full export

-->

## Drupal Entities Structure

Complete reference of content types, media types, taxonomies, and custom entities. See "HOW TO DISCOVER FULL ENTITY STRUCTURE" guide above for discovery commands.

### Content Types (Node Bundles)

```toon
content_types[3]{machine_name,label,description,features,key_fields}:
  article,Article,"Time-sensitive content like news, press releases or blog posts","revisions,menu_ui","body,field_intro,field_tags,field_main_image,field_main_video"
  page,Basic Page,"Static content such as About us page","revisions,menu_ui","body,field_intro,field_main_image"
  speaking,Speaking,"Presentations and conference talks","revisions","body,field_speaking_conference,field_speaking_date,field_speaking_where"
```

### Paragraph Types

**Note**: This project does not use the Paragraphs module.

### Media Types

```toon
media_types[2]{machine_name,label,source,source_field}:
  image,Image,image,field_m_image_image
  remote_video,Remote Video,oembed:video,field_media_oembed_video
```

### Taxonomy Vocabularies

```toon
taxonomies[3]{machine_name,label,description,hierarchy}:
  tags,Tags,Content tagging,false
  media_tags,Media Tags,Media content tagging,false
  conference,Conference,Conference and event categorization,false
```

### Custom Entities

**Note**: This project does not use custom content entities. All content uses standard Drupal node bundles.

### Entity Relationships

- **Article** → **Tags** (many-to-many via `field_tags`)
- **Article** → **Author** (many-to-one via `uid`)
- **Article** → **Media** (one-to-one via `field_main_image`, `field_main_video`)
- **Speaking** → **Conference** (many-to-one via `field_speaking_conference`)
- **Media** → **Media Tags** (many-to-many via `field_media_tags`)

### Field Patterns

**Common field naming patterns in this project**:

- `field_[name]` - Standard field prefix (e.g., `field_intro`, `field_tags`, `field_main_image`)
- `field_speaking_[name]` - Speaking content type fields
- `field_m_[name]` - Media-specific fields (e.g., `field_m_image_image`, `field_m_tweet_link`)
- Base fields: `title`, `body`, `created`, `changed`, `uid`, `status`

**Key Field Types**:
- Reference fields: `entity_reference`, `entity_reference_revisions`
- Text fields: `string`, `text_long`, `text_with_summary`
- Date fields: `datetime`, `daterange`, `timestamp`
- Media: `image`, `file`
- Structured: `link`, `address`, `telephone`

### View Modes

**Node View Modes**:
- `full` - Full content display (triggers custom libraries in theme)
- `teaser` - Summary/card display
- `search_result` - Search results display

**Media View Modes**:
- `full` - Full media display
- `media_library` - Media library thumbnail

### Entity Constants

<!--
Document entity status values and other constants used in the project.
-->

```php
// Example: Content workflow states
define('ENTITY_STATUS_DRAFT', 0);
define('ENTITY_STATUS_PUBLISHED', 1);
define('ENTITY_STATUS_ARCHIVED', 2);

// Custom entity states
define('CUSTOM_ENTITY_PENDING', 0);
define('CUSTOM_ENTITY_APPROVED', 1);
define('CUSTOM_ENTITY_REJECTED', 2);
```

### Entity Access Patterns

- View: `access content` | Edit own: `edit own [type] content` | Delete own: `delete own [type] content` | Admin: `administer [type] content`

### Migration Patterns

If project uses migrations, document source to destination mappings:

```yaml
# Example migration mapping
source:
  entity_type: legacy_node
  bundle: legacy_article

destination:
  entity_type: node
  bundle: article

field_mapping:
  legacy_title → title
  legacy_body → body
  legacy_image → field_image
  legacy_category → field_category
```

## Project-Specific Features

<!--
Add documentation for project-specific features here.
Examples:
- Custom entity workflows
- Integration with external services
- PDF generation
- Email notifications
- API endpoints
-->

## Development Workflow

- Document all significant changes in "Tasks and Problems" section below
- Follow the format and examples provided
- Review existing entries before making architectural changes
- Always run `date` command to get current date before adding entries

---

## Tasks and Problems Log

**Format**: `YYYY-MM-DD | [TYPE] Description` — Types: TASK, PROBLEM/SOLUTION, CONFIG, PERF, SECURITY, NOTE

Run `date` first. Add new entries at top. Include file paths, module names, config keys.

```
[Add entries here - newest first]

Examples:
2024-01-15 | TASK: Created custom module d_custom_feature for special workflow
2024-01-15 | PROBLEM: Config import failing with UUID mismatch
          | SOLUTION: drush config:set system.site uuid [correct-uuid]
2024-01-14 | CONFIG: Enabled Redis cache backend in settings.php
2024-01-14 | PERF: Enabled CSS/JS aggregation and AdvAgg module
2024-01-13 | SECURITY: Applied security update for Drupal core 10.1.8
2024-01-13 | NOTE: Custom entity queries must include ->accessCheck(TRUE/FALSE)
```

