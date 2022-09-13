<?php

namespace App\DataFixtures;

use App\Entity\Chat\Chat;
use App\Entity\Chat\ChatParticipant;
use App\Entity\Chat\GroupChat;
use App\Entity\User;
use App\Tests\BaseCest;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ChatHighloadFixture extends Fixture implements DependentFixtureInterface
{
    public function getDependencies()
    {
        return [UserFixture::class];
    }

    public function load(ObjectManager $manager)
    {
//        for ($i = 0; $i < 100; $i++) {
//            $user = new User();
//            $user->email = 'email-'.$i.'@test.ru';
//            $user->name = 'name-'.$i;
//            $user->surname = 'surname-'.$i;
//            $manager->persist($user);
//
//            $this->setReference('user-'.$i, $user);
//        }
//
//        $main = $manager->getRepository('App:User')->findOneBy(['email' => BaseCest::MAIN_USER_EMAIL]);
//
//        for ($i = 0; $i < 100; $i++) {
//            $chat = new GroupChat('group_chat_'.uniqid().'_'.$i, 'Group chat title '.$i, $main);
//            $chat->addParticipant(new ChatParticipant($chat, $main));
//            for ($j = 0; $j < 100; $j++) {
//                $chat->addParticipant(new ChatParticipant($chat, $this->getReference('user-'.$j)));
//            }
//            $manager->persist($chat);
//        }

//        $manager->flush();
    }
}
