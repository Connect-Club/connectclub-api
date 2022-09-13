<?php

namespace App\DataFixtures\Highload;

use App\DataFixtures\AccessTokenFixture;
use App\Entity\Community\Community;
use App\Entity\Event\EventSchedule;
use App\Entity\Event\EventScheduleInterest;
use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Entity\User;
use App\Entity\VideoChat\VideoMeeting;
use App\Entity\VideoChat\VideoMeetingParticipant;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class OnlineEventsFixture extends Fixture implements FixtureGroupInterface, DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [
            AccessTokenFixture::class
        ];
    }

    public function load(ObjectManager $manager)
    {
//        $language = new User\Language('RU', 'RU');
//        $manager->persist($language);
//
//        $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//        $main->alwaysShowOngoingUpcomingEvents = true;
//        $main->addNativeLanguage($language);
//        $manager->persist($main);
//        $manager->flush();
//
//        $interestGroup = new InterestGroup('Group');
//        $manager->persist($interestGroup);
//        for ($i = 0; $i < 20; $i++) {
//            $interest = new Interest($interestGroup, 'Interest '.$i);
//            $manager->persist($interest);
//            $this->setReference('interest-'.$i, $interest);
//        }
//
//        for ($i = 0; $i < 100; $i++) {
//            $user = new User();
//            $user->addNativeLanguage($language);
//            $user->username = 'user-'.$i;
//            $user->name = 'user '.$i;
//            $user->surname = 'user '.$i;
//            $user->state = User::STATE_VERIFIED;
//
//            $manager->persist($user);
//            $this->setReference('user-'.$i, $user);
//        }
//
//        for ($i = 0; $i < 50; $i++) {
//            /** @var User $owner */
//            $owner = $this->getReference('user-'.$i);
//
//            $room = new Community($owner, '');
//            $room->videoRoom->language = $language;
//
//            if ($i % 2 === 0) {
//                $eventSchedule = new EventSchedule($owner, 'Event schedule '.$i, time() + 30, '');
//                for ($j = 0; $j < 20; $j++) {
//                    $eventSchedule->interests->add(new EventScheduleInterest(
//                        $eventSchedule,
//                        $this->getReference('interest-'.$j)
//                    ));
//                }
//                $manager->persist($eventSchedule);
//            } else {
//                $eventSchedule = null;
//            }
//
//            $room->name = 'video-room-'.$i;
//            $room->videoRoom->eventSchedule = $eventSchedule ?? null;
//            $room->videoRoom->startedAt = time();
//            $manager->persist($room);
//
//            $meeting = new VideoMeeting($room->videoRoom, 'meeting-'.$i, time());
//            $manager->persist($meeting);
//
//            $manager->persist(new VideoMeetingParticipant($meeting, $owner, time()));
//            for ($j = 0; $j < $i; $j++) {
//                $manager->persist(new VideoMeetingParticipant($meeting, $this->getReference('user-'.$j), time()));
//            }
//
//            if ($i % 10 === 0) {
//                $manager->flush();
//            }
//        }
//
//        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['highload'];
    }
}
