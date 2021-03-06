<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace PhpCsFixer\Tests\Runner;

use PhpCsFixer\FixerFileProcessedEvent;
use PhpCsFixer\Runner\FileFilterIterator;
use PhpCsFixer\Tests\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @author SpacePossum
 *
 * @internal
 *
 * @covers \PhpCsFixer\Runner\FileFilterIterator
 */
final class FileFilterIteratorTest extends TestCase
{
    /**
     * @param int $repeat
     *
     * @testWith [1]
     *           [2]
     *           [3]
     */
    public function testAccept($repeat)
    {
        $file = __FILE__;
        $content = file_get_contents($file);
        $events = [];

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            FixerFileProcessedEvent::NAME,
            static function ($event) use (&$events) {
                $events[] = $event;
            }
        );

        $cache = $this->prophesize(\PhpCsFixer\Cache\CacheManagerInterface::class);
        $cache->needFixing($file, $content)->willReturn(true);

        $fileInfo = new \SplFileInfo($file);

        $filter = new FileFilterIterator(
            new \ArrayIterator(array_fill(0, $repeat, $fileInfo)),
            $eventDispatcher,
            $cache->reveal()
        );

        static::assertCount(0, $events);

        $files = iterator_to_array($filter);

        static::assertCount(1, $files);
        static::assertSame($fileInfo, reset($files));
    }

    public function testEmitSkipEventWhenCacheNeedFixingFalse()
    {
        $file = __FILE__;
        $content = file_get_contents($file);
        $events = [];

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            FixerFileProcessedEvent::NAME,
            static function ($event) use (&$events) {
                $events[] = $event;
            }
        );

        $cache = $this->prophesize(\PhpCsFixer\Cache\CacheManagerInterface::class);
        $cache->needFixing($file, $content)->willReturn(false);

        $filter = new FileFilterIterator(
            new \ArrayIterator([new \SplFileInfo($file)]),
            $eventDispatcher,
            $cache->reveal()
        );

        static::assertCount(0, $filter);
        static::assertCount(1, $events);

        /** @var FixerFileProcessedEvent $event */
        $event = reset($events);

        static::assertInstanceOf(\PhpCsFixer\FixerFileProcessedEvent::class, $event);
        static::assertSame(FixerFileProcessedEvent::STATUS_SKIPPED, $event->getStatus());
    }

    public function testIgnoreEmptyFile()
    {
        $file = __DIR__.'/../Fixtures/empty.php';
        $content = file_get_contents($file);
        $events = [];

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            FixerFileProcessedEvent::NAME,
            static function ($event) use (&$events) {
                $events[] = $event;
            }
        );

        $cache = $this->prophesize(\PhpCsFixer\Cache\CacheManagerInterface::class);
        $cache->needFixing($file, $content)->willReturn(true);

        $filter = new FileFilterIterator(
            new \ArrayIterator([new \SplFileInfo($file)]),
            $eventDispatcher,
            $cache->reveal()
        );

        static::assertCount(0, $filter);
        static::assertCount(1, $events);

        /** @var FixerFileProcessedEvent $event */
        $event = reset($events);

        static::assertInstanceOf(\PhpCsFixer\FixerFileProcessedEvent::class, $event);
        static::assertSame(FixerFileProcessedEvent::STATUS_SKIPPED, $event->getStatus());
    }

    public function testIgnore()
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            FixerFileProcessedEvent::NAME,
            static function () {
                throw new \Exception('No event expected.');
            }
        );

        $filter = new FileFilterIterator(
            new \ArrayIterator([
                new \SplFileInfo(__DIR__),
                new \SplFileInfo('__INVALID__'),
            ]),
            $eventDispatcher,
            $this->prophesize(\PhpCsFixer\Cache\CacheManagerInterface::class)->reveal()
        );

        static::assertCount(0, $filter);
    }

    public function testWithoutDispatcher()
    {
        $file = __FILE__;
        $content = file_get_contents($file);

        $cache = $this->prophesize(\PhpCsFixer\Cache\CacheManagerInterface::class);
        $cache->needFixing($file, $content)->willReturn(false);

        $filter = new FileFilterIterator(
            new \ArrayIterator([new \SplFileInfo($file)]),
            null,
            $cache->reveal()
        );

        static::assertCount(0, $filter);
    }

    public function testInvalidIterator()
    {
        $filter = new FileFilterIterator(
            new \ArrayIterator([__FILE__]),
            null,
            $this->prophesize(\PhpCsFixer\Cache\CacheManagerInterface::class)->reveal()
        );

        $this->expectException(
            \RuntimeException::class
        );
        $this->expectExceptionMessageRegExp(
            '#^Expected instance of "\\\SplFileInfo", got "string"\.$#'
        );

        iterator_to_array($filter);
    }

    /**
     * @requires OS Linux|Darwin
     */
    public function testFileIsAcceptedAfterFilteredAsSymlink()
    {
        $link = __DIR__.'/../Fixtures/Test/FileFilterIteratorTest/FileFilterIteratorTest.php.link';

        static::assertTrue(is_link($link), 'Fixture data is no longer correct for this test.');
        static::assertSame(__FILE__, realpath($link), 'Fixture data is no longer correct for this test.');

        $file = new \SplFileInfo(__FILE__);
        $link = new \SplFileInfo($link);

        $cache = $this->prophesize(\PhpCsFixer\Cache\CacheManagerInterface::class);
        $cache->needFixing(
            __FILE__,
            file_get_contents($file->getPathname())
        )->willReturn(true);

        $filter = new FileFilterIterator(
            new \ArrayIterator([$link, $file]),
            null,
            $cache->reveal()
        );

        $files = iterator_to_array($filter);
        static::assertCount(1, $files);
        static::assertSame($file, reset($files));
    }
}
