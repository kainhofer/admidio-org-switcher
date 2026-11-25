<?php
namespace AdmidioPlugin\OrgSwitcher\Tests\Unit;

use Admidio\Infrastructure\Plugins\Plugin;
use PHPUnit\Framework\TestCase;

final class ManifestTest extends TestCase
{
    public function testManifestIsValid(): void
    {
        $plugin = Plugin::read(dirname(__DIR__, 2));

        $this->assertTrue($plugin->isValid(), (string)$plugin->error);
        $this->assertSame('org-switcher', $plugin->id);
        $this->assertContains('index.php', $plugin->getPages());
    }

    public function testLanguageFilesAreCompleteAndSorted(): void
    {
        $root = dirname(__DIR__, 2);
        $keys = array();
        foreach (array('en', 'de', 'de-DE') as $language) {
            preg_match_all('/<string name="([A-Z0-9_]+)"/', (string)file_get_contents($root . '/languages/' . $language . '.xml'), $matches);
            $keys[$language] = $matches[1];
        }
        $this->assertSame($keys['en'], $keys['de']);
        $this->assertSame($keys['en'], $keys['de-DE']);
        $sorted = $keys['en'];
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $keys['en']);

        $used = array();
        foreach (array('src', 'modules', 'templates') as $directory) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $directory, \FilesystemIterator::SKIP_DOTS)) as $file) {
                preg_match_all('/PLG_ORG_SWITCHER_[A-Z0-9_]*[A-Z0-9]/', (string)file_get_contents($file->getPathname()), $matches);
                $used = array_merge($used, $matches[0]);
            }
        }
        $this->assertSame(array(), array_values(array_diff(array_unique($used), $keys['en'])));
    }
}
