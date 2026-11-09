<?php

declare(strict_types=1);

namespace YefCms\TextPages;

use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\ContentTypeRegistry;

/**
 * The content type of the text pages module; it also shows what a module has to provide.
 */
final class TextContentType
{
    public const string ID = 'text';

    public static function definition(): ContentTypeDefinition
    {
        return new ContentTypeDefinition(
            new ContentTypeId(self::ID),
            'Text page',
            new TextPageForm(),
            new TextPayloadFactory(),
            new TextPayloadSerializer(),
            new TextContentRenderer(),
        );
    }

    public static function registry(): ContentTypeRegistry
    {
        return new ContentTypeRegistry([self::definition()]);
    }
}
