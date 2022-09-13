<?php

namespace App\DataFixtures;

use App\Entity\Interest\Interest;
use App\Entity\Interest\InterestGroup;
use App\Entity\Invite\Invite;
use App\Entity\Location\City;
use App\Entity\Location\Country;
use App\Entity\Role;
use App\Entity\User;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use libphonenumber\PhoneNumberUtil;

class UserFixture extends Fixture implements FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['highload'];
    }

    public function load(ObjectManager $manager)
    {
        $country = new Country();
        $country->id = 1;
        $country->continentCode = 'EU';
        $country->continentName = 'Europe';
        $country->isoCode = '000';
        $country->locale = 'RU';
        $country->name = 'Russia';
        $country->isInEuropeanUnion = true;

        $city = new City();
        $city->id = 1;
        $city->country = $country;
        $city->name = 'Moscow';
        $city->timeZone = 'Europe/Moscow';
        $city->subdivisionFirstIsoCode = '0';
        $city->subdivisionFirstName = '0';
        $city->subdivisionSecondIsoCode = '0';
        $city->subdivisionSecondName = '0';
        $city->metroCode = '0';
        $city->latitude = 24.4;
        $city->longitude = 25.3;
        $city->accuracyRadius = 45;

        $manager->persist($country);
        $manager->persist($city);
        $manager->flush();

        $interestGroupFirst = new InterestGroup('InterestGroup_1');
        $interestGroupFirst->isOld = false;
        $interestGroupSecond = new InterestGroup('InterestGroup_2');
        $interestGroupSecond->isOld = false;

        $manager->persist($interestGroupFirst);
        $manager->persist($interestGroupSecond);

        $interests = [];

        $mainUser = new User();
        $mainUser->email = BaseCest::MAIN_USER_EMAIL;
        $mainUser->name = BaseCest::MAIN_USER_NAME;
        $mainUser->surname = BaseCest::MAIN_USER_SURNAME;
        $mainUser->city = $city;
        $mainUser->addInterest($interests[] = new Interest($interestGroupFirst, 'Interest_1'));
        $mainUser->addInterest($interests[] = new Interest($interestGroupSecond, 'Interest_2'));
        $manager->persist($mainUser);

        $manager->persist(new Role($mainUser, Role::ROLE_ADMIN));

        $aliceUser = new User();
        $aliceUser->email = BaseCest::ALICE_USER_EMAIL;
        $aliceUser->name = BaseCest::ALICE_USER_NAME;
        $aliceUser->surname = BaseCest::ALICE_USER_SURNAME;
        $aliceUser->city = $city;
        $aliceUser->addInterest($interests[] = new Interest($interestGroupFirst, 'Interest_1'));
        $aliceUser->addInterest($interests[] = new Interest($interestGroupSecond, 'Interest_2'));
        $manager->persist($aliceUser);

        $bobUser = new User();
        $bobUser->email = BaseCest::BOB_USER_EMAIL;
        $bobUser->name = BaseCest::BOB_USER_NAME;
        $bobUser->surname = BaseCest::BOB_USER_SURNAME;
        $bobUser->city = $city;
        $bobUser->addInterest($interests[] = new Interest($interestGroupFirst, 'Interest_1'));
        $bobUser->addInterest($interests[] = new Interest($interestGroupSecond, 'Interest_2'));
        $manager->persist($bobUser);

        $mikeUser = new User();
        $mikeUser->email = BaseCest::MIKE_USER_EMAIL;
        $mikeUser->name = 'Mike';
        $mikeUser->surname = 'Mike';
        $manager->persist($mikeUser);

        $mikeUser->state = User::STATE_NOT_INVITED;
        $mikeUser->phone = PhoneNumberUtil::getInstance()->parse('+79636417680');
        $manager->persist($mikeUser);

        $inviteMain = new Invite($mikeUser, PhoneNumberUtil::getInstance()->parse('+79636417681'));
        $inviteMain->registeredUser = $mainUser;
        $mainUser->state = User::STATE_VERIFIED;
        $manager->persist($inviteMain);

        $inviteAlice = new Invite($mikeUser, PhoneNumberUtil::getInstance()->parse('+79636417682'));
        $inviteAlice->registeredUser = $aliceUser;
        $aliceUser->state = User::STATE_VERIFIED;
        $manager->persist($inviteAlice);

        $inviteBob = new Invite($mikeUser, PhoneNumberUtil::getInstance()->parse('+79636417683'));
        $inviteBob->registeredUser = $bobUser;
        $bobUser->state = User::STATE_VERIFIED;
        $manager->persist($inviteBob);

        foreach ($interests as $interest) {
            $interest->isOld = false;
            $manager->persist($interest);
        }

        $manager->flush();

        $this->setReference('user-test', $mainUser);
        $this->setReference('alice-test', $aliceUser);
        $this->setReference('bob-test', $bobUser);
        $this->setReference('mike-test', $mikeUser);
    }
}
