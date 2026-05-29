<?php
// plugins/system/blocks/src/Helper/BlocksHelper.php

declare(strict_types=1);

namespace NPEU\Plugin\System\Blocks\Helper;

\defined('_JEXEC') or die;

use RuntimeException;

class BlocksHelper
{
    // where icons live (change as needed)
    public static function getIconsFolder(): string
    {
        return JPATH_ROOT . '/images/icons';
    }

    public static function getIdPrefix(): string
    {
        return 'blocksicon-';
    }

    /**
     * Return a list of available icon names (filenames without .svg).
     *
     * Intended for building select options in custom fields.
     *
     * @param  string|null $iconsFolder
     * @return string[]    Array of icon names
     */
    public static function getIconNames(?string $iconsFolder = null): array
    {
        $iconsFolder = $iconsFolder ?? self::getIconsFolder();

        if (!is_dir($iconsFolder) || !is_readable($iconsFolder))
        {
            return [];
        }

        $files = glob($iconsFolder . '/*.svg') ?: [];
        $files = array_filter($files, 'is_readable');

        $icons = [];

        foreach ($files as $file)
        {
            $name = pathinfo($file, PATHINFO_FILENAME);

            // Normalise name the same way symbol IDs are built
            $name = preg_replace('/[^a-z0-9\-_]/i', '-', $name);

            if ($name !== '')
            {
                $icons[] = $name;
            }
        }

        sort($icons, SORT_NATURAL | SORT_FLAG_CASE);

        return $icons;
    }


    /**
     * Build a sprite from the SVG files found in the icons folder.
     *
     * Conservative sanitisation:
     *  - skips files containing <script or on...= attributes
     *  - rejects files containing <foreignObject>
     *
     * @param  string|null $iconsFolder
     * @param  string|null $prefix
     * @return string       The sprite markup (SVG) or empty string on failure
     */
    public static function buildSpriteFromFolder(?string $iconsFolder = null, ?string $prefix = null): string
    {
        $iconsFolder = $iconsFolder ?? self::getIconsFolder();
        $prefix = $prefix ?? self::getIdPrefix();

        if (!is_dir($iconsFolder) || !is_readable($iconsFolder))
        {
            return '';
        }

        // Use glob to find .svg files (full paths)
        $files = glob($iconsFolder . '/*.svg') ?: [];
        // Optionally filter unreadable files
        $files = array_filter($files, 'is_readable');

        if (empty($files))
        {
            return '';
        }

        $symbols = [];

        foreach ($files as $file)
        {
            $basename = pathinfo($file, PATHINFO_FILENAME);

            $svg = @file_get_contents($file);
            if ($svg === false)
            {
                continue;
            }

            // Basic safety checks
            if (stripos($svg, '<script') !== false || preg_match('/on\w+=/i', $svg))
            {
                // suspicious file; skip
                continue;
            }

            // Extract viewBox if present
            $viewBox = '';
            if (preg_match('/viewBox=["\']([^"\']+)["\']/i', $svg, $m))
            {
                $viewBox = $m[1];
            }
            else
            {
                // fallback to width/height if provided
                if (preg_match('/<svg[^>]*\swidth=["\']?([\d.]+)[^"\']*["\']?/i', $svg, $mw) &&
                    preg_match('/<svg[^>]*\sheight=["\']?([\d.]+)[^"\']*["\']?/i', $svg, $mh))
                {
                    $viewBox = '0 0 ' . $mw[1] . ' ' . $mh[1];
                }
            }

            // Strip the outer <svg> wrapper to get inner content
            $inner = preg_replace('/^.*?<svg[^>]*>/is', '', $svg);
            $inner = preg_replace('/<\/svg>.*$/is', '', $inner);
            $inner = trim($inner);

            // Reject risky tags or empty content
            if ($inner === '' || stripos($inner, '<foreignObject') !== false || stripos($inner, '<script') !== false)
            {
                continue;
            }

            // Clean symbol id from filename
            $symbolId = $prefix . preg_replace('/[^a-z0-9\-_]/i', '-', $basename);
            $vbAttr = $viewBox !== '' ? ' viewBox="' . htmlspecialchars($viewBox, ENT_QUOTES, 'UTF-8') . '"' : '';

            // NOTE: the presentational attributes here are very much entrenched with the feather icon style
            // If we want the flexibiity to change icon sets, these attributes need to be changed.
            // so it might be best ot have them set as parameter in the plugin config.

            $symbols[] = '<symbol id="' . $symbolId . '"' . $vbAttr . ' fill="none" fill-opacity="0" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $inner . '</symbol>';
        }

        if (empty($symbols))
        {
            return '';
        }


        $sprite = '<svg xmlns="http://www.w3.org/2000/svg" display="none" aria-hidden="true">' . PHP_EOL
            . implode(PHP_EOL, $symbols) . PHP_EOL
            . '</svg>';

        return $sprite;
    }

