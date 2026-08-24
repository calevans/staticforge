<?php

declare(strict_types=1);

namespace EICC\StaticForge\Tests\Unit\Core;

use EICC\StaticForge\Core\PathGuard;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class PathGuardTest extends TestCase
{
    public function testResolveInsideAllowsPathInsideRoot(): void
    {
        $this->assertSame(
            '/content/sub/file.txt',
            PathGuard::resolveInside('/content/sub/file.txt', '/content')
        );
    }

    public function testResolveInsideAllowsRootItself(): void
    {
        $this->assertSame('/content', PathGuard::resolveInside('/content', '/content'));
    }

    public function testResolveInsideAllowsNonExistentWriteTarget(): void
    {
        // No file needs to exist on disk — this is pure path arithmetic.
        $this->assertSame(
            '/public/blog/new-post.html',
            PathGuard::resolveInside('/public/blog/new-post.html', '/public')
        );
    }

    public function testResolveInsideRejectsSiblingDirectoryWithSharedPrefix(): void
    {
        // This is the exact bug PathGuard fixes: naive strpos($path, $root) === 0
        // would let "/content-evil/secret.txt" match a root of "/content".
        $this->expectException(RuntimeException::class);
        PathGuard::resolveInside('/content-evil/secret.txt', '/content');
    }

    public function testResolveInsideRejectsDotDotEscape(): void
    {
        $this->expectException(RuntimeException::class);
        PathGuard::resolveInside('/content/sub/../../etc/passwd', '/content');
    }

    public function testResolveInsideCollapsesDotDotThatStaysInsideRoot(): void
    {
        $this->assertSame(
            '/content/sibling.md',
            PathGuard::resolveInside('/content/sub/../sibling.md', '/content')
        );
    }

    public function testResolveInsidePassesThroughVfsPaths(): void
    {
        $this->assertSame('vfs://root/file.txt', PathGuard::resolveInside('vfs://root/file.txt', '/content'));
    }

    public function testRelativeToReturnsRelativePathInsideRoot(): void
    {
        $this->assertSame('sub/file.txt', PathGuard::relativeTo('/content/sub/file.txt', '/content'));
    }

    public function testRelativeToReturnsEmptyStringForRootItself(): void
    {
        $this->assertSame('', PathGuard::relativeTo('/content', '/content'));
    }

    public function testRelativeToReturnsNullForSiblingDirectoryWithSharedPrefix(): void
    {
        $this->assertNull(PathGuard::relativeTo('/content-evil/secret.txt', '/content'));
    }

    public function testRelativeToReturnsNullForUnrelatedPath(): void
    {
        $this->assertNull(PathGuard::relativeTo('/elsewhere/post.md', '/content'));
    }

    public function testRelativeToWorksForNestedVfsPaths(): void
    {
        // relativeTo() does real path arithmetic even for vfs:// paths — it
        // is not a jail-enforcement bypass like resolveInside()'s vfs://
        // passthrough. A test fixture nested two levels deep must still
        // resolve its full relative path, not silently fall back to just
        // the basename.
        $this->assertSame(
            'sub/deeper/file.md',
            PathGuard::relativeTo('vfs://test/content/sub/deeper/file.md', 'vfs://test/content')
        );
    }

    public function testRelativeToReturnsNullForVfsPathOutsideRoot(): void
    {
        $this->assertNull(PathGuard::relativeTo('vfs://test/content-evil/secret.md', 'vfs://test/content'));
    }
}
