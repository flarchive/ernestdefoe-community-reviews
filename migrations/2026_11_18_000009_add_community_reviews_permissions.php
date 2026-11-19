<?php

declare(strict_types=1);

use Flarum\Database\Migration;
use Flarum\Group\Group;

// Every permission the extension checks, seeded so each shows on the Permissions page with its default.
// MyBB parity: guests read, members add products, review and comment, moderators moderate.
return Migration::addPermissions([
    'communityReviews.view' => Group::GUEST_ID,
    'communityReviews.addItems' => Group::MEMBER_ID,
    'communityReviews.write' => Group::MEMBER_ID,
    'communityReviews.comment' => Group::MEMBER_ID,
    'communityReviews.editOwn' => Group::MEMBER_ID,
    'communityReviews.deleteOwn' => Group::MEMBER_ID,
    'communityReviews.vote' => Group::MEMBER_ID,
    'communityReviews.skipApproval' => Group::MODERATOR_ID,
    'communityReviews.moderate' => Group::MODERATOR_ID,
]);
