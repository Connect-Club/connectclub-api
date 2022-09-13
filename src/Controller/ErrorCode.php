<?php

namespace App\Controller;

class ErrorCode
{
    const V1_COMMUNITY_PHOTO_NOT_FOUND = 'photoId:community.photo.not_found';
    const V1_COMMUNITY_NOT_FOUND = 'id:community.not_found';
    const V1_COMMUNITY_ALREADY_JOINED = 'participants:community.join.already_joined';
    const V1_COMMUNITY_PARTICIPANT_NOT_FOUND = 'participants:community.join.participant_not_found';
    const V1_COMMUNITY_DELETE_OWNED = 'v1.community.delete_owned';
    const V1_COMMUNITY_ABOUT_MAX_LENGTH_MESSAGE = 'title:v1.community.validation.about.max_length';

    const V1_VIDEO_ROOM_NOT_FOUND = 'v1.room.not_found';
    const V1_VIDEO_ROOM_INCORRECT_PASSWORD = 'v1.room.incorrect_password';
    const V1_VIDEO_ROOM_MAX_COUNT_PARTICIPANTS = 'v1.room.max_count_participants';
    const V1_VIDEO_ROOM_HISTORY_NOT_FOUND = 'v1.room_history.not_found';
    const V1_VIDEO_ROOM_HISTORY_DELETE_OWNED = 'v1.room_history.delete_owned_room_history';
    const V1_VIDEO_ROOM_MEETING_NOT_FOUND = 'v1.room.meeting.not_found';
    const V1_VIDEO_ROOM_BACKGROUND_NOT_FOUND = 'v1.background.not_found';
    const V1_VIDEO_ROOM_BAN_ABUSER_NOT_FOUND = 'v1.video_room.ban.abuser_not_found';
    const V1_VIDEO_ROOM_BAN_ALREADY_EXISTS = 'v1.video_room.ban.already_exists';
    const V1_VIDEO_ROOM_JOIN_USER_BANNED = 'v1.video_room.token.user_banned';
    const V1_VIDEO_ROOM_DRAFT_NOT_FOUND = 'v1.video_room.draft_not_found';
    const V1_VIDEO_ROOM_VALIDATION_DESCRIPTION_EMPTY = 'v1.video_room.empty';
    const V1_VIDEO_ROOM_VALIDATION_DESCRIPTION_MAX_LENGTH = 'v1.video_room.max_length';
    const V1_VIDEO_ROOM_VALIDATION_DESCRIPTION_MIN_LENGTH = 'v1.video_room.min_length';
    const V1_VIDEO_ROOM_CONFLICT_ANOTHER_SERVER_MEETING_EXISTS = 'v1.video_room.another_server_meeting_exists';
    const V1_VIDEO_ROOM_PAYMENT_REQUIRED = 'v1.video_room.payment_required';

    const V1_MOBILE_VERSION_PLATFORM_NOT_FOUND = 'v1.mobile_version.platform_not_found';
    const V1_MOBILE_VERSION_NOT_FOUND = 'v1.mobile_version.version_not_found';

    const V1_COMPLAINT_ABUSER_NOT_FOUND = 'v1.complaint.abuser_not_found';
    const V1_COMPLAINT_ALREADY_EXISTS = 'v1.complaint.already_exists';
    const V1_COMPLAINT_REQUIRED_FROM_SAME_COMMUNITY = 'v1.complaint.required_from_same_community';

    const V1_CONTACT_USER_NOT_FOUND = 'v1.contact.user_not_found';
    const V1_CONTACT_NOT_FOUND = 'v1.contact.not_found';
    const V1_CONTACT_ALREADY_EXISTS = 'v1.contact.already_exists';
    const V1_ADD_CONTACT_YOURSELF = 'v1.contact.attempt_add_yourself';

    const V1_CHAT_USER_NOT_FOUND = 'v1.chat.user_not_found';
    const V1_CHAT_NOT_FOUND = 'v1.chat.chat_not_found';
    const V1_GROUP_CHAT_USER_ALREADY_JOINED = 'v1.group_chat.user_already_joined';
    const V1_CHAT_CHAT_WITH_YOURSELF = 'v1.chat.chat_with_yourself';

    const V1_CHAT_VALIDATION_TITLE_MAX_LENGTH = 'title:max_length';
    const V1_CHAT_VALIDATION_TITLE_MIN_LENGTH = 'title:min_length';
    const V1_CHAT_VALIDATION_TITLE_EMPTY = 'title:empty';

