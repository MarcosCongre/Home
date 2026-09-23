<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Members\CreateMember\CreateMemberCommand;
use App\Application\Members\CreateMember\CreateMemberHandler;
use App\Application\Members\DeleteMember\DeleteMemberCommand;
use App\Application\Members\DeleteMember\DeleteMemberHandler;
use App\Application\Members\ListMembers\ListMembersHandler;
use App\Application\Members\ListMembers\ListMembersQuery;
use App\Application\Members\UpdateMember\UpdateMemberCommand;
use App\Application\Members\UpdateMember\UpdateMemberHandler;
use App\Domain\Members\Member;
use App\Domain\Members\MemberRepositoryInterface;

final class MemberController
{
    public function __construct(
        private MemberRepositoryInterface $memberRepository
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $handler = new CreateMemberHandler($this->memberRepository);
        $member = $handler->handle(new CreateMemberCommand(
            name: (string) ($payload['name'] ?? ''),
            avatar: (string) ($payload['avatar'] ?? ''),
            color: (string) ($payload['color'] ?? '#000000'),
            householdId: (string) ($payload['householdId'] ?? '')
        ));

        return $this->serializeMember($member);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(int $memberId, array $payload): array
    {
        $handler = new UpdateMemberHandler($this->memberRepository);
        $member = $handler->handle(new UpdateMemberCommand(
            memberId: $memberId,
            name: (string) ($payload['name'] ?? ''),
            avatar: (string) ($payload['avatar'] ?? ''),
            color: (string) ($payload['color'] ?? '#000000')
        ));

        return $this->serializeMember($member);
    }

    public function delete(int $memberId): void
    {
        $handler = new DeleteMemberHandler($this->memberRepository);
        $handler->handle(new DeleteMemberCommand($memberId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(string $householdId): array
    {
        $handler = new ListMembersHandler($this->memberRepository);
        $members = $handler->handle(new ListMembersQuery($householdId));

        return array_map(fn (Member $member): array => $this->serializeMember($member), $members);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMember(Member $member): array
    {
        return [
            'id' => $member->id(),
            'name' => $member->name(),
            'avatar' => $member->avatar(),
            'color' => $member->color(),
            'householdId' => $member->householdId(),
        ];
    }
}
