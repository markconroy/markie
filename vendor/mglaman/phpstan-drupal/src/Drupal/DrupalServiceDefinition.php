<?php declare(strict_types=1);

namespace PHPStan\Drupal;

class DrupalServiceDefinition
{

    /**
     * @var string
     */
    private $id;

    /**
     * @var string|null
     */
    private $class;

    /**
     * @var bool
     */
    private $public;

    /**
     * @var bool
     */
    private $deprecated = false;

    /**
     * @var string|null
     */
    private $deprecationTemplate;

    /**
     * @var string
     */
    private static $defaultDeprecationTemplate = 'The "%service_id%" service is deprecated. You should stop using it, as it will soon be removed.';

    /**
     * @var string|null
     */
    private $alias;

    public function __construct(string $id, ?string $class, bool $public = true, ?string $alias = null)
    {
        $this->id = $id;
        $this->class = $class;
        $this->public = $public;
        $this->alias = $alias;
    }

    public function setDeprecated(bool $status = true, ?string $template = null): void
    {
        $this->deprecated = $status;
        $this->deprecationTemplate = $template;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getClass(): ?string
    {
        return $this->class;
    }

    /**
     * @return bool
     */
    public function isPublic(): bool
    {
        return $this->public;
    }

    /**
     * @return string|null
     */
    public function getAlias(): ?string
    {
        return $this->alias;
    }

    public function isDeprecated(): bool
    {
        return $this->deprecated;
    }

    public function getDeprecatedDescription(): string
    {
        return str_replace('%service_id%', $this->id, $this->deprecationTemplate ?? self::$defaultDeprecationTemplate);
    }
}