    const V1_NOTIFICATION_PARTICIPANT_NOT_FOUND = 'v1.notification.participant.not_found';

    const V1_FREE_SUPPORT_ACCOUNT_NOT_FOUND = 'v1.support.free_support_account.not_found';

    const V1_ACCESS_DENIED = 'v1.access_denied';
    const V1_BAD_REQUEST = 'v1.bad_request';
    const V1_INTERNAL_SERVER_ERROR = 'v1.internal_server_error';

    const V1_VIDEO_ROOM_OBJECT_NOT_FOUND = 'v1.video_room.object_not_found';

    const V1_NETWORKING_MEETING_NOT_FOUND = 'v1.networking_meeting.not_found';
    const V1_NETWORKING_MEETING_USER_NOT_FOUND = 'v1.networking_meeting.user.not_found';
    const V1_NETWORKING_MEETING_MATCHING_USER_ALREADY_MATCHED = 'v1.networking_meeting.matching.user_already_matched';

    const V1_USER_NOT_FOUND = 'v1.user.not_found';
    const V1_USER_BANNED = 'v1.user.banned';
    const V1_USER_USERNAME_ALREADY_EXISTS = 'v1.user.username.already_exists';

    const V1_ERROR_ACTION_LOCK = 'v1.action.lock';

    const V1_ERROR_NOT_FOUND = 'v1.not_found';

    const V1_ERROR_INVITE_ALREADY_EXISTS = 'v1.invite.already_exists';

    const V1_ERROR_INVITE_USER_ALREADY_REGISTERED = 'v1.invite.user_already_registered';
    const V1_ERROR_INVITE_NO_FREE_INVITES = 'v1.invite.no_free_invites';

    const V1_ERROR_USER_ALREADY_FOLLOWED = 'v1.user.already_followed';
    const V1_ERROR_USER_NOT_FOLLOWED = 'v1.user.not_followed';

    const V1_ACCOUNT_SKIP_NOTIFICATIONS_INCORRECT_TIME = 'account.skip.date_time_must_be_greater_now';

    const V1_EVENT_SCHEDULE_DATE_TIME_IS_NEGATIVE = 'event_schedule.date_time_must_be_greater_now';
    const V1_EVENT_SCHEDULE_DATE_TIME_END_IS_NEGATIVE = 'event_schedule.date_time_end_must_be_grater_then_start';
    const V1_EVENT_SCHEDULE_NOT_FOUND = 'event_schedule.not_found';

    const V1_EVENT_NO_ACTIVE_MEETING = 'v1.event.no_active_meeting';

    const V1_EVENT_SCHEDULE_IS_EXPIRED = 'v1.event_schedule.expired';

    const V1_CONTACT_PHONE_NOT_READY_YET = 'v1.phone_contact.not_ready_yet';

    const V1_SUBSCRIPTION_ACTIVE_LIMIT = 'v1.subscription.active_limit';
    const V1_SUBSCRIPTION_BUY_SUBSCRIPTION_FIRSTLY = 'v1.subscription.buy_subscription_firstly';

    const V1_STRIPE_INVALID_SIGNATURE = 'v1.stripe.invalid_signature';
    const V1_STRIPE_INVALID_PAYLOAD = 'v1.stripe.invalid_payload';

    const V1_LANGUAGE_NOT_FOUND = 'v1.language.not_found';

    const V1_PRIVATE_VIDEO_ROOM_INVITE_NOT_FOUND = 'v1.private_video_room_invite_not_found';

    const V1_CLUB_IMAGE_NOT_FOUND = 'v1.club.photo_not_found';
    const V1_CLUB_NOT_FOUND = 'v1.club.not_found';
    const V1_CLUB_DESCRIPTION_MAX_LENGTH = 'v1.club.description_max_length';
    const V1_CLUB_DESCRIPTION_TITLE_ALREADY_EXISTS = 'v1.club.title_already_exists';
    const V1_CLUB_JOIN_REQUEST_ALREADY_EXISTS = 'v1.club.join_request.already_exists';
    const V1_CLUB_PARTICIPANT_NOT_FOUND = 'v1.club_participant.not_found';

    const V1_LANDING_URL_ALREADY_RESERVED = 'v1.landing.url.already_reserved';
}
