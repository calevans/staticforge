<?php

namespace EICC\StaticForge\Tests\Unit\Services\Upload;

use EICC\StaticForge\Services\Upload\UploadCheckService;
use PHPUnit\Framework\TestCase;

class UploadCheckServiceTest extends TestCase
{
    private UploadCheckService $service;

    protected function setUp(): void
    {
        $this->service = new UploadCheckService();
    }

    public function testHashCalculationForBinaryFiles()
    {
        $content = random_bytes(1024);
        $file = sys_get_temp_dir() . '/test.bin';
        file_put_contents($file, $content);

        $hash = $this->service->calculateHash($file);

        $this->assertEquals(md5($content), $hash);
        unlink($file);
    }

    public function testHashCalculationIgnoresSfcbInHtml()
    {
        $content1 = '<html><link href="style.css?sfcb=1234567890"></html>';
        $content2 = '<html><link href="style.css?sfcb=0987654321"></html>';

        $file1 = sys_get_temp_dir() . '/test1.html';
        $file2 = sys_get_temp_dir() . '/test2.html';

        file_put_contents($file1, $content1);
        file_put_contents($file2, $content2);

        $hash1 = $this->service->calculateHash($file1);
        $hash2 = $this->service->calculateHash($file2);

        $this->assertEquals($hash1, $hash2);

        unlink($file1);
        unlink($file2);
    }

    public function testHashCalculationRespectsOtherParams()
    {
        $content1 = '<html><link href="style.css?other=123&sfcb=111"></html>';
        $content2 = '<html><link href="style.css?other=456&sfcb=111"></html>';

        $file1 = sys_get_temp_dir() . '/test1.html';
        $file2 = sys_get_temp_dir() . '/test2.html';

        file_put_contents($file1, $content1);
        file_put_contents($file2, $content2);

        $hash1 = $this->service->calculateHash($file1);
        $hash2 = $this->service->calculateHash($file2);

        $this->assertNotEquals($hash1, $hash2);

        unlink($file1);
        unlink($file2);
    }

    public function testHashCalculationHandlesMixedParams()
    {
        // sfcb is stripped, leaving 'a=1' in both cases ideally, or at least handled consistently.
        // 'file.css?a=1&sfcb=2' -> 'file.css?a=1'
        // 'file.css?sfcb=2&a=1' -> 'file.css?a=1' (if regex covers both leading/trailing &)

        // Simpler case: verify sfcb removal doesn't break other params
        $content1 = 'url("image.png?v=1&sfcb=123")';
        $content2 = 'url("image.png?v=1&sfcb=456")';

        $file1 = sys_get_temp_dir() . '/test_mixed1.css';
        $file2 = sys_get_temp_dir() . '/test_mixed2.css';

        file_put_contents($file1, $content1);
        file_put_contents($file2, $content2);

        $hash1 = $this->service->calculateHash($file1);
        $hash2 = $this->service->calculateHash($file2);

        $this->assertEquals($hash1, $hash2);

        unlink($file1);
        unlink($file2);
    }
}