    /**
     * Return a cached sprite string. Cache filename uses the most-recent file mtime
     * so updating any icon invalidates the cache automatically.
     *
     * @param  bool        $forceRebuild Force rebuild even if cache exists
     * @param  string|null $iconsFolder
     * @return string
     */
    public static function getSpriteHtml(bool $forceRebuild = false, ?string $iconsFolder = null): string
    {
        $iconsFolder = $iconsFolder ?? self::getIconsFolder();

        if (!is_dir($iconsFolder))
        {
            return '';
        }

        $cacheDir = JPATH_ROOT . '/cache';
        $lastMtime = 0;

        $files = glob($iconsFolder . '/*.svg') ?: [];
        $files = array_filter($files, 'is_readable');

        foreach ($files as $f)
        {
            $mt = @filemtime($f);
            if ($mt !== false && $mt > $lastMtime)
            {
                $lastMtime = $mt;
            }
        }

        if ($lastMtime === 0)
        {
            return '';
        }

        $cacheFile = $cacheDir . '/blocks-sprite-' . $lastMtime . '.svg';

        if (!$forceRebuild && file_exists($cacheFile))
        {
            $contents = @file_get_contents($cacheFile);
            if ($contents !== false)
            {
                return $contents;
            }
        }

        $sprite = self::buildSpriteFromFolder($iconsFolder);
        if ($sprite === '')
        {
            return '';
        }

        if (!is_dir($cacheDir))
        {
            @mkdir($cacheDir, 0755, true);
        }

        @file_put_contents($cacheFile, $sprite, LOCK_EX);

        // Remove older cache files for cleanliness
        $pattern = $cacheDir . '/blocks-sprite-*.svg';
        foreach (glob($pattern) as $oldFile)
        {
            if ($oldFile !== $cacheFile)
            {
                @unlink($oldFile);
            }
        }

        return $sprite;
    }

    /**
     * Return the <svg><use></use></svg> markup for a given icon id.
     *
     * @param string $iconId (filename without .svg)
     * @param array $svgAttrs Associative HTML attributes for the outer <svg> (class, width, height, role, aria-hidden, etc)
     * @param string|null $prefix
     * @param string|null $text
     * @return string
     */
    public static function renderUse(string $iconId, array $svgAttrs = [], ?string $prefix = null, ?string $text = null): string
    {
        $prefix = $prefix ?? self::getIdPrefix();
        $safeId = $prefix . preg_replace('/[^a-z0-9\-_]/i', '-', $iconId);

        // default attributes
        $default = [
            'width'       => '1.25em',
            'height'      => '1.25em',
            'aria-hidden' => 'true',
            'focusable'   => 'false',
            'display'     => 'inline'
        ];
        $attrs = array_merge($default, $svgAttrs);
        $attrStrings = [];
        foreach ($attrs as $k => $v)
        {
            $attrStrings[] = $k . '="' . htmlspecialchars($v, ENT_QUOTES, 'UTF-8') . '"';
        }

        // Use href (modern) and also xlink:href for legacy browsers if you want (optional).
        $use = '<use href="#' . htmlspecialchars($safeId, ENT_QUOTES, 'UTF-8') . '"></use>';


        $svg = '<svg ' . implode(' ', $attrStrings) . '>' . $use . $text . '</svg>';
        return $svg;
    }

}

