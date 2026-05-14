<?php

namespace App\Security;

use App\Entity\Playlist;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;

class PlaylistVoter extends Voter
{
    public const MANAGE = 'PLAYLIST_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::MANAGE && $subject instanceof Playlist;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Playlist $subject */
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        return (string) $subject->getUser()->getId() === (string) $user->getId();
    }
}
