<?php

namespace EICC\StaticForge\Services\Upload;

class UploadCheckService
{
    private const TEXT_EXTENSIONS = ['html', 'css', 'js', 'json', 'xml', 'txt', 'md', 'rss', 'atom', 'svg'];

    public function calculateHash(string $filePath): string
    {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (in_array($ext, self::TEXT_EXTENSIONS)) {
            $content = file_get_contents($filePath);
            if ($content === false) {
                return '';
            }

            // Normalize sfcb parameters to ensure timestamp changes don't affect hash
            // We replace the specific timestamp digits with a constant placeholder
            $content = preg_replace('/sfcb=\d+/', 'sfcb=IGNORED', $content);

            return md5($content);
        }

        return md5_file($filePath) ?: '';
    }
}
