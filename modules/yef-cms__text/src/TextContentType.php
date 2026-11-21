<?php

declare(strict_types=1);

namespace YefCms\TextPages;

use Yef\Domain\ContentType\ContentTypeDefinition;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\ContentTypeRegistry;
use Yef\Domain\Text\Texts;

/**
 * The content type of the text pages module; it also shows what a module has to provide.
 */
final class TextContentType
{
    public const string ID = 'text';

    /**
     * @param Texts $texts The texts of this module, see `messages/` in the directory of the module.
     */
    public static function definition(Texts $texts): ContentTypeDefinition
    {
        return new ContentTypeDefinition(
            new ContentTypeId(self::ID),
            $texts->t('type.title'),
            new TextPageForm($texts),
            new TextPayloadFactory($texts),
            new TextPayloadSerializer($texts),
            new TextContentRenderer(),
        );
    }

    public static function registry(Texts $texts): ContentTypeRegistry
    {
        return new ContentTypeRegistry([self::definition($texts)]);
    }
}
