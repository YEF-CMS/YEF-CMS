<?php

declare(strict_types=1);

namespace YefCms\TextPages;

use InvalidArgumentException;
use Override;
use Yef\Domain\ContentType\FieldKind;
use Yef\Domain\ContentType\FormField;
use Yef\Domain\ContentType\PageForm;
use Yef\Domain\ContentType\Payload;
use Yef\Domain\Text\Texts;

/**
 * The form of a text page: one field for the content.
 */
final readonly class TextPageForm implements PageForm
{
    public function __construct(private Texts $texts) {}

    /**
     * @return list<FormField>
     */
    #[Override]
    public function fields(): array
    {
        return [new FormField('body', FieldKind::RichText, $this->texts->t('field.body'))];
    }

    #[Override]
    public function valuesOf(Payload $payload): array
    {
        if (!$payload instanceof TextPayload) {
            throw new InvalidArgumentException('Not a text page payload.');
        }

        return ['body' => $payload->html];
    }
}
