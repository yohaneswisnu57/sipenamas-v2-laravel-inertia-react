<?php

namespace Tests\Unit;

use App\Services\Proposal\DocxToPdfConverter;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DocxToPdfConverterTest extends TestCase
{
    public function test_throws_when_libreoffice_binary_is_missing(): void
    {
        $this->expectException(RuntimeException::class);

        (new DocxToPdfConverter('binary-tidak-ada-xyz'))->convert(sys_get_temp_dir().'/x.docx');
    }
}
