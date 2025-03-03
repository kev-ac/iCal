<?php

/*
 * This file is part of the eluceo/iCal package.
 *
 * (c) 2021 Markus Poerschke <markus@poerschke.nrw>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Unit\Presentation\Factory;

use DateTimeImmutable;
use DateTimeZone;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\ValueObject\Attachment;
use Eluceo\iCal\Domain\ValueObject\BinaryContent;
use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\DateTime;
use Eluceo\iCal\Domain\ValueObject\EmailAddress;
use Eluceo\iCal\Domain\ValueObject\GeographicPosition;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\MultiDay;
use Eluceo\iCal\Domain\ValueObject\Organizer;
use Eluceo\iCal\Domain\ValueObject\SingleDay;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;
use Eluceo\iCal\Domain\ValueObject\Timestamp;
use Eluceo\iCal\Domain\ValueObject\UniqueIdentifier;
use Eluceo\iCal\Domain\ValueObject\Uri;
use Eluceo\iCal\Presentation\ContentLine;
use Eluceo\iCal\Presentation\Factory\EventFactory;
use PHPUnit\Framework\TestCase;

class CalendarFactoryTest extends TestCase
{
    public function testMinimalEvent()
    {
        $currentTime = new Timestamp(
            DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                '2019-11-10 11:22:33',
                new DateTimeZone('UTC')
            )
        );

        $lastModified = new Timestamp(
            DateTimeImmutable::createFromFormat(
                'Y-m-d H:i:s',
                '2019-10-09 10:11:22',
                new DateTimeZone('UTC')
            )
        );

        $event = (new Event(new UniqueIdentifier('event1')))
            ->touch($currentTime)
            ->setLastModified($lastModified)
        ;

        $expected = implode(ContentLine::LINE_SEPARATOR, [
            'BEGIN:VEVENT',
            'UID:event1',
            'DTSTAMP:20191110T112233Z',
            'LAST-MODIFIED:20191009T101122Z',
            'END:VEVENT',
            '',
        ]);

        self::assertSame($expected, (string) (new EventFactory())->createComponent($event));
    }

    public function testEventWithSummaryAndDescription()
    {
        $event = (new Event())
            ->setSummary('Lorem Summary')
            ->setDescription('Lorem Description');

        self::assertEventRendersCorrect($event, [
            'SUMMARY:Lorem Summary',
            'DESCRIPTION:Lorem Description',
        ]);
    }

    public function testEventWithLocation()
    {
        $geographicalPosition = new GeographicPosition(51.333333333333, 7.05);
        $location = (new Location('Location Name', 'Somewhere'))->withGeographicPosition($geographicalPosition);
        $event = (new Event())->setLocation($location);

        self::assertEventRendersCorrect(
            $event,
            [
                'LOCATION:Location Name',
                'GEO:51.333333;7.050000',
                'X-APPLE-STRUCTURED-LOCATION;VALUE=URI;X-ADDRESS=Location Name;X-APPLE-RADIU',
                ' S=49;X-TITLE=Somewhere:geo:51.333333,7.050000',
            ]
        );
    }

    public function testSingleDayEvent()
    {
        $event = (new Event())->setOccurrence(new SingleDay(new Date(DateTimeImmutable::createFromFormat('Y-m-d', '2030-12-24'))));

        self::assertEventRendersCorrect($event, [
            'DTSTART:20301224',
        ]);
    }

    public function testMultiDayEvent()
    {
        $firstDay = new Date(DateTimeImmutable::createFromFormat('Y-m-d', '2030-12-24'));
        $lastDay = new Date(DateTimeImmutable::createFromFormat('Y-m-d', '2030-12-26'));
        $occurrence = new MultiDay($firstDay, $lastDay);
        $event = (new Event())->setOccurrence($occurrence);

        self::assertEventRendersCorrect($event, [
            'DTSTART:20301224',
            'DTEND:20301227',
        ]);
    }

    public function testTimespanEvent()
    {
        $begin = new DateTime(DateTimeImmutable::createFromFormat('Y-m-d H:i', '2030-12-24 12:15'), false);
        $end = new DateTime(DateTimeImmutable::createFromFormat('Y-m-d H:i', '2030-12-24 13:45'), false);
        $occurrence = new TimeSpan($begin, $end);
        $event = (new Event())->setOccurrence($occurrence);

        self::assertEventRendersCorrect($event, [
            'DTSTART:20301224T121500',
            'DTEND:20301224T134500',
        ]);
    }

    public function testUrlAttachments()
    {
        $event = (new Event())
            ->addAttachment(
                new Attachment(
                    new Uri('http://example.com/document.txt'),
                    'text/plain')
            );

        self::assertEventRendersCorrect($event, [
            'ATTACH;FMTTYPE=text/plain:http://example.com/document.txt',
        ]);
    }

    public function testFileAttachments()
    {
        $event = (new Event())
            ->addAttachment(
                new Attachment(
                    new BinaryContent('Hello World!'),
                    'text/plain'
                )
            );

        self::assertEventRendersCorrect($event, [
            'ATTACH;FMTTYPE=text/plain;ENCODING=BASE64;VALUE=BINARY:SGVsbG8gV29ybGQh',
        ]);
    }

    public function testOrganizer()
    {
        $event = (new Event())
            ->setOrganizer(new Organizer(
                new EmailAddress('test@example.com'),
                'Test Display Name',
                new Uri('example://directory-entry'),
                new EmailAddress('sendby@example.com')
            ));

        self::assertEventRendersCorrect($event, [
            'ORGANIZER;CN=Test Display Name;DIR=example://directory-entry;SENT-BY=mailto',
            ' :sendby%40example.com:mailto:test%40example.com',
        ]);
    }

    public function testEventUrl()
    {
        $event = (new Event())
            ->setUrl(new Uri('https://example.org/calendarevent'));

        self::assertEventRendersCorrect($event, [
            'URL:https://example.org/calendarevent',
        ]);
    }

    private static function assertEventRendersCorrect(Event $event, array $expected)
    {
        $resultAsString = (string) (new EventFactory())->createComponent($event);

        $resultAsArray = explode(ContentLine::LINE_SEPARATOR, $resultAsString);

        self::assertGreaterThan(5, count($resultAsArray), 'No additional content lines were produced.');

        $resultAsArray = array_slice($resultAsArray, 3, -2);
        self::assertSame($expected, $resultAsArray);
    }
}
