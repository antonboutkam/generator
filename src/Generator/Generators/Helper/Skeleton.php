<?php/*** @unfixed**/

namespace Generator\Helper\Skeleton;

use Core\DataType\Path;
use Core\InlineTemplate;
use Core\Utils;
use DirectoryIterator;
use Hi\Helpers\DirectoryStructure;

final class Skeleton
{
    public static function copyParseStructure(Path $sSourceDirectory, Path $oDestinationDirectory, $aData): void
    {
        $oSourceFileDirectoryIterator = new DirectoryIterator($sSourceDirectory);

        foreach ($oSourceFileDirectoryIterator as $oSourceItem) {
            if ($oSourceItem->isDot()) {
                continue;
            }

            $sDestinationFilename = Utils::makePath($oDestinationDirectory, $oSourceItem->getBasename());
            if ($oSourceItem->isDir()) {
                echo "Creating directory $sDestinationFilename " . PHP_EOL;
                Utils::makeDir($sDestinationFilename);
                self::copyParseStructure(new Path($oSourceItem->getPathname()), new Path($sDestinationFilename), $aData);
                chmod($sDestinationFilename, 0777);
            } else {
                if ($oSourceItem->isFile()) {
                    echo "Creating file $sDestinationFilename " . PHP_EOL;
                    $sTemplateHtml = file_get_contents($oSourceItem->getPathname());

                    // Skip parsing binary files
                    if (ctype_print($sTemplateHtml)) {
                        $sTemplateHtml = InlineTemplate::parse($sTemplateHtml, $aData);
                    }
                    Utils::makeDir($oDestinationDirectory);
                    Utils::filePutContents($sDestinationFilename, $sTemplateHtml, 0777);
                }
            }
        }
    }

    public static function parseTemplate(?string $sFolder, string $sFile, $aVars): string
    {
        $oDirectoryStructure = new DirectoryStructure();
        if ($sFolder) {
            $sSkeletonFile = Utils::makePath($oDirectoryStructure->getSystemDir(true), 'build', '_skel', $sFolder, "$sFile.twig");
        } else {
            $sSkeletonFile = Utils::makePath($oDirectoryStructure->getSystemDir(true), 'build', '_skel', "$sFile.twig");
        }

        $sTemplateHtml = file_get_contents($sSkeletonFile);
        return InlineTemplate::parse($sTemplateHtml, $aVars);
    }
}