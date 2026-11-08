<?php

declare(strict_types=1);

namespace Yef\Tests\Domain\ContentType;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Yef\Domain\ContentType\ContentTypeId;
use Yef\Domain\ContentType\PayloadData;
use Yef\Domain\ContentType\UnavailablePayload;
use Yef\Domain\Page\Exception\PageDataCorrupted;
use Yef\Domain\Page\PageId;

#[CoversClass(UnavailablePayload::class)]
#[CoversClass(PageDataCorrupted::class)]
final class UnavailablePayloadTest extends TestCase
{
    public function testKeepsTypeAndDataUntouched(): void
    {
        $type = new ContentTypeId('gallery');
        $data = new PayloadData(['photos' => '[]']);

        $payload = new UnavailablePayload($type, $data);

        $this->assertSame($type, $payload->type);
        $this->assertSame($data, $payload->data);
    }

    public function testCorruptionMessageNamesThePageOnly(): void
    {
        $exception = PageDataCorrupted::forPage(new PageId('01ARZ3NDEKTSV4RRFFQ69G5001'));

        $this->assertSame('Stored data of page "01ARZ3NDEKTSV4RRFFQ69G5001" is corrupted.', $exception->getMessage());
    }
}
