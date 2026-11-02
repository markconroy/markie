<?php

namespace OpenAI\Testing\Responses\Fixtures\Skills\Versions;

final class SkillVersionDeleteResponseFixture
{
    public const ATTRIBUTES = [
        'id' => 'skillver_abc123',
        'object' => 'skill.version.deleted',
        'deleted' => true,
        'version' => '1',
    ];
}
